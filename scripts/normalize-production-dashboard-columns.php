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
    throw $lastError ?? new RuntimeException('No Vercel database URL is available.');
}

$columnType = static function (PDO $pdo, string $table, string $column): ?string {
    $statement = $pdo->prepare(
        'select data_type from information_schema.columns where table_schema = current_schema() and table_name = :table and column_name = :column'
    );
    $statement->execute(['table' => $table, 'column' => $column]);

    return $statement->fetchColumn() ?: null;
};

$numericColumns = [
    'customer_id' => 'bigint',
    'event_id' => 'bigint',
    'quantity' => 'integer',
];
$result = [];

$pdo->beginTransaction();

try {
    foreach ($numericColumns as $column => $targetType) {
        $type = $columnType($pdo, 'bookings', $column);
        if ($type === $targetType) {
            $result['bookings.'.$column] = 'already '.$targetType;
            continue;
        }

        $invalid = (int) $pdo->query(sprintf(
            'select count(*) from "bookings" where nullif(btrim("%1$s"::text), \'\') is not null and btrim("%1$s"::text) !~ \'^[0-9]+$\'',
            $column,
        ))->fetchColumn();
        if ($invalid !== 0) {
            throw new RuntimeException("bookings.{$column} contains {$invalid} non-numeric value(s)");
        }

        $pdo->exec(sprintf(
            'alter table "bookings" alter column "%1$s" type %2$s using nullif(btrim("%1$s"::text), \'\')::%2$s',
            $column,
            $targetType,
        ));
        $result['bookings.'.$column] = $type.' -> '.$targetType;
    }

    $withdrawAmountType = $columnType($pdo, 'withdraws', 'amount');
    if ($withdrawAmountType !== 'numeric') {
        $invalid = (int) $pdo->query(
            'select count(*) from "withdraws" where btrim("amount"::text) !~ \'^[0-9]+([.][0-9]+)?$\''
        )->fetchColumn();
        if ($invalid !== 0) {
            throw new RuntimeException("withdraws.amount contains {$invalid} non-numeric value(s)");
        }

        $pdo->exec(
            'alter table "withdraws" alter column "amount" type numeric(18,2) using btrim("amount"::text)::numeric(18,2)'
        );
        $result['withdraws.amount'] = $withdrawAmountType.' -> numeric';
    } else {
        $result['withdraws.amount'] = 'already numeric';
    }

    $defaultType = $columnType($pdo, 'organizer_payout_accounts', 'is_default');
    if ($defaultType !== 'boolean') {
        $invalid = (int) $pdo->query(
            'select count(*) from "organizer_payout_accounts" where "is_default"::text not in (\'0\', \'1\', \'true\', \'false\')'
        )->fetchColumn();
        if ($invalid !== 0) {
            throw new RuntimeException("organizer_payout_accounts.is_default contains {$invalid} invalid value(s)");
        }

        $pdo->exec(
            'alter table "organizer_payout_accounts" alter column "is_default" drop default'
        );
        $pdo->exec(
            'alter table "organizer_payout_accounts" alter column "is_default" type boolean using "is_default"::text in (\'1\', \'true\')'
        );
        $pdo->exec(
            'alter table "organizer_payout_accounts" alter column "is_default" set default true'
        );
        $result['organizer_payout_accounts.is_default'] = $defaultType.' -> boolean';
    } else {
        $result['organizer_payout_accounts.is_default'] = 'already boolean';
    }

    $promotionType = $columnType($pdo, 'tica_promotion_plans', 'is_active');
    if ($promotionType !== 'boolean') {
        $invalid = (int) $pdo->query(
            'select count(*) from "tica_promotion_plans" where "is_active"::text not in (\'0\', \'1\', \'true\', \'false\')'
        )->fetchColumn();
        if ($invalid !== 0) {
            throw new RuntimeException("tica_promotion_plans.is_active contains {$invalid} invalid value(s)");
        }

        $pdo->exec('alter table "tica_promotion_plans" alter column "is_active" drop default');
        $pdo->exec(
            'alter table "tica_promotion_plans" alter column "is_active" type boolean using "is_active"::text in (\'1\', \'true\')'
        );
        $pdo->exec('alter table "tica_promotion_plans" alter column "is_active" set default true');
        $result['tica_promotion_plans.is_active'] = $promotionType.' -> boolean';
    } else {
        $result['tica_promotion_plans.is_active'] = 'already boolean';
    }

    $pdo->commit();
} catch (Throwable $error) {
    $pdo->rollBack();
    throw $error;
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
