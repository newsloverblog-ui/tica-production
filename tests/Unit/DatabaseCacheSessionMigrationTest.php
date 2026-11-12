<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseCacheSessionMigrationTest extends TestCase
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

    public function test_migration_provides_database_cache_locks_and_sessions_idempotently(): void
    {
        $migration = require database_path('migrations/2026_11_10_124000_create_database_cache_and_session_tables.php');
        $migration->up();
        $migration->up();

        $this->assertTrue(Schema::hasColumns('cache', ['key', 'value', 'expiration']));
        $this->assertTrue(Schema::hasColumns('cache_locks', ['key', 'owner', 'expiration']));
        $this->assertTrue(Schema::hasColumns('sessions', [
            'id', 'user_id', 'ip_address', 'user_agent', 'payload', 'last_activity',
        ]));

        $migration->down();
        $this->assertTrue(Schema::hasTable('sessions'));
    }
}
