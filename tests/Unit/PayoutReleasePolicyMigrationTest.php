<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PayoutReleasePolicyMigrationTest extends TestCase
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
        Schema::create('payout_transfers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('requested_by_admin_id');
            $table->timestamps();
        });
    }

    public function test_migration_adds_auditable_automatic_release_metadata_and_rolls_back_safely(): void
    {
        $migration = require database_path(
            'migrations/2026_11_03_120000_add_release_policy_metadata_to_payout_transfers.php'
        );

        $migration->up();

        $this->assertTrue(Schema::hasColumns('payout_transfers', [
            'requested_by_admin_id', 'release_source', 'policy_version',
        ]));
        DB::table('payout_transfers')->insert([
            'requested_by_admin_id' => null,
            'release_source' => 'automatic',
            'policy_version' => '2026-11-v1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->assertNull(DB::table('payout_transfers')->value('requested_by_admin_id'));

        $migration->down();

        $this->assertFalse(Schema::hasColumn('payout_transfers', 'release_source'));
        $this->assertFalse(Schema::hasColumn('payout_transfers', 'policy_version'));
        $this->assertNull(DB::table('payout_transfers')->value('requested_by_admin_id'));
    }
}
