<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CustomerPrivacyRequestMigrationTest extends TestCase
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

    public function test_migration_applies_and_rolls_back_privacy_request_ledger(): void
    {
        $migration = require database_path(
            'migrations/2026_11_04_010000_create_customer_privacy_requests_table.php'
        );
        $migration->up();

        $this->assertTrue(Schema::hasColumns('customer_privacy_requests', [
            'public_id', 'customer_id', 'request_type', 'status', 'customer_email', 'customer_name',
            'reason', 'policy_version', 'reviewed_by_admin_id', 'resolution', 'evidence_reference',
            'submitted_at', 'reviewed_at', 'completed_at',
        ]));

        $migration->down();
        $this->assertFalse(Schema::hasTable('customer_privacy_requests'));
    }
}
