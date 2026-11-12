<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\ProductOrderDelivery;
use App\Models\ShopManagement\ProductOrder;
use DomainException;
use Illuminate\Support\Facades\DB;
use LogicException;

class ManualProductOrderPaymentService
{
    public function transition(int $orderId, string $targetStatus): ManualProductOrderPaymentResult
    {
        if (!in_array($targetStatus, ['completed', 'rejected'], true)) {
            throw new DomainException('Manual payment review can only approve or reject a pending order.');
        }

        return DB::transaction(function () use ($orderId, $targetStatus): ManualProductOrderPaymentResult {
            $order = ProductOrder::query()
                ->whereNull('archived_at')
                ->lockForUpdate()
                ->findOrFail($orderId);
            $before = strtolower((string) $order->payment_status);

            if (strtolower((string) $order->gateway_type) !== 'offline') {
                throw new DomainException('Only offline product orders can be manually approved or rejected.');
            }

            if ($before === $targetStatus) {
                $this->verifyTerminalEffects($order, $targetStatus);
                return new ManualProductOrderPaymentResult($order->fresh(), $before, false);
            }
            if ($before !== 'pending') {
                throw new DomainException('Only pending offline product orders can be approved or rejected.');
            }
            if ($targetStatus === 'completed' && !$order->inventory_reserved_at) {
                throw new DomainException('This order has no verified inventory reservation and cannot be approved.');
            }
            if ($targetStatus === 'rejected') {
                $this->assertNoExistingFinancialEvidence($order);
            }

            $order->forceFill(['payment_status' => $targetStatus])->save();
            AuditLogger::record('product_payment.status_changed', $order,
                ['payment_status' => $before], ['payment_status' => $targetStatus]);

            $this->applyTerminalEffects($order, $targetStatus);

            return new ManualProductOrderPaymentResult($order->fresh(), $before, true);
        }, 3);
    }

    private function applyTerminalEffects(ProductOrder $order, string $status): void
    {
        if ($status === 'completed') {
            app(ProductOrderAccountingService::class)->recordCompleted($order->fresh());
            $this->ensureFulfillmentPending($order);
            return;
        }

        app(ProductOrderInventoryService::class)->release($order->fresh(), 'payment_rejected');
        $this->ensureRejectionNotificationPending($order);
    }

    private function verifyTerminalEffects(ProductOrder $order, string $status): void
    {
        if ($status === 'rejected') {
            $this->assertNoExistingFinancialEvidence($order);
            app(ProductOrderInventoryService::class)->release($order->fresh(), 'payment_rejected');
            $this->ensureRejectionNotificationPending($order);
            return;
        }

        $ledgers = Transaction::query()
            ->where('transcation_type', 2)
            ->where('booking_id', (string) $order->id)
            ->lockForUpdate()
            ->get();
        if ($ledgers->count() !== 1 || (string) $ledgers->first()->payment_status !== '1') {
            throw new DomainException(
                'This completed order has no single paid ledger record and requires finance review.'
            );
        }

        try {
            app(ProductOrderAccountingService::class)->recordCompleted($order->fresh());
            $this->ensureFulfillmentPending($order);
        } catch (LogicException $exception) {
            throw new DomainException($exception->getMessage(), previous: $exception);
        }
    }

    private function ensureFulfillmentPending(ProductOrder $order): void
    {
        ProductOrderDelivery::firstOrCreate(
            ['product_order_id' => $order->id, 'kind' => 'product_order_confirmation'],
            ['status' => 'pending']
        );
    }

    private function ensureRejectionNotificationPending(ProductOrder $order): void
    {
        ProductOrderDelivery::firstOrCreate(
            ['product_order_id' => $order->id, 'kind' => 'product_order_rejection'],
            ['status' => 'pending']
        );
    }

    private function assertNoExistingFinancialEvidence(ProductOrder $order): void
    {
        $hasLedger = Transaction::query()
            ->where('transcation_type', 2)
            ->where('booking_id', (string) $order->id)
            ->lockForUpdate()
            ->exists();

        if ($hasLedger) {
            throw new DomainException(
                'This order already has financial ledger evidence and requires finance review.'
            );
        }
    }
}
