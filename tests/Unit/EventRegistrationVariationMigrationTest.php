<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EventRegistrationVariationMigrationTest extends TestCase
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

        Schema::create('event_registration_attendees', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('ticket_id')->nullable();
        });
    }

    public function test_migration_adds_and_removes_the_explicit_ticket_variation(): void
    {
        $migration = require database_path(
            'migrations/2026_11_03_120000_add_variation_to_event_registration_attendees.php'
        );
        $migration->up();

        $this->assertTrue(Schema::hasColumn('event_registration_attendees', 'variation_name'));

        $migration->down();
        $this->assertFalse(Schema::hasColumn('event_registration_attendees', 'variation_name'));
    }
}
