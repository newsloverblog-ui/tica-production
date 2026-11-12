<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BookingTicketDeliveryDispatchMigrationTest extends TestCase
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
        Schema::create('booking_ticket_delivery_requests', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamps();
        });
    }

    public function test_migration_adds_and_removes_ticket_resend_dispatch_contract(): void
    {
        $migration = require database_path(
            'migrations/2026_11_05_170000_add_booking_ticket_delivery_dispatch_metadata.php'
        );
        $migration->up();

        $this->assertTrue(Schema::hasColumns('booking_ticket_delivery_requests', [
            'dispatch_attempts', 'dispatch_last_attempt_at', 'dispatch_last_error',
        ]));
        DB::table('booking_ticket_delivery_requests')->insert([
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->assertSame(0, (int) DB::table('booking_ticket_delivery_requests')
            ->value('dispatch_attempts'));

        $migration->down();

        $this->assertFalse(Schema::hasColumn('booking_ticket_delivery_requests', 'dispatch_attempts'));
        $this->assertDatabaseCount('booking_ticket_delivery_requests', 1);
    }
}
