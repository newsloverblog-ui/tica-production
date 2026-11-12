<?php

namespace App\Services;

use App\Models\Organizer;
use App\Models\Withdraw;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WithdrawalDecisionService
{
    public function __construct(
        private readonly WithdrawalTransactionEvidenceService $evidence = new WithdrawalTransactionEvidenceService()
    ) {
    }

    public function hold(int $withdrawalId, string $reason, int $adminId): Withdraw
    {
        return DB::transaction(function () use ($withdrawalId, $reason, $adminId) {
            $withdrawal = Withdraw::whereKey($withdrawalId)->lockForUpdate()->firstOrFail();
            if ((int) $withdrawal->status !== Withdraw::STATUS_PENDING) {
                throw new RuntimeException('Only pending withdrawal requests can be placed on hold.');
            }
            $reason = trim($reason);
            if ($reason === '') throw new RuntimeException('A hold reason is required.');
            $this->evidence->lockAndValidate($withdrawal, [0]);

            $withdrawal->status = Withdraw::STATUS_HELD;
            $withdrawal->decision_reason = $reason;
            $withdrawal->reviewed_by_admin_id = $adminId;
            $withdrawal->reviewed_at = now();
            $withdrawal->save();

            AuditLogger::record('withdrawal.held', $withdrawal, ['status' => Withdraw::STATUS_PENDING], [
                'status' => Withdraw::STATUS_HELD,
            ], ['reason' => $reason]);
            return $withdrawal;
        }, 3);
    }

    public function markPaid(int $withdrawalId, string $payoutReference, int $adminId): Withdraw
    {
        return DB::transaction(function () use ($withdrawalId, $payoutReference, $adminId) {
            $withdrawal = Withdraw::whereKey($withdrawalId)->lockForUpdate()->firstOrFail();
            $payoutReference = trim($payoutReference);
            if ((int) $withdrawal->status === Withdraw::STATUS_PAID) {
                if ($payoutReference !== (string) $withdrawal->payout_reference) {
                    throw new RuntimeException('The withdrawal is already paid under a different payout reference.');
                }
                $this->evidence->lockAndValidate($withdrawal, [1]);
                return $withdrawal;
            }
            if (!in_array((int) $withdrawal->status, [Withdraw::STATUS_PENDING, Withdraw::STATUS_HELD], true)) {
                throw new RuntimeException('Only pending or held withdrawal requests can be marked paid.');
            }
            if ($payoutReference === '') throw new RuntimeException('A payout reference is required.');
            if (Withdraw::where('payout_reference', $payoutReference)->where('id', '!=', $withdrawal->id)->exists()) {
                throw new RuntimeException('This payout reference is already attached to another withdrawal.');
            }
            $transaction = $this->evidence->lockAndValidate($withdrawal, [0]);

            $beforeStatus = (int) $withdrawal->status;
            $withdrawal->status = Withdraw::STATUS_PAID;
            $withdrawal->payout_reference = $payoutReference;
            $withdrawal->decision_reason = null;
            $withdrawal->reviewed_by_admin_id = $adminId;
            $withdrawal->reviewed_at = now();
            $withdrawal->paid_at = now();
            $withdrawal->save();

            $transaction->payment_status = 1;
            $transaction->save();
            AuditLogger::record('withdrawal.paid', $withdrawal, ['status' => $beforeStatus], [
                'status' => Withdraw::STATUS_PAID,
            ], [
                'amount' => (float) $withdrawal->amount,
                'payable_amount' => (float) $withdrawal->payable_amount,
                'payout_reference' => $payoutReference,
            ]);
            return $withdrawal;
        }, 3);
    }

    public function reject(int $withdrawalId, string $reason, int $adminId): Withdraw
    {
        return DB::transaction(function () use ($withdrawalId, $reason, $adminId) {
            $withdrawal = Withdraw::whereKey($withdrawalId)->lockForUpdate()->firstOrFail();
            if ((int) $withdrawal->status === Withdraw::STATUS_REJECTED) {
                $this->evidence->lockAndValidate($withdrawal, [2]);
                return $withdrawal;
            }
            if (!in_array((int) $withdrawal->status, [Withdraw::STATUS_PENDING, Withdraw::STATUS_HELD], true)) {
                throw new RuntimeException('Only pending or held withdrawal requests can be rejected.');
            }
            $reason = trim($reason);
            if ($reason === '') throw new RuntimeException('A rejection reason is required.');
            $transaction = $this->evidence->lockAndValidate($withdrawal, [0]);

            $organizer = Organizer::whereKey($withdrawal->organizer_id)->lockForUpdate()->firstOrFail();
            $beforeStatus = (int) $withdrawal->status;
            $beforeBalance = (float) $organizer->amount;
            $organizer->amount = round($beforeBalance + (float) $withdrawal->amount, 2);
            $organizer->save();

            $withdrawal->status = Withdraw::STATUS_REJECTED;
            $withdrawal->decision_reason = $reason;
            $withdrawal->reviewed_by_admin_id = $adminId;
            $withdrawal->reviewed_at = now();
            $withdrawal->save();
            $transaction->payment_status = 2;
            $transaction->save();

            AuditLogger::record('withdrawal.rejected', $withdrawal, ['status' => $beforeStatus], [
                'status' => Withdraw::STATUS_REJECTED,
            ], [
                'reason' => $reason,
                'balance_before' => $beforeBalance,
                'balance_after' => (float) $organizer->amount,
                'restored_amount' => (float) $withdrawal->amount,
                'transaction_id' => $transaction->id,
            ]);
            return $withdrawal;
        }, 3);
    }
}
