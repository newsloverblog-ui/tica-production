<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EventCancellationCaseMigrationTest extends TestCase
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

        Schema::create('event_cancellation_cases', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('event_cancellation_request_id');
            $table->unsignedBigInteger('booking_id');
            $table->unsignedBigInteger('payment_intent_id')->nullable();
            $table->unsignedBigInteger('payment_refund_id')->nullable();
            $table->string('status');
            $table->decimal('amount', 18, 2)->default(0);
            $table->text('reason')->nullable();
            $table->timestamps();
        });
    }

    public function test_migration_adds_and_removes_the_case_resolution_contract(): void
    {
        $migration = require database_path(
            'migrations/2026_11_02_200000_add_resolution_to_event_cancellation_cases.php'
        );

        $migration->up();

        $this->assertTrue(Schema::hasColumns('event_cancellation_cases', [
            'payment_refund_dispute_id', 'resolved_by_admin_id', 'external_refund_reference',
            'resolution', 'resolved_at',
        ]));

        $migration->down();

        $this->assertFalse(Schema::hasColumn('event_cancellation_cases', 'payment_refund_dispute_id'));
        $this->assertFalse(Schema::hasColumn('event_cancellation_cases', 'external_refund_reference'));
        $this->assertFalse(Schema::hasColumn('event_cancellation_cases', 'resolved_at'));
    }
}
