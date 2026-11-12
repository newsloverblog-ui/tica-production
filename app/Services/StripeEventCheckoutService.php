<?php

namespace App\Services;

use App\Exceptions\GatewayOutcomeUnknownException;
use App\Models\PaymentIntent;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class StripeEventCheckoutService
{
    public function initialize(array $checkout, ?StripeGatewayClient $client = null): PaymentIntent
    {
        $eventId = (int) ($checkout['event_id'] ?? 0);
        $amount = (float) ($checkout['amount'] ?? 0);
        $currency = strtoupper(trim((string) ($checkout['currency'] ?? '')));
        $email = strtolower(trim((string) ($checkout['email'] ?? '')));
        $payload = (array) ($checkout['payload'] ?? []);

        if ($eventId < 1 || $amount <= 0 || !in_array($currency, StripeGatewayClient::CURRENCIES, true) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('The Stripe event checkout data is invalid.');
        }

        $reference = 'tica-strp-' . Str::lower(Str::random(32));
        $intent = PaymentIntent::create([
            'gateway' => 'stripe',
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

        AuditLogger::record('payment.created', $intent, [], ['status' => 'initializing', 'amount' => $amount, 'currency' => $currency], [
            'gateway' => 'stripe', 'reference' => $reference, 'event_id' => $eventId,
        ]);

        try {
            // Stripe swaps {CHECKOUT_SESSION_ID} for the real id; it must stay unencoded.
            $session = ($client ?: new StripeGatewayClient())->createCheckoutSession(
                $reference,
                StripeGatewayClient::minorUnits($amount),
                $currency,
                $email,
                (string) ($checkout['description'] ?? 'TICA tickets'),
                route('event_booking.stripe.notify') . '?session_id={CHECKOUT_SESSION_ID}',
                (string) ($checkout['cancel_url'] ?? url('/'))
            );

            $intent->update([
                'status' => 'initialized',
                'authorization_url' => $session['url'],
                'gateway_transaction_id' => $session['id'],
                'last_error' => null,
            ]);
        } catch (GatewayOutcomeUnknownException $exception) {
            $intent->update(['status' => 'verification_required', 'last_error' => mb_substr($exception->getMessage(), 0, 1000)]);
            app(IncidentAlertService::class)->alert(
                'stripe-event-init-unknown:' . $intent->id,
                'Event Stripe initialization has an unknown outcome.',
                ['reference' => $reference, 'event_id' => $eventId]
            );
        } catch (Throwable $exception) {
            $intent->update(['status' => 'failed', 'last_error' => mb_substr($exception->getMessage(), 0, 1000)]);
            AuditLogger::record('payment.initialization_failed', $intent, [], ['status' => 'failed'], [
                'gateway' => 'stripe', 'reference' => $reference, 'event_id' => $eventId,
            ]);
            throw $exception;
        }

        return $intent->fresh();
    }
}
