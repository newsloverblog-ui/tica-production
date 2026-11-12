<?php

namespace App\Services;

use App\Models\Withdraw;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

class AutomaticPayoutReleaseService
{
    public function __construct(
        private readonly PayoutEligibilityService $eligibility,
        private readonly PayoutTransferService $transfers
    ) {
    }

    public function run(bool $apply = false, ?int $limit = null): array
    {
        $configuredLimit = (int) config('payouts.eligibility_policy.batch_limit', 100);
        $limit = $limit === null ? $configuredLimit : $limit;
        if ($limit < 1 || $limit > min(500, max(1, $configuredLimit))) {
            throw new RuntimeException('The payout release limit must be within the approved batch limit.');
        }
        if ($apply && !(bool) config('payouts.automatic_release_enabled', false)) {
            throw new RuntimeException('Automatic payout release is disabled.');
        }

        return Cache::lock('payout-automatic-release', 300)->block(5, function () use ($apply, $limit): array {
            $summary = [
                'mode' => $apply ? 'apply' : 'dry-run',
                'checked' => 0,
                'eligible' => 0,
                'ineligible' => 0,
                'queued' => 0,
                'errors' => 0,
                'reason_counts' => [],
                'withdrawals' => [],
            ];

            $scanLimit = max($limit, min(10000, (int) config('payouts.eligibility_policy.scan_limit', 5000)));
            $withdrawals = Withdraw::query()
                ->with(['organizer', 'payoutAccount'])
                ->where('status', Withdraw::STATUS_PENDING)
                ->oldest('created_at')
                ->oldest('id')
                ->limit($scanLimit)
                ->get();

            foreach ($withdrawals as $withdrawal) {
                if ($summary['eligible'] >= $limit) break;
                $summary['checked']++;
                $decision = $this->eligibility->evaluate($withdrawal);
                $item = [
                    'withdrawal_id' => $withdrawal->id,
                    'public_id' => $withdrawal->withdraw_id,
                    'eligible' => $decision['eligible'],
                    'reasons' => $decision['reasons'],
                    'scheduled_release_at' => $decision['scheduled_release_at'],
                ];
                if (!$decision['eligible']) {
                    $summary['ineligible']++;
                    foreach ($decision['reasons'] as $reason) {
                        $summary['reason_counts'][$reason] = ($summary['reason_counts'][$reason] ?? 0) + 1;
                    }
                    $summary['withdrawals'][] = $item;
                    continue;
                }

                $summary['eligible']++;
                if ($apply) {
                    try {
                        $transfer = $this->transfers->queueAutomatically((int) $withdrawal->id, $this->eligibility);
                        $item['transfer_reference'] = $transfer->reference;
                        $summary['queued']++;
                    } catch (Throwable $exception) {
                        report($exception);
                        $item['error'] = $exception->getMessage();
                        $summary['errors']++;
                    }
                }
                $summary['withdrawals'][] = $item;
            }

            ksort($summary['reason_counts']);
            return $summary;
        });
    }
}
