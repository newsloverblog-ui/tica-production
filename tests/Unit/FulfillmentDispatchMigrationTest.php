<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FulfillmentDispatchMigrationTest extends TestCase
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

        Schema::create('booking_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('booking_id');
            $table->string('kind', 64);
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamps();
        });
        Schema::create('product_order_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_order_id');
            $table->string('kind', 64);
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamps();
        });
    }

    public function test_migration_adds_and_removes_dispatch_metadata_without_losing_rows(): void
    {
        DB::table('booking_deliveries')->insert([
            'booking_id' => 11,
            'kind' => 'event_booking_confirmation',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('product_order_deliveries')->insert([
            'product_order_id' => 12,
            'kind' => 'product_order_confirmation',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path(
            'migrations/2026_11_05_210000_add_fulfillment_dispatch_metadata.php'
        );
        $migration->up();

        foreach (['booking_deliveries', 'product_order_deliveries'] as $table) {
            $this->assertTrue(Schema::hasColumns($table, [
                'dispatch_attempts',
                'dispatch_last_attempt_at',
                'dispatch_last_error',
            ]));
            $this->assertSame(0, (int) DB::table($table)->value('dispatch_attempts'));
        }

        $migration->down();

        foreach (['booking_deliveries', 'product_order_deliveries'] as $table) {
            $this->assertFalse(Schema::hasColumn($table, 'dispatch_attempts'));
            $this->assertDatabaseCount($table, 1);
        }
    }
}
