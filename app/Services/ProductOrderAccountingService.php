<?php

namespace App\Services;

use App\Models\ShopManagement\ProductOrder;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;

class ProductOrderAccountingService
{
    /**
     * Post a completed product sale exactly once. Pending manual-payment
     * orders must never increase revenue or appear in the paid ledger.
     */
    public function recordCompleted(
        ProductOrder $order,
        ?string $expectedGateway = null,
        ?string $expectedReference = null
    ): void
    {
        if (strtolower((string) $order->payment_status) !== 'completed') {
            return;
        }

        DB::transaction(function () use ($order, $expectedGateway, $expectedReference): void {
            $lockedOrder = ProductOrder::whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            if (strtolower((string) $lockedOrder->payment_status) !== 'completed') {
                return;
            }

            [$gateway, $gatewayReference] = $this->expectedIdentity(
                $lockedOrder,
                $expectedGateway,
                $expectedReference
            );

            $identityLedger = null;
            if ($gateway !== null) {
                $identityLedger = Transaction::where('gateway', $gateway)
                    ->where('gateway_reference', $gatewayReference)
                    ->lockForUpdate()
                    ->first();

                if ($identityLedger) {
                    $this->assertSameOrder($lockedOrder, $identityLedger);
                }
            }

            $existing = Transaction::where('transcation_type', 2)
                ->where('booking_id', (string) $lockedOrder->id)
                ->where('payment_status', 1)
                ->lockForUpdate()
                ->get();

            if ($existing->count() > 1) {
                throw new LogicException('The product order has multiple paid ledger rows and requires finance review.');
            }

            if ($existing->count() === 1) {
                $ledger = $existing->first();
                $this->assertSameOrder($lockedOrder, $ledger);
                $this->repairOfflineLedgerIdentity($lockedOrder, $ledger);

                if ($gateway !== null && (
                    strtolower(trim((string) $ledger->gateway)) !== $gateway
                    || trim((string) $ledger->gateway_reference) !== $gatewayReference
                )) {
                    throw new LogicException('The product order paid ledger row has a conflicting gateway identity.');
                }

                if ($identityLedger && !$identityLedger->is($ledger)) {
                    throw new LogicException('The product gateway reference is linked to a different ledger row.');
                }

                return;
            }

            $earning = app(PlatformEarningService::class)->lock();
            $earning->total_revenue = (float) $earning->total_revenue + (float) $lockedOrder->total;
            $earning->total_earning = (float) $earning->total_earning + (float) $lockedOrder->total;
            $earning->save();

            $reference = $gatewayReference
                ?? (trim((string) $lockedOrder->tnxid) ?: (string) $lockedOrder->order_number);
            $transaction = [
                'transcation_id' => $reference,
                'booking_id' => $lockedOrder->id,
                'transcation_type' => 2,
                'customer_id' => $lockedOrder->user_id ?: null,
                'organizer_id' => null,
                'payment_status' => 1,
                'payment_method' => $lockedOrder->method,
                'grand_total' => $lockedOrder->total,
                'commission' => $lockedOrder->total,
                'gateway_type' => $lockedOrder->gateway_type,
                'currency_symbol' => $lockedOrder->currency_symbol,
                'currency_symbol_position' => $lockedOrder->currency_symbol_position,
            ];
            if (Schema::hasColumn('transactions', 'gateway') && Schema::hasColumn('transactions', 'gateway_reference')) {
                $transaction['gateway'] = $gateway;
                $transaction['gateway_reference'] = $gatewayReference;
            }
            Transaction::create($transaction);

            AuditLogger::record('product_payment.accounted', $lockedOrder, [], [
                'payment_status' => 'completed',
                'total' => (float) $lockedOrder->total,
            ], ['reference' => $reference]);
        }, 3);
    }

    private function expectedIdentity(
        ProductOrder $order,
        ?string $expectedGateway,
        ?string $expectedReference
    ): array {
        $gateway = strtolower(trim((string) $expectedGateway));
        $reference = trim((string) $expectedReference);

        if (($gateway === '') xor ($reference === '')) {
            throw new LogicException('A product gateway and reference must be supplied together.');
        }

        if ($gateway === '' && strtolower((string) $order->gateway_type) === 'offline') {
            return ['offline', 'order:'.$order->id];
        }

        if ($gateway === '' && strtolower(trim((string) $order->method)) === 'paystack') {
            $reference = trim((string) $order->tnxid);
            if ($reference === '') {
                throw new LogicException('The completed Paystack product order is missing its gateway reference.');
            }

            return ['paystack', $reference];
        }

        return $gateway === '' ? [null, null] : [$gateway, $reference];
    }

    private function assertSameOrder(ProductOrder $order, Transaction $ledger): void
    {
        if ((int) $ledger->transcation_type !== 2
            || (string) $ledger->booking_id !== (string) $order->id) {
            throw new LogicException('The product gateway reference is linked to a different order.');
        }
        if (!in_array(strtolower(trim((string) $ledger->payment_status)), ['1', 'paid', 'completed', 'success'], true)) {
            throw new LogicException('The product order ledger row is not paid.');
        }
        if (abs((float) $ledger->grand_total - (float) $order->total) >= 0.005) {
            throw new LogicException('The product order already has a paid ledger row with a different total.');
        }
        if ((string) ($ledger->customer_id ?? '') !== (string) ($order->user_id ?? '')) {
            throw new LogicException('The product order paid ledger row belongs to a different customer.');
        }

        $ledgerMethod = strtolower(trim((string) $ledger->payment_method));
        $orderMethod = strtolower(trim((string) $order->method));
        if ($ledgerMethod !== '' && $orderMethod !== '' && $ledgerMethod !== $orderMethod) {
            throw new LogicException('The product order paid ledger row uses a different payment method.');
        }
    }

    private function repairOfflineLedgerIdentity(ProductOrder $order, Transaction $ledger): void
    {
        if (strtolower((string) $order->gateway_type) !== 'offline'
            || !Schema::hasColumn('transactions', 'gateway')
            || !Schema::hasColumn('transactions', 'gateway_reference')) {
            return;
        }

        $gateway = trim((string) $ledger->gateway);
        $reference = trim((string) $ledger->gateway_reference);
        $expectedReference = 'order:'.$order->id;

        if (($gateway === '') xor ($reference === '')) {
            throw new LogicException('The product order paid ledger row has a partial gateway identity.');
        }

        if ($gateway !== '' || $reference !== '') {
            if (strtolower($gateway) !== 'offline' || $reference !== $expectedReference) {
                throw new LogicException('The product order paid ledger row has a conflicting gateway identity.');
            }

            return;
        }

        $ledger->forceFill([
            'gateway' => 'offline',
            'gateway_reference' => $expectedReference,
        ])->save();

        AuditLogger::record('product_payment.ledger_identity_repaired', $order, [
            'gateway' => null,
            'gateway_reference' => null,
        ], [
            'gateway' => 'offline',
            'gateway_reference' => $expectedReference,
        ], ['transaction_id' => $ledger->id]);
    }
}
