<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TicketTransferDeliveryMigrationTest extends TestCase
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

        Schema::create('booking_ticket_transfers', function (Blueprint $table): void {
            $table->id();
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }

    public function test_migration_adds_and_removes_transfer_delivery_state(): void
    {
        $migration = require database_path(
            'migrations/2026_11_03_090000_add_delivery_state_to_booking_ticket_transfers_table.php'
        );
        $migration->up();

        $this->assertTrue(Schema::hasColumns('booking_ticket_transfers', [
            'delivery_status', 'delivery_attempts', 'last_error',
            'processing_at', 'sent_at', 'alerted_at',
        ]));

        $migration->down();
        $this->assertFalse(Schema::hasColumn('booking_ticket_transfers', 'delivery_status'));
    }
}
