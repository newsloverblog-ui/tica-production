<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ScannerStaffAttributionMigrationTest extends TestCase
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

        Schema::dropIfExists('booking_tickets');
        Schema::create('booking_tickets', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('checked_in_scanner_device_id')->nullable();
        });
        Schema::create('scanner_check_in_conflicts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('attempted_scanner_device_id');
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('booking_tickets');
        Schema::dropIfExists('scanner_check_in_conflicts');

        parent::tearDown();
    }

    public function test_migration_adds_and_removes_scanner_staff_attribution(): void
    {
        $migration = require database_path(
            'migrations/2026_11_05_010000_add_scanner_staff_identity_to_booking_tickets.php'
        );

        $migration->up();
        $this->assertTrue(Schema::hasColumn('booking_tickets', 'checked_in_scanner_staff_id'));
        $this->assertTrue(Schema::hasColumn('scanner_check_in_conflicts', 'original_scanner_staff_id'));
        $this->assertTrue(Schema::hasColumn('scanner_check_in_conflicts', 'attempted_scanner_staff_id'));

        $migration->down();
        $this->assertFalse(Schema::hasColumn('booking_tickets', 'checked_in_scanner_staff_id'));
        $this->assertFalse(Schema::hasColumn('scanner_check_in_conflicts', 'original_scanner_staff_id'));
        $this->assertFalse(Schema::hasColumn('scanner_check_in_conflicts', 'attempted_scanner_staff_id'));
    }
}
