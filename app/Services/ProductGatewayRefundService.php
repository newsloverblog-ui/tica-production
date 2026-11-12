<?php

namespace App\Services;

use App\Exceptions\GatewayOutcomeUnknownException;
use App\Models\ProductGatewayRefund;
use App\Models\ProductOrderRefund;
use App\Models\ProductPaymentIntent;
use App\Models\ShopManagement\ProductOrder;
use App\Models\Transaction;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ProductGatewayRefundService
{
    public function requestFull(
        int $orderId,
        string $reason,
        int $adminId,
        bool $restoreInventory
    ): ProductGatewayRefund {
        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('A refund reason is required.');
        }

        return DB::transaction(function () use ($orderId, $reason, $adminId, $restoreInventory): ProductGatewayRefund {
            $order = ProductOrder::whereKey($orderId)->lockForUpdate()->firstOrFail();
            $intent = ProductPaymentIntent::query()
                ->where('product_order_id', $order->id)
                ->where('gateway', 'paystack')
                ->lockForUpdate()
                ->first();
            if (!$intent || $intent->status !== 'completed' || strtolower((string) $order->payment_status) !== 'completed') {
                throw new RuntimeException('Only a completed durable Paystack product payment can use provider refunds.');
            }
            if (ProductGatewayRefund::where('product_order_id', $order->id)->exists()
                || ProductOrderRefund::where('product_order_id', $order->id)->exists()) {
                throw new RuntimeException('This product order already has a refund record.');
            }
            if (strtolower((string) $order->order_status) !== 'completed' && !$restoreInventory) {
                throw new RuntimeException('Unfulfilled refunded orders must restore their reserved physical inventory.');
            }
            if ($restoreInventory && (!$order->inventory_reserved_at || $order->inventory_released_at)) {
                throw new RuntimeException('The order inventory is not eligible for restoration.');
            }

            $sale = Transaction::query()
                ->where('transcation_type', 2)
                ->where('booking_id', (string) $order->id)
                ->where('payment_status', 1)
                ->lockForUpdate()
                ->first();
            if (!$sale || abs((float) $sale->grand_total - (float) $intent->amount) >= 0.005) {
                throw new RuntimeException('The paid product sale ledger is missing or does not reconcile with this payment.');
            }
            if ($sale->gateway && ($sale->gateway !== 'paystack' || $sale->gateway_reference !== $intent->reference)) {
                throw new RuntimeException('The product sale gateway identity does not match the payment intent.');
            }

            $refund = ProductGatewayRefund::create([
                'public_id' => (string) Str::uuid(),
                'product_payment_intent_id' => $intent->id,
                'product_order_id' => $order->id,
                'sale_transaction_id' => $sale->id,
                'requested_by_admin_id' => $adminId,
                'gateway' => 'paystack',
                'gateway_reference' => $intent->reference,
                'amount' => $intent->amount,
                'currency' => strtoupper((string) $intent->currency),
                'reason' => $reason,
                'restore_inventory' => $restoreInventory,
                'status' => 'requested',
                'requested_at' => now(),
            ]);
            AuditLogger::record('product_gateway_refund.requested', $refund, [], [
                'status' => 'requested', 'amount' => (float) $refund->amount,
                'restore_inventory' => $restoreInventory,
            ], ['reference' => $intent->reference, 'order_id' => $order->id]);

            return $refund;
        }, 3);
    }

    public function submit(ProductGatewayRefund $refund, ?PaystackGatewayClient $client = null): ProductGatewayRefund
    {
        $claimed = DB::transaction(function () use ($refund): bool {
            $locked = ProductGatewayRefund::whereKey($refund->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'requested') {
                return false;
            }
            $locked->update(['status' => 'submitting', 'last_error' => null]);
            return true;
        }, 3);
        if (!$claimed) {
            return $refund->fresh();
        }

        try {
            $payload = ($client ?: new PaystackGatewayClient())->createRefund(
                $refund->gateway_reference,
                (string) $refund->amount,
                $refund->currency,
                $refund->reason
            );
            $data = (array) ($payload['data'] ?? []);
            $gatewayStatus = strtolower((string) ($data['status'] ?? 'pending'));
            $refund->fresh()->update(['submitted_at' => now(), 'gateway_response' => $payload]);
            $this->recordGatewayStatus($refund->fresh(), $gatewayStatus, $data);
        } catch (ConnectionException|GatewayOutcomeUnknownException $exception) {
            $refund->fresh()->update([
                'status' => 'verification_required',
                'last_error' => mb_substr($exception->getMessage(), 0, 1000),
            ]);
        } catch (Throwable $exception) {
            $refund->fresh()->update([
                'status' => 'failed',
                'last_error' => mb_substr($exception->getMessage(), 0, 1000),
            ]);
        }

        return $refund->fresh();
    }

    public function handleWebhook(string $eventName, array $data): ?ProductGatewayRefund
    {
        $reference = (string) ($data['transaction_reference'] ?? '');
        $amount = round(((float) ($data['amount'] ?? 0)) / 100, 2);
        if ($reference === '' || $amount <= 0) {
            return null;
        }
        $refund = ProductGatewayRefund::query()
            ->where('gateway', 'paystack')
            ->where('gateway_reference', $reference)
            ->where('amount', $amount)
            ->where('status', '!=', 'processed')
            ->latest('id')
            ->first();
        if (!$refund) {
            return null;
        }
        return $this->recordGatewayStatus($refund, str_replace('refund.', '', $eventName), $data);
    }

    public function recoverUnknownSubmission(
        ProductGatewayRefund $refund,
        ?PaystackGatewayClient $client = null
    ): ProductGatewayRefund {
        $refund->refresh();
        if ($refund->gateway_refund_id || !in_array($refund->status, ['verification_required', 'submitting'], true)) {
            return $refund;
        }

        $intent = ProductPaymentIntent::find($refund->product_payment_intent_id);
        $transactionId = trim((string) $intent?->gateway_transaction_id);
        if ($transactionId === '') {
            return $this->markRecoveryAttention(
                $refund,
                'The original Paystack transaction ID is unavailable for deterministic refund recovery.'
            );
        }

        $payload = ($client ?: new PaystackGatewayClient())->listRefundsForTransaction($transactionId);
        $matches = collect((array) ($payload['data'] ?? []))->filter(function ($candidate) use ($refund, $transactionId): bool {
            if (!is_array($candidate) || empty($candidate['id'])) {
                return false;
            }
            $reportedTransaction = data_get($candidate, 'transaction.id', $candidate['transaction'] ?? null);
            return ($reportedTransaction === null || (string) $reportedTransaction === $transactionId)
                && round(((float) ($candidate['amount'] ?? 0)) / 100, 2) === round((float) $refund->amount, 2)
                && strtoupper((string) ($candidate['currency'] ?? '')) === strtoupper($refund->currency);
        })->values();

        if ($matches->isEmpty()) {
            $refund->update([
                'status' => 'verification_required',
                'last_error' => 'Paystack has not returned a matching refund yet; reconciliation will retry.',
            ]);
            $this->alertIfRecoveryStale($refund->fresh());
            return $refund->fresh();
        }
        if ($matches->count() !== 1) {
            return $this->markRecoveryAttention(
                $refund,
                'Multiple Paystack refunds matched the controlled transaction, amount and currency.'
            );
        }

        $data = (array) $matches->first();
        return $this->recordGatewayStatus($refund, (string) ($data['status'] ?? 'pending'), $data);
    }

    public function recordGatewayStatus(ProductGatewayRefund $refund, string $gatewayStatus, array $data): ProductGatewayRefund
    {
        $refund->refresh();
        if ($refund->status === 'processed') {
            $this->applyAfterProviderSuccess($refund);
            return $refund->fresh();
        }

        $reportedReference = (string) ($data['transaction_reference'] ?? data_get($data, 'transaction.reference', ''));
        $reportedAmount = $data['amount'] ?? null;
        $reportedCurrency = strtoupper((string) ($data['currency'] ?? ''));
        $reportedRefundId = (string) ($data['id'] ?? '');
        $amountMismatch = $reportedAmount !== null
            && round(((float) $reportedAmount) / 100, 2) !== round((float) $refund->amount, 2);
        $referenceMismatch = $reportedReference !== '' && $reportedReference !== $refund->gateway_reference;
        $currencyMismatch = $reportedCurrency !== '' && $reportedCurrency !== strtoupper($refund->currency);
        $refundIdMismatch = $reportedRefundId !== '' && $refund->gateway_refund_id
            && $reportedRefundId !== (string) $refund->gateway_refund_id;
        if ($amountMismatch || $referenceMismatch || $currencyMismatch || $refundIdMismatch) {
            $refund->update([
                'status' => 'verification_required',
                'last_error' => 'Provider refund identity mismatch.',
            ]);
            app(IncidentAlertService::class)->alert(
                'product-paystack-refund-mismatch:' . $refund->id,
                'Paystack product refund status did not match the controlled refund.',
                [
                    'refund_id' => $refund->public_id,
                    'amount_mismatch' => $amountMismatch,
                    'reference_mismatch' => $referenceMismatch,
                    'currency_mismatch' => $currencyMismatch,
                    'refund_id_mismatch' => $refundIdMismatch,
                ]
            );
            return $refund->fresh();
        }

        $updated = DB::transaction(function () use ($refund, $gatewayStatus, $data): ProductGatewayRefund {
            $locked = ProductGatewayRefund::whereKey($refund->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'processed') {
                return $locked;
            }
            $status = $this->localStatus($gatewayStatus);
            $before = $locked->status;
            $locked->update([
                'status' => $status,
                'gateway_status' => $gatewayStatus,
                'gateway_response' => $data,
                'gateway_refund_id' => $data['refund_reference'] ?? $data['id'] ?? $locked->gateway_refund_id,
                'completed_at' => $status === 'processed' ? now() : $locked->completed_at,
                'last_error' => $status === 'failed' ? ($data['reason'] ?? 'Gateway refund failed.') : null,
            ]);
            AuditLogger::record('product_gateway_refund.status_changed', $locked, ['status' => $before], [
                'status' => $status, 'gateway_status' => $gatewayStatus,
            ]);
            return $locked->fresh();
        }, 3);
        if ($updated->status === 'processed') {
            $this->applyAfterProviderSuccess($updated);
        }
        return $updated->fresh();
    }

    public function applyFinancialEffects(ProductGatewayRefund $refund): void
    {
        $refund->refresh();
        if ($refund->status !== 'processed' || $refund->financial_applied_at) {
            return;
        }
        $externalReference = 'PAYSTACK-' . ($refund->gateway_refund_id ?: $refund->public_id);
        try {
            app(ProductOrderRefundService::class)->recordFullManualRefund(
                (int) $refund->product_order_id,
                $externalReference,
                'Paystack-confirmed refund: ' . $refund->reason,
                (int) $refund->requested_by_admin_id,
                (bool) $refund->restore_inventory
            );
        } catch (RuntimeException $exception) {
            if (!ProductOrderRefund::where('product_order_id', $refund->product_order_id)
                ->where('external_reference', Str::upper($externalReference))
                ->where('status', 'processed')
                ->exists()) {
                throw $exception;
            }
        }
        $refund->fresh()->update(['financial_applied_at' => now()]);
        AuditLogger::record('product_gateway_refund.financial_effects_applied', $refund->fresh(), [], [
            'amount' => (float) $refund->amount,
        ], ['order_id' => $refund->product_order_id]);
    }

    private function localStatus(string $gatewayStatus): string
    {
        return match (strtolower($gatewayStatus)) {
            'processed' => 'processed',
            'processing' => 'processing',
            'needs-attention', 'needs_attention' => 'needs_attention',
            'failed' => 'failed',
            default => 'pending',
        };
    }

    private function applyAfterProviderSuccess(ProductGatewayRefund $refund): void
    {
        try {
            $this->applyFinancialEffects($refund);
        } catch (Throwable $exception) {
            $refund->fresh()->update([
                'status' => 'needs_attention',
                'last_error' => mb_substr($exception->getMessage(), 0, 1000),
            ]);
            app(IncidentAlertService::class)->alert(
                'product-refund-financial-apply:' . $refund->id,
                'A provider-confirmed product refund could not be applied locally.',
                ['refund_id' => $refund->public_id, 'order_id' => $refund->product_order_id],
                'critical'
            );
        }
    }

    private function markRecoveryAttention(ProductGatewayRefund $refund, string $message): ProductGatewayRefund
    {
        $refund->update(['status' => 'needs_attention', 'last_error' => $message]);
        app(IncidentAlertService::class)->alert(
            'product-refund-recovery:' . $refund->id,
            'A Paystack product refund could not be recovered deterministically.',
            ['refund_id' => $refund->public_id, 'failure_recorded' => filled($message)],
            'critical'
        );
        return $refund->fresh();
    }

    private function alertIfRecoveryStale(ProductGatewayRefund $refund): void
    {
        $minutes = (int) config('operations.refund_recovery_alert_minutes', 30);
        if (!$refund->requested_at || $refund->requested_at->isAfter(now()->subMinutes($minutes))) {
            return;
        }
        app(IncidentAlertService::class)->alert(
            'product-refund-recovery-stale:' . $refund->id,
            'A Paystack product refund remains indeterminate after provider reconciliation.',
            ['refund_id' => $refund->public_id, 'age_minutes' => $refund->requested_at->diffInMinutes(now())],
            'critical'
        );
    }
}
