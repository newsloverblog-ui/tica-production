<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EventWaitlistDispatchMigrationTest extends TestCase
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
        Schema::create('event_waitlist_entries', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamps();
        });
    }

    public function test_migration_adds_and_removes_waitlist_dispatch_contract(): void
    {
        $migration = require database_path(
            'migrations/2026_11_05_200000_add_event_waitlist_dispatch_metadata.php'
        );
        $migration->up();

        $this->assertTrue(Schema::hasColumns('event_waitlist_entries', [
            'dispatch_attempts', 'dispatch_last_attempt_at', 'dispatch_last_error',
        ]));
        DB::table('event_waitlist_entries')->insert([
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->assertSame(0, (int) DB::table('event_waitlist_entries')
            ->value('dispatch_attempts'));

        $migration->down();

        $this->assertFalse(Schema::hasColumn('event_waitlist_entries', 'dispatch_attempts'));
        $this->assertDatabaseCount('event_waitlist_entries', 1);
    }
}
