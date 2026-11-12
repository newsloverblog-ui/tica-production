<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BuyerIdentityHashMigrationTest extends TestCase
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
        config()->set('tickets.buyer_identity_hash_key', 'buyer-identity-test-key-with-32-bytes-minimum');
        config()->set('tickets.buyer_identity_hash_version', 3);

        Schema::create('bookings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->uuid('booking_id');
            $table->char('checkout_submission_hash', 64)->nullable()->unique();
            $table->unsignedBigInteger('event_id');
            $table->string('email');
        });
        Schema::create('ticket_reservations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('token');
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('ticket_id');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('status', 24)->default('active');
        });
    }

    public function test_migration_backfills_only_guests_and_rolls_back_cleanly(): void
    {
        DB::table('bookings')->insert([
            [
                'customer_id' => null,
                'booking_id' => '11111111-1111-4111-8111-111111111111',
                'event_id' => 41,
                'email' => ' Guest@Example.COM ',
            ],
            [
                'customer_id' => 7,
                'booking_id' => '22222222-2222-4222-8222-222222222222',
                'event_id' => 41,
                'email' => 'member@example.com',
            ],
        ]);

        $migration = require database_path(
            'migrations/2026_11_05_040000_add_buyer_identity_hashes.php'
        );
        $migration->up();

        $this->assertTrue(Schema::hasColumn('bookings', 'buyer_email_hash'));
        $this->assertTrue(Schema::hasColumn('bookings', 'buyer_email_hash_version'));
        $this->assertTrue(Schema::hasColumn('ticket_reservations', 'buyer_email_hash'));
        $this->assertSame(
            hash_hmac('sha256', 'guest@example.com', (string) config('tickets.buyer_identity_hash_key')),
            DB::table('bookings')->whereNull('customer_id')->value('buyer_email_hash'),
        );
        $this->assertNull(DB::table('bookings')->where('customer_id', 7)->value('buyer_email_hash'));
        $this->assertSame(3, (int) DB::table('bookings')->whereNull('customer_id')->value('buyer_email_hash_version'));

        $migration->down();
        $this->assertFalse(Schema::hasColumn('bookings', 'buyer_email_hash'));
        $this->assertFalse(Schema::hasColumn('bookings', 'buyer_email_hash_version'));
        $this->assertFalse(Schema::hasColumn('ticket_reservations', 'buyer_email_hash'));
    }
}
