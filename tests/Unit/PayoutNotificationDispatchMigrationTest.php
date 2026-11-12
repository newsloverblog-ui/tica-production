<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PayoutNotificationDispatchMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        Schema::create('payout_transfers', function (Blueprint $table): void {
            $table->id();
            $table->timestamp('dispatch_alerted_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_migration_adds_and_removes_notification_dispatch_contract(): void
    {
        $migration = require database_path(
            'migrations/2026_11_05_110000_add_payout_notification_dispatch_metadata.php'
        );

        $migration->up();

        $this->assertTrue(Schema::hasColumns('payout_transfers', [
            'notification_dispatch_attempts',
            'notification_dispatch_last_attempt_at',
            'notification_dispatch_last_error',
            'notification_dispatch_alerted_at',
        ]));
        DB::table('payout_transfers')->insert([
            'notification_dispatch_attempts' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->assertSame(0, (int) DB::table('payout_transfers')
            ->value('notification_dispatch_attempts'));

        $migration->down();

        $this->assertFalse(Schema::hasColumn(
            'payout_transfers',
            'notification_dispatch_attempts'
        ));
        $this->assertDatabaseCount('payout_transfers', 1);
    }
}
