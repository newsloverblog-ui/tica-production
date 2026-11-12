<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Throwable;

class QueueHealthService
{
    public function queues(): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn ($queue) => trim((string) $queue),
            (array) config('operations.queue_heartbeat_queues', ['payments', 'default'])
        ))));
    }

    public function graceSeconds(): int
    {
        return max(60, (int) config('operations.queue_heartbeat_grace_seconds', 180));
    }

    public function heartbeatKey(string $connection, string $queue): string
    {
        return 'ops:queue-heartbeat:' . $connection . ':' . $queue;
    }

    public function record(string $connection, string $queue): void
    {
        Cache::put($this->heartbeatKey($connection, $queue), now()->timestamp, now()->addSeconds($this->graceSeconds() * 2));
    }

    public function evaluate(): array
    {
        $connection = (string) config('queue.default', 'sync');
        if (in_array($connection, ['sync', 'null'], true)) {
            $allowed = (bool) config('operations.sync_queue_allowed', false);

            return [
                'healthy' => !app()->environment('production') || $allowed,
                'connection' => $connection,
                'queues' => [],
                'message' => app()->environment('production') && !$allowed
                    ? 'Production queue connection is not asynchronous.'
                    : 'Synchronous queue execution is explicitly allowed for this environment.',
            ];
        }

        $now = now()->timestamp;
        $grace = $this->graceSeconds();
        $monitorBacklog = (bool) config('operations.queue_backlog_monitoring_enabled', true);
        $backlogLimit = max(1, (int) config('operations.queue_backlog_limit', 500));
        $results = [];
        foreach ($this->queues() as $queue) {
            $timestamp = Cache::get($this->heartbeatKey($connection, $queue));
            $age = is_numeric($timestamp) ? max(0, $now - (int) $timestamp) : null;
            $backlog = $monitorBacklog ? $this->backlog($connection, $queue, $backlogLimit) : [
                'observable' => false,
                'healthy' => true,
                'size' => null,
                'limit' => $backlogLimit,
                'message' => 'Backlog monitoring is disabled.',
            ];
            $results[$queue] = [
                'healthy' => $age !== null && $age <= $grace && $backlog['healthy'],
                'heartbeat_healthy' => $age !== null && $age <= $grace,
                'age_seconds' => $age,
                'backlog' => $backlog,
            ];
        }

        $failed = $monitorBacklog ? $this->recentFailures() : [
            'observable' => false,
            'healthy' => true,
            'count' => null,
            'limit' => max(0, (int) config('operations.queue_failed_limit', 10)),
            'window_minutes' => max(1, (int) config('operations.queue_failed_window_minutes', 60)),
            'message' => 'Failed-job monitoring is disabled.',
        ];

        $healthy = collect($results)->every(fn ($result) => $result['healthy']) && $failed['healthy'];

        return [
            'healthy' => $healthy,
            'connection' => $connection,
            'queues' => $results,
            'failed_jobs' => $failed,
            'message' => $healthy
                ? 'Queue workers, backlog and recent failures are within configured limits.'
                : 'Queue workers must be fresh and queue backlog/failures must remain within configured limits.',
        ];
    }

    protected function queueSize(string $connection, string $queue): int
    {
        return (int) Queue::connection($connection)->size($queue);
    }

    protected function backlog(string $connection, string $queue, int $limit): array
    {
        try {
            $ttl = max(1, (int) config('operations.queue_health_observation_ttl_seconds', 10));
            $size = Cache::remember(
                'ops:queue-backlog:' . $connection . ':' . $queue,
                now()->addSeconds($ttl),
                fn () => $this->queueSize($connection, $queue)
            );

            return [
                'observable' => true,
                'healthy' => $size <= $limit,
                'size' => $size,
                'limit' => $limit,
                'message' => $size <= $limit
                    ? 'Backlog is within the configured limit.'
                    : 'Backlog exceeds the configured limit.',
            ];
        } catch (Throwable $exception) {
            report($exception);

            return [
                'observable' => false,
                'healthy' => false,
                'size' => null,
                'limit' => $limit,
                'message' => 'Queue backlog could not be inspected.',
            ];
        }
    }

    protected function recentFailures(): array
    {
        $limit = max(0, (int) config('operations.queue_failed_limit', 10));
        $window = max(1, (int) config('operations.queue_failed_window_minutes', 60));
        $driver = (string) config('queue.failed.driver', 'database-uuids');

        if (!in_array($driver, ['database', 'database-uuids'], true)) {
            return [
                'observable' => false,
                'healthy' => false,
                'count' => null,
                'limit' => $limit,
                'window_minutes' => $window,
                'message' => 'Failed-job storage is not database-backed and cannot be inspected.',
            ];
        }

        try {
            $connection = (string) config('queue.failed.database', config('database.default'));
            $table = (string) config('queue.failed.table', 'failed_jobs');
            $ttl = max(1, (int) config('operations.queue_health_observation_ttl_seconds', 10));
            $count = Cache::remember(
                'ops:queue-failures:' . $connection . ':' . $table . ':' . $window,
                now()->addSeconds($ttl),
                fn () => DB::connection($connection)
                    ->table($table)
                    ->where('failed_at', '>=', now()->subMinutes($window))
                    ->count()
            );

            return [
                'observable' => true,
                'healthy' => $count <= $limit,
                'count' => $count,
                'limit' => $limit,
                'window_minutes' => $window,
                'message' => $count <= $limit
                    ? 'Recent failures are within the configured limit.'
                    : 'Recent failed jobs exceed the configured limit.',
            ];
        } catch (Throwable $exception) {
            report($exception);

            return [
                'observable' => false,
                'healthy' => false,
                'count' => null,
                'limit' => $limit,
                'window_minutes' => $window,
                'message' => 'Recent failed jobs could not be inspected.',
            ];
        }
    }
}
