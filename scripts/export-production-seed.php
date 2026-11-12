<?php

declare(strict_types=1);

use Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

$database = $_ENV['DB_DATABASE'] ?? '';
$username = $_ENV['DB_USERNAME'] ?? '';
$password = $_ENV['DB_PASSWORD'] ?? '';
$host = $_ENV['DB_HOST'] ?? '127.0.0.1';
$port = $_ENV['DB_PORT'] ?? '3306';

if ($database === '' || $username === '') {
    throw new RuntimeException('The local MySQL connection is not configured.');
}

$pdo = new PDO(
    sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $database),
    $username,
    $password,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ],
);

$tables = [
    'languages',
    'basic_settings',
    'about_us_sections',
    'action_sections',
    'advertisements',
    'blog_categories',
    'blogs',
    'blog_informations',
    'contact_pages',
    'cookie_alerts',
    'count_informations',
    'event_categories',
    'event_feature_sections',
    'event_features',
    'faqs',
    'features',
    'footer_contents',
    'fun_fact_sections',
    'hero_sections',
    'how_works',
    'how_work_items',
    'mail_templates',
    'menu_builders',
    'offline_gateways',
    'online_gateways',
    'pages',
    'page_contents',
    'page_headings',
    'partner_sections',
    'partners',
    'popups',
    'quick_links',
    'sections',
    'section_titles',
    'seos',
    'shipping_charges',
    'social_medias',
    'support_ticket_statuses',
    'testimonial_sections',
    'testimonials',
    'timezones',
    'withdraw_payment_methods',
    'withdraw_method_inputs',
    'withdraw_method_options',
];

$quoteIdentifier = static fn (string $value): string => '"'.str_replace('"', '""', $value).'"';
$quoteLiteral = static fn (string $value): string => "'".str_replace("'", "''", $value)."'";

$columnTypes = [];
$metadata = $pdo->prepare(<<<'SQL'
    SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table
    ORDER BY ORDINAL_POSITION
SQL);

$sanitizeGatewayInformation = static function (?string $value): string {
    if ($value === null || str_starts_with($value, 'enc:v1:')) {
        return '{}';
    }

    $decoded = json_decode($value, true);
    if (!is_array($decoded)) {
        return '{}';
    }

    $clear = static function (mixed $item) use (&$clear): mixed {
        if (is_array($item)) {
            return array_map($clear, $item);
        }

        return is_bool($item) ? false : '';
    };

    return json_encode($clear($decoded), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
};

$sanitize = static function (string $table, string $column, mixed $value, string $nullable) use ($sanitizeGatewayInformation): mixed {
    if ($table === 'basic_settings') {
        $overrides = [
            'website_title' => 'TICA',
            'email_address' => 'hello@tica.example',
            'contact_number' => null,
            'address' => 'Nigeria',
            'latitude' => null,
            'longitude' => null,
            'primary_color' => '2866D3',
            'smtp_status' => 0,
            'from_mail' => null,
            'from_name' => 'TICA',
            'to_mail' => null,
            'google_recaptcha_status' => 0,
            'facebook_login_status' => 0,
            'google_login_status' => 0,
            'whatsapp_status' => 0,
            'whatsapp_number' => null,
            'whatsapp_popup_status' => 0,
            'maintenance_status' => 0,
            'shop_status' => 0,
        ];

        if (array_key_exists($column, $overrides)) {
            return $overrides[$column];
        }

        if (preg_match('/(?:password|secret|token|client_id|app_id|site_key|publisher_id|smtp_host|smtp_username|bypass)/i', $column)) {
            return $nullable === 'YES' ? null : '';
        }
    }

    if ($table === 'online_gateways') {
        return match ($column) {
            'status' => 0,
            'information' => $sanitizeGatewayInformation(is_string($value) ? $value : null),
            default => $value,
        };
    }

    if ($table === 'offline_gateways') {
        return match ($column) {
            'status', 'has_attachment' => 0,
            'instructions', 'short_description' => null,
            default => $value,
        };
    }

    if ($table === 'social_medias' && $column === 'url') {
        return '#';
    }

    if ($table === 'advertisements' && $column === 'url') {
        return '#';
    }

    if ($table === 'popups') {
        return match ($column) {
            'status' => 0,
            'button_url' => '#',
            default => $value,
        };
    }

    if ($table === 'quick_links' && $column === 'url') {
        return '#';
    }

    if (in_array($table, ['action_sections', 'hero_sections'], true) && str_ends_with($column, '_button_url')) {
        return '#';
    }

    if ($table === 'contact_pages' && in_array($column, ['contact_addresses', 'contact_numbers', 'contact_mails'], true)) {
        return null;
    }

    if ($table === 'withdraw_payment_methods' && $column === 'status') {
        return 0;
    }

    return $value;
};

$serialize = static function (mixed $value, string $type) use ($quoteLiteral): string {
    if ($value === null) {
        return 'NULL';
    }

    if (in_array($type, ['bigint', 'int', 'integer', 'mediumint', 'smallint', 'tinyint', 'decimal', 'numeric', 'float', 'double', 'real', 'year'], true)) {
        return is_numeric($value) ? (string) $value : 'NULL';
    }

    if (in_array($type, ['binary', 'varbinary', 'tinyblob', 'blob', 'mediumblob', 'longblob'], true)) {
        return "decode('".bin2hex((string) $value)."', 'hex')";
    }

    if ($type === 'json') {
        return $quoteLiteral((string) $value).'::jsonb';
    }

    return $quoteLiteral((string) $value);
};

$lines = [
    '-- Sanitized public content for a fresh TICA production database.',
    '-- No users, orders, tickets, credentials, payment secrets, or operational history.',
    'BEGIN;',
    '',
];

foreach ($tables as $table) {
    $metadata->execute(['schema' => $database, 'table' => $table]);
    $columns = $metadata->fetchAll();
    if ($columns === []) {
        continue;
    }

    $names = array_column($columns, 'COLUMN_NAME');
    foreach ($columns as $column) {
        $columnTypes[$table][$column['COLUMN_NAME']] = $column;
    }

    $statement = $pdo->query('SELECT * FROM `'.str_replace('`', '``', $table).'` ORDER BY 1');
    $count = 0;
    while ($row = $statement->fetch()) {
        $values = [];
        foreach ($names as $name) {
            $meta = $columnTypes[$table][$name];
            $value = $sanitize($table, $name, $row[$name], $meta['IS_NULLABLE']);
            $values[] = $serialize($value, strtolower((string) $meta['DATA_TYPE']));
        }

        $lines[] = sprintf(
            'INSERT INTO %s (%s) VALUES (%s) ON CONFLICT DO NOTHING;',
            $quoteIdentifier($table),
            implode(', ', array_map($quoteIdentifier, $names)),
            implode(', ', $values),
        );
        $count++;
    }

    if ($count > 0 && in_array('id', $names, true)) {
        $lines[] = sprintf(
            'SELECT setval(pg_get_serial_sequence(%s, %s), COALESCE(MAX("id"), 1), true) FROM %s;',
            $quoteLiteral('public.'.$table),
            $quoteLiteral('id'),
            $quoteIdentifier($table),
        );
    }
    $lines[] = '';
}

$lines[] = 'COMMIT;';

$output = dirname(__DIR__).'/database/schema/production-seed.sql';
file_put_contents($output, implode("\n", $lines)."\n");
fwrite(STDOUT, "Wrote sanitized production content to {$output}\n");
