<?php

namespace App\Services;

use App\Models\Organizer;
use App\Models\Transaction;
use App\Support\TicaCurrency;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;

class TransactionLedgerService
{
    public function recordGatewaySale(object $booking, string $gateway, string $reference): Transaction
    {
        $gateway = strtolower(trim($gateway));
        $reference = trim($reference);

        if ($gateway === '' || $reference === '') {
            throw new LogicException('A gateway sale requires a gateway and reference.');
        }

        return DB::transaction(function () use ($booking, $gateway, $reference) {
            $existing = Transaction::where('gateway', $gateway)
                ->where('gateway_reference', $reference)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $this->assertSameSale($existing, $booking);
                return $existing;
            }

            $organizer = !empty($booking->organizer_id)
                ? Organizer::whereKey($booking->organizer_id)->lockForUpdate()->first()
                : null;
            // Sales in another currency move that currency's separate balance.
            $currency = TicaCurrency::normalize($booking->currencyText ?? null);
            $net = (float) $booking->price - (float) $booking->commission;
            if ($organizer && !TicaCurrency::isBase($currency)) {
                $current = DB::table('organizer_currency_balances')->where('organizer_id', $organizer->id)->where('currency', $currency)->lockForUpdate()->value('amount');
                $preBalance = round((float) $current, 2);
            } else {
                $preBalance = $organizer ? (float) $organizer->amount : 0.0;
            }
            $afterBalance = $organizer ? $preBalance + $net : null;

            try {
                $attributes = [
                    'transcation_id' => $reference,
                    'gateway' => $gateway,
                    'gateway_reference' => $reference,
                    'booking_id' => $booking->id,
                    'transcation_type' => 1,
                    'customer_id' => $booking->customer_id ?? null,
                    'organizer_id' => $booking->organizer_id ?? null,
                    'payment_status' => $booking->paymentStatus,
                    'payment_method' => $booking->paymentMethod,
                    'grand_total' => $booking->price,
                    'tax' => $booking->tax,
                    'commission' => $booking->commission,
                    'pre_balance' => $preBalance,
                    'after_balance' => $afterBalance,
                    'gateway_type' => $booking->gatewayType,
                    'currency_symbol' => $booking->currencySymbol,
                    'currency_symbol_position' => $booking->currencySymbolPosition,
                ];
                if (Schema::hasColumn('transactions', 'currency')) {
                    $attributes['currency'] = $currency;
                }

                return Transaction::create($attributes);
            } catch (QueryException $exception) {
                // A concurrent process may have won the unique-key race.
                $existing = Transaction::where('gateway', $gateway)
                    ->where('gateway_reference', $reference)
                    ->first();
                if (!$existing) {
                    throw $exception;
                }
                $this->assertSameSale($existing, $booking);
                return $existing;
            }
        }, 3);
    }

    private function assertSameSale(Transaction $transaction, object $booking): void
    {
        $sameBooking = (string) $transaction->booking_id === (string) $booking->id;
        $sameAmount = abs((float) $transaction->grand_total - (float) $booking->price) < 0.005;
        $sameTax = abs((float) $transaction->tax - (float) $booking->tax) < 0.005;
        $sameCommission = abs((float) $transaction->commission - (float) $booking->commission) < 0.005;
        $sameOrganizer = (string) ($transaction->organizer_id ?? '') === (string) ($booking->organizer_id ?? '');
        $sameCustomer = (string) ($transaction->customer_id ?? '') === (string) ($booking->customer_id ?? '');
        $sameMethod = strcasecmp(
            trim((string) $transaction->payment_method),
            trim((string) $booking->paymentMethod)
        ) === 0;
        $paidStatuses = ['1', 'completed', 'paid', 'success'];
        $transactionPaid = in_array(strtolower(trim((string) $transaction->payment_status)), $paidStatuses, true);
        $bookingPaid = in_array(strtolower(trim((string) $booking->paymentStatus)), $paidStatuses, true);

        if (!$sameBooking || !$sameAmount || !$sameTax || !$sameCommission
            || !$sameOrganizer || !$sameCustomer || !$sameMethod
            || !$transactionPaid || !$bookingPaid
            || (int) $transaction->transcation_type !== 1) {
            throw new LogicException('Gateway reference is already linked to a different ledger sale.');
        }
    }
}
