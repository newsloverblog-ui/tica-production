<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CustomerEmailVerificationMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
    }

    public function test_migration_applies_and_rolls_back_delivery_ledger(): void
    {
        $migration = require database_path(
            'migrations/2026_11_03_130000_create_customer_email_verification_deliveries_table.php'
        );
        $migration->up();

        $this->assertTrue(Schema::hasColumns('customer_email_verification_deliveries', [
            'public_id', 'customer_id', 'email_hash', 'status', 'attempts', 'last_error',
            'processing_at', 'sent_at', 'verified_at', 'expires_at', 'alerted_at',
        ]));

        $migration->down();
        $this->assertFalse(Schema::hasTable('customer_email_verification_deliveries'));
    }
}
