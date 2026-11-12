<?php

namespace App\Services;

use App\Models\OrganizerPayoutAccount;
use App\Models\PayoutDispute;
use App\Models\PayoutTransfer;
use App\Models\Withdraw;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeZone;
use RuntimeException;
use Throwable;

class PayoutEligibilityService
{
    private const UNRESOLVED_TRANSFER_STATUSES = [
        'verification_required', 'needs_attention',
    ];

    public function evaluate(Withdraw $withdrawal, ?CarbonInterface $at = null): array
    {
        $policy = (array) config('payouts.eligibility_policy', []);
        $timezone = $this->timezone($policy);
        $now = CarbonImmutable::instance($at ?: now())->setTimezone($timezone);
        $withdrawal->loadMissing(['organizer', 'payoutAccount']);
        $organizer = $withdrawal->organizer;
        $account = $withdrawal->payoutAccount;
        $reasons = $this->configurationIssues($policy);

        if (!(bool) config('payouts.paystack_transfers_enabled', false)) {
            $reasons[] = 'transfers_disabled';
        }
        if (!(bool) config('payouts.automatic_release_enabled', false)) {
            $reasons[] = 'automatic_release_disabled';
        }
        if (!(bool) ($policy['approved'] ?? false)) {
            $reasons[] = 'policy_not_approved';
        }
        if ((int) $withdrawal->status !== Withdraw::STATUS_PENDING) {
            $reasons[] = 'withdrawal_not_pending';
        }
        if (!$withdrawal->created_at) {
            $reasons[] = 'withdrawal_created_at_missing';
        } else {
            try {
                if ($now->lt($this->scheduledReleaseAt($withdrawal->created_at))) {
                    $reasons[] = 'next_business_day_not_reached';
                }
            } catch (RuntimeException) {
                $reasons[] = 'business_calendar_invalid';
            }
        }

        if (!$organizer || (int) $organizer->status !== 1) {
            $reasons[] = 'organizer_inactive';
        } elseif ($organizer->kyc_status !== 'verified'
            || !$organizer->kyc_verified_at
            || trim((string) $organizer->verified_legal_name) === '') {
            $reasons[] = 'organizer_kyc_unverified';
        } else {
            if ($organizer->created_at && $organizer->created_at->gt(
                $now->subDays(max(0, (int) ($policy['minimum_organizer_age_days'] ?? 0)))
            )) {
                $reasons[] = 'organizer_too_new';
            }
            if ($organizer->kyc_verified_at->gt(
                $now->subDays(max(0, (int) ($policy['minimum_kyc_age_days'] ?? 0)))
            )) {
                $reasons[] = 'kyc_cooling_period';
            }
        }

        if (!$this->validAccount($account, $withdrawal)) {
            $reasons[] = 'payout_account_invalid';
        } elseif ($account->hold_until && $now->lt($account->hold_until)) {
            $reasons[] = 'payout_account_security_hold';
        } elseif (!$account->verified_at || $account->verified_at->gt(
            $now->subDays(max(0, (int) ($policy['minimum_account_age_days'] ?? 0)))
        )) {
            $reasons[] = 'payout_account_cooling_period';
        }

        $amount = round((float) $withdrawal->payable_amount, 2);
        $minimum = (float) ($policy['minimum_amount'] ?? 0);
        $maximum = (float) ($policy['maximum_amount'] ?? 0);
        if ($amount < $minimum || $amount > $maximum) {
            $reasons[] = 'amount_outside_automatic_limits';
        }

        if ($organizer) {
            $minimumSuccessful = max(0, (int) ($policy['minimum_successful_payouts'] ?? 0));
            if ($minimumSuccessful > 0 && $this->successfulPayoutCount((int) $organizer->id) < $minimumSuccessful) {
                $reasons[] = 'manual_payout_history_required';
            }
            if ($this->hasUnresolvedDispute((int) $organizer->id)) {
                $reasons[] = 'unresolved_payout_dispute';
            }
            if ($this->hasHeldWithdrawal((int) $organizer->id, (int) $withdrawal->id)) {
                $reasons[] = 'held_withdrawal_requires_review';
            }
            if ($this->hasUnresolvedTransfer((int) $organizer->id)) {
                $reasons[] = 'unresolved_transfer_requires_review';
            }
            $dailyLimit = (float) ($policy['daily_organizer_limit'] ?? 0);
            if ($dailyLimit <= 0 || $this->releasedToday((int) $organizer->id, $now) + $amount > $dailyLimit) {
                $reasons[] = 'daily_organizer_limit_exceeded';
            }
        }

        $reasons = array_values(array_unique($reasons));

        return [
            'eligible' => $reasons === [],
            'reasons' => $reasons,
            'policy_version' => trim((string) ($policy['version'] ?? '')),
            'scheduled_release_at' => $this->safeScheduledReleaseAt($withdrawal),
        ];
    }

