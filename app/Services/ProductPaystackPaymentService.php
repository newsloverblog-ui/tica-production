<?php

namespace App\Services;

use App\Models\ProductPaymentIntent;
use App\Models\ShopManagement\ProductOrder;
use DomainException;
use Illuminate\Support\Facades\DB;

class ProductPaystackPaymentService
{
    public function verify(ProductPaymentIntent $intent, ?PaystackGatewayClient $client = null): array
    {
        $intent->refresh();
        if ($intent->status === 'completed') {
            return $this->afterCompletion($intent, 'already_completed');
        }

        $intent->increment('verification_attempts');
        $response = ($client ?: new PaystackGatewayClient())->verifyTransaction($intent->reference);
        $intent->forceFill(['last_verified_at' => now()])->save();

        return $this->acceptGatewayData($intent->fresh(), (array) $response['data'], 'api');
    }

    public function acceptWebhook(array $payment): array
    {
        $reference = trim((string) ($payment['reference'] ?? ''));
        if ($reference === '') {
            throw new DomainException('The Paystack webhook has no transaction reference.');
        }
        $intent = ProductPaymentIntent::query()
            ->where('gateway', 'paystack')
            ->where('reference', $reference)
            ->firstOrFail();
        $intent->increment('verification_attempts');
        $intent->forceFill(['last_verified_at' => now()])->save();

        return $this->acceptGatewayData($intent->fresh(), $payment, 'webhook');
    }

    private function acceptGatewayData(ProductPaymentIntent $intent, array $payment, string $source): array
    {
        $gatewayStatus = strtolower((string) ($payment['status'] ?? 'unknown'));
        if ($gatewayStatus === 'success') {
            $errors = ProductPaystackPaymentVerifier::errors($payment, $intent);
            if ($errors !== []) {
                $message = 'Payment verification mismatch: ' . implode(', ', $errors);
                $intent->update(['status' => 'verification_required', 'last_error' => $message]);
                app(IncidentAlertService::class)->alert(
                    'product-paystack-verification-mismatch:' . $intent->id,
                    'Product Paystack payment verification mismatch.',
                    ['reference' => $intent->reference, 'order_id' => $intent->product_order_id, 'errors' => $errors]
                );
                AuditLogger::record('product_payment.verification_mismatch', $intent, [], [
                    'status' => 'verification_required',
                ], ['source' => $source, 'errors' => $errors]);
                return ['status' => 'verification_required', 'errors' => $errors];
            }

            return $this->complete($intent, $payment, $source);
        }

        if (in_array($gatewayStatus, ['pending', 'ongoing', 'processing', 'queued'], true)) {
            $intent->update(['status' => 'initialized', 'last_error' => null]);
            return ['status' => 'pending', 'order_id' => $intent->product_order_id];
        }

        if (in_array($gatewayStatus, ['failed', 'abandoned', 'reversed'], true)) {
            $message = 'Paystack reported the transaction as ' . $gatewayStatus . '.';
            $intent->update(['status' => 'failed', 'last_error' => $message]);
            app(ProductOrderInventoryService::class)->release($intent->order()->firstOrFail(), 'payment_' . $gatewayStatus);
            AuditLogger::record('product_payment.failed', $intent, [], ['status' => 'failed'], [
                'source' => $source, 'gateway_status' => $gatewayStatus,
            ]);
            return ['status' => 'failed', 'order_id' => $intent->product_order_id];
        }

        $intent->update(['last_error' => 'Unrecognized Paystack status: ' . $gatewayStatus]);
        return ['status' => 'pending', 'order_id' => $intent->product_order_id];
    }

    private function complete(ProductPaymentIntent $intent, array $payment, string $source): array
    {
        $order = null;
        $outcome = DB::transaction(function () use ($intent, $payment, $source, &$order): string {
            $lockedIntent = ProductPaymentIntent::query()->whereKey($intent->id)->lockForUpdate()->firstOrFail();
            $order = ProductOrder::query()->whereKey($lockedIntent->product_order_id)->lockForUpdate()->firstOrFail();

            if ($lockedIntent->status === 'completed') {
                return 'already_completed';
            }
            if ($order->inventory_released_at || !$order->inventory_reserved_at) {
                $lockedIntent->update([
                    'status' => 'verification_required',
                    'last_error' => 'Payment succeeded after the inventory reservation was unavailable.',
                    'gateway_transaction_id' => (string) ($payment['id'] ?? ''),
                ]);
                app(IncidentAlertService::class)->alert(
                    'product-paystack-paid-without-stock:' . $lockedIntent->id,
                    'A product payment succeeded without an active inventory reservation.',
                    ['reference' => $lockedIntent->reference, 'order_id' => $order->id]
                );
                return 'verification_required';
            }
            if (strtolower((string) $order->payment_status) === 'completed') {
                $lockedIntent->update([
                    'status' => 'completed',
                    'processed_at' => $lockedIntent->processed_at ?: now(),
                    'gateway_transaction_id' => (string) ($payment['id'] ?? $lockedIntent->gateway_transaction_id),
                    'last_error' => null,
                ]);
                return 'recovered_completed_order';
            }

            $before = ['payment_status' => $order->payment_status, 'status' => $lockedIntent->status];
            $order->forceFill([
                'payment_status' => 'completed',
                'tnxid' => $lockedIntent->reference,
            ])->save();
            $lockedIntent->update([
                'status' => 'completed',
                'processed_at' => now(),
                'gateway_transaction_id' => (string) ($payment['id'] ?? ''),
                'last_error' => null,
            ]);
            AuditLogger::record('product_payment.completed', $lockedIntent, $before, [
                'payment_status' => 'completed', 'status' => 'completed',
            ], ['source' => $source, 'reference' => $lockedIntent->reference, 'order_id' => $order->id]);

            return 'completed';
        }, 3);

        if ($outcome === 'verification_required') {
            return ['status' => $outcome, 'order_id' => $order?->id];
        }

        $order = $order->fresh();
        app(ProductOrderAccountingService::class)->recordCompleted($order);
        app(PaidProductOrderFulfillmentService::class)->dispatch((int) $order->id);

        return ['status' => 'completed', 'source' => $outcome, 'order_id' => $order->id];
    }

    private function afterCompletion(ProductPaymentIntent $intent, string $source): array
    {
        $order = $intent->order()->firstOrFail();
        app(ProductOrderAccountingService::class)->recordCompleted($order);
        app(PaidProductOrderFulfillmentService::class)->dispatch((int) $order->id);
        return ['status' => 'completed', 'source' => $source, 'order_id' => $order->id];
    }
}
