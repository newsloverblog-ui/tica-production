<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\Event\Booking;
use App\Services\GuestTicketClaimService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GuestTicketClaimServiceTest extends TestCase
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

    public function test_matching_customer_claims_booking_and_unowned_ticket_units_idempotently(): void
    {
        $customer = $this->customer('buyer@example.com');
        $booking = $this->booking('Buyer@Example.com');
        DB::table('booking_tickets')->insert([
            ['public_id' => 'unit-1', 'booking_id' => $booking->id, 'holder_customer_id' => null, 'created_at' => now(), 'updated_at' => now()],
            ['public_id' => 'unit-2', 'booking_id' => $booking->id, 'holder_customer_id' => 99, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $service = app(GuestTicketClaimService::class);
        $this->assertSame($customer->id, $service->claim($booking->id, $customer)->customer_id);
        $this->assertSame($customer->id, DB::table('booking_tickets')->where('public_id', 'unit-1')->value('holder_customer_id'));
        $this->assertSame(99, DB::table('booking_tickets')->where('public_id', 'unit-2')->value('holder_customer_id'));
        $service->claim($booking->id, $customer);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'booking.guest_tickets_claimed')->count());
    }

    public function test_claim_is_bound_to_booking_email_and_existing_owner(): void
    {
        $customer = $this->customer('wrong@example.com');
        $booking = $this->booking('buyer@example.com');

        $this->expectException(AuthorizationException::class);
        app(GuestTicketClaimService::class)->claim($booking->id, $customer);
    }

    private function customer(string $email): Customer
    {
        return Customer::create(['email' => $email, 'status' => 1, 'email_verified_at' => now()]);
    }

    private function booking(string $email): Booking
    {
        return Booking::create([
            'booking_id' => 'TIC-CLAIM-1', 'email' => $email, 'event_id' => 12,
            'quantity' => 2, 'paymentStatus' => 'completed',
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->id(); $table->string('email')->unique(); $table->unsignedTinyInteger('status')->default(0);
            $table->timestamp('email_verified_at')->nullable(); $table->timestamps();
        });
        Schema::create('bookings', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('customer_id')->nullable(); $table->string('booking_id');
            $table->string('email'); $table->unsignedBigInteger('event_id'); $table->unsignedInteger('quantity');
            $table->string('paymentStatus')->nullable(); $table->timestamps();
        });
        Schema::create('booking_tickets', function (Blueprint $table): void {
            $table->id(); $table->string('public_id')->unique(); $table->unsignedBigInteger('booking_id');
            $table->unsignedBigInteger('holder_customer_id')->nullable(); $table->timestamps();
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
