<?php

namespace App\Services;

use App\Exceptions\GatewayOutcomeUnknownException;
use App\Models\PaymentIntent;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class MonnifyEventCheckoutService
{
    public function initialize(array $checkout, ?MonnifyGatewayClient $client = null): PaymentIntent
    {
        $eventId = (int) ($checkout['event_id'] ?? 0);
        $amount = (float) ($checkout['amount'] ?? 0);
        $currency = strtoupper(trim((string) ($checkout['currency'] ?? '')));
        $email = strtolower(trim((string) ($checkout['email'] ?? '')));
        $payload = (array) ($checkout['payload'] ?? []);

        if ($eventId < 1 || $amount <= 0 || $currency !== 'NGN' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('The Monnify event checkout data is invalid.');
        }

        $reference = 'tica-mnfy-' . Str::lower(Str::random(32));
        $intent = PaymentIntent::create([
            'gateway' => 'monnify',
            'reference' => $reference,
            'event_id' => $eventId,
            'customer_id' => $checkout['customer_id'] ?? null,
            'status' => 'initializing',
            'amount' => $amount,
            'currency' => $currency,
            'email' => $email,
            'payload' => $payload,
            'expires_at' => now()->addDay(),
        ]);

        AuditLogger::record('payment.created', $intent, [], [
            'status' => 'initializing',
            'amount' => $amount,
            'currency' => $currency,
        ], [
            'gateway' => 'monnify',
            'reference' => $reference,
            'event_id' => $eventId,
        ]);

        try {
            $body = ($client ?: new MonnifyGatewayClient())->initializeTransaction(
                $reference,
                $amount,
                trim(($payload['fname'] ?? '') . ' ' . ($payload['lname'] ?? '')) ?: $email,
                $email,
                (string) ($checkout['description'] ?? 'TICA tickets'),
                route('event_booking.monnify.notify'),
                ['event_id' => (string) $eventId, 'payment_kind' => 'event_booking']
            );

            $intent->update([
                'status' => 'initialized',
                'authorization_url' => $body['checkoutUrl'],
                'gateway_transaction_id' => $body['transactionReference'] ?? null,
                'last_error' => null,
            ]);
        } catch (GatewayOutcomeUnknownException $exception) {
            $intent->update([
                'status' => 'verification_required',
                'last_error' => mb_substr($exception->getMessage(), 0, 1000),
            ]);
            app(IncidentAlertService::class)->alert(
                'monnify-event-init-unknown:' . $intent->id,
                'Event Monnify initialization has an unknown outcome.',
                ['reference' => $reference, 'event_id' => $eventId]
            );
        } catch (Throwable $exception) {
            $intent->update([
                'status' => 'failed',
                'last_error' => mb_substr($exception->getMessage(), 0, 1000),
            ]);
            AuditLogger::record('payment.initialization_failed', $intent, [], ['status' => 'failed'], [
                'gateway' => 'monnify',
                'reference' => $reference,
                'event_id' => $eventId,
            ]);
            throw $exception;
        }

        return $intent->fresh();
    }
}
