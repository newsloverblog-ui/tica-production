<?php

namespace App\Services;

use App\Models\Organizer;
use App\Models\Transaction;
use App\Models\Withdraw;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WithdrawalCancellationService
{
    public function __construct(
        private readonly WithdrawalTransactionEvidenceService $evidence = new WithdrawalTransactionEvidenceService()
    ) {
    }

    public function cancel(int $withdrawalId, int $organizerId): bool
    {
        return $this->cancelMany([$withdrawalId], $organizerId) === 1;
    }

    /**
     * Cancel an exact organizer-owned set atomically.
     *
     * Terminal replays are verification-only and never restore the balance again.
     *
     * @param  array<int, int|string>  $withdrawalIds
     */
    public function cancelMany(array $withdrawalIds, int $organizerId): int
    {
        $ids = array_values(array_map('intval', $withdrawalIds));
        if ($ids === [] || in_array(0, $ids, true) || count(array_unique($ids)) !== count($ids)) {
            throw new RuntimeException('Select a unique set of valid withdrawal requests to cancel.');
        }
        sort($ids);

        return DB::transaction(function () use ($ids, $organizerId): int {
            $withdrawals = Withdraw::query()
                ->where('organizer_id', $organizerId)
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($withdrawals->count() !== count($ids)) {
                throw new RuntimeException('One or more withdrawal requests are unavailable.');
            }

            $organizer = Organizer::whereKey($organizerId)->lockForUpdate()->firstOrFail();
            $pending = [];

            foreach ($withdrawals as $withdrawal) {
                if ((int) $withdrawal->status === Withdraw::STATUS_REJECTED) {
                    $this->evidence->lockAndValidate($withdrawal, [2]);
                    continue;
                }
                if ((int) $withdrawal->status !== Withdraw::STATUS_PENDING) {
                    throw new RuntimeException('Only pending withdrawal requests can be cancelled.');
                }

                $pending[] = [
                    'withdrawal' => $withdrawal,
                    'transaction' => $this->evidence->lockAndValidate($withdrawal, [0]),
                ];
            }

            $runningBalance = (float) $organizer->amount;
            foreach ($pending as $entry) {
                /** @var Withdraw $withdrawal */
                $withdrawal = $entry['withdrawal'];
                /** @var Transaction $transaction */
                $transaction = $entry['transaction'];
                $beforeBalance = $runningBalance;
                $runningBalance = round($runningBalance + (float) $withdrawal->amount, 2);

                $withdrawal->forceFill([
                    'status' => Withdraw::STATUS_REJECTED,
                    'decision_reason' => 'Cancelled by organizer before payout processing.',
                    'reviewed_at' => now(),
                ])->save();

                // Preserve the original reservation balance evidence; only its
                // lifecycle status changes when the reservation is released.
                $transaction->payment_status = 2;
                $transaction->save();

                AuditLogger::record('withdrawal.cancelled', $withdrawal, [
                    'status' => Withdraw::STATUS_PENDING,
                ], [
                    'status' => Withdraw::STATUS_REJECTED,
                ], [
                    'bulk' => count($ids) > 1,
                    'balance_before' => $beforeBalance,
                    'balance_after' => $runningBalance,
                    'restored_amount' => (float) $withdrawal->amount,
                    'transaction_id' => $transaction->id,
                ]);
            }

            if ($pending !== []) {
                $organizer->amount = $runningBalance;
                $organizer->save();
            }

            return count($pending);
        }, 3);
    }
}
