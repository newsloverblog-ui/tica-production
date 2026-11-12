<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EventRegistrationNotificationDispatchMigrationTest extends TestCase
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
        Schema::create('event_registration_notifications', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamps();
        });
    }

    public function test_migration_adds_and_removes_registration_notification_dispatch_contract(): void
    {
        $migration = require database_path(
            'migrations/2026_11_05_190000_add_event_registration_notification_dispatch_metadata.php'
        );
        $migration->up();

        $this->assertTrue(Schema::hasColumns('event_registration_notifications', [
            'dispatch_attempts', 'dispatch_last_attempt_at', 'dispatch_last_error',
        ]));
        DB::table('event_registration_notifications')->insert([
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->assertSame(0, (int) DB::table('event_registration_notifications')
            ->value('dispatch_attempts'));

        $migration->down();

        $this->assertFalse(Schema::hasColumn('event_registration_notifications', 'dispatch_attempts'));
        $this->assertDatabaseCount('event_registration_notifications', 1);
    }
}
