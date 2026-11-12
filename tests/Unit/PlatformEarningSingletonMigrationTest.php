<?php

namespace Tests\Unit;

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

class PlatformEarningSingletonMigrationTest extends TestCase
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
        $this->createEarningsTable();
    }

    public function test_migration_seeds_and_enforces_the_singleton_then_rolls_back(): void
    {
        $migration = require database_path('migrations/2026_11_04_000000_enforce_single_platform_earning_row.php');
        $migration->up();

        $this->assertTrue(Schema::hasColumn('earnings', 'singleton_key'));
        $this->assertDatabaseCount('earnings', 1);
        $this->assertDatabaseHas('earnings', ['singleton_key' => 1, 'total_revenue' => 0]);

        try {
            DB::table('earnings')->insert([
                'total_revenue' => 1,
                'total_earning' => 1,
                'singleton_key' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->fail('The database must reject a second platform earnings row.');
        } catch (QueryException) {
            $this->assertDatabaseCount('earnings', 1);
        }

        $migration->down();
        $this->assertFalse(Schema::hasColumn('earnings', 'singleton_key'));
    }

    public function test_migration_refuses_ambiguous_historical_rows(): void
    {
        DB::table('earnings')->insert([
            ['total_revenue' => 10, 'total_earning' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['total_revenue' => 20, 'total_earning' => 4, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('finance reconciliation');

        $migration = require database_path('migrations/2026_11_04_000000_enforce_single_platform_earning_row.php');
        $migration->up();
    }

    private function createEarningsTable(): void
    {
        Schema::create('earnings', function (Blueprint $table): void {
            $table->id();
            $table->decimal('total_revenue', 18, 2)->default(0);
            $table->decimal('total_earning', 18, 2)->default(0);
            $table->timestamps();
        });
    }
}
