<?php

namespace App\Services;

use App\Models\Organizer;
use App\Models\Transaction;
use App\Support\TicaCurrency;
use Illuminate\Support\Facades\DB;
use LogicException;

class EventSaleAccountingService
{
    public function __construct(
        private readonly TransactionLedgerService $ledger = new TransactionLedgerService()
    ) {
    }

    /**
     * Record an event sale and apply its platform/organizer balances exactly once.
     *
     * The gateway/reference ledger identity is the idempotency boundary. An
     * existing exact ledger is verification-only and never moves balances again.
     */
    public function apply(
        object $booking,
        string $gateway,
        string $reference,
        ?float $grossRevenue = null
    ): Transaction {
        $price = round((float) $booking->price, 2);
        $tax = round((float) $booking->tax, 2);
        $commission = round((float) $booking->commission, 2);
        $grossRevenue = round($grossRevenue ?? ($price + $tax), 2);

        if ($price < 0 || $tax < 0 || $commission < 0 || $grossRevenue < 0) {
            throw new LogicException('Event sale accounting amounts cannot be negative.');
        }
        if (!empty($booking->organizer_id) && $commission > $price) {
            throw new LogicException('Event sale commission cannot exceed the organizer sale amount.');
        }

        return DB::transaction(function () use (
            $booking,
            $gateway,
            $reference,
            $grossRevenue,
            $price,
            $tax,
            $commission
        ): Transaction {
            // Keep the global lock order aligned with other accounting services:
            // platform earnings first, then organizer balance via the ledger.
            $earning = app(PlatformEarningService::class)->lock();

            $transaction = $this->ledger->recordGatewaySale($booking, $gateway, $reference);
            if (!$transaction->wasRecentlyCreated) {
                return $transaction;
            }

            $platformEarning = !empty($booking->organizer_id)
                ? $tax + $commission
                : $grossRevenue;
            $currency = TicaCurrency::normalize($booking->currencyText ?? null);
            if (!TicaCurrency::isBase($currency)) {
                // Another currency: its own platform totals and organiser balance.
                TicaCurrency::addPlatformEarning($currency, $grossRevenue, $platformEarning);
                if (!empty($booking->organizer_id)) {
                    [$before, $after] = TicaCurrency::adjustBalance((int) $booking->organizer_id, $currency, round($price - $commission, 2));
                    if (abs($after - (float) $transaction->after_balance) >= 0.009) {
                        throw new LogicException('Event sale ledger does not reconcile the organizer currency balance.');
                    }
                }
                AuditLogger::record('event_sale.accounting_applied', $transaction, [], [
                    'gateway' => strtolower(trim($gateway)),
                    'gateway_reference' => trim($reference),
                    'currency' => $currency,
                    'gross_revenue' => $grossRevenue,
                    'platform_earning' => $platformEarning,
                    'organizer_balance_after' => $transaction->after_balance,
                ]);

                return $transaction;
            }
            $earning->forceFill([
                'total_revenue' => round((float) $earning->total_revenue + $grossRevenue, 2),
                'total_earning' => round((float) $earning->total_earning + $platformEarning, 2),
            ])->save();

            if (!empty($booking->organizer_id)) {
                $organizer = Organizer::whereKey($booking->organizer_id)->lockForUpdate()->firstOrFail();
                $expectedAfter = round((float) $transaction->pre_balance + $price - $commission, 2);
                if (abs($expectedAfter - (float) $transaction->after_balance) >= 0.009) {
                    throw new LogicException('Event sale ledger does not reconcile the organizer balance movement.');
                }
                $organizer->amount = $expectedAfter;
                $organizer->save();
            }

            AuditLogger::record('event_sale.accounting_applied', $transaction, [], [
                'gateway' => strtolower(trim($gateway)),
                'gateway_reference' => trim($reference),
                'gross_revenue' => $grossRevenue,
                'platform_earning' => $platformEarning,
                'organizer_balance_after' => $transaction->after_balance,
            ], [
                'booking_id' => $booking->id,
                'organizer_id' => $booking->organizer_id ?? null,
            ]);

            return $transaction;
        }, 3);
    }
}
