<?php

namespace App\Services;

use App\Models\ProductOrderRefund;
use App\Models\ShopManagement\ProductOrder;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ProductOrderRefundService
{
    public function recordFullManualRefund(
        int $orderId,
        string $externalReference,
        string $reason,
        int $adminId,
        bool $restoreInventory
    ): ProductOrderRefund {
        $externalReference = Str::upper(trim($externalReference));
        $reason = trim($reason);
        if ($externalReference === '' || mb_strlen($externalReference) > 191) {
            throw new RuntimeException('A valid external refund reference is required.');
        }
        if ($reason === '') {
            throw new RuntimeException('A refund reason is required.');
        }

        return DB::transaction(function () use (
            $orderId,
            $externalReference,
            $reason,
            $adminId,
            $restoreInventory
        ): ProductOrderRefund {
            $order = ProductOrder::whereKey($orderId)->lockForUpdate()->firstOrFail();
            if (strtolower((string) $order->payment_status) !== 'completed') {
                throw new RuntimeException('Only a completed product payment can be refunded.');
            }
            if ($order->refund_status === 'refunded' || (float) $order->refunded_amount > 0) {
                throw new RuntimeException('This product order has already been refunded.');
            }
            if (ProductOrderRefund::where('product_order_id', $order->id)->exists()) {
                throw new RuntimeException('This product order already has a refund record.');
            }
            if (ProductOrderRefund::where('external_reference', $externalReference)->exists()) {
                throw new RuntimeException('That external refund reference has already been used.');
            }
            if (strtolower((string) $order->order_status) !== 'completed' && !$restoreInventory) {
                throw new RuntimeException('Unfulfilled refunded orders must restore their reserved physical inventory.');
            }
            if ($restoreInventory && !$order->inventory_reserved_at) {
                throw new RuntimeException('This historical order has no authoritative inventory reservation to restore.');
            }
            if ($restoreInventory && $order->inventory_released_at) {
                throw new RuntimeException('This order inventory was already released and cannot be restored again.');
            }

            $sales = Transaction::where('transcation_type', 2)
                ->where('booking_id', (string) $order->id)
                ->where('payment_status', 1)
                ->lockForUpdate()
                ->get();
            if ($sales->count() !== 1) {
                throw new RuntimeException('The paid product sale ledger is missing or ambiguous.');
            }
            $sale = $sales->first();
            if (abs((float) $sale->grand_total - (float) $order->total) >= 0.005) {
                throw new RuntimeException('The paid product sale ledger is missing or does not reconcile with this order.');
            }

            $earning = app(PlatformEarningService::class)->lock();
            if ((float) $earning->total_revenue + 0.005 < (float) $order->total
                || (float) $earning->total_earning + 0.005 < (float) $order->total) {
                throw new RuntimeException('Platform earnings do not reconcile with the requested product refund.');
            }

            $refund = ProductOrderRefund::create([
                'public_id' => (string) Str::uuid(),
                'product_order_id' => $order->id,
                'sale_transaction_id' => $sale->id,
                'requested_by_admin_id' => $adminId,
                'external_reference' => $externalReference,
                'amount' => $order->total,
                'currency' => strtoupper((string) $order->currency_text),
                'reason' => $reason,
                'restore_inventory' => $restoreInventory,
                'status' => 'processed',
                'processed_at' => now(),
            ]);

            $earning->forceFill([
                'total_revenue' => round((float) $earning->total_revenue - (float) $order->total, 2),
                'total_earning' => round((float) $earning->total_earning - (float) $order->total, 2),
            ])->save();

            Transaction::create([
                'transcation_id' => $refund->public_id,
                'booking_id' => $order->id,
                'transcation_type' => 7,
                'customer_id' => $order->user_id ?: null,
                'organizer_id' => null,
                'payment_status' => 'processed',
                'payment_method' => 'Product Refund',
                'grand_total' => $refund->amount,
                'commission' => $refund->amount,
                'tax' => 0,
                'gateway_type' => $order->gateway_type,
                'currency_symbol' => $order->currency_symbol,
                'currency_symbol_position' => $order->currency_symbol_position,
            ]);

            $order->forceFill([
                'payment_status' => 'refunded',
                'order_status' => 'cancelled',
                'refunded_amount' => $order->total,
                'refund_status' => 'refunded',
                'refunded_at' => now(),
            ])->save();

            if ($restoreInventory) {
                app(ProductOrderInventoryService::class)->releaseRefunded($order->fresh(), 'full_product_refund');
                $refund->forceFill(['inventory_restored_at' => now()])->save();
            }
            $refund->forceFill(['financial_applied_at' => now()])->save();

            AuditLogger::record('product_refund.processed', $refund, [], [
                'status' => 'processed',
                'amount' => (float) $refund->amount,
                'restore_inventory' => $restoreInventory,
            ], [
                'product_order_id' => $order->id,
                'external_reference' => $externalReference,
            ]);

            return $refund->fresh();
        }, 3);
    }
}
