<?php

namespace App\Services;

use App\Exceptions\GatewayOutcomeUnknownException;
use App\Models\PaymentIntent;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class PaystackEventCheckoutService
{
    public function initialize(array $checkout, ?PaystackGatewayClient $client = null): PaymentIntent
    {
        $eventId = (int) ($checkout['event_id'] ?? 0);
        $amount = (float) ($checkout['amount'] ?? 0);
        $currency = strtoupper(trim((string) ($checkout['currency'] ?? '')));
        $email = strtolower(trim((string) ($checkout['email'] ?? '')));
        $payload = (array) ($checkout['payload'] ?? []);

        if ($eventId < 1 || $amount <= 0 || $currency === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('The Paystack event checkout data is invalid.');
        }

        $reference = 'tica-pay-' . Str::lower(Str::random(32));
        $intent = PaymentIntent::create([
            'gateway' => 'paystack',
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
            'gateway' => 'paystack',
            'reference' => $reference,
            'event_id' => $eventId,
        ]);

        try {
            $response = ($client ?: new PaystackGatewayClient())->initializeTransaction(
                $reference,
                $email,
                $amount,
                $currency,
                route('event_booking.paystack.notify'),
                ['event_id' => $eventId, 'payment_kind' => 'event_booking']
            );
            if ((string) ($response['data']['reference'] ?? '') !== $reference) {
                throw new RuntimeException('Paystack returned a different transaction reference.');
            }

            $intent->update([
                'status' => 'initialized',
                'authorization_url' => $response['data']['authorization_url'],
                'last_error' => null,
            ]);
        } catch (GatewayOutcomeUnknownException $exception) {
            $intent->update([
                'status' => 'verification_required',
                'last_error' => mb_substr($exception->getMessage(), 0, 1000),
            ]);
            app(IncidentAlertService::class)->alert(
                'paystack-event-init-unknown:' . $intent->id,
                'Event Paystack initialization has an unknown outcome.',
                ['reference' => $reference, 'event_id' => $eventId]
            );
        } catch (Throwable $exception) {
            $intent->update([
                'status' => 'failed',
                'last_error' => mb_substr($exception->getMessage(), 0, 1000),
            ]);
            AuditLogger::record('payment.initialization_failed', $intent, [], ['status' => 'failed'], [
                'gateway' => 'paystack',
                'reference' => $reference,
                'event_id' => $eventId,
            ]);
            throw $exception;
        }

        return $intent->fresh();
    }
}
