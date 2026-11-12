<?php

namespace App\Services;

use App\Models\Withdraw;

class OrganizerPayoutScheduleService
{
    public function __construct(private readonly PayoutEligibilityService $eligibility)
    {
    }

    public function summary(int $organizerId): array
    {
        $withdrawal = Withdraw::query()
            ->where('organizer_id', $organizerId)
            ->whereIn('status', [
                Withdraw::STATUS_PENDING,
                Withdraw::STATUS_HELD,
                Withdraw::STATUS_PROCESSING,
            ])
            ->orderByRaw('CASE status WHEN ? THEN 0 WHEN ? THEN 1 ELSE 2 END', [
                Withdraw::STATUS_PROCESSING,
                Withdraw::STATUS_PENDING,
            ])
            ->oldest('created_at')
            ->first();

        if (!$withdrawal) {
            return $this->result('none', 'No payout requested', 'Submit a withdrawal when funds are available.');
        }

        if ((int) $withdrawal->status === Withdraw::STATUS_PROCESSING) {
            return $this->result(
                'processing',
                'Processing now',
                'Your payout has been submitted for transfer.',
                null,
                $withdrawal
            );
        }

        if ((int) $withdrawal->status === Withdraw::STATUS_HELD) {
            return $this->result(
                'review_required',
                'Review required',
                'This payout is on hold. Check your withdrawal history for the review reason.',
                null,
                $withdrawal
            );
        }

        $decision = $this->eligibility->evaluate($withdrawal);
        $reasons = (array) $decision['reasons'];
        $scheduledAt = $decision['scheduled_release_at'] ?? null;

        if ($decision['eligible']) {
            return $this->result(
                'ready',
                'Ready for release',
                'This payout is eligible for the next automatic release run.',
                $scheduledAt,
                $withdrawal
            );
        }

        if ($scheduledAt && $reasons === ['next_business_day_not_reached']) {
            return $this->result(
                'scheduled',
                'Scheduled',
                'Your payout is queued for the next business-day release window.',
                $scheduledAt,
                $withdrawal
            );
        }

        $configurationReasons = [
            'transfers_disabled', 'automatic_release_disabled', 'policy_not_approved',
            'policy_version_invalid', 'settlement_owner_missing', 'approval_reference_missing',
            'release_timezone_invalid', 'business_days_invalid', 'release_hour_invalid',
            'automatic_amount_limits_invalid', 'daily_organizer_limit_invalid',
            'batch_limit_invalid', 'scan_limit_invalid', 'holiday_dates_invalid',
        ];
        if (array_intersect($reasons, $configurationReasons)) {
            return $this->result(
                'manual_review',
                'Manual finance review',
                'Automatic payout release is not active. The finance team will review this request.',
                null,
                $withdrawal
            );
        }

        return $this->result(
            'action_required',
            'Action or review required',
            $this->messageFor($reasons),
            null,
            $withdrawal
        );
    }

    private function result(
        string $status,
        string $label,
        string $message,
        ?string $scheduledAt = null,
        ?Withdraw $withdrawal = null
    ): array {
        return [
            'status' => $status,
            'label' => $label,
            'message' => $message,
            'scheduled_at' => $scheduledAt,
            'withdrawal_id' => $withdrawal?->id,
            'reference' => $withdrawal?->withdraw_id,
        ];
    }

    private function messageFor(array $reasons): string
    {
        $messages = [
            'organizer_inactive' => 'Activate your organizer account before payouts can be released.',
            'organizer_kyc_unverified' => 'Complete organizer identity verification before payouts can be released.',
            'organizer_too_new' => 'This organizer account is still within the payout safety period.',
            'kyc_cooling_period' => 'Identity verification is complete and still within its safety period.',
            'payout_account_invalid' => 'Add and verify a matching default Nigerian bank account.',
            'payout_account_cooling_period' => 'Your verified bank account is still within its safety period.',
            'manual_payout_history_required' => 'The first payout requires a manual finance review.',
            'unresolved_payout_dispute' => 'Resolve the open payout dispute before release.',
            'held_withdrawal_requires_review' => 'Another held payout requires finance review.',
            'unresolved_transfer_requires_review' => 'A previous transfer outcome requires finance review.',
            'amount_outside_automatic_limits' => 'This amount requires a manual finance review.',
            'daily_organizer_limit_exceeded' => 'The daily automatic payout limit has been reached.',
            'business_calendar_invalid' => 'The payout calendar requires finance review.',
        ];

        foreach ($reasons as $reason) {
            if (isset($messages[$reason])) return $messages[$reason];
        }

        return 'The finance team must review this payout before a release date can be confirmed.';
    }
}
