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

        $dsn = sprintf(
            'pgsql:host=%s;port=%d;dbname=%s;sslmode=require',
            $parts['host'],
            $parts['port'] ?? 5432,
            ltrim($parts['path'] ?? '/postgres', '/'),
        );

        $pdo = new PDO(
            $dsn,
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

foreach (['production-postgres.sql', 'production-seed.sql'] as $filename) {
    $path = dirname(__DIR__).'/database/schema/'.$filename;
    $sql = file_get_contents($path);

    if ($sql === false) {
        throw new RuntimeException("Cannot read {$path}.");
    }

    $pdo->exec($sql);
}

$tableCount = (int) $pdo
    ->query("select count(*) from information_schema.tables where table_schema = 'public' and table_type = 'BASE TABLE'")
    ->fetchColumn();
$templateCount = (int) $pdo
    ->query('select count(*) from mail_templates')
    ->fetchColumn();

echo json_encode([
    'tables' => $tableCount,
    'mail_templates' => $templateCount,
], JSON_THROW_ON_ERROR).PHP_EOL;
