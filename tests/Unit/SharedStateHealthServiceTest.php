<?php

namespace Tests\Unit;

use App\Services\SharedStateHealthService;
use Illuminate\Support\Facades\DB;
use SessionHandlerInterface;
use Tests\TestCase;

class SharedStateHealthServiceTest extends TestCase
{
    public function test_non_shared_drivers_fail_closed_without_a_probe(): void
    {
        config()->set('cache.default', 'file');
        config()->set('session.driver', 'array');

        $result = (new SharedStateHealthService())->evaluate();

        $this->assertFalse($result['healthy']);
        $this->assertFalse($result['cache']['healthy']);
        $this->assertFalse($result['session']['healthy']);
        $this->assertSame(
            'The configured cache is not a supported shared store.',
            $result['cache']['message']
        );
        $this->assertSame(
            'The configured session backend is not a supported shared store.',
            $result['session']['message']
        );
    }

    public function test_shared_cache_and_session_must_round_trip_state(): void
    {
        config()->set('cache.default', 'database');
        config()->set('cache.stores.database', ['driver' => 'array']);
        config()->set('session.driver', 'database');

        $handler = new class implements SessionHandlerInterface
        {
            public array $values = [];

            public function open(string $path, string $name): bool { return true; }
            public function close(): bool { return true; }
            public function read(string $id): string|false { return $this->values[$id] ?? ''; }
            public function write(string $id, string $data): bool { $this->values[$id] = $data; return true; }
            public function destroy(string $id): bool { unset($this->values[$id]); return true; }
            public function gc(int $max_lifetime): int|false { return 0; }
        };
        $service = new class($handler) extends SharedStateHealthService
        {
            public function __construct(private SessionHandlerInterface $handler) {}
            protected function makeSessionHandler(string $driver): SessionHandlerInterface
            {
                return $this->handler;
            }
        };

        $result = $service->evaluate();

        $this->assertTrue($result['healthy']);
        $this->assertSame(['healthy' => true, 'message' => 'Ready.'], $result['cache']);
        $this->assertSame(['healthy' => true, 'message' => 'Ready.'], $result['session']);
        $this->assertSame([], $handler->values, 'The session probe must clean up its temporary record.');
    }

    public function test_database_session_probe_is_isolated_from_the_request_session_handler(): void
    {
        config()->set('cache.default', 'database');
        config()->set('cache.stores.database', ['driver' => 'array']);
        config()->set('database.connections.session_probe', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        config()->set('session.driver', 'database');
        config()->set('session.connection', 'session_probe');
        config()->set('session.table', 'sessions');

        DB::connection('session_probe')->statement(<<<'SQL'
            CREATE TABLE sessions (
                id VARCHAR(255) PRIMARY KEY,
                user_id INTEGER NULL,
                ip_address VARCHAR(45) NULL,
                user_agent TEXT NULL,
                payload TEXT NOT NULL,
                last_activity INTEGER NOT NULL
            )
        SQL);

        $this->app->instance('session.store', new class
        {
            public function getHandler(): never
            {
                throw new \LogicException('The request-scoped handler must not be used.');
            }
        });

        $result = (new SharedStateHealthService())->evaluate();

        $this->assertTrue($result['session']['healthy']);
        $this->assertSame(0, DB::connection('session_probe')->table('sessions')->count());
    }
}
