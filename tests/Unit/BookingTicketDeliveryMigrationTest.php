<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BookingTicketDeliveryMigrationTest extends TestCase
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

    public function test_migration_applies_and_rolls_back_the_resend_delivery_ledger(): void
    {
        $migration = require database_path(
            'migrations/2026_11_02_210000_create_booking_ticket_delivery_requests_table.php'
        );
        $migration->up();

        $this->assertTrue(Schema::hasColumns('booking_ticket_delivery_requests', [
            'public_id', 'booking_id', 'requested_by_type', 'requested_by_id',
            'recipient_email_hash', 'status', 'attempts', 'last_error',
            'processing_at', 'sent_at', 'alerted_at',
        ]));

        $migration->down();
        $this->assertFalse(Schema::hasTable('booking_ticket_delivery_requests'));
    }
}
