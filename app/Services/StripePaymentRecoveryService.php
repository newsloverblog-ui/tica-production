<?php

namespace App\Services;

use App\Models\PaymentIntent;

/**
 * Asks Stripe for the authoritative state of a Checkout session and fulfils
 * it once. Used by the success redirect, the webhook and the recovery page.
 */
class StripePaymentRecoveryService
{
    public function attempt(PaymentIntent $intent, callable $fulfill, ?StripeGatewayClient $client = null): array
    {
        $intent->refresh();
        if ($intent->status === 'completed') {
            return ['status' => 'completed', 'booking_id' => $intent->booking_id, 'source' => 'already_completed'];
        }
        if (!$intent->gateway_transaction_id) {
            return ['status' => 'pending', 'message' => 'This payment has not reached Stripe yet. No charge has been made.'];
        }

        $intent->increment('verification_attempts');
        $session = ($client ?: new StripeGatewayClient())->retrieveSession($intent->gateway_transaction_id);
        $intent->forceFill(['last_verified_at' => now()])->save();

        if (($session['payment_status'] ?? null) === 'paid') {
            $errors = StripePaymentVerifier::errors($session, $intent->payload ?: [], $intent->reference);
            if ($errors) {
                $intent->update(['status' => 'failed', 'last_error' => 'Payment verification mismatch: ' . implode(', ', $errors)]);
                app(IncidentAlertService::class)->alert('stripe-recovery-mismatch:' . $intent->id, 'Stripe payment verification mismatch.', [
                    'reference' => $intent->reference, 'errors' => $errors,
                ]);
                AuditLogger::record('payment.recovery_mismatch', $intent, [], ['status' => 'failed'], [
                    'gateway' => 'stripe', 'reference' => $intent->reference, 'errors' => $errors,
                ]);
                return ['status' => 'failed', 'message' => 'We could not safely match this payment to the checkout. Support has been alerted.'];
            }

            $result = $fulfill();
            $intent->refresh();
            if ($intent->status === 'completed') {
                return ['status' => 'completed', 'booking_id' => $intent->booking_id, 'source' => (string) ($result['status'] ?? 'completed')];
            }
            if (($result['status'] ?? null) === 'already_processed') {
                return ['status' => 'completed', 'booking_id' => $result['existingTxn']->booking_id ?? $intent->booking_id, 'source' => 'already_processed'];
            }

            return ['status' => 'processing', 'message' => 'Your verified payment is currently being finalized. Please check again shortly.'];
        }

        if (($session['status'] ?? null) === 'expired') {
            $intent->update(['status' => 'failed', 'last_error' => 'The Stripe checkout session expired without payment.']);
            $token = (string) (($intent->payload ?: [])['reservation_token'] ?? '');
            if ($token !== '') {
                app(TicketReservationService::class)->release($token, 'payment_expired');
            }
            AuditLogger::record('payment.recovery_failed', $intent, [], ['status' => 'failed'], [
                'gateway' => 'stripe', 'reference' => $intent->reference, 'gateway_status' => 'expired',
            ]);
            return ['status' => 'failed', 'message' => 'This Stripe checkout expired without payment. You can safely return to checkout.'];
        }

        // Open, or complete but still processing (for example a delayed bank payment).
        $intent->update(['status' => 'initialized', 'last_error' => null]);
        return ['status' => 'pending', 'message' => 'Stripe has not confirmed this payment yet. No second charge has been created.'];
    }
}
