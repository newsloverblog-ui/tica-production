<?php

namespace App\Services;

use App\Models\Organizer;
use App\Models\OrganizerPayoutAccount;
use App\Models\Transaction;
use App\Models\Withdraw;
use App\Models\WithdrawPaymentMethod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WithdrawalService
{
    public function request(
        int $organizerId,
        int $methodId,
        float $amount,
        ?string $additionalReference,
        string $currencySymbol,
        string $currencyPosition
    ): Withdraw {
        return DB::transaction(function () use (
            $organizerId, $methodId, $amount, $additionalReference, $currencySymbol, $currencyPosition
        ) {
            $method = WithdrawPaymentMethod::whereKey($methodId)
                ->where('status', 1)->lockForUpdate()->first();
            if (!$method) {
                throw ValidationException::withMessages([
                    'withdraw_method' => ['The selected withdrawal method is unavailable.'],
                ]);
            }
            $this->validateAmount($method, $amount);

            $organizer = Organizer::whereKey($organizerId)->lockForUpdate()->firstOrFail();
            if ($organizer->kyc_status !== 'verified') {
                throw ValidationException::withMessages([
                    'withdraw_method' => ['Complete organizer verification before requesting a withdrawal.'],
                ]);
            }
            $payoutAccount = OrganizerPayoutAccount::where('organizer_id', $organizer->id)
                ->where('status', 'verified')->where('is_default', true)
                ->lockForUpdate()->first();
            if (!$payoutAccount) {
                throw ValidationException::withMessages([
                    'withdraw_method' => ['Add and verify a payout bank account before requesting a withdrawal.'],
                ]);
            }
            if ($payoutAccount->hold_until && now()->lt($payoutAccount->hold_until)) {
                throw ValidationException::withMessages([
                    'withdraw_method' => ['Payouts are on a 24-hour security hold after a bank-account change.'],
                ]);
            }
            if ((float) $organizer->amount < $amount) {
                throw ValidationException::withMessages([
                    'withdraw_amount' => ['You do not have enough available balance to withdraw.'],
                ]);
            }

            [$totalCharge, $payableAmount] = $this->charges($method, $amount);
            $preBalance = (float) $organizer->amount;
            $organizer->amount = round($preBalance - $amount, 2);
            $organizer->save();

            $withdraw = new Withdraw();
            $withdraw->withdraw_id = (string) Str::uuid();
            $withdraw->organizer_id = $organizer->id;
            $withdraw->method_id = $method->id;
            $withdraw->payout_account_id = $payoutAccount->id;
            $withdraw->amount = $amount;
            $withdraw->payable_amount = $payableAmount;
            $withdraw->total_charge = $totalCharge;
            $withdraw->additional_reference = $additionalReference;
            $withdraw->feilds = json_encode([
                'bank_name' => $payoutAccount->bank_name,
                'account_name' => $payoutAccount->resolved_account_name,
                'account_number' => $payoutAccount->maskedNumber(),
            ]);
            $withdraw->status = 0;
            $withdraw->save();

            Transaction::create([
                'transcation_id' => (string) Str::uuid(),
                'booking_id' => $withdraw->id,
                'transcation_type' => 3,
                'customer_id' => null,
                'organizer_id' => $organizer->id,
                'payment_status' => 0,
                'payment_method' => $method->id,
                'grand_total' => $withdraw->amount,
                'pre_balance' => $preBalance,
                'after_balance' => (float) $organizer->amount,
                'gateway_type' => null,
                'currency_symbol' => $currencySymbol,
                'currency_symbol_position' => $currencyPosition,
            ]);

            AuditLogger::record('withdrawal.requested', $withdraw, [], [
                'status' => 0,
                'amount' => $amount,
                'payable_amount' => $payableAmount,
                'total_charge' => $totalCharge,
            ], [
                'balance_before' => $preBalance,
                'balance_after' => (float) $organizer->amount,
                'method_id' => $method->id,
                'payout_account_id' => $payoutAccount->id,
                'payout_account_last4' => $payoutAccount->account_last4,
            ]);

            return $withdraw;
        }, 3);
    }

    public function charges(WithdrawPaymentMethod $method, float $amount): array
    {
        $totalCharge = round(
            (float) $method->fixed_charge + ($amount * (float) $method->percentage_charge / 100),
            2
        );
        if ($totalCharge >= $amount) {
            throw ValidationException::withMessages([
                'withdraw_amount' => ['The withdrawal amount must be greater than the applicable fees.'],
            ]);
        }
        return [$totalCharge, round($amount - $totalCharge, 2)];
    }

    private function validateAmount(WithdrawPaymentMethod $method, float $amount): void
    {
        if ($amount < (float) $method->min_limit) {
            throw ValidationException::withMessages([
                'withdraw_amount' => ['Minimum withdrawal amount is ' . $method->min_limit . '.'],
            ]);
        }
        if ($amount > (float) $method->max_limit) {
            throw ValidationException::withMessages([
                'withdraw_amount' => ['Maximum withdrawal amount is ' . $method->max_limit . '.'],
            ]);
        }
    }
}
