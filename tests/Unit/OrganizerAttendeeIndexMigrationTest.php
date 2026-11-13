<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrganizerAttendeeIndexMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        Schema::create('bookings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organizer_id');
        });
    }

    public function test_migration_adds_and_removes_organizer_order_index(): void
    {
        $migration = require database_path('migrations/2026_11_13_100000_add_organizer_order_index_to_bookings.php');

        $migration->up();
        $this->assertTrue(Schema::hasIndex('bookings', 'bookings_organizer_orders_idx'));

        $migration->down();
        $this->assertFalse(Schema::hasIndex('bookings', 'bookings_organizer_orders_idx'));
    }
}
