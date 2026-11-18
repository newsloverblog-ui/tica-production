<?php

namespace App\Services;

use Illuminate\Database\ConnectionInterface;
use RuntimeException;

/**
 * Cross-database session advisory locks for payment fulfillment.
 *
 * PostgreSQL receives a stable BIGINT key while MySQL/MariaDB keeps the
 * human-readable lock name. SQLite is used only by isolated tests and relies
 * on the surrounding row locks and unique gateway-reference constraints.
 */
class DatabaseAdvisoryLock
{
    public function __construct(private ?ConnectionInterface $connection = null)
    {
    }

    public function acquire(string $name, int $timeoutSeconds = 0): bool
    {
        $connection = $this->connection();

        return match ($connection->getDriverName()) {
            'mysql', 'mariadb' => (int) ($connection
                ->selectOne('SELECT GET_LOCK(?, ?) AS acquired', [$name, max(0, $timeoutSeconds)])
                ->acquired ?? 0) === 1,
            'pgsql' => (int) ($connection
                ->selectOne('SELECT CASE WHEN pg_try_advisory_lock(?) THEN 1 ELSE 0 END AS acquired', [$this->postgresKey($name)])
                ->acquired ?? 0) === 1,
            'sqlite' => true,
            default => throw new RuntimeException('Payment advisory locks are not configured for this database driver.'),
        };
    }

    public function release(string $name): void
    {
        $connection = $this->connection();

        match ($connection->getDriverName()) {
            'mysql', 'mariadb' => $connection->selectOne('SELECT RELEASE_LOCK(?) AS released', [$name]),
            'pgsql' => $connection->selectOne('SELECT pg_advisory_unlock(?) AS released', [$this->postgresKey($name)]),
            'sqlite' => null,
            default => throw new RuntimeException('Payment advisory locks are not configured for this database driver.'),
        };
    }

    private function connection(): ConnectionInterface
    {
        return $this->connection ?: app('db')->connection();
    }

    private function postgresKey(string $name): int
    {
        // Fifteen hex characters stay inside PHP/PostgreSQL's signed BIGINT range.
        return (int) hexdec(substr(hash('sha256', $name), 0, 15));
    }
}
