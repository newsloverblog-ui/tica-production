<?php

namespace Tests\Unit;

use App\Http\Controllers\FrontEnd\Event\BookingController;
use App\Jobs\FinalizePaidEventBooking;
use App\Jobs\FinalizePaidProductOrder;
use App\Jobs\SendCustomerEmailVerification;
use App\Jobs\SendOrganizerEmailVerification;
use App\Jobs\SendRejectedEventBookingNotification;
use App\Jobs\SendRejectedProductOrderNotification;
use App\Models\BookingDelivery;
use App\Models\BookingTicketDeliveryRequest;
use App\Models\ProductOrderDelivery;
use App\Models\EventGuestInvitation;
use App\Models\EventRegistrationNotification;
use App\Models\Event\Booking;
use App\Models\Event\BookingTicketTransfer;
use App\Models\OrganizerEmailVerificationDelivery;
use App\Models\CustomerEmailVerificationDelivery;
use App\Models\SubscriberCampaign;
use App\Models\SubscriberCampaignDelivery;
use App\Models\ApplicationMailDelivery;
use App\Models\PushNotificationCampaign;
use App\Models\PushNotificationCampaignDelivery;
use App\Services\PaidBookingFulfillmentService;
use App\Services\ManualPaymentDecisionMailService;
use App\Services\IncidentAlertService;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class FinalizePaidEventBookingTest extends TestCase
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

    public function test_dispatch_creates_durable_delivery_and_encrypted_job(): void
    {
        Queue::fake();
        $booking = $this->booking();

        $delivery = (new PaidBookingFulfillmentService())->dispatch($booking->id);

        $this->assertSame('pending', $delivery->status);
        $this->assertSame(1, $delivery->dispatch_attempts);
        $this->assertNotNull($delivery->dispatch_last_attempt_at);
        $this->assertDatabaseHas('booking_deliveries', [
            'booking_id' => $booking->id,
            'kind' => 'event_booking_confirmation',
        ]);
        Queue::assertPushed(FinalizePaidEventBooking::class, function ($job) use ($booking) {
            return $job->bookingId === $booking->id && $job instanceof ShouldBeEncrypted;
        });
    }

    public function test_fulfillment_dispatcher_recovers_committed_event_and_product_handoffs(): void
    {
        Queue::fake();
        config()->set('ticket_delivery.sms.enabled', true);
        $booking = $this->booking();
        $eventDelivery = BookingDelivery::create([
            'booking_id' => $booking->id,
            'kind' => 'event_booking_confirmation',
            'status' => 'pending',
            'dispatch_last_attempt_at' => now()->subMinutes(2),
        ]);
        $productDelivery = ProductOrderDelivery::create([
            'product_order_id' => 91,
            'kind' => 'product_order_confirmation',
            'status' => 'pending',
        ]);
        $smsDelivery = BookingDelivery::create([
            'booking_id' => $booking->id,
            'kind' => 'event_booking_sms',
            'status' => 'pending',
        ]);
        $bookingRejection = BookingDelivery::create([
            'booking_id' => 92,
            'kind' => 'event_booking_rejection',
            'status' => 'pending',
        ]);
        $productRejection = ProductOrderDelivery::create([
            'product_order_id' => 93,
            'kind' => 'product_order_rejection',
            'status' => 'pending',
        ]);

        $this->artisan('payments:dispatch-fulfillments')
            ->expectsOutputToContain('Fulfillment dispatch attempted: 5 | failed: 0 | alerted: 0')
            ->assertSuccessful();

        $this->assertSame(1, $eventDelivery->fresh()->dispatch_attempts);
        $this->assertSame(1, $smsDelivery->fresh()->dispatch_attempts);
        $this->assertSame(1, $productDelivery->fresh()->dispatch_attempts);
        $this->assertSame(1, $bookingRejection->fresh()->dispatch_attempts);
        $this->assertSame(1, $productRejection->fresh()->dispatch_attempts);
        Queue::assertPushed(FinalizePaidEventBooking::class, fn ($job): bool =>
            $job->bookingId === $booking->id
        );
        Queue::assertPushed(FinalizePaidProductOrder::class, fn ($job): bool =>
            $job->productOrderId === 91
        );
        Queue::assertPushed(\App\Jobs\SendEventBookingSms::class, fn ($job): bool =>
            $job->bookingId === $booking->id
        );
        Queue::assertPushed(SendRejectedEventBookingNotification::class, fn ($job): bool =>
            $job->bookingId === 92 && $job instanceof ShouldBeEncrypted
        );
        Queue::assertPushed(SendRejectedProductOrderNotification::class, fn ($job): bool =>
            $job->productOrderId === 93 && $job instanceof ShouldBeEncrypted
        );
    }

    public function test_email_verification_poller_recovers_both_account_handoffs(): void
    {
        Queue::fake();
        $organizer = OrganizerEmailVerificationDelivery::create([
            'public_id' => (string) \Illuminate\Support\Str::uuid(),
            'organizer_id' => 81,
            'email_hash' => str_repeat('a', 64),
            'status' => 'pending',
            'expires_at' => now()->addHour(),
        ]);
        $customer = CustomerEmailVerificationDelivery::create([
            'public_id' => (string) \Illuminate\Support\Str::uuid(),
            'customer_id' => 91,
            'email_hash' => str_repeat('b', 64),
            'status' => 'pending',
            'expires_at' => now()->addHour(),
        ]);

        $this->artisan('accounts:dispatch-verifications')
            ->expectsOutputToContain('Email verification dispatch attempted: 2')
            ->assertSuccessful();

        $this->assertSame(1, $organizer->fresh()->dispatch_attempts);
        $this->assertSame(1, $customer->fresh()->dispatch_attempts);
        Queue::assertPushed(SendOrganizerEmailVerification::class, fn ($job): bool =>
            $job->deliveryId === $organizer->id
        );
        Queue::assertPushed(SendCustomerEmailVerification::class, fn ($job): bool =>
            $job->deliveryId === $customer->id
        );
    }

    public function test_rejected_booking_notice_sends_once_and_records_delivery(): void
    {
        $booking = $this->booking('rejected');
        $mail = Mockery::mock(ManualPaymentDecisionMailService::class);
        $mail->shouldReceive('sendBookingRejection')->once()
            ->with(Mockery::type(Booking::class));

        $job = new SendRejectedEventBookingNotification($booking->id);
        $job->handle($mail);
        $job->handle($mail);

        $this->assertDatabaseHas('booking_deliveries', [
            'booking_id' => $booking->id,
            'kind' => 'event_booking_rejection',
            'status' => 'sent',
            'attempts' => 1,
        ]);
    }

    public function test_rejected_booking_notice_failure_is_retryable_and_recorded(): void
    {
        $booking = $this->booking('rejected');
        $mail = Mockery::mock(ManualPaymentDecisionMailService::class);
        $mail->shouldReceive('sendBookingRejection')->once()
            ->andThrow(new RuntimeException('SMTP unavailable'));

        try {
            (new SendRejectedEventBookingNotification($booking->id))->handle($mail);
            $this->fail('A rejected-booking mail failure must be retried by the queue.');
        } catch (RuntimeException $exception) {
            $this->assertSame('SMTP unavailable', $exception->getMessage());
        }

        $this->assertDatabaseHas('booking_deliveries', [
            'booking_id' => $booking->id,
            'kind' => 'event_booking_rejection',
            'status' => 'failed',
            'attempts' => 1,
        ]);
        $delivery = BookingDelivery::where('booking_id', $booking->id)
            ->where('kind', 'event_booking_rejection')
            ->firstOrFail();
        $this->assertSame('SMTP unavailable', $delivery->last_error);
        $this->assertNull($delivery->processing_at);
    }

    public function test_fulfilment_still_schedules_sms_when_email_queue_scheduling_fails(): void
    {
        $booking = $this->booking();
        $service = new class extends PaidBookingFulfillmentService {
            public bool $smsScheduled = false;

            protected function dispatchEmail(int $bookingId): void
            {
                throw new RuntimeException('email queue unavailable');
            }

            protected function dispatchSms(int $bookingId): void
            {
                $this->smsScheduled = true;
            }
        };

        try {
            $service->dispatch($booking->id);
            $this->fail('A partial scheduling failure must remain visible to the caller.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('email queue unavailable', $exception->getMessage());
        }

        $this->assertTrue($service->smsScheduled);
        $delivery = BookingDelivery::query()->where('booking_id', $booking->id)->firstOrFail();
        $this->assertSame('pending', $delivery->status);
        $this->assertSame(1, $delivery->dispatch_attempts);
        $this->assertSame(
            'Email queue scheduling failed: email queue unavailable',
            $delivery->dispatch_last_error
        );
    }

    public function test_fulfilment_still_schedules_email_when_sms_queue_scheduling_fails(): void
    {
        $booking = $this->booking();
        $service = new class extends PaidBookingFulfillmentService {
            public bool $emailScheduled = false;

            protected function dispatchEmail(int $bookingId): void
            {
                $this->emailScheduled = true;
            }

            protected function dispatchSms(int $bookingId): void
            {
                throw new RuntimeException('sms queue unavailable');
            }
        };

        try {
            $service->dispatch($booking->id);
            $this->fail('A partial scheduling failure must remain visible to the caller.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('sms queue unavailable', $exception->getMessage());
        }

        $this->assertTrue($service->emailScheduled);
        $this->assertDatabaseHas('booking_deliveries', [
            'booking_id' => $booking->id,
            'kind' => 'event_booking_confirmation',
            'status' => 'pending',
        ]);
    }

    public function test_job_generates_invoice_sends_once_and_records_delivery(): void
    {
        $booking = $this->booking();
        $controller = Mockery::mock(BookingController::class);
        $controller->shouldReceive('generateInvoice')->once()->andReturn('booking-81.pdf');
        $controller->shouldReceive('sendMail')->once()->with(Mockery::type(Booking::class), true)->andReturnTrue();

        $job = new FinalizePaidEventBooking($booking->id);
        $job->handle($controller);
        $job->handle($controller);

        $this->assertSame('booking-81.pdf', $booking->fresh()->invoice);
        $delivery = BookingDelivery::where('booking_id', $booking->id)->firstOrFail();
        $this->assertSame('sent', $delivery->status);
        $this->assertSame(1, (int) $delivery->attempts);
        $this->assertNotNull($delivery->sent_at);
        $this->assertDatabaseHas('funnel_analytics_events', [
            'event_name' => 'ticket_delivered',
            'booking_id' => $booking->id,
            'event_id' => $booking->event_id,
            'source' => 'server',
        ]);
        $this->assertSame(1, DB::table('funnel_analytics_events')->where('event_name', 'ticket_delivered')->count());
    }

    public function test_free_booking_uses_the_same_durable_idempotent_delivery_boundary(): void
    {
        $booking = $this->booking('free');
        $controller = Mockery::mock(BookingController::class);
        $controller->shouldReceive('generateInvoice')->once()->andReturn('free-booking-81.pdf');
        $controller->shouldReceive('sendMail')->once()->with(Mockery::type(Booking::class), true)->andReturnTrue();

        $job = new FinalizePaidEventBooking($booking->id);
        $job->handle($controller);
        $job->handle($controller);

        $this->assertSame('sent', BookingDelivery::where('booking_id', $booking->id)->value('status'));
        $this->assertSame('free-booking-81.pdf', $booking->fresh()->invoice);
    }

    public function test_mail_failure_is_recorded_and_rethrown_for_queue_retry(): void
    {
        $booking = $this->booking();
        $controller = Mockery::mock(BookingController::class);
        $controller->shouldReceive('generateInvoice')->once()->andReturn('booking-81.pdf');
        $controller->shouldReceive('sendMail')->once()->andThrow(new RuntimeException('SMTP unavailable'));

        try {
            (new FinalizePaidEventBooking($booking->id))->handle($controller);
            $this->fail('Delivery failure must be retried by the queue.');
        } catch (RuntimeException $exception) {
            $this->assertSame('SMTP unavailable', $exception->getMessage());
        }

        $this->assertDatabaseHas('booking_deliveries', [
            'booking_id' => $booking->id,
            'status' => 'failed',
            'attempts' => 1,
        ]);
        $this->assertSame(
            'SMTP unavailable',
            BookingDelivery::where('booking_id', $booking->id)->firstOrFail()->last_error
        );
    }

    public function test_monitor_marks_and_alerts_a_stuck_delivery_once(): void
    {
        $booking = $this->booking();
        $delivery = BookingDelivery::create([
            'booking_id' => $booking->id,
            'kind' => 'event_booking_confirmation',
            'status' => 'processing',
            'attempts' => 2,
            'processing_at' => now()->subMinutes(31),
        ]);
        Log::spy();

        $this->artisan('bookings:monitor-deliveries')->assertSuccessful();

        $delivery->refresh();
        $this->assertSame('failed', $delivery->status);
        $this->assertNotNull($delivery->alerted_at);
        Log::shouldHaveReceived('critical')->once();
    }

    public function test_monitor_drains_a_delivery_family_in_bounded_batches(): void
    {
        foreach (range(1, 3) as $index) {
            $booking = $this->booking();
            BookingDelivery::create([
                'booking_id' => $booking->id,
                'kind' => 'event_booking_confirmation',
                'status' => 'processing',
                'attempts' => 2,
                'processing_at' => now()->subMinutes(31),
            ]);
        }
        Log::spy();

        $this->artisan('bookings:monitor-deliveries', ['--batch-size' => 2])
            ->expectsOutputToContain('Booking stuck: 2')
            ->assertSuccessful();

        $this->assertSame(2, BookingDelivery::where('status', 'failed')->count());
        $this->assertSame(1, BookingDelivery::where('status', 'processing')->count());

        $this->artisan('bookings:monitor-deliveries', ['--batch-size' => 2])
            ->expectsOutputToContain('Booking stuck: 1')
            ->assertSuccessful();

        $this->assertSame(3, BookingDelivery::where('status', 'failed')->count());
        $this->assertSame(0, BookingDelivery::where('status', 'processing')->count());
        Log::shouldHaveReceived('critical')->times(3);
    }

    public function test_monitor_leaves_recoverable_pending_handoffs_for_their_dispatchers(): void
    {
        $booking = $this->booking();
        $delivery = BookingDelivery::create([
            'booking_id' => $booking->id,
            'kind' => 'event_booking_confirmation',
            'status' => 'pending',
        ]);
        $delivery->forceFill(['updated_at' => now()->subMinutes(31)])->save();
        $mail = ApplicationMailDelivery::create([
            'public_id' => (string) \Illuminate\Support\Str::uuid(),
            'kind' => 'password_reset',
            'recipient_hash' => hash('sha256', 'buyer@example.com'),
            'recipient_email' => 'buyer@example.com',
            'subject' => 'Reset access',
            'body' => '<p>Private link</p>',
            'attachments' => [],
            'status' => 'pending',
        ]);
        $mail->forceFill(['updated_at' => now()->subMinutes(31)])->save();
        Log::spy();

        $this->artisan('bookings:monitor-deliveries')
            ->expectsOutputToContain('Pending handoff: 0')
            ->assertSuccessful();
        $this->artisan('bookings:monitor-deliveries')->assertSuccessful();

        $delivery->refresh();
        $mail->refresh();
        $this->assertSame('pending', $delivery->status);
        $this->assertSame('pending', $mail->status);
        $this->assertNull($delivery->alerted_at);
        $this->assertNull($mail->alerted_at);
        Log::shouldNotHaveReceived('critical');
    }

    public function test_monitor_fails_on_exhausted_delivery_without_repeating_alert(): void
    {
        $booking = $this->booking();
        $delivery = BookingDelivery::create([
            'booking_id' => $booking->id,
            'kind' => 'event_booking_confirmation',
            'status' => 'failed',
            'attempts' => 5,
            'last_error' => 'SMTP unavailable',
        ]);
        $alerts = Mockery::mock(IncidentAlertService::class);
        $alerts->shouldReceive('alert')
            ->once()
            ->with(
                'booking-delivery-exhausted:'.$delivery->id,
                'Booking confirmation delivery exhausted retries.',
                Mockery::on(function (array $context) use ($delivery, $booking): bool {
                    return $context === [
                        'delivery_id' => $delivery->id,
                        'booking_id' => $booking->id,
                        'attempts' => 5,
                        'failure_recorded' => true,
                    ];
                }),
                'critical'
            )
            ->andReturn([]);
        $this->app->instance(IncidentAlertService::class, $alerts);

        $this->artisan('bookings:monitor-deliveries')->assertFailed();
        $this->artisan('bookings:monitor-deliveries')->assertSuccessful();

        $this->assertNotNull($delivery->fresh()->alerted_at);
        $source = file_get_contents(app_path('Console/Commands/MonitorBookingDeliveries.php'));
        $this->assertIsString($source);
        $this->assertStringNotContainsString("'last_error' => $", $source);
        $this->assertSame(14, substr_count($source, "'failure_recorded' =>"));
    }

    public function test_monitor_marks_and_alerts_a_stuck_ticket_resend_once(): void
    {
        $booking = $this->booking();
        $delivery = BookingTicketDeliveryRequest::create([
            'public_id' => (string) \Illuminate\Support\Str::uuid(),
            'booking_id' => $booking->id,
            'requested_by_type' => 'customer',
            'requested_by_id' => 12,
            'recipient_email_hash' => hash('sha256', 'ada@example.com'),
            'status' => 'processing',
            'attempts' => 2,
            'processing_at' => now()->subMinutes(31),
        ]);
        Log::spy();

        $this->artisan('bookings:monitor-deliveries')->assertSuccessful();
        $this->artisan('bookings:monitor-deliveries')->assertSuccessful();

        $this->assertSame('failed', $delivery->fresh()->status);
        $this->assertNotNull($delivery->fresh()->alerted_at);
        Log::shouldHaveReceived('critical')->once();
    }

    public function test_monitor_marks_and_alerts_stuck_ticket_transfer_invitation_once(): void
    {
        $transfer = BookingTicketTransfer::create([
            'booking_ticket_id' => 45,
            'from_customer_id' => 12,
            'recipient_name' => 'Chidi Okafor',
            'recipient_email' => 'chidi@example.com',
            'token_hash' => hash('sha256', 'token'),
            'status' => 'pending',
            'delivery_status' => 'processing',
            'delivery_attempts' => 2,
            'processing_at' => now()->subMinutes(31),
            'expires_at' => now()->addDay(),
        ]);
        Log::spy();

        $this->artisan('bookings:monitor-deliveries')->assertSuccessful();
        $this->artisan('bookings:monitor-deliveries')->assertSuccessful();

        $this->assertSame('failed', $transfer->fresh()->delivery_status);
        $this->assertNotNull($transfer->fresh()->alerted_at);
        Log::shouldHaveReceived('critical')->once();
    }

    public function test_monitor_marks_and_alerts_stuck_organizer_verification_once(): void
    {
        $delivery = OrganizerEmailVerificationDelivery::create([
            'public_id' => (string) \Illuminate\Support\Str::uuid(),
            'organizer_id' => 7,
            'email_hash' => hash('sha256', 'organizer@example.com'),
            'status' => 'processing',
            'attempts' => 2,
            'processing_at' => now()->subMinutes(31),
            'expires_at' => now()->addHour(),
        ]);
        Log::spy();

        $this->artisan('bookings:monitor-deliveries')->assertSuccessful();
        $this->artisan('bookings:monitor-deliveries')->assertSuccessful();

        $this->assertSame('failed', $delivery->fresh()->status);
        $this->assertNotNull($delivery->fresh()->alerted_at);
        Log::shouldHaveReceived('critical')->once();
    }

    public function test_monitor_marks_and_alerts_stuck_customer_verification_once(): void
    {
        $delivery = CustomerEmailVerificationDelivery::create([
            'public_id' => (string) \Illuminate\Support\Str::uuid(),
            'customer_id' => 11,
            'email_hash' => hash('sha256', 'customer@example.com'),
            'status' => 'processing',
            'attempts' => 2,
            'processing_at' => now()->subMinutes(31),
            'expires_at' => now()->addHour(),
        ]);
        Log::spy();

        $this->artisan('bookings:monitor-deliveries')->assertSuccessful();
        $this->artisan('bookings:monitor-deliveries')->assertSuccessful();

        $this->assertSame('failed', $delivery->fresh()->status);
        $this->assertNotNull($delivery->fresh()->alerted_at);
        Log::shouldHaveReceived('critical')->once();
    }

    public function test_monitor_marks_and_alerts_stuck_subscriber_campaign_delivery_once(): void
    {
        $campaign = SubscriberCampaign::create([
            'public_id' => (string) \Illuminate\Support\Str::uuid(),
            'subject' => 'New events',
            'body' => '<p>Ready</p>',
            'status' => 'processing',
            'total_count' => 1,
            'started_at' => now()->subHour(),
        ]);
        $delivery = SubscriberCampaignDelivery::create([
            'campaign_id' => $campaign->id,
            'recipient_hash' => hash('sha256', 'buyer@example.com'),
            'recipient_email' => 'buyer@example.com',
            'status' => 'processing',
            'attempts' => 2,
            'processing_at' => now()->subMinutes(31),
        ]);
        Log::spy();

        $this->artisan('bookings:monitor-deliveries')->assertSuccessful();
        $this->artisan('bookings:monitor-deliveries')->assertSuccessful();

        $this->assertSame('failed', $delivery->fresh()->status);
        $this->assertNotNull($delivery->fresh()->alerted_at);
        $this->assertSame('partial_failed', $campaign->fresh()->status);
        Log::shouldHaveReceived('critical')->once();
    }

    public function test_monitor_marks_and_alerts_stuck_push_campaign_delivery_once(): void
    {
        $campaign = PushNotificationCampaign::create([
            'public_id' => (string) \Illuminate\Support\Str::uuid(),
            'title' => 'Tonight nearby',
            'message' => 'Doors open at 7.',
            'button_name' => 'View event',
            'button_url' => 'https://tica.test/events/1',
            'status' => 'processing',
            'total_count' => 1,
            'started_at' => now()->subHour(),
        ]);
        $delivery = PushNotificationCampaignDelivery::create([
            'campaign_id' => $campaign->id,
            'guest_id' => 42,
            'status' => 'processing',
            'attempts' => 2,
            'processing_at' => now()->subMinutes(31),
        ]);
        Log::spy();

        $this->artisan('bookings:monitor-deliveries')->assertSuccessful();
        $this->artisan('bookings:monitor-deliveries')->assertSuccessful();

        $this->assertSame('failed', $delivery->fresh()->status);
        $this->assertNotNull($delivery->fresh()->alerted_at);
        $this->assertSame('partial_failed', $campaign->fresh()->status);
        Log::shouldHaveReceived('critical')->once();
    }

    public function test_monitor_marks_and_alerts_stuck_application_mail_once(): void
    {
        $delivery = ApplicationMailDelivery::create([
            'public_id' => (string) \Illuminate\Support\Str::uuid(),
            'kind' => 'password_reset',
            'recipient_hash' => hash('sha256', 'buyer@example.com'),
            'recipient_email' => 'buyer@example.com',
            'subject' => 'Reset access',
            'body' => '<p>Private link</p>',
            'attachments' => [],
            'status' => 'processing',
            'attempts' => 2,
            'processing_at' => now()->subMinutes(31),
        ]);
        Log::spy();

        $this->artisan('bookings:monitor-deliveries')->assertSuccessful();
        $this->artisan('bookings:monitor-deliveries')->assertSuccessful();

        $this->assertSame('failed', $delivery->fresh()->status);
        $this->assertNotNull($delivery->fresh()->alerted_at);
        Log::shouldHaveReceived('critical')->once();
    }

    public function test_monitor_marks_and_alerts_stuck_guest_invitation_once(): void
    {
        $invitation = EventGuestInvitation::create([
            'public_id' => (string) \Illuminate\Support\Str::ulid(),
            'event_id' => 88,
            'organizer_id' => 7,
            'name' => 'Ada Guest',
            'email' => 'ada@example.com',
            'email_hash' => hash('sha256', 'ada@example.com'),
            'party_size' => 1,
            'status' => 'pending',
            'delivery_status' => 'processing',
            'token_hash' => hash('sha256', 'token'),
            'source_attestation_version' => 'test-v1',
            'source_attested_at' => now(),
            'expires_at' => now()->addDay(),
            'delivery_attempts' => 2,
            'processing_at' => now()->subMinutes(31),
        ]);
        Log::spy();

        $this->artisan('bookings:monitor-deliveries')->assertSuccessful();
        $this->artisan('bookings:monitor-deliveries')->assertSuccessful();

        $this->assertSame('failed', $invitation->fresh()->delivery_status);
        $this->assertNotNull($invitation->fresh()->alerted_at);
        Log::shouldHaveReceived('critical')->once();
    }

    public function test_monitor_marks_and_alerts_stuck_registration_notice_once(): void
    {
        $notification = EventRegistrationNotification::create([
            'submission_id' => 55,
            'event_id' => 88,
            'kind' => 'approved',
            'status' => 'processing',
            'attempts' => 2,
            'processing_at' => now()->subMinutes(31),
        ]);
        Log::spy();

        $this->artisan('bookings:monitor-deliveries')->assertSuccessful();
        $this->artisan('bookings:monitor-deliveries')->assertSuccessful();

        $this->assertSame('failed', $notification->fresh()->status);
        $this->assertNotNull($notification->fresh()->alerted_at);
        Log::shouldHaveReceived('critical')->once();
    }

    private function booking(string $paymentStatus = 'completed'): Booking
    {
        return Booking::create([
            'booking_id' => 'booking-81',
            'event_id' => 9,
            'fname' => 'Ada',
            'lname' => 'Okafor',
            'email' => 'ada@example.com',
            'price' => 25000,
            'tax' => 250,
            'commission' => 1500,
            'quantity' => 1,
            'discount' => 0,
            'early_bird_discount' => 0,
            'paymentMethod' => 'Paystack',
            'gatewayType' => 'online',
            'paymentStatus' => $paymentStatus,
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id(); $table->string('booking_id'); $table->unsignedBigInteger('event_id');
            $table->string('fname')->nullable(); $table->string('lname')->nullable(); $table->string('email')->nullable();
            $table->decimal('price', 18, 2)->default(0); $table->decimal('tax', 18, 2)->default(0);
            $table->decimal('commission', 18, 2)->default(0); $table->integer('quantity')->default(1);
            $table->decimal('discount', 18, 2)->default(0); $table->decimal('early_bird_discount', 18, 2)->default(0);
            $table->string('paymentMethod')->nullable(); $table->string('gatewayType')->nullable();
            $table->string('paymentStatus')->nullable(); $table->string('invoice')->nullable(); $table->timestamps();
        });
        Schema::create('booking_deliveries', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('booking_id'); $table->string('kind', 64);
            $table->string('status', 20)->default('pending'); $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('dispatch_attempts')->default(0);
            $table->timestamp('dispatch_last_attempt_at')->nullable(); $table->text('dispatch_last_error')->nullable();
            $table->text('last_error')->nullable(); $table->timestamp('processing_at')->nullable();
            $table->timestamp('sent_at')->nullable(); $table->timestamps();
            $table->timestamp('alerted_at')->nullable();
            $table->unique(['booking_id', 'kind']);
        });
        Schema::create('booking_ticket_delivery_requests', function (Blueprint $table) {
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
        Schema::create('booking_ticket_transfers', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('booking_ticket_id'); $table->unsignedBigInteger('from_customer_id');
            $table->string('recipient_name'); $table->string('recipient_email'); $table->char('token_hash', 64)->unique();
            $table->string('status')->default('pending'); $table->string('delivery_status')->default('pending');
            $table->unsignedSmallInteger('delivery_attempts')->default(0); $table->text('last_error')->nullable();
            $table->timestamp('processing_at')->nullable(); $table->timestamp('sent_at')->nullable();
            $table->timestamp('alerted_at')->nullable(); $table->timestamp('expires_at');
            $table->unsignedBigInteger('accepted_by_customer_id')->nullable(); $table->timestamp('accepted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable(); $table->timestamps();
        });
        Schema::create('organizer_email_verification_deliveries', function (Blueprint $table) {
            $table->id(); $table->uuid('public_id')->unique(); $table->unsignedBigInteger('organizer_id');
            $table->char('email_hash', 64); $table->string('status')->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0); $table->text('last_error')->nullable();
            $table->unsignedSmallInteger('dispatch_attempts')->default(0);
            $table->timestamp('dispatch_last_attempt_at')->nullable(); $table->text('dispatch_last_error')->nullable();
            $table->timestamp('processing_at')->nullable(); $table->timestamp('sent_at')->nullable();
            $table->timestamp('verified_at')->nullable(); $table->timestamp('expires_at');
            $table->timestamp('alerted_at')->nullable(); $table->timestamps();
        });
        Schema::create('customer_email_verification_deliveries', function (Blueprint $table) {
            $table->id(); $table->uuid('public_id')->unique(); $table->unsignedBigInteger('customer_id');
            $table->char('email_hash', 64); $table->string('status')->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0); $table->text('last_error')->nullable();
            $table->unsignedSmallInteger('dispatch_attempts')->default(0);
            $table->timestamp('dispatch_last_attempt_at')->nullable(); $table->text('dispatch_last_error')->nullable();
            $table->timestamp('processing_at')->nullable(); $table->timestamp('sent_at')->nullable();
            $table->timestamp('verified_at')->nullable(); $table->timestamp('expires_at');
            $table->timestamp('alerted_at')->nullable(); $table->timestamps();
        });
        $subscriberCampaignMigration = require database_path(
            'migrations/2026_11_03_140000_create_subscriber_campaign_tables.php'
        );
        $subscriberCampaignMigration->up();
        $applicationMailMigration = require database_path(
            'migrations/2026_11_03_150000_create_application_mail_deliveries_table.php'
        );
        $applicationMailMigration->up();
        $applicationMailDispatchMigration = require database_path(
            'migrations/2026_11_05_160000_add_application_mail_dispatch_metadata.php'
        );
        $applicationMailDispatchMigration->up();
        $pushCampaignMigration = require database_path(
            'migrations/2026_11_03_160000_create_push_notification_campaign_tables.php'
        );
        $pushCampaignMigration->up();
        Schema::create('product_order_deliveries', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('product_order_id'); $table->string('kind', 64);
            $table->string('status', 20)->default('pending'); $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('dispatch_attempts')->default(0);
            $table->timestamp('dispatch_last_attempt_at')->nullable(); $table->text('dispatch_last_error')->nullable();
            $table->text('last_error')->nullable(); $table->timestamp('processing_at')->nullable();
            $table->timestamp('sent_at')->nullable(); $table->timestamp('alerted_at')->nullable(); $table->timestamps();
            $table->unique(['product_order_id', 'kind']);
        });
        Schema::create('event_guest_invitations', function (Blueprint $table) {
            $table->id(); $table->string('public_id', 26)->unique();
            $table->unsignedBigInteger('event_id'); $table->unsignedBigInteger('organizer_id');
            $table->text('name'); $table->text('email'); $table->char('email_hash', 64);
            $table->unsignedSmallInteger('party_size')->default(1); $table->string('status', 24);
            $table->string('delivery_status', 24); $table->char('token_hash', 64);
            $table->text('dispatch_token')->nullable(); $table->unsignedSmallInteger('dispatch_attempts')->default(0);
            $table->timestamp('dispatch_last_attempt_at')->nullable(); $table->text('dispatch_last_error')->nullable();
            $table->string('source_attestation_version', 32); $table->timestamp('source_attested_at');
            $table->string('source_attested_ip_hash', 64)->nullable(); $table->timestamp('invited_at')->nullable();
            $table->timestamp('expires_at'); $table->timestamp('responded_at')->nullable();
            $table->timestamp('rsvp_consent_at')->nullable(); $table->timestamp('marketing_consent_at')->nullable();
            $table->timestamp('cancelled_at')->nullable(); $table->timestamp('checked_in_at')->nullable();
            $table->unsignedBigInteger('checked_in_by_organizer_id')->nullable();
            $table->unsignedSmallInteger('delivery_attempts')->default(0); $table->timestamp('processing_at')->nullable();
            $table->timestamp('alerted_at')->nullable(); $table->text('last_error')->nullable(); $table->timestamps();
        });
        Schema::create('event_registration_notifications', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('submission_id'); $table->unsignedBigInteger('event_id');
            $table->string('kind'); $table->string('status')->default('pending'); $table->integer('attempts')->default(0);
            $table->unsignedSmallInteger('dispatch_attempts')->default(0);
            $table->timestamp('dispatch_last_attempt_at')->nullable(); $table->text('dispatch_last_error')->nullable();
            $table->timestamp('processing_at')->nullable(); $table->timestamp('sent_at')->nullable();
            $table->timestamp('alerted_at')->nullable(); $table->text('last_error')->nullable(); $table->timestamps();
            $table->unique(['submission_id', 'kind']);
        });
        $this->createAnalyticsSchema();
    }

    private function createAnalyticsSchema(): void
    {
        Schema::create('funnel_analytics_events', function (Blueprint $table) {
            $table->id(); $table->uuid('public_id')->unique(); $table->string('event_name', 64);
            $table->string('source', 16); $table->unsignedBigInteger('event_id')->nullable();
            $table->unsignedBigInteger('booking_id')->nullable(); $table->unsignedBigInteger('payment_intent_id')->nullable();
            $table->unsignedBigInteger('organizer_id')->nullable(); $table->unsignedBigInteger('customer_id')->nullable();
            $table->char('session_hash', 64)->nullable(); $table->string('dedupe_key', 191)->nullable()->unique();
            $table->longText('properties')->nullable(); $table->timestamp('occurred_at'); $table->timestamps();
        });
    }
}
