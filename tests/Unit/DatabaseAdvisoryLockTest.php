<?php

namespace Tests\Unit;

use App\Services\DatabaseAdvisoryLock;
use Illuminate\Database\ConnectionInterface;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class DatabaseAdvisoryLockTest extends TestCase
{
    public function test_mysql_uses_named_provider_lock_with_a_bounded_timeout(): void
    {
        $connection = Mockery::mock(ConnectionInterface::class);
        $connection->shouldReceive('getDriverName')->twice()->andReturn('mysql');
        $connection->shouldReceive('selectOne')->once()
            ->with('SELECT GET_LOCK(?, ?) AS acquired', ['paystack_ref_example', 0])
            ->andReturn((object) ['acquired' => 1]);
        $connection->shouldReceive('selectOne')->once()
            ->with('SELECT RELEASE_LOCK(?) AS released', ['paystack_ref_example'])
            ->andReturn((object) ['released' => 1]);

        $lock = new DatabaseAdvisoryLock($connection);
        $this->assertTrue($lock->acquire('paystack_ref_example'));
        $lock->release('paystack_ref_example');
    }

    public function test_postgres_uses_the_same_stable_bigint_for_lock_and_unlock(): void
    {
        $connection = Mockery::mock(ConnectionInterface::class);
        $connection->shouldReceive('getDriverName')->twice()->andReturn('pgsql');
        $key = null;
        $connection->shouldReceive('selectOne')->once()
            ->withArgs(function (string $sql, array $bindings) use (&$key): bool {
                $key = $bindings[0] ?? null;
                return $sql === 'SELECT CASE WHEN pg_try_advisory_lock(?) THEN 1 ELSE 0 END AS acquired'
                    && is_int($key)
                    && $key >= 0;
            })->andReturn((object) ['acquired' => 1]);
        $connection->shouldReceive('selectOne')->once()
            ->withArgs(function (string $sql, array $bindings) use (&$key): bool {
                return $sql === 'SELECT pg_advisory_unlock(?) AS released'
                    && ($bindings[0] ?? null) === $key;
            })->andReturn((object) ['released' => true]);

        $lock = new DatabaseAdvisoryLock($connection);
        $this->assertTrue($lock->acquire('paystack_ref_example'));
        $lock->release('paystack_ref_example');
    }

    public function test_unknown_database_driver_fails_closed(): void
    {
        $connection = Mockery::mock(ConnectionInterface::class);
        $connection->shouldReceive('getDriverName')->once()->andReturn('unknown');

        $this->expectException(RuntimeException::class);
        (new DatabaseAdvisoryLock($connection))->acquire('payment_ref_example');
    }
}