    public function assertEligible(Withdraw $withdrawal, ?CarbonInterface $at = null): array
    {
        $decision = $this->evaluate($withdrawal, $at);
        if (!$decision['eligible']) {
            throw new RuntimeException(
                'Withdrawal is not eligible for automatic release: ' . implode(', ', $decision['reasons']) . '.'
            );
        }

        return $decision;
    }

    public function scheduledReleaseAt(CarbonInterface $createdAt): CarbonImmutable
    {
        $policy = (array) config('payouts.eligibility_policy', []);
        $timezone = $this->timezone($policy);
        $candidate = CarbonImmutable::instance($createdAt)
            ->setTimezone($timezone)
            ->addDay()
            ->startOfDay()
            ->addHours(max(0, min(23, (int) ($policy['release_hour'] ?? 10))));
        $businessDays = array_map('intval', (array) ($policy['business_days'] ?? [1, 2, 3, 4, 5]));
        $holidays = array_flip(array_map('strval', (array) ($policy['holiday_dates'] ?? [])));

        for ($guard = 0; $guard < 370; $guard++) {
            if (in_array($candidate->dayOfWeekIso, $businessDays, true)
                && !isset($holidays[$candidate->toDateString()])) {
                return $candidate;
            }
            $candidate = $candidate->addDay();
        }

        throw new RuntimeException('No payout business day is configured within the next year.');
    }

    public function configurationIssues(?array $policy = null): array
    {
        $policy ??= (array) config('payouts.eligibility_policy', []);
        $issues = [];
        $version = trim((string) ($policy['version'] ?? ''));
        if ($version === '' || mb_strlen($version) > 64) $issues[] = 'policy_version_invalid';
        if (($policy['settlement_owner'] ?? null) !== 'platform') {
            $issues[] = 'settlement_owner_missing';
        }
        if ((bool) ($policy['approved'] ?? false)
            && trim((string) ($policy['approval_reference'] ?? '')) === '') {
            $issues[] = 'approval_reference_missing';
        }
        try {
            new DateTimeZone((string) ($policy['timezone'] ?? ''));
        } catch (Throwable) {
            $issues[] = 'release_timezone_invalid';
        }
        $days = array_values(array_unique(array_map('intval', (array) ($policy['business_days'] ?? []))));
        if ($days === [] || array_diff($days, range(1, 7)) !== []) $issues[] = 'business_days_invalid';
        if ((int) ($policy['release_hour'] ?? -1) < 0 || (int) ($policy['release_hour'] ?? -1) > 23) {
            $issues[] = 'release_hour_invalid';
        }
        foreach (['minimum_organizer_age_days', 'minimum_kyc_age_days', 'minimum_account_age_days', 'minimum_successful_payouts'] as $key) {
            if ((int) ($policy[$key] ?? 0) < 1) $issues[] = $key . '_invalid';
        }
        $minimum = (float) ($policy['minimum_amount'] ?? 0);
        $maximum = (float) ($policy['maximum_amount'] ?? 0);
        $daily = (float) ($policy['daily_organizer_limit'] ?? 0);
        if ($minimum <= 0 || $maximum < $minimum) $issues[] = 'automatic_amount_limits_invalid';
        if ($daily < $maximum) $issues[] = 'daily_organizer_limit_invalid';
        if ((int) ($policy['batch_limit'] ?? 0) < 1 || (int) ($policy['batch_limit'] ?? 0) > 500) {
            $issues[] = 'batch_limit_invalid';
        }
        if ((int) ($policy['scan_limit'] ?? 0) < (int) ($policy['batch_limit'] ?? 0)
            || (int) ($policy['scan_limit'] ?? 0) > 10000) {
            $issues[] = 'scan_limit_invalid';
        }
        foreach ((array) ($policy['holiday_dates'] ?? []) as $holiday) {
            $holiday = (string) $holiday;
            try {
                $validHoliday = preg_match('/^\d{4}-\d{2}-\d{2}$/', $holiday)
                    && CarbonImmutable::createFromFormat('Y-m-d', $holiday, 'UTC')->toDateString() === $holiday;
            } catch (Throwable) {
                $validHoliday = false;
            }
            if (!$validHoliday) {
                $issues[] = 'holiday_dates_invalid';
                break;
            }
        }

        return array_values(array_unique($issues));
    }

