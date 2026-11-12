<?php

namespace App\Console\Commands;

use App\Models\WebhookReceipt;
use App\Services\IncidentAlertService;
use Illuminate\Console\Command;

class MonitorWebhooks extends Command
{
    protected $signature = 'ops:monitor-webhooks {--json : Emit machine-readable JSON}';
    protected $description = 'Detect stuck webhook processing and elevated gateway failure or rejection windows';

    public function handle(IncidentAlertService $alerts): int
    {
        $window = max(1, (int) config('operations.webhook_monitor_window_minutes', 15));
        $failureLimit = max(1, (int) config('operations.webhook_failure_limit', 5));
        $rejectionLimit = max(1, (int) config('operations.webhook_rejection_limit', 20));
        $stuckMinutes = max(1, (int) config('operations.webhook_stuck_minutes', 5));
        $recent = WebhookReceipt::query()->where('received_at', '>=', now()->subMinutes($window));
        $gateways = (clone $recent)->select('gateway')->distinct()->pluck('gateway');
        $results = [];
        $healthy = true;

        foreach ($gateways as $gateway) {
            $base = (clone $recent)->where('gateway', $gateway);
            $counts = (clone $base)->selectRaw('outcome, COUNT(*) as aggregate')
                ->groupBy('outcome')->pluck('aggregate', 'outcome')->map(fn ($value) => (int) $value)->all();
            $failed = (int) ($counts['failed'] ?? 0);
            $rejected = (int) ($counts['rejected'] ?? 0);
            $stuck = WebhookReceipt::query()->where('gateway', $gateway)->where('outcome', 'received')
                ->where('received_at', '<=', now()->subMinutes($stuckMinutes))->count();
            $gatewayHealthy = $failed < $failureLimit && $rejected < $rejectionLimit && $stuck === 0;
            $healthy = $healthy && $gatewayHealthy;
            $results[$gateway] = compact('gatewayHealthy', 'counts', 'stuck');
            if (!$gatewayHealthy) {
                $alerts->alert('webhook-health:' . $gateway, ucfirst($gateway) . ' webhook operations are unhealthy.', [
                    'window_minutes' => $window,
                    'counts' => $counts,
                    'stuck' => $stuck,
                    'failure_limit' => $failureLimit,
                    'rejection_limit' => $rejectionLimit,
                ]);
            }
        }

        $payload = ['healthy' => $healthy, 'window_minutes' => $window, 'gateways' => $results];
        $this->line($this->option('json')
            ? json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            : ($healthy ? 'Webhook operations are healthy.' : 'Webhook operations require attention.'));
        return $healthy ? self::SUCCESS : self::FAILURE;
    }
}
