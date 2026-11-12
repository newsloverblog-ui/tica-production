<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TicketSmsDeliveryMigrationTest extends TestCase
{
    public function test_sms_delivery_metadata_migrates_and_rolls_back_cleanly(): void
    {
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
            $table->string('status', 20);
            $table->timestamp('updated_at')->nullable();
        });

        $migration = require database_path('migrations/2026_11_05_050000_add_sms_metadata_to_booking_deliveries.php');
        $migration->up();
        $this->assertTrue(Schema::hasColumns('booking_deliveries', [
            'channel', 'recipient_hash', 'provider_reference',
        ]));

        $migration->down();
        $this->assertFalse(Schema::hasColumn('booking_deliveries', 'channel'));
        $this->assertFalse(Schema::hasColumn('booking_deliveries', 'recipient_hash'));
        $this->assertFalse(Schema::hasColumn('booking_deliveries', 'provider_reference'));
    }

    public function test_sms_provider_reference_is_unique_and_the_constraint_rolls_back(): void
    {
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
            $table->string('provider_reference', 191)->nullable();
        });

        $migration = require database_path('migrations/2026_11_05_060000_enforce_unique_sms_provider_references.php');
        $migration->up();
        DB::table('booking_deliveries')->insert([
            'booking_id' => 1,
            'kind' => 'event_booking_sms',
            'provider_reference' => 'provider-message-1',
        ]);

        try {
            DB::table('booking_deliveries')->insert([
                'booking_id' => 2,
                'kind' => 'event_booking_sms',
                'provider_reference' => 'provider-message-1',
            ]);
            $this->fail('Duplicate provider references must be rejected.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }

        $migration->down();
        DB::table('booking_deliveries')->insert([
            'booking_id' => 2,
            'kind' => 'event_booking_sms',
            'provider_reference' => 'provider-message-1',
        ]);
        $this->assertSame(2, DB::table('booking_deliveries')->count());
    }
}
