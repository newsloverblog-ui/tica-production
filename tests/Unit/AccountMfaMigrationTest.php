<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AccountMfaMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        foreach (['admins', 'organizers', 'customers'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table): void {
                $table->id();
                $table->timestamp('two_factor_enabled_at')->nullable();
            });
        }
    }

    public function test_totp_columns_are_added_and_rolled_back_for_every_account_actor(): void
    {
        $migration = require database_path('migrations/2026_11_14_160000_add_totp_to_account_actors.php');
        $migration->up();
        $migration->up();

        foreach (['admins', 'organizers', 'customers'] as $tableName) {
            $this->assertTrue(Schema::hasColumns($tableName, ['two_factor_secret', 'two_factor_recovery_codes']));
        }

        $migration->down();
        foreach (['admins', 'organizers', 'customers'] as $tableName) {
            $this->assertFalse(Schema::hasColumn($tableName, 'two_factor_secret'));
            $this->assertFalse(Schema::hasColumn($tableName, 'two_factor_recovery_codes'));
        }
    }
}
