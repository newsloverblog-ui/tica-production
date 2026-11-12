<?php

namespace App\Services;

use App\Models\Organizer;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrganizerBalanceAdjustmentService
{
    public function adjust(
        int $organizerId,
        string $direction,
        float $amount,
        string $reason,
        int $adminId,
        string $currencySymbol,
        string $currencyPosition
    ): array {
        return DB::transaction(function () use (
            $organizerId, $direction, $amount, $reason, $adminId, $currencySymbol, $currencyPosition
        ) {
            if (!in_array($direction, ['credit', 'debit'], true)) {
                throw ValidationException::withMessages(['amount_status' => ['Choose credit or debit.']]);
            }
            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount' => ['The adjustment amount must be greater than zero.']]);
            }
            $reason = trim($reason);
            if ($reason === '') {
                throw ValidationException::withMessages(['reason' => ['A reason is required for every balance adjustment.']]);
            }

            $organizer = Organizer::whereKey($organizerId)->lockForUpdate()->firstOrFail();
            $before = (float) $organizer->amount;
            if ($direction === 'debit' && $amount > $before) {
                throw ValidationException::withMessages([
                    'amount' => ['The debit cannot exceed the organizer’s available balance.'],
                ]);
            }
            $after = round($direction === 'credit' ? $before + $amount : $before - $amount, 2);
            $organizer->amount = $after;
            $organizer->save();

            $transaction = Transaction::create([
                'transcation_id' => (string) Str::uuid(),
                'booking_id' => null,
                'transcation_type' => $direction === 'credit' ? 4 : 5,
                'customer_id' => null,
                'organizer_id' => $organizer->id,
                'payment_status' => 1,
                'payment_method' => 'Admin adjustment',
                'grand_total' => $amount,
                'pre_balance' => $before,
                'after_balance' => $after,
                'gateway_type' => null,
                'currency_symbol' => $currencySymbol,
                'currency_symbol_position' => $currencyPosition,
            ]);

            AuditLogger::record('organizer.balance_adjusted', $transaction, [
                'balance' => $before,
            ], [
                'balance' => $after,
            ], [
                'organizer_id' => $organizer->id,
                'direction' => $direction,
                'amount' => $amount,
                'reason' => $reason,
                'admin_id' => $adminId,
            ]);

            return [$organizer, $transaction];
        }, 3);
    }
}
