<?php

namespace Tests\Unit;

use App\Http\Controllers\FrontEnd\Event\BookingController;
use App\Models\Event;
use App\Models\Event\Ticket;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FreeBookingIdempotencyTest extends TestCase
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
        $this->createSchema();
    }

    public function test_replaying_one_free_checkout_returns_original_booking_without_consuming_stock_twice(): void
    {
        DB::table('basic_settings')->insert(['uniqid' => 12345, 'tax' => 0, 'commission' => 0]);
        $event = Event::create([
            'organizer_id' => 17,
            'status' => 1,
            'lifecycle_status' => 'scheduled',
            'registration_mode' => 'ticketing',
        ]);
        $ticket = Ticket::create([
            'event_id' => $event->id,
            'title' => 'Community admission',
            'pricing_type' => 'free',
            'ticket_available_type' => 'limited',
            'ticket_available' => 5,
            'price' => 0,
        ]);
        $submissionHash = hash('sha256', 'one-reviewed-checkout');
        $payload = [
            'event_id' => $event->id,
            'price' => 0,
            'tax' => 0,
            'commission' => 0,
            'quantity' => 2,
            'discount' => 0,
            'total_early_bird_dicount' => 0,
            'currencyText' => null,
            'currencyTextPosition' => null,
            'currencySymbol' => null,
            'currencySymbolPosition' => null,
            'fname' => 'Ada',
            'lname' => 'Okafor',
            'email' => 'ada@example.com',
            'phone' => '08000000000',
            'paymentMethod' => null,
            'gatewayType' => null,
            'paymentStatus' => 'free',
            'checkout_submission_hash' => $submissionHash,
            'selected_tickets' => [[
                'ticket_id' => $ticket->id,
                'name' => 'Community admission',
                'qty' => 2,
                'price' => 0,
            ]],
        ];
        $controller = new BookingController();

        $first = $controller->storeData($payload);
        $replayed = $controller->storeData($payload);

        $this->assertSame($first->id, $replayed->id);
        $this->assertFalse($replayed->wasRecentlyCreated);
        $this->assertSame(1, DB::table('bookings')->count());
        $this->assertSame(2, DB::table('booking_tickets')->count());
        $this->assertSame(3, (int) $ticket->fresh()->ticket_available);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'booking.created')->count());
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'booking.tickets_issued')->count());
    }

    public function test_checkout_submission_hash_migration_is_reversible(): void
    {
        $migration = require database_path('migrations/2026_11_05_030000_add_checkout_submission_hash_to_bookings.php');
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropUnique(['checkout_submission_hash']);
            $table->dropColumn('checkout_submission_hash');
        });

        $migration->up();
        $this->assertTrue(Schema::hasColumn('bookings', 'checkout_submission_hash'));

        $migration->down();
        $this->assertFalse(Schema::hasColumn('bookings', 'checkout_submission_hash'));
    }

    private function createSchema(): void
    {
        Schema::create('events', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('organizer_id')->nullable(); $table->integer('status')->default(1);
            $table->string('lifecycle_status')->nullable(); $table->string('registration_mode')->nullable();
            $table->timestamp('sales_closed_at')->nullable(); $table->timestamps();
        });
        Schema::create('tickets', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('event_id'); $table->string('title')->nullable();
            $table->string('pricing_type')->nullable(); $table->string('ticket_available_type')->nullable();
            $table->integer('ticket_available')->nullable(); $table->decimal('price', 18, 2)->default(0);
            $table->text('variations')->nullable(); $table->softDeletes(); $table->timestamps();
        });
        Schema::create('basic_settings', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('uniqid')->unique();
            $table->decimal('tax', 8, 2)->default(0); $table->decimal('commission', 8, 2)->default(0);
        });
        Schema::create('bookings', function (Blueprint $table): void {
            $table->id(); $table->uuid('booking_id')->unique(); $table->char('checkout_submission_hash', 64)->nullable()->unique();
            $table->unsignedBigInteger('customer_id')->nullable(); $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('organizer_id')->nullable(); $table->unsignedBigInteger('ticket_id')->nullable();
            $table->string('fname')->nullable(); $table->string('lname')->nullable(); $table->string('email')->nullable();
            $table->string('phone')->nullable(); $table->string('country')->nullable(); $table->string('state')->nullable();
            $table->string('city')->nullable(); $table->string('zip_code')->nullable(); $table->string('address')->nullable();
            $table->text('variation')->nullable(); $table->decimal('price', 18, 2)->default(0);
            $table->decimal('tax_percentage', 8, 2)->default(0); $table->decimal('commission_percentage', 8, 2)->default(0);
            $table->decimal('tax', 18, 2)->default(0); $table->decimal('commission', 18, 2)->default(0);
            $table->unsignedInteger('quantity'); $table->decimal('discount', 18, 2)->default(0);
            $table->decimal('early_bird_discount', 18, 2)->default(0); $table->string('currencyText')->nullable();
            $table->string('currencyTextPosition')->nullable(); $table->string('currencySymbol')->nullable();
            $table->string('currencySymbolPosition')->nullable(); $table->string('paymentMethod')->nullable();
            $table->string('gatewayType')->nullable(); $table->string('paymentStatus')->nullable();
            $table->string('invoice')->nullable(); $table->string('attachmentFile')->nullable();
            $table->string('event_date')->nullable(); $table->integer('scan_status')->default(0);
            $table->string('conversation_id')->nullable(); $table->timestamps();
        });
        Schema::create('booking_tickets', function (Blueprint $table): void {
            $table->id(); $table->uuid('public_id')->unique(); $table->unsignedBigInteger('booking_id');
            $table->unsignedBigInteger('event_id'); $table->unsignedBigInteger('organizer_id')->nullable();
            $table->unsignedBigInteger('ticket_id')->nullable(); $table->unsignedInteger('sequence');
            $table->string('variation_name')->nullable(); $table->string('holder_name')->nullable();
            $table->string('holder_email')->nullable(); $table->string('status');
            $table->timestamp('checked_in_at')->nullable(); $table->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id(); $table->string('actor_type'); $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action'); $table->string('subject_type')->nullable(); $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('before')->nullable(); $table->json('after')->nullable(); $table->json('metadata')->nullable();
            $table->string('request_id')->nullable(); $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable(); $table->timestamp('created_at')->nullable();
        });
    }
}
