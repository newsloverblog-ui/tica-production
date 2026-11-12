<?php

namespace Tests\Unit;

use App\Services\QueueHealthService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class QueueMonitorCommandTest extends TestCase
{
    public function test_unhealthy_queue_alert_is_rate_limited(): void
    {
        config()->set('cache.default', 'array');
        config()->set('operations.queue_monitor_alert_cooldown_seconds', 900);
        Cache::flush();
        Log::spy();

        $result = [
            'healthy' => false,
            'connection' => 'redis',
            'queues' => [
                'payments' => [
                    'healthy' => false,
                    'heartbeat_healthy' => true,
                    'age_seconds' => 10,
                    'backlog' => ['observable' => true, 'healthy' => false, 'size' => 501, 'limit' => 500],
                ],
            ],
            'failed_jobs' => ['observable' => true, 'healthy' => true, 'count' => 0, 'limit' => 10],
            'message' => 'Queue operations are unhealthy.',
        ];

        $service = Mockery::mock(QueueHealthService::class);
        $service->shouldReceive('evaluate')->twice()->andReturn($result);
        $this->app->instance(QueueHealthService::class, $service);

        $this->artisan('ops:monitor-queues')->assertExitCode(1);
        $this->artisan('ops:monitor-queues')->assertExitCode(1);

        Log::shouldHaveReceived('critical')->once();
    }
}
