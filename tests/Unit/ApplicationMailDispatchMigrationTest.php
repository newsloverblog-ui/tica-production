<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ApplicationMailDispatchMigrationTest extends TestCase
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
        Schema::create('application_mail_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamps();
        });
    }

    public function test_migration_adds_and_removes_application_mail_dispatch_contract(): void
    {
        $migration = require database_path(
            'migrations/2026_11_05_160000_add_application_mail_dispatch_metadata.php'
        );
        $migration->up();

        $this->assertTrue(Schema::hasColumns('application_mail_deliveries', [
            'dispatch_attempts', 'dispatch_last_attempt_at', 'dispatch_last_error',
        ]));
        DB::table('application_mail_deliveries')->insert([
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->assertSame(0, (int) DB::table('application_mail_deliveries')
            ->value('dispatch_attempts'));

        $migration->down();

        $this->assertFalse(Schema::hasColumn('application_mail_deliveries', 'dispatch_attempts'));
        $this->assertDatabaseCount('application_mail_deliveries', 1);
    }
}
