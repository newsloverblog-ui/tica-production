<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EventRegistrationFilesMigrationTest extends TestCase
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

    public function test_migration_creates_and_removes_private_registration_file_ledger(): void
    {
        $migration = require database_path(
            'migrations/2026_11_03_131000_create_event_registration_files_table.php'
        );
        $migration->up();

        $this->assertTrue(Schema::hasTable('event_registration_files'));
        foreach (['public_id', 'answer_id', 'submission_id', 'disk', 'path', 'original_name', 'mime_type', 'size', 'sha256'] as $column) {
            $this->assertTrue(Schema::hasColumn('event_registration_files', $column));
        }

        $migration->down();
        $this->assertFalse(Schema::hasTable('event_registration_files'));
    }
}
