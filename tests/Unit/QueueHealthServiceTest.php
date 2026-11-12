<?php

namespace Tests\Unit;

use App\Jobs\QueueHeartbeat;
use App\Services\QueueHealthService;
use App\Services\SharedStateHealthService;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class QueueHealthServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('cache.default', 'array');
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        config()->set('operations.queue_heartbeat_queues', ['payments', 'default']);
        config()->set('operations.queue_heartbeat_grace_seconds', 180);
        config()->set('operations.queue_backlog_monitoring_enabled', false);
        Cache::flush();
        app()->detectEnvironment(fn () => 'testing');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_local_sync_queue_is_explicitly_skipped(): void
    {
        config()->set('queue.default', 'sync');

        $result = (new QueueHealthService())->evaluate();

        $this->assertTrue($result['healthy']);
        $this->assertSame('sync', $result['connection']);
    }

    public function test_production_sync_queue_is_unhealthy(): void
    {
        app()->detectEnvironment(fn () => 'production');
        config()->set('queue.default', 'sync');

        $this->assertFalse((new QueueHealthService())->evaluate()['healthy']);
    }

    public function test_production_sync_queue_can_be_explicitly_allowed(): void
    {
        app()->detectEnvironment(fn () => 'production');
        config()->set('queue.default', 'sync');
        config()->set('operations.sync_queue_allowed', true);

        $this->assertTrue((new QueueHealthService())->evaluate()['healthy']);
    }

    public function test_every_configured_queue_requires_a_fresh_heartbeat(): void
    {
        config()->set('queue.default', 'redis');
        Carbon::setTestNow('2026-11-01 12:00:00');
        $service = new QueueHealthService();

        $service->record('redis', 'payments');
        $this->assertFalse($service->evaluate()['healthy']);

        $service->record('redis', 'default');
        $this->assertTrue($service->evaluate()['healthy']);

        Carbon::setTestNow('2026-11-01 12:03:01');
        $this->assertFalse($service->evaluate()['healthy']);
    }

    public function test_encrypted_heartbeat_job_records_the_queue_it_runs_on(): void
    {
        config()->set('queue.default', 'redis');
        $job = new QueueHeartbeat('payments');

        $this->assertInstanceOf(ShouldBeEncrypted::class, $job);
        $this->assertSame('redis:payments', $job->uniqueId());
        $job->handle(new QueueHealthService());

        $this->assertTrue((new QueueHealthService())->evaluate()['queues']['payments']['healthy']);
    }

    public function test_excessive_backlog_makes_queue_health_fail_closed(): void
    {
        config()->set('queue.default', 'redis');
        config()->set('operations.queue_backlog_monitoring_enabled', true);
        config()->set('operations.queue_backlog_limit', 500);
        $service = new class extends QueueHealthService {
            protected function queueSize(string $connection, string $queue): int
            {
                return $queue === 'payments' ? 501 : 0;
            }

            protected function recentFailures(): array
            {
                return [
                    'observable' => true, 'healthy' => true, 'count' => 0,
                    'limit' => 10, 'window_minutes' => 60, 'message' => 'Ready.',
                ];
            }
        };
        $service->record('redis', 'payments');
        $service->record('redis', 'default');

        $result = $service->evaluate();

        $this->assertFalse($result['healthy']);
        $this->assertSame(501, $result['queues']['payments']['backlog']['size']);
        $this->assertFalse($result['queues']['payments']['backlog']['healthy']);
    }

    public function test_recent_failed_jobs_make_queue_health_fail_closed(): void
    {
        config()->set('queue.default', 'redis');
        config()->set('operations.queue_backlog_monitoring_enabled', true);
        $service = new class extends QueueHealthService {
            protected function queueSize(string $connection, string $queue): int
            {
                return 0;
            }

            protected function recentFailures(): array
            {
                return [
                    'observable' => true, 'healthy' => false, 'count' => 11,
                    'limit' => 10, 'window_minutes' => 60,
                    'message' => 'Recent failed jobs exceed the configured limit.',
                ];
            }
        };
        $service->record('redis', 'payments');
        $service->record('redis', 'default');

        $result = $service->evaluate();

        $this->assertFalse($result['healthy']);
        $this->assertSame(11, $result['failed_jobs']['count']);
    }

    public function test_readiness_endpoint_requires_database_and_queue_health(): void
    {
        config()->set('queue.default', 'sync');
        $sharedState = Mockery::mock(SharedStateHealthService::class);
        $sharedState->shouldReceive('evaluate')->once()->andReturn([
            'healthy' => true,
            'cache' => ['healthy' => true, 'message' => 'Ready.'],
            'session' => ['healthy' => true, 'message' => 'Ready.'],
        ]);
        $this->app->instance(SharedStateHealthService::class, $sharedState);
        $this->get('/health/ready')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertExactJson(['status' => 'ready']);
    }

    public function test_readiness_endpoint_requires_shared_cache_and_session_health(): void
    {
        config()->set('queue.default', 'sync');
        $sharedState = Mockery::mock(SharedStateHealthService::class);
        $sharedState->shouldReceive('evaluate')->once()->andReturn([
            'healthy' => false,
            'cache' => ['healthy' => false, 'message' => 'private cache detail'],
            'session' => ['healthy' => true, 'message' => 'Ready.'],
        ]);
        $this->app->instance(SharedStateHealthService::class, $sharedState);

        $this->get('/health/ready')
            ->assertStatus(503)
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertExactJson(['status' => 'unavailable']);
    }

    public function test_readiness_endpoint_returns_503_for_stale_production_workers(): void
    {
        app()->detectEnvironment(fn () => 'production');
        config()->set('queue.default', 'redis');

        $this->get('/health/ready')
            ->assertStatus(503)
            ->assertExactJson(['status' => 'unavailable']);
    }

    public function test_readiness_endpoint_hides_database_and_queue_diagnostics(): void
    {
        config()->set('queue.default', 'sync');

        Log::spy();
        DB::shouldReceive('select')
            ->once()
            ->with('select 1')
            ->andThrow(new RuntimeException(
                'password=super-secret host=private-db.internal queue=payments'
            ));

        $response = $this->get('/health/ready')
            ->assertStatus(503)
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertExactJson(['status' => 'unavailable']);

        $body = $response->getContent();
        $this->assertStringNotContainsString('super-secret', $body);
        $this->assertStringNotContainsString('private-db.internal', $body);
        $this->assertStringNotContainsString('payments', $body);
        Log::shouldHaveReceived('error')
            ->once()
            ->with(
                'Readiness database check failed.',
                Mockery::on(fn (array $context): bool => $context === [
                    'exception_type' => RuntimeException::class,
                    'exception_code' => '0',
                ])
            );
    }
}
