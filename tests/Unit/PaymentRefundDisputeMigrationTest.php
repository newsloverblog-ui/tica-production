<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PaymentRefundDisputeMigrationTest extends TestCase
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

        Schema::create('payment_refunds', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('payment_intent_id');
            $table->unsignedBigInteger('event_cancellation_request_id')->nullable();
        });
    }

    public function test_migration_adds_and_removes_the_complete_dispute_contract(): void
    {
        $migration = require database_path(
            'migrations/2026_11_02_190000_create_payment_refund_disputes_table.php'
        );

        $migration->up();

        $this->assertTrue(Schema::hasTable('payment_refund_disputes'));
        $this->assertTrue(Schema::hasColumns('payment_refund_disputes', [
            'public_id', 'payment_intent_id', 'booking_id', 'organizer_id',
            'requested_by_organizer_id', 'requested_by_admin_id', 'reviewed_by_admin_id',
            'payment_refund_id', 'category', 'description', 'status', 'requested_amount',
            'currency', 'checked_in_ticket_count', 'resolution', 'opened_at', 'reviewed_at', 'closed_at',
        ]));
        $this->assertTrue(Schema::hasColumn('payment_refunds', 'payment_refund_dispute_id'));

        $migration->down();

        $this->assertFalse(Schema::hasTable('payment_refund_disputes'));
        $this->assertFalse(Schema::hasColumn('payment_refunds', 'payment_refund_dispute_id'));
    }
}
