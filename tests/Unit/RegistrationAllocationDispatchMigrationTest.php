<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RegistrationAllocationDispatchMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        Schema::create('event_registration_allocations', function (Blueprint $table): void {
            $table->id();
            $table->char('access_token_hash', 64);
            $table->timestamps();
        });
        Schema::create('event_registration_allocation_claims', function (Blueprint $table): void {
            $table->id();
            $table->char('token_hash', 64);
            $table->timestamps();
        });
    }

    public function test_migration_adds_and_removes_recoverable_dispatch_contracts(): void
    {
        $migration = require database_path(
            'migrations/2026_11_05_120000_add_allocation_dispatch_metadata.php'
        );

        $migration->up();

        foreach (['event_registration_allocations', 'event_registration_allocation_claims'] as $table) {
            $this->assertTrue(Schema::hasColumns($table, [
                'dispatch_token', 'dispatch_attempts',
                'dispatch_last_attempt_at', 'dispatch_last_error',
            ]));
        }
        DB::table('event_registration_allocations')->insert([
            'access_token_hash' => str_repeat('a', 64),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('event_registration_allocation_claims')->insert([
            'token_hash' => str_repeat('b', 64),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->assertSame(0, (int) DB::table('event_registration_allocations')
            ->value('dispatch_attempts'));
        $this->assertSame(0, (int) DB::table('event_registration_allocation_claims')
            ->value('dispatch_attempts'));

        $migration->down();

        $this->assertFalse(Schema::hasColumn('event_registration_allocations', 'dispatch_token'));
        $this->assertFalse(Schema::hasColumn('event_registration_allocation_claims', 'dispatch_token'));
        $this->assertDatabaseCount('event_registration_allocations', 1);
        $this->assertDatabaseCount('event_registration_allocation_claims', 1);
    }
}
