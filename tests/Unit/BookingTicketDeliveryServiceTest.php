<?php

namespace Tests\Unit;

use App\Http\Controllers\FrontEnd\Event\BookingController;
use App\Jobs\ResendEventBookingTickets;
use App\Models\BookingTicketDeliveryRequest;
use App\Models\Event\Booking;
use App\Services\BookingTicketDeliveryService;
use DomainException;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class BookingTicketDeliveryServiceTest extends TestCase
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

    public function test_customer_can_queue_an_encrypted_resend_only_to_the_booking_recipient(): void
    {
        Queue::fake();
        $booking = $this->booking();

        $delivery = (new BookingTicketDeliveryService())->requestForCustomer($booking->id, 31);

        $this->assertSame('pending', $delivery->status);
        $this->assertSame(1, $delivery->fresh()->dispatch_attempts);
        $this->assertNotNull($delivery->fresh()->dispatch_last_attempt_at);
        $this->assertSame(hash_hmac('sha256', 'ada@example.com', (string) config('app.key')), $delivery->recipient_email_hash);
        $this->assertDatabaseHas('audit_logs', ['action' => 'event_booking.tickets_resend_requested']);
        Queue::assertPushed(ResendEventBookingTickets::class, function ($job) use ($delivery) {
            return $job->deliveryRequestId === $delivery->id && $job instanceof ShouldBeEncrypted;
        });
    }

    public function test_organizer_ownership_and_duplicate_cooldown_are_enforced(): void
    {
        Queue::fake();
        $booking = $this->booking();
        $service = new BookingTicketDeliveryService();

        $this->expectException(DomainException::class);
        try {
            $service->requestForOrganizer($booking->id, 999);
        } finally {
            $this->assertDatabaseCount('booking_ticket_delivery_requests', 0);
        }
    }

    public function test_recent_duplicate_and_inactive_tickets_are_rejected(): void
    {
        Queue::fake();
        $booking = $this->booking();
        $service = new BookingTicketDeliveryService();
        $service->requestForOrganizer($booking->id, 41);

        try {
            $service->requestForOrganizer($booking->id, 41);
            $this->fail('A recent duplicate resend must be rejected.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('recently requested', $exception->getMessage());
        }

        DB::table('booking_ticket_delivery_requests')->delete();
        DB::table('booking_tickets')->where('booking_id', $booking->id)->update(['status' => 'refunded']);
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('no active tickets');
        $service->requestForCustomer($booking->id, 31);
    }

    public function test_job_rebuilds_current_invoice_sends_once_and_records_delivery(): void
    {
        Queue::fake();
        $booking = $this->booking();
        $delivery = (new BookingTicketDeliveryService())->requestForCustomer($booking->id, 31);
        $controller = Mockery::mock(BookingController::class);
        $controller->shouldReceive('generateInvoice')->once()
            ->with(Mockery::type(Booking::class), $booking->event_id, true)
            ->andReturn('private/event-bookings/'.$booking->id.'/invoices/booking-81.pdf');
        $controller->shouldReceive('sendMail')->once()
            ->with(Mockery::type(Booking::class), true)->andReturnTrue();

        $job = new ResendEventBookingTickets($delivery->id);
        $job->handle($controller, new BookingTicketDeliveryService());
        $job->handle($controller, new BookingTicketDeliveryService());

        $delivery->refresh();
        $this->assertSame('sent', $delivery->status);
        $this->assertSame(1, (int) $delivery->attempts);
        $this->assertNull($delivery->processing_at);
        $this->assertNotNull($delivery->sent_at);
        $this->assertSame('private/event-bookings/'.$booking->id.'/invoices/booking-81.pdf', $booking->fresh()->invoice);
        $this->assertDatabaseHas('funnel_analytics_events', [
            'event_name' => 'ticket_delivered',
            'booking_id' => $booking->id,
            'source' => 'server',
        ]);
    }

    public function test_ticket_transferred_to_another_customer_is_not_deliverable_to_original_buyer(): void
    {
        Queue::fake();
        $booking = $this->booking();
        DB::table('booking_tickets')->where('booking_id', $booking->id)->update([
            'holder_customer_id' => 88,
            'holder_email' => 'recipient@example.com',
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('no active tickets');
        (new BookingTicketDeliveryService())->requestForCustomer($booking->id, 31);
    }

    public function test_failed_queue_handoff_is_durable_and_the_recovery_command_dispatches_the_same_request(): void
    {
        Queue::fake();
        $booking = $this->booking();
        $failing = new class extends BookingTicketDeliveryService {
            protected function pushDeliveryJob(int $deliveryRequestId): void
            {
                throw new \RuntimeException('queue unavailable');
            }
        };

        $delivery = $failing->requestForCustomer($booking->id, 31)->fresh();

        $this->assertSame('pending', $delivery->status);
        $this->assertSame(1, $delivery->dispatch_attempts);
        $this->assertSame('queue unavailable', $delivery->dispatch_last_error);
        Queue::assertNothingPushed();

        $this->artisan('tickets:dispatch-resends')
            ->expectsOutputToContain('Ticket resend dispatch attempted: 1 | failed: 0 | alerted: 0')
            ->assertSuccessful();

        $delivery->refresh();
        $this->assertSame(2, $delivery->dispatch_attempts);
        $this->assertNull($delivery->dispatch_last_error);
        Queue::assertPushed(ResendEventBookingTickets::class, function ($job) use ($delivery): bool {
            return $job->deliveryRequestId === $delivery->id;
        });
    }

    private function booking(): Booking
    {
        DB::table('events')->insert([
            'id' => 9, 'organizer_id' => 41, 'lifecycle_status' => 'scheduled',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $booking = Booking::create([
            'customer_id' => 31, 'organizer_id' => 41, 'booking_id' => 'booking-81',
            'event_id' => 9, 'fname' => 'Ada', 'lname' => 'Okafor',
            'email' => 'ada@example.com', 'price' => 25000, 'quantity' => 1,
            'paymentStatus' => 'completed', 'refund_status' => null,
        ]);
        DB::table('booking_tickets')->insert([
            'public_id' => '9550c3dc-37f0-4ba0-a8c3-437a31afefc1',
            'booking_id' => $booking->id, 'event_id' => 9, 'organizer_id' => 41,
            'sequence' => 1, 'status' => 'issued', 'created_at' => now(), 'updated_at' => now(),
        ]);
        return $booking;
    }

    private function createSchema(): void
    {
        Schema::create('events', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('organizer_id');
            $table->string('lifecycle_status')->default('scheduled'); $table->timestamps();
        });
        Schema::create('bookings', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('organizer_id'); $table->string('booking_id');
            $table->unsignedBigInteger('event_id'); $table->string('fname')->nullable();
            $table->string('lname')->nullable(); $table->string('email')->nullable();
            $table->decimal('price', 18, 2)->default(0); $table->unsignedInteger('quantity')->default(1);
            $table->string('paymentStatus')->nullable(); $table->decimal('refunded_amount', 18, 2)->default(0);
            $table->string('refund_status')->nullable(); $table->string('invoice')->nullable(); $table->timestamps();
        });
        Schema::create('booking_tickets', function (Blueprint $table): void {
            $table->id(); $table->uuid('public_id')->unique(); $table->unsignedBigInteger('booking_id');
            $table->unsignedBigInteger('event_id'); $table->unsignedBigInteger('organizer_id');
            $table->unsignedBigInteger('holder_customer_id')->nullable(); $table->string('holder_email')->nullable();
            $table->unsignedInteger('sequence'); $table->string('status'); $table->timestamps();
        });
        Schema::create('booking_ticket_delivery_requests', function (Blueprint $table): void {
            $table->id(); $table->uuid('public_id')->unique(); $table->unsignedBigInteger('booking_id');
            $table->string('requested_by_type', 20); $table->unsignedBigInteger('requested_by_id');
            $table->char('recipient_email_hash', 64); $table->string('status', 24)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('dispatch_attempts')->default(0);
            $table->timestamp('dispatch_last_attempt_at')->nullable(); $table->text('dispatch_last_error')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('processing_at')->nullable(); $table->timestamp('sent_at')->nullable();
            $table->timestamp('alerted_at')->nullable(); $table->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id(); $table->string('actor_type'); $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action'); $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable(); $table->json('before')->nullable();
            $table->json('after')->nullable(); $table->json('metadata')->nullable();
            $table->string('request_id')->nullable(); $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable(); $table->timestamp('created_at')->nullable();
        });
        Schema::create('funnel_analytics_events', function (Blueprint $table): void {
            $table->id(); $table->uuid('public_id')->unique(); $table->string('event_name', 64);
            $table->string('source', 16); $table->unsignedBigInteger('event_id')->nullable();
            $table->unsignedBigInteger('booking_id')->nullable(); $table->unsignedBigInteger('payment_intent_id')->nullable();
            $table->unsignedBigInteger('organizer_id')->nullable(); $table->unsignedBigInteger('customer_id')->nullable();
            $table->char('session_hash', 64)->nullable(); $table->string('dedupe_key', 191)->nullable()->unique();
            $table->longText('properties')->nullable(); $table->timestamp('occurred_at'); $table->timestamps();
        });
    }
}
