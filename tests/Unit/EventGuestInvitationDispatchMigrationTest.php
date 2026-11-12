<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EventGuestInvitationDispatchMigrationTest extends TestCase
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
        Schema::create('event_guest_invitations', function (Blueprint $table): void {
            $table->id();
            $table->char('token_hash', 64);
            $table->timestamps();
        });
    }

    public function test_migration_adds_and_removes_guest_dispatch_contract(): void
    {
        $migration = require database_path(
            'migrations/2026_11_05_140000_add_event_guest_invitation_dispatch_metadata.php'
        );

        $migration->up();

        $this->assertTrue(Schema::hasColumns('event_guest_invitations', [
            'dispatch_token', 'dispatch_attempts',
            'dispatch_last_attempt_at', 'dispatch_last_error',
        ]));
        DB::table('event_guest_invitations')->insert([
            'token_hash' => str_repeat('a', 64),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->assertSame(0, (int) DB::table('event_guest_invitations')
            ->value('dispatch_attempts'));

        $migration->down();

        $this->assertFalse(Schema::hasColumn('event_guest_invitations', 'dispatch_token'));
        $this->assertDatabaseCount('event_guest_invitations', 1);
    }
}
