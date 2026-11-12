<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EmailVerificationDispatchMigrationTest extends TestCase
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
        foreach ([
            'organizer_email_verification_deliveries',
            'customer_email_verification_deliveries',
        ] as $tableName) {
            Schema::create($tableName, function (Blueprint $table): void {
                $table->id();
                $table->unsignedSmallInteger('attempts')->default(0);
                $table->timestamps();
            });
        }
    }

    public function test_migration_adds_and_removes_both_dispatch_contracts(): void
    {
        $migration = require database_path(
            'migrations/2026_11_05_150000_add_email_verification_dispatch_metadata.php'
        );
        $migration->up();

        foreach ([
            'organizer_email_verification_deliveries',
            'customer_email_verification_deliveries',
        ] as $tableName) {
            $this->assertTrue(Schema::hasColumns($tableName, [
                'dispatch_attempts', 'dispatch_last_attempt_at', 'dispatch_last_error',
            ]));
            DB::table($tableName)->insert(['created_at' => now(), 'updated_at' => now()]);
            $this->assertSame(0, (int) DB::table($tableName)->value('dispatch_attempts'));
        }

        $migration->down();
        foreach ([
            'organizer_email_verification_deliveries',
            'customer_email_verification_deliveries',
        ] as $tableName) {
            $this->assertFalse(Schema::hasColumn($tableName, 'dispatch_attempts'));
            $this->assertDatabaseCount($tableName, 1);
        }
    }
}
