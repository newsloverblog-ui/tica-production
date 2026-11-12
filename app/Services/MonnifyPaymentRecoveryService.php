<?php

namespace App\Services;

use App\Models\PaymentIntent;

/**
 * Asks Monnify for the authoritative status of a checkout and fulfils it
 * once. Used by the browser redirect, the webhook and the "check again"
 * recovery page, so a lost redirect never loses a paid order.
 */
class MonnifyPaymentRecoveryService
{
    public function attempt(
        PaymentIntent $intent,
        callable $fulfill,
        ?MonnifyGatewayClient $client = null
    ): array {
        $intent->refresh();
        if ($intent->status === 'completed') {
            return $this->completed($intent, 'already_completed');
        }

        $intent->increment('verification_attempts');
        $data = ($client ?: new MonnifyGatewayClient())->queryByPaymentReference($intent->reference);
        $gatewayStatus = strtoupper((string) ($data['paymentStatus'] ?? 'UNKNOWN'));
        $intent->forceFill([
            'last_verified_at' => now(),
            'gateway_transaction_id' => $intent->gateway_transaction_id ?: ($data['transactionReference'] ?? null),
        ])->save();

        if ($gatewayStatus === 'PAID') {
            $errors = MonnifyPaymentVerifier::errors($data, $intent->payload ?: [], $intent->reference);
            if ($errors) {
                $intent->update(['status' => 'failed', 'last_error' => 'Payment verification mismatch: ' . implode(', ', $errors)]);
                app(IncidentAlertService::class)->alert(
                    'monnify-recovery-mismatch:' . $intent->id,
                    'Monnify payment verification mismatch.', [
                    'reference' => $intent->reference,
                    'errors' => $errors,
                ]);
                AuditLogger::record('payment.recovery_mismatch', $intent, [], ['status' => 'failed'], [
                    'gateway' => 'monnify', 'reference' => $intent->reference, 'errors' => $errors,
                ]);
                return ['status' => 'failed', 'message' => 'We could not safely match this payment to the checkout. Support has been alerted.'];
            }

            $result = $fulfill();
            $intent->refresh();
            if ($intent->status === 'completed') {
                return $this->completed($intent, (string) ($result['status'] ?? 'completed'));
            }
            if (($result['status'] ?? null) === 'already_processed') {
                return ['status' => 'completed', 'booking_id' => $result['existingTxn']->booking_id ?? $intent->booking_id, 'source' => 'already_processed'];
            }

            return ['status' => 'processing', 'message' => 'Your verified payment is currently being finalized. Please check again shortly.'];
        }

        if ($gatewayStatus === 'PENDING') {
            $intent->update(['status' => 'initialized', 'last_error' => null]);
            return ['status' => 'pending', 'message' => 'Monnify has not confirmed this payment yet. No second charge has been created.'];
        }

        // Partial and over payments are refused by Monnify by default; treat
        // them, like failures, as not fulfilled so support can resolve them.
        if (in_array($gatewayStatus, ['FAILED', 'EXPIRED', 'REVERSED', 'PARTIALLY_PAID', 'OVERPAID', 'CANCELLED'], true)) {
            $intent->update(['status' => 'failed', 'last_error' => 'Monnify reported the transaction as ' . $gatewayStatus . '.']);
            $token = (string) (($intent->payload ?: [])['reservation_token'] ?? '');
            if ($token !== '') {
                app(TicketReservationService::class)->release($token, 'payment_' . strtolower($gatewayStatus));
            }
            if (in_array($gatewayStatus, ['PARTIALLY_PAID', 'OVERPAID'], true)) {
                app(IncidentAlertService::class)->alert(
                    'monnify-wrong-amount:' . $intent->id,
                    'A Monnify buyer paid the wrong amount and needs a refund or top-up.',
                    ['reference' => $intent->reference, 'status' => $gatewayStatus]
                );
            }
            AuditLogger::record('payment.recovery_failed', $intent, [], ['status' => 'failed'], [
                'gateway' => 'monnify', 'reference' => $intent->reference, 'gateway_status' => $gatewayStatus,
            ]);
            return ['status' => 'failed', 'message' => 'Monnify did not complete this transaction. You can safely return to checkout.'];
        }

        $intent->update(['last_error' => 'Unrecognized Monnify status: ' . $gatewayStatus]);
        return ['status' => 'pending', 'message' => 'The payment status is not final yet. Please check again shortly.'];
    }

    private function completed(PaymentIntent $intent, string $source): array
    {
        return ['status' => 'completed', 'booking_id' => $intent->booking_id, 'source' => $source];
    }
}
