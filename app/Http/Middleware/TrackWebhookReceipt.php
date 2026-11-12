<?php

namespace App\Http\Middleware;

use App\Models\WebhookReceipt;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class TrackWebhookReceipt
{
    public function handle(Request $request, Closure $next, string $gateway)
    {
        $started = hrtime(true);
        $receipt = $this->begin($request, $gateway);

        try {
            $response = $next($request);
            $this->complete($receipt, $request, $response->getStatusCode(), $response->getContent(), $started);
            return $response;
        } catch (Throwable $exception) {
            $this->complete($receipt, $request, 500, '', $started, class_basename($exception));
            throw $exception;
        }
    }

    private function begin(Request $request, string $gateway): ?WebhookReceipt
    {
        try {
            $payload = $request->json()->all();
            $eventType = trim((string) ($payload['event'] ?? $payload['eventType'] ?? $payload['type'] ?? ''));
            $reference = (string) data_get($payload, match ($gateway) {
                'flutterwave' => 'data.tx_ref',
                'monnify' => 'eventData.paymentReference',
                'stripe' => 'data.object.client_reference_id',
                default => 'data.reference',
            }, '');
            if ($reference === '') {
                $reference = (string) (data_get($payload, 'data.transfer_code') ?: data_get($payload, 'data.id', ''));
            }
            $signatureHeader = match ($gateway) {
                'paystack' => 'x-paystack-signature',
                'monnify' => 'monnify-signature',
                'stripe' => 'Stripe-Signature',
                default => 'verif-hash',
            };

            return WebhookReceipt::create([
                'gateway' => mb_substr(strtolower($gateway), 0, 32),
                'event_type' => $eventType !== '' ? mb_substr($eventType, 0, 100) : null,
                'body_hash' => hash('sha256', $request->getContent()),
                'reference_hash' => $reference !== '' ? hash_hmac('sha256', $reference, (string) config('app.key')) : null,
                'request_id' => mb_substr((string) $request->attributes->get('request_id'), 0, 100) ?: null,
                'signature_present' => trim((string) $request->header($signatureHeader, '')) !== '',
                'signature_valid' => null,
                'outcome' => 'received',
                'received_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Log::error('Webhook receipt could not be started.', [
                'gateway' => $gateway,
                'exception_type' => $exception::class,
                'exception_code' => $exception->getCode(),
            ]);
            return null;
        }
    }

    private function complete(
        ?WebhookReceipt $receipt,
        Request $request,
        int $httpStatus,
        string $content,
        int $started,
        ?string $errorCode = null
    ): void {
        if (!$receipt) {
            return;
        }
        try {
            $decoded = json_decode($content, true);
            $status = strtolower((string) (is_array($decoded) ? ($decoded['status'] ?? '') : ''));
            $outcome = match (true) {
                $httpStatus >= 500 => 'failed',
                $httpStatus >= 400 => 'rejected',
                str_contains($status, 'ignored') => 'ignored',
                str_contains($status, 'already') || str_contains($status, 'being processed') => 'duplicate',
                default => 'processed',
            };
            $receipt->forceFill([
                'signature_valid' => $request->attributes->get('webhook_signature_valid'),
                'outcome' => $outcome,
                'http_status' => $httpStatus,
                'processing_ms' => max(0, (int) round((hrtime(true) - $started) / 1_000_000)),
                'error_code' => $errorCode ?: ($outcome === 'failed' || $outcome === 'rejected' ? mb_substr($status ?: 'http_' . $httpStatus, 0, 100) : null),
                'completed_at' => now(),
            ])->save();
        } catch (Throwable $exception) {
            Log::error('Webhook receipt could not be completed.', [
                'receipt_id' => $receipt->public_id,
                'exception_type' => $exception::class,
                'exception_code' => $exception->getCode(),
            ]);
        }
    }
}
