<?php

declare(strict_types=1);

use Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

Dotenv::createMutable(dirname(__DIR__), '.env.local')->safeLoad();

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
    throw $lastError ?? new RuntimeException('No production database URL is available.');
}

$tables = ['events', 'organizers'];
$result = [];

$pdo->beginTransaction();

try {
    foreach ($tables as $table) {
        $type = $pdo->query(
            "select data_type from information_schema.columns where table_schema = 'public' and table_name = "
            .$pdo->quote($table)." and column_name = 'status'"
        )->fetchColumn();

        if (! is_string($type)) {
            throw new RuntimeException("Missing {$table}.status column.");
        }

        $invalid = (int) $pdo->query(
            "select count(*) from public.{$table} where trim(status::text) not in ('0', '1')"
        )->fetchColumn();

        if ($invalid > 0) {
            throw new RuntimeException("{$table}.status contains {$invalid} non-numeric value(s).");
        }

        if ($type !== 'smallint') {
            $pdo->exec("alter table public.{$table} alter column status drop default");
            $pdo->exec(
                "alter table public.{$table} alter column status type smallint using trim(status::text)::smallint"
            );
            $pdo->exec("alter table public.{$table} alter column status set default 1");
        }

        $result[$table] = [
            'previous_type' => $type,
            'invalid_values' => $invalid,
        ];
    }

    $pdo->commit();
} catch (Throwable $error) {
    $pdo->rollBack();
    throw $error;
}

echo json_encode($result, JSON_THROW_ON_ERROR).PHP_EOL;
