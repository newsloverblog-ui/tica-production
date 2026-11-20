<?php

namespace App\Services;

use App\Models\PaymentIntent;
use Illuminate\Support\Facades\Log;
use Throwable;

class PaystackPaymentRecoveryService
{
    public function attempt(
        PaymentIntent $intent,
        callable $fulfill,
        ?PaystackGatewayClient $client = null
    ): array {
        $intent->refresh();
        if ($intent->status === 'completed') {
            return $this->completed($intent, 'already_completed');
        }

        $intent->increment('verification_attempts');
        $gateway = ($client ?: new PaystackGatewayClient())->verifyTransaction($intent->reference);
        $data = $gateway['data'];
        $gatewayStatus = strtolower((string) ($data['status'] ?? 'unknown'));
        $intent->forceFill(['last_verified_at' => now()])->save();

        if ($gatewayStatus === 'success') {
            $errors = PaystackPaymentVerifier::errors($data, array_merge(
                $intent->payload ?: [],
                ['reference' => $intent->reference]
            ));
            if ($errors) {
                $message = 'Payment verification mismatch: ' . implode(', ', $errors);
                $intent->update(['status' => 'failed', 'last_error' => $message]);
                app(IncidentAlertService::class)->alert(
                    'paystack-recovery-mismatch:' . $intent->id,
                    'Paystack recovery verification mismatch.', [
                    'reference' => $intent->reference,
                    'errors' => $errors,
                ]);
                AuditLogger::record(
                    'payment.recovery_mismatch',
                    $intent,
                    [],
                    ['status' => 'failed'],
                    ['gateway' => 'paystack', 'reference' => $intent->reference, 'errors' => $errors]
                );
                return ['status' => 'failed', 'message' => 'We could not safely match this payment to the checkout. Support has been alerted.'];
            }

            try {
                $result = $fulfill();
            } catch (Throwable $exception) {
                $intent->refresh();
                if ($intent->status !== 'completed') {
                    $intent->forceFill([
                        'status' => 'verification_required',
                        'last_error' => 'Paystack verified the payment, but ticket finalization needs to be retried.',
                    ])->save();
                }

                Log::error('Verified Paystack payment is awaiting ticket finalization.', [
                    'payment_intent_id' => $intent->id,
                    'reference' => $intent->reference,
                    'exception_type' => $exception::class,
                    'exception_code' => $exception->getCode(),
                ]);
                app(IncidentAlertService::class)->alert(
                    'paystack-fulfillment-retry:' . $intent->id,
                    'A verified Paystack payment is awaiting ticket finalization.', [
                        'reference' => $intent->reference,
                        'payment_intent_id' => $intent->id,
                    ]
                );
                AuditLogger::record(
                    'payment.fulfillment_retry_required',
                    $intent,
                    [],
                    ['status' => 'verification_required'],
                    ['gateway' => 'paystack', 'reference' => $intent->reference]
                );

                return [
                    'status' => 'verification_required',
                    'message' => 'Paystack verified your payment. TICA is still finalizing your ticket; no second charge was created. Check again shortly.',
                ];
            }
            $intent->refresh();
            if ($intent->status === 'completed') {
                return $this->completed($intent, (string) ($result['status'] ?? 'completed'));
            }
            if (($result['status'] ?? null) === 'already_processed') {
                $bookingId = $result['existingTxn']->booking_id ?? $intent->booking_id;
                return ['status' => 'completed', 'booking_id' => $bookingId, 'source' => 'already_processed'];
            }

            return ['status' => 'processing', 'message' => 'Your verified payment is currently being finalized. Please check again shortly.'];
        }

        if (in_array($gatewayStatus, ['pending', 'ongoing', 'processing', 'queued'], true)) {
            $intent->update(['status' => 'initialized', 'last_error' => null]);
            return ['status' => 'pending', 'message' => 'Paystack has not confirmed this payment yet. No second charge has been created.'];
        }

        if (in_array($gatewayStatus, ['failed', 'abandoned', 'reversed'], true)) {
            $message = 'Paystack reported the transaction as ' . $gatewayStatus . '.';
            $intent->update(['status' => 'failed', 'last_error' => $message]);
            $token = (string) (($intent->payload ?: [])['reservation_token'] ?? '');
            if ($token !== '') {
                app(TicketReservationService::class)->release($token, 'payment_' . $gatewayStatus);
            }
            AuditLogger::record(
                'payment.recovery_failed',
                $intent,
                [],
                ['status' => 'failed'],
                ['gateway' => 'paystack', 'reference' => $intent->reference, 'gateway_status' => $gatewayStatus]
            );
            return ['status' => 'failed', 'message' => 'Paystack did not complete this transaction. You can safely return to checkout.'];
        }

        $intent->update(['last_error' => 'Unrecognized Paystack status: ' . $gatewayStatus]);
        return ['status' => 'pending', 'message' => 'The payment status is not final yet. Please check again shortly.'];
    }

    private function completed(PaymentIntent $intent, string $source): array
    {
        return [
            'status' => 'completed',
            'booking_id' => $intent->booking_id,
            'source' => $source,
        ];
    }
}
