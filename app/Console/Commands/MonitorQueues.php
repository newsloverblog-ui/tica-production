<?php

namespace App\Console\Commands;

use App\Services\QueueHealthService;
use App\Services\IncidentAlertService;
use Illuminate\Console\Command;

class MonitorQueues extends Command
{
    protected $signature = 'ops:monitor-queues {--json : Emit machine-readable JSON}';

    protected $description = 'Inspect queue workers, backlog and recent failures and alert on unhealthy state';

    public function handle(QueueHealthService $health, IncidentAlertService $alerts): int
    {
        $result = $health->evaluate();

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line($result['healthy'] ? 'Queue operations are healthy.' : $result['message']);
        }

        if (!$result['healthy']) {
            $cooldown = max(60, (int) config('operations.queue_monitor_alert_cooldown_seconds', 900));
            $alerts->alert('queue-health:' . $result['connection'], 'Queue operations are unhealthy.', [
                'connection' => $result['connection'],
                'queues' => $result['queues'],
                'failed_jobs' => $result['failed_jobs'] ?? null,
            ], 'critical', $cooldown);
        }

        return $result['healthy'] ? self::SUCCESS : self::FAILURE;
    }
}
