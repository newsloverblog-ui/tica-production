<?php

namespace App\Console\Commands;

use App\Jobs\DispatchPushNotificationCampaign;
use App\Jobs\DispatchSubscriberCampaign;
use App\Models\PushNotificationCampaign;
use App\Models\SubscriberCampaign;
use App\Services\IncidentAlertService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Throwable;

class DispatchPendingCampaigns extends Command
{
    protected $signature = 'campaigns:dispatch-pending {--limit=100} {--stale-minutes=30}';

    protected $description = 'Recover pending subscriber and browser-push campaign fan-out jobs';

    public function handle(IncidentAlertService $alerts): int
    {
        $limit = max(1, min(500, (int) $this->option('limit')));
        $staleMinutes = max(5, (int) $this->option('stale-minutes'));
        $attempted = 0;
        $failed = 0;
        $alerted = 0;

        $definitions = [
            [SubscriberCampaign::class, DispatchSubscriberCampaign::class, 'subscriber'],
            [PushNotificationCampaign::class, DispatchPushNotificationCampaign::class, 'push'],
        ];

        foreach ($definitions as [$model, $job, $kind]) {
            $table = (new $model)->getTable();
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'dispatch_attempts')) {
                continue;
            }

            $campaigns = $model::query()
                ->where('status', 'pending')
                ->orderBy('id')
                ->limit($limit)
                ->get();

            foreach ($campaigns as $campaign) {
                $attempted++;
                $error = null;
                $campaign->forceFill([
                    'dispatch_attempts' => (int) $campaign->dispatch_attempts + 1,
                    'dispatch_last_attempt_at' => now(),
                    'dispatch_last_error' => null,
                ])->save();

                try {
                    $job::dispatch($campaign->id);
                } catch (Throwable $exception) {
                    $failed++;
                    $error = mb_substr($exception->getMessage(), 0, 2000);
                    $campaign->forceFill(['dispatch_last_error' => $error])->save();
                }

                if ($campaign->created_at->lt(now()->subMinutes($staleMinutes))
                    && $campaign->dispatch_alerted_at === null) {
                    $campaign->forceFill(['dispatch_alerted_at' => now()])->save();
                    $alerts->alert('campaign-dispatch-pending:'.$kind.':'.$campaign->id,
                        'Campaign fan-out did not start within the expected window.', [
                            'campaign_type' => $kind,
                            'campaign_id' => $campaign->id,
                            'dispatch_attempts' => $campaign->dispatch_attempts,
                            'queue_error' => $error !== null,
                        ], 'critical');
                    $alerted++;
                }
            }
        }

        $this->line(sprintf('Campaign dispatch attempted: %d | failed: %d | alerted: %d', $attempted, $failed, $alerted));

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
