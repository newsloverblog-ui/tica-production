<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\Withdraw;
use RuntimeException;

class WithdrawalTransactionEvidenceService
{
    /**
     * Lock and reconcile the single reservation ledger row for a withdrawal.
     *
     * @param  array<int, int|string>  $expectedPaymentStatuses
     */
    public function lockAndValidate(
        Withdraw $withdrawal,
        array $expectedPaymentStatuses = [0]
    ): Transaction {
        $transactions = Transaction::query()
            ->where('booking_id', $withdrawal->id)
            ->where('transcation_type', 3)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($transactions->count() !== 1) {
            throw new RuntimeException(
                'Withdrawal financial evidence is incomplete or duplicated; finance review is required.'
            );
        }

        /** @var Transaction $transaction */
        $transaction = $transactions->first();
        $issues = [];

        if ((int) $transaction->organizer_id !== (int) $withdrawal->organizer_id) {
            $issues[] = 'organizer';
        }
        if ((string) $transaction->payment_method !== (string) $withdrawal->method_id) {
            $issues[] = 'payment method';
        }
        if (!$this->sameMoney($transaction->grand_total, $withdrawal->amount)) {
            $issues[] = 'amount';
        }

        $expected = array_map(static fn ($status): string => (string) $status, $expectedPaymentStatuses);
        if (!in_array((string) $transaction->payment_status, $expected, true)) {
            $issues[] = 'payment status';
        }

        // Pending evidence must still prove the original balance reservation.
        // Terminal legacy rows may have had after_balance rewritten by older code,
        // so their identity is reconciled from the immutable owner/method/amount fields.
        if (in_array('0', $expected, true)) {
            $reserved = round((float) $transaction->pre_balance - (float) $transaction->after_balance, 2);
            if (!$this->sameMoney($reserved, $withdrawal->amount)
                || (float) $transaction->after_balance < 0) {
                $issues[] = 'balance reservation';
            }
        }

        if ($issues !== []) {
            throw new RuntimeException(
                'Withdrawal financial evidence does not reconcile (' . implode(', ', $issues) . '); finance review is required.'
            );
        }

        return $transaction;
    }

    private function sameMoney(mixed $left, mixed $right): bool
    {
        return abs(round((float) $left, 2) - round((float) $right, 2)) < 0.009;
    }
}
