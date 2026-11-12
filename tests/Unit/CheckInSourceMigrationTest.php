<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CheckInSourceMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        Schema::create('booking_tickets', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('checked_in_scanner_staff_id')->nullable();
        });
    }

    public function test_migration_adds_and_removes_truthful_check_in_source(): void
    {
        $migration = require database_path('migrations/2026_11_05_020000_add_check_in_source_to_booking_tickets.php');

        $migration->up();
        $this->assertTrue(Schema::hasColumn('booking_tickets', 'checked_in_source'));

        $migration->down();
        $this->assertFalse(Schema::hasColumn('booking_tickets', 'checked_in_source'));
    }
}
