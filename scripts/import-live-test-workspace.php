<?php

declare(strict_types=1);

use Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

Dotenv::createMutable(dirname(__DIR__), '.env.local')->safeLoad();

$rawPayload = getenv('TICA_WORKSPACE_JSON') ?: stream_get_contents(STDIN);
$payload = json_decode($rawPayload, true, flags: JSON_THROW_ON_ERROR);
$urls = array_values(array_filter([
    $_ENV['POSTGRES_URL_NON_POOLING'] ?? null,
    $_ENV['POSTGRES_URL'] ?? null,
]));

$pdo = null;
$lastError = null;

foreach ($urls as $url) {
    try {
        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['host'])) {
            continue;
        }

        $pdo = new PDO(
            sprintf(
                'pgsql:host=%s;port=%d;dbname=%s;sslmode=require',
                $parts['host'],
                $parts['port'] ?? 5432,
                ltrim($parts['path'] ?? '/postgres', '/'),
            ),
            urldecode($parts['user'] ?? ''),
            urldecode($parts['pass'] ?? ''),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => true,
            ],
        );

        break;
    } catch (Throwable $error) {
        $lastError = $error;
    }
}

if (! $pdo instanceof PDO) {
    throw $lastError ?? new RuntimeException('No Vercel database URL is available.');
}

$records = [
    'admins' => [$payload['admin']],
    'organizers' => [$payload['organizer']],
    'organizer_infos' => [$payload['organizer_info']],
    'customers' => [$payload['customer']],
    'events' => [$payload['event']],
    'event_contents' => [$payload['event_content']],
    'tickets' => [$payload['ticket']],
    'bookings' => [$payload['booking']],
    'booking_tickets' => $payload['booking_tickets'],
];

$quoteIdentifier = static fn (string $value): string => '"'.str_replace('"', '""', $value).'"';

$pdo->beginTransaction();

try {
    foreach ($records as $table => $rows) {
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $columns = array_keys($row);
            $quotedColumns = array_map($quoteIdentifier, $columns);
            $placeholders = array_map(static fn (string $column): string => ':'.$column, $columns);
            $updates = array_map(
                static fn (string $column): string => $quoteIdentifier($column).' = excluded.'.$quoteIdentifier($column),
                array_values(array_filter($columns, static fn (string $column): bool => $column !== 'id')),
            );

            $statement = $pdo->prepare(sprintf(
                'insert into %s (%s) values (%s) on conflict ("id") do update set %s',
                $quoteIdentifier($table),
                implode(', ', $quotedColumns),
                implode(', ', $placeholders),
                implode(', ', $updates),
            ));

            foreach ($row as $column => $value) {
                if (is_array($value) || is_object($value)) {
                    $value = json_encode($value, JSON_THROW_ON_ERROR);
                } elseif (is_bool($value)) {
                    $value = $value ? 1 : 0;
                }

                $statement->bindValue(':'.$column, $value);
            }

            $statement->execute();
        }

        $pdo->exec(sprintf(
            "select setval(pg_get_serial_sequence('%s', 'id'), greatest(coalesce((select max(id) from %s), 1), 1), true)",
            $table,
            $quoteIdentifier($table),
        ));
    }

    $pdo->commit();
} catch (Throwable $error) {
    $pdo->rollBack();
    throw $error;
}

echo json_encode([
    'admins' => count($records['admins']),
    'organizers' => count($records['organizers']),
    'customers' => count($records['customers']),
    'events' => count($records['events']),
    'bookings' => count($records['bookings']),
    'booking_tickets' => count($records['booking_tickets']),
], JSON_THROW_ON_ERROR).PHP_EOL;
