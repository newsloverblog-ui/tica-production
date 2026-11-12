<?php

namespace Tests\Unit;

use App\Services\QueueHealthService;
use App\Services\SharedStateHealthService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ReleaseReadinessCommandTest extends TestCase
{
    public function test_database_failure_diagnostics_do_not_leak_into_command_output(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->with(
                'Release readiness database check failed.',
                Mockery::on(function (array $context): bool {
                    $serialized = json_encode($context, JSON_THROW_ON_ERROR);

                    return $context === [
                        'exception_type' => RuntimeException::class,
                        'exception_code' => '0',
                    ]
                        && ! str_contains($serialized, 'super-secret')
                        && ! str_contains($serialized, 'private-db.internal');
                })
            );

        DB::shouldReceive('select')
            ->once()
            ->with('SELECT 1')
            ->andThrow(new RuntimeException(
                'password=super-secret host=private-db.internal database=tica SQL=SELECT 1'
            ));

        $exitCode = Artisan::call('ops:release-readiness', [
            '--configuration-only' => true,
            '--json' => true,
        ]);

        $output = Artisan::output();
        $payload = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
        $database = collect($payload['checks'])->firstWhere('id', 'database.connection');
        $checks = collect($payload['checks'])->keyBy('id');

        $this->assertSame(1, $exitCode);
        $this->assertSame('critical', $database['status']);
        $this->assertSame(
            'Database connection failed. Review protected application logs and verify the deployment-managed database configuration.',
            $database['message']
        );
        $this->assertStringNotContainsString('super-secret', $output);
        $this->assertStringNotContainsString('private-db.internal', $output);
        $this->assertStringNotContainsString('tica SQL', $output);

        $this->assertSame('critical', $checks['payments.production_contract']['status']);
        $this->assertSame(
            'The enabled gateway inventory cannot be verified while the database is unavailable.',
            $checks['payments.production_contract']['message']
        );

        foreach ([
            'database.transactional_schema',
            'database.transactional_columns',
            'database.transactional_indexes',
            'database.platform_earning_singleton',
            'tickets.guest_buyer_identity_integrity',
            'tickets.booking_unit_integrity',
            'security.gateway_credentials_encrypted',
            'security.basic_setting_secrets_encrypted',
            'security.payment_intent_sensitive_fields_encrypted',
            'security.finance_diagnostics_encrypted',
            'security.operational_diagnostics_encrypted',
            'storage.product_order_legacy_documents',
            'storage.event_booking_legacy_documents',
        ] as $id) {
            $this->assertSame('critical', $checks[$id]['status'], $id);
            $this->assertSame(
                'Cannot verify this live transactional control while the database is unavailable.',
                $checks[$id]['message'],
                $id
            );
        }

        $this->assertSame(
            count($payload['checks']),
            collect($payload['checks'])->pluck('id')->unique()->count(),
            'Readiness output must retain one result per stable check ID.'
        );
    }

    public function test_synchronous_queue_cannot_report_workers_as_healthy(): void
    {
        config()->set('queue.default', 'sync');

        $sharedState = Mockery::mock(SharedStateHealthService::class);
        $sharedState->shouldReceive('evaluate')->once()->andReturn([
            'healthy' => false,
            'cache' => ['healthy' => false, 'message' => 'The shared cache could not be reached.'],
            'session' => ['healthy' => false, 'message' => 'The shared session backend could not be reached.'],
        ]);
        $this->app->instance(SharedStateHealthService::class, $sharedState);

        $queue = Mockery::mock(QueueHealthService::class);
        $queue->shouldNotReceive('evaluate');
        $this->app->instance(QueueHealthService::class, $queue);

        Log::shouldReceive('error')->once();
        DB::shouldReceive('select')
            ->once()
            ->with('SELECT 1')
            ->andThrow(new RuntimeException('database unavailable'));

        $exitCode = Artisan::call('ops:release-readiness', ['--json' => true]);
        $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $worker = collect($payload['checks'])->firstWhere('id', 'queue.workers');
        $checks = collect($payload['checks'])->keyBy('id');

        $this->assertSame(1, $exitCode);
        $this->assertSame('critical', $worker['status']);
        $this->assertSame(
            'Worker health cannot be verified until QUEUE_CONNECTION uses a durable asynchronous backend.',
            $worker['message']
        );
        $this->assertSame('critical', $checks['cache.shared_store_reachable']['status']);
        $this->assertSame('critical', $checks['session.shared_store_reachable']['status']);
    }
}