    private function timezone(array $policy): string
    {
        $timezone = (string) ($policy['timezone'] ?? 'Africa/Lagos');
        try {
            new DateTimeZone($timezone);
            return $timezone;
        } catch (Throwable) {
            return 'UTC';
        }
    }

    private function safeScheduledReleaseAt(Withdraw $withdrawal): ?string
    {
        if (!$withdrawal->created_at) return null;
        try {
            return $this->scheduledReleaseAt($withdrawal->created_at)->toIso8601String();
        } catch (RuntimeException) {
            return null;
        }
    }

    private function validAccount(?OrganizerPayoutAccount $account, Withdraw $withdrawal): bool
    {
        return $account
            && (int) $account->organizer_id === (int) $withdrawal->organizer_id
            && $account->provider === 'paystack'
            && $account->status === 'verified'
            && $account->name_match_status === 'matched'
            && (bool) $account->is_default
            && strtoupper((string) $account->currency) === 'NGN';
    }

    private function successfulPayoutCount(int $organizerId): int
    {
        return PayoutTransfer::query()
            ->where('status', 'success')
            ->where('release_source', 'manual')
            ->whereHas('withdrawal', fn ($query) => $query->where('organizer_id', $organizerId))
            ->count();
    }

    private function hasUnresolvedDispute(int $organizerId): bool
    {
        return PayoutDispute::query()
            ->where('organizer_id', $organizerId)
            ->whereIn('status', [PayoutDispute::STATUS_OPEN, PayoutDispute::STATUS_UNDER_REVIEW])
            ->exists();
    }

    private function hasHeldWithdrawal(int $organizerId, int $exceptWithdrawalId): bool
    {
        return Withdraw::query()
            ->where('organizer_id', $organizerId)
            ->where('id', '!=', $exceptWithdrawalId)
            ->where('status', Withdraw::STATUS_HELD)
            ->exists();
    }

    private function hasUnresolvedTransfer(int $organizerId): bool
    {
        return PayoutTransfer::query()
            ->whereIn('status', self::UNRESOLVED_TRANSFER_STATUSES)
            ->whereHas('withdrawal', fn ($query) => $query->where('organizer_id', $organizerId))
            ->exists();
    }

    private function releasedToday(int $organizerId, CarbonImmutable $now): float
    {
        $start = $now->startOfDay()->setTimezone((string) config('app.timezone', 'UTC'));
        $end = $now->endOfDay()->setTimezone((string) config('app.timezone', 'UTC'));

        return (float) PayoutTransfer::query()
            ->whereBetween('created_at', [$start, $end])
            ->whereNotIn('status', ['failed', 'reversed'])
            ->whereHas('withdrawal', fn ($query) => $query->where('organizer_id', $organizerId))
            ->sum('amount');
    }
}
