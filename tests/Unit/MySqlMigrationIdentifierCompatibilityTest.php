<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MySqlMigrationIdentifierCompatibilityTest extends TestCase
{
    #[DataProvider('longCompositeIndexProvider')]
    public function test_long_composite_indexes_use_explicit_mysql_safe_names(
        string $migration,
        string $expectedName,
    ): void {
        $source = file_get_contents(dirname(__DIR__, 2).'/database/migrations/'.$migration);

        $this->assertIsString($source);
        $this->assertStringContainsString("'{$expectedName}'", $source);
        $this->assertLessThanOrEqual(
            64,
            strlen($expectedName),
            "MySQL identifiers may not exceed 64 characters: {$expectedName}",
        );
    }

    public static function longCompositeIndexProvider(): array
    {
        return [
            'gateway settlement entry uniqueness' => [
                '2026_11_01_050000_create_gateway_settlements_tables.php',
                'settlement_entries_settlement_reference_unique',
            ],
            'event cancellation notification lookup' => [
                '2026_11_02_010000_create_event_cancellation_workflow.php',
                'event_cancel_notice_request_index',
            ],
            'customer email delivery lookup' => [
                '2026_11_03_130000_create_customer_email_verification_deliveries_table.php',
                'customer_email_delivery_customer_created_index',
            ],
        ];
    }
}
