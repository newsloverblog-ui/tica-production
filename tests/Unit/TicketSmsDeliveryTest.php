<?php

namespace Tests\Unit;

use App\Jobs\FinalizePaidEventBooking;
use App\Jobs\SendEventBookingSms;
use App\Models\BookingDelivery;
use App\Models\Event;
use App\Models\Event\Booking;
use App\Models\Event\EventContent;
use App\Services\IncidentAlertService;
use App\Services\PaidBookingFulfillmentService;
use App\Services\TicketSmsService;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class TicketSmsDeliveryTest extends TestCase
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
        config()->set('app.url', 'https://tica.example');
        config()->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        config()->set('tickets.buyer_identity_hash_key', 'base64:'.base64_encode(str_repeat('b', 32)));
        config()->set('ticket_delivery.sms', [
            'enabled' => true,
            'driver' => 'termii',
            'base_url' => 'https://api.ng.termii.com',
            'api_key' => str_repeat('s', 32),
            'sender_id' => 'Tica',
            'channel' => 'dnd',
            'default_country_code' => '234',
            'connect_timeout_seconds' => 2,
            'timeout_seconds' => 5,
        ]);
        config()->set('operations.incident_email', null);
        config()->set('operations.incident_webhook_url', null);
        $this->createSchema();
    }

    public function test_paid_fulfilment_dispatches_independent_encrypted_email_and_sms_jobs(): void
    {
        Queue::fake();
        $booking = $this->booking();

        $emailDelivery = app(PaidBookingFulfillmentService::class)->dispatch($booking->id);

        $this->assertSame('event_booking_confirmation', $emailDelivery->kind);
        $this->assertDatabaseHas('booking_deliveries', [
            'booking_id' => $booking->id,
            'kind' => 'event_booking_sms',
            'channel' => 'sms',
            'status' => 'pending',
        ]);
        $smsDelivery = BookingDelivery::query()->where('kind', 'event_booking_sms')->firstOrFail();
        $this->assertSame(1, $smsDelivery->dispatch_attempts);
        $this->assertNotNull($smsDelivery->dispatch_last_attempt_at);
        Queue::assertPushed(FinalizePaidEventBooking::class);
        Queue::assertPushed(SendEventBookingSms::class, fn ($job) =>
            $job->bookingId === $booking->id && $job instanceof ShouldBeEncrypted
        );
    }

    public function test_sms_job_sends_once_and_records_only_hashed_recipient_and_provider_reference(): void
    {
        $booking = $this->booking();
        Http::fake([
            'https://api.ng.termii.com/api/sms/send' => Http::response([
                'message_id' => 'termii-message-42',
                'message' => 'Successfully Sent',
            ]),
        ]);

        $job = new SendEventBookingSms($booking->id);
        $job->handle(app(TicketSmsService::class), app(IncidentAlertService::class));
        $job->handle(app(TicketSmsService::class), app(IncidentAlertService::class));

        Http::assertSentCount(1);
        $request = Http::recorded()->first()[0];
        $payload = $request->data();
        $this->assertSame('https://api.ng.termii.com/api/sms/send', $request->url());
        $this->assertSame('2348012345678', $payload['to']);
        $this->assertSame('Tica', $payload['from']);
        $this->assertSame('dnd', $payload['channel']);
        $this->assertStringContainsString('Lagos Night Live', $payload['sms']);
        $this->assertStringContainsString('/event-booking-complete?', $payload['sms']);

        $delivery = BookingDelivery::query()->where('kind', 'event_booking_sms')->firstOrFail();
        $this->assertSame('sent', $delivery->status);
        $this->assertSame('termii-message-42', $delivery->provider_reference);
        $this->assertSame(64, strlen($delivery->getRawOriginal('recipient_hash')));
        $this->assertStringNotContainsString('08012345678', implode('|', $delivery->getAttributes()));
        $this->assertNotNull($delivery->sent_at);
    }

    public function test_invalid_phone_fails_closed_and_records_an_immediate_operational_alert_state(): void
    {
        $booking = $this->booking('not-a-phone');
        Http::fake();
        $alerts = Mockery::mock(IncidentAlertService::class);
        $alerts->shouldReceive('alert')->once()->withArgs(function (
            $key,
            $title,
            $context,
            $severity
        ): bool {
            return str_starts_with($key, 'ticket-sms-delivery-failed:')
                && $title === 'Ticket SMS delivery failed and requires review.'
                && $severity === 'critical'
                && !array_key_exists('error', $context)
                && $context['exception_type'] === RuntimeException::class;
        })->andReturn([]);

        try {
            (new SendEventBookingSms($booking->id))->handle(
                app(TicketSmsService::class),
                $alerts
            );
            $this->fail('Invalid recipient must fail the SMS delivery.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('cannot be delivered', $exception->getMessage());
        }

        Http::assertNothingSent();
        $delivery = BookingDelivery::query()->where('kind', 'event_booking_sms')->firstOrFail();
        $this->assertSame('failed', $delivery->status);
        $this->assertSame(1, (int) $delivery->attempts);
        $this->assertNotNull($delivery->alerted_at);
        $this->assertNull($delivery->recipient_hash);
        $this->assertStringNotContainsString(
            'cannot be delivered',
            (string) DB::table('booking_deliveries')->where('id', $delivery->id)->value('last_error')
        );
    }

    public function test_operator_can_reconcile_an_ambiguous_failure_without_sending_again(): void
    {
        Queue::fake();
        $booking = $this->booking();
        $delivery = BookingDelivery::create([
            'booking_id' => $booking->id,
            'kind' => 'event_booking_sms',
            'channel' => 'sms',
            'status' => 'failed',
            'attempts' => 1,
            'last_error' => 'Provider outcome is ambiguous.',
        ]);

        $exit = Artisan::call('tickets:recover-sms-delivery', [
            'delivery' => $delivery->id,
            '--accepted-reference' => 'termii-message-recovered-42',
            '--evidence' => 'termii-console:case-42',
            '--json' => true,
        ]);

        $this->assertSame(0, $exit, Artisan::output());
        Queue::assertNothingPushed();
        $this->assertDatabaseHas('booking_deliveries', [
            'id' => $delivery->id,
            'status' => 'sent',
            'provider_reference' => 'termii-message-recovered-42',
            'last_error' => null,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ticket.sms_delivery_reconciled',
            'subject_id' => $delivery->id,
        ]);
    }

    public function test_operator_can_queue_one_replacement_only_after_confirming_non_acceptance(): void
    {
        Queue::fake();
        $booking = $this->booking();
        $delivery = BookingDelivery::create([
            'booking_id' => $booking->id,
            'kind' => 'event_booking_sms',
            'channel' => 'sms',
            'status' => 'failed',
            'attempts' => 1,
            'last_error' => 'Provider rejected request.',
            'alerted_at' => now(),
        ]);

        $exit = Artisan::call('tickets:recover-sms-delivery', [
            'delivery' => $delivery->id,
            '--confirm-not-accepted' => true,
            '--evidence' => 'termii-console:not-found-42',
            '--json' => true,
        ]);

        $this->assertSame(0, $exit, Artisan::output());
        $this->assertDatabaseHas('booking_deliveries', [
            'id' => $delivery->id,
            'status' => 'pending',
            'last_error' => null,
            'alerted_at' => null,
        ]);
        Queue::assertPushed(SendEventBookingSms::class, fn (SendEventBookingSms $job) =>
            $job->bookingId === $booking->id
        );
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ticket.sms_delivery_requeued',
            'subject_id' => $delivery->id,
        ]);
    }

    public function test_sms_recovery_fails_closed_without_one_provider_verified_action(): void
    {
        $booking = $this->booking();
        $delivery = BookingDelivery::create([
            'booking_id' => $booking->id,
            'kind' => 'event_booking_sms',
            'channel' => 'sms',
            'status' => 'failed',
        ]);

        $this->assertSame(1, Artisan::call('tickets:recover-sms-delivery', [
            'delivery' => $delivery->id,
            '--evidence' => 'termii-console:case-42',
            '--json' => true,
        ]));
        $this->assertStringContainsString('Choose exactly one recovery action', Artisan::output());
        $this->assertDatabaseHas('booking_deliveries', ['id' => $delivery->id, 'status' => 'failed']);
    }

    private function booking(string $phone = '0801 234 5678'): Booking
    {
        $event = Event::create([
            'status' => 1,
            'lifecycle_status' => 'scheduled',
            'registration_mode' => 'ticketing',
        ]);
        EventContent::create(['event_id' => $event->id, 'title' => 'Lagos Night Live']);

        return Booking::create([
            'booking_id' => 'booking-'.str()->uuid(),
            'event_id' => $event->id,
            'fname' => 'Ada',
            'lname' => 'Okafor',
            'email' => 'ada@example.com',
            'phone' => $phone,
            'quantity' => 1,
            'paymentStatus' => 'completed',
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('events', function (Blueprint $table): void {
            $table->id();
            $table->boolean('status')->default(true);
            $table->string('lifecycle_status')->default('scheduled');
            $table->string('registration_mode')->default('ticketing');
            $table->timestamps();
        });
        Schema::create('event_contents', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->string('title');
            $table->timestamps();
        });
        Schema::create('bookings', function (Blueprint $table): void {
            $table->id();
            $table->string('booking_id')->unique();
            $table->unsignedBigInteger('event_id');
            $table->string('fname')->nullable();
            $table->string('lname')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('paymentStatus')->nullable();
            $table->timestamps();
        });
        Schema::create('booking_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('booking_id');
            $table->string('kind', 64);
            $table->string('channel', 24)->nullable();
            $table->char('recipient_hash', 64)->nullable();
            $table->string('provider_reference', 191)->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('dispatch_attempts')->default(0);
            $table->timestamp('dispatch_last_attempt_at')->nullable();
            $table->text('dispatch_last_error')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('processing_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('alerted_at')->nullable();
            $table->timestamps();
            $table->unique(['booking_id', 'kind']);
            $table->unique('provider_reference');
        });
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('actor_type', 32)->default('system');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action', 120);
            $table->string('subject_type', 160)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->json('metadata')->nullable();
            $table->string('request_id', 100)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }
}
