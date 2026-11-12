<?php

namespace App\Services;

use App\Models\PayoutDispute;
use App\Models\Withdraw;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class PayoutDisputeService
{
    private const ACTIVE_STATUSES = [
        PayoutDispute::STATUS_OPEN,
        PayoutDispute::STATUS_UNDER_REVIEW,
    ];

    public function open(
        int $withdrawalId,
        int $organizerId,
        string $category,
        string $description
    ): PayoutDispute {
        return DB::transaction(function () use ($withdrawalId, $organizerId, $category, $description) {
            $withdrawal = Withdraw::whereKey($withdrawalId)->lockForUpdate()->firstOrFail();
            if ((int) $withdrawal->organizer_id !== $organizerId) {
                throw new RuntimeException('This payout does not belong to the organizer.');
            }
            if (!in_array($category, PayoutDispute::CATEGORIES, true)) {
                throw new RuntimeException('Select a valid payout issue category.');
            }

            $description = trim($description);
            if (mb_strlen($description) < 20 || mb_strlen($description) > 2000) {
                throw new RuntimeException('Describe the payout issue in 20 to 2000 characters.');
            }

            $active = PayoutDispute::where('withdrawal_id', $withdrawal->id)
                ->whereIn('status', self::ACTIVE_STATUSES)
                ->lockForUpdate()
                ->first();
            if ($active) {
                throw new RuntimeException('This payout already has an active dispute.');
            }

            $dispute = PayoutDispute::create([
                'public_id' => (string) Str::uuid(),
                'withdrawal_id' => $withdrawal->id,
                'organizer_id' => $organizerId,
                'category' => $category,
                'description' => $description,
                'status' => PayoutDispute::STATUS_OPEN,
                'withdrawal_status' => (int) $withdrawal->status,
                'amount' => round((float) $withdrawal->payable_amount, 2),
                'opened_at' => now(),
            ]);

            AuditLogger::record('payout.dispute_opened', $dispute, [], [
                'status' => PayoutDispute::STATUS_OPEN,
                'category' => $category,
            ], [
                'withdrawal_id' => $withdrawal->id,
                'withdrawal_status' => (int) $withdrawal->status,
                'amount' => (float) $dispute->amount,
            ]);

            return $dispute;
        }, 3);
    }

    public function review(
        int $disputeId,
        int $adminId,
        string $status,
        ?string $resolution
    ): PayoutDispute {
        return DB::transaction(function () use ($disputeId, $adminId, $status, $resolution) {
            $dispute = PayoutDispute::whereKey($disputeId)->lockForUpdate()->firstOrFail();
            $allowed = [
                PayoutDispute::STATUS_UNDER_REVIEW,
                PayoutDispute::STATUS_RESOLVED,
                PayoutDispute::STATUS_REJECTED,
            ];
            if (!in_array($status, $allowed, true)) {
                throw new RuntimeException('Select a valid dispute review status.');
            }
            if (!in_array($dispute->status, self::ACTIVE_STATUSES, true)) {
                throw new RuntimeException('A closed payout dispute cannot be changed.');
            }

            $resolution = trim((string) $resolution);
            if (in_array($status, [PayoutDispute::STATUS_RESOLVED, PayoutDispute::STATUS_REJECTED], true)
                && (mb_strlen($resolution) < 10 || mb_strlen($resolution) > 2000)) {
                throw new RuntimeException('A 10 to 2000 character resolution is required to close a dispute.');
            }
            if ($status === PayoutDispute::STATUS_UNDER_REVIEW && mb_strlen($resolution) > 2000) {
                throw new RuntimeException('The review note may not exceed 2000 characters.');
            }

            $before = $dispute->status;
            $closed = in_array($status, [PayoutDispute::STATUS_RESOLVED, PayoutDispute::STATUS_REJECTED], true);
            $dispute->forceFill([
                'status' => $status,
                'resolution' => $resolution !== '' ? $resolution : null,
                'resolved_by_admin_id' => $adminId,
                'reviewed_at' => now(),
                'closed_at' => $closed ? now() : null,
            ])->save();

            AuditLogger::record('payout.dispute_reviewed', $dispute, [
                'status' => $before,
            ], [
                'status' => $status,
                'resolved_by_admin_id' => $adminId,
            ], [
                'withdrawal_id' => $dispute->withdrawal_id,
                'resolution_recorded' => $resolution !== '',
            ]);

            return $dispute->fresh();
        }, 3);
    }
}
