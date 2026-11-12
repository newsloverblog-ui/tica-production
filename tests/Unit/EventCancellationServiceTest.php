<?php

namespace Tests\Unit;

use App\Jobs\SendEventCancellationNotice;
use App\Jobs\SendEventRegistrationNotification;
use App\Mail\EventCancellationNoticeMail;
use App\Models\Event;
use App\Models\Event\Booking;
use App\Models\Event\Ticket;
use App\Models\Event\EventContent;
use App\Models\EventCancellationNotification;
use App\Models\EventCancellationCase;
use App\Models\EventCancellationRequest;
use App\Models\Earning;
use App\Models\Organizer;
use App\Models\PaymentIntent;
use App\Models\PaymentRefund;
use App\Models\PaymentRefundDispute;
use App\Models\TicketReservation;
use App\Models\Transaction;
use App\Services\EventCancellationService;
use App\Services\EventCancellationCaseService;
use App\Services\EventCancellationNotificationDispatchService;
use App\Services\PaymentRefundDisputeService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class EventCancellationServiceTest extends TestCase
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
        $this->schema();
        Queue::fake();
    }

    public function test_organizer_request_closes_sales_and_releases_active_holds(): void
    {
        [$event, $ticket] = $this->event();
        TicketReservation::create([
            'token' => 'hold-1', 'event_id' => $event->id, 'ticket_id' => $ticket->id,
            'quantity' => 2, 'status' => 'active', 'expires_at' => now()->addMinutes(10),
        ]);
        $ticket->update(['ticket_available' => 3]);

        $request = (new EventCancellationService())->requestByOrganizer(
            $event->id,
            7,
            'The venue unexpectedly became unavailable.',
            'We are sorry, but the event cannot proceed.'
        );

        $this->assertSame('approval_pending', $request->status);
        $this->assertSame('cancellation_pending', $event->fresh()->lifecycle_status);
        $this->assertSame(0, (int) $event->fresh()->status);
        $this->assertNotNull($event->fresh()->sales_closed_at);
        $this->assertSame('event_cancellation_pending', TicketReservation::first()->status);
        $this->assertSame(5, (int) $ticket->fresh()->ticket_available);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Ticket sales are closed');
        app(\App\Services\TicketReservationService::class)->reserve(
            $event->id,
            [['ticket_id' => $ticket->id, 'qty' => 1]],
            null,
            null
        );
    }

    public function test_rejection_restores_the_previous_visibility_and_sales_state(): void
    {
        [$event] = $this->event();
        $request = (new EventCancellationService())->requestByOrganizer(
            $event->id,
            7,
            'The organizer requested a cancellation review.',
        );

        $rejected = (new EventCancellationService())->reject(
            $request->id,
            19,
            'The venue has confirmed that the event can proceed.'
        );

        $this->assertSame('rejected', $rejected->status);
        $this->assertSame('scheduled', $event->fresh()->lifecycle_status);
        $this->assertSame(1, (int) $event->fresh()->status);
        $this->assertNull($event->fresh()->sales_closed_at);
    }

    public function test_approval_cancels_event_queues_verified_refunds_and_isolates_manual_reviews(): void
    {
        [$event] = $this->event();
        DB::table('event_registration_submissions')->insert([
            [
                'public_id' => 'registration-submitted', 'form_id' => 1, 'event_id' => $event->id,
                'organizer_id' => 7, 'form_version' => 1, 'status' => 'submitted',
                'primary_name' => 'Encrypted name', 'primary_email' => 'Encrypted email',
                'primary_email_hash' => hash('sha256', 'registered@example.com'), 'attendee_count' => 1,
                'consent_version' => '2026-11', 'consented_at' => now(), 'submitted_at' => now(),
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'public_id' => 'registration-rejected', 'form_id' => 1, 'event_id' => $event->id,
                'organizer_id' => 7, 'form_version' => 1, 'status' => 'rejected',
                'primary_name' => 'Encrypted name', 'primary_email' => 'Encrypted email',
                'primary_email_hash' => hash('sha256', 'rejected@example.com'), 'attendee_count' => 1,
                'consent_version' => '2026-11', 'consented_at' => now(), 'submitted_at' => now(),
                'created_at' => now(), 'updated_at' => now(),
            ],
        ]);
        [$intent, $eligibleBooking] = $this->paidBooking($event, 'paystack', 'verified-ref');
        $manualBooking = Booking::create([
            'booking_id' => 'legacy-booking', 'event_id' => $event->id, 'organizer_id' => 7,
            'price' => 45, 'tax' => 5, 'paymentStatus' => 'completed', 'scan_status' => 0,
            'refunded_amount' => 0, 'email' => 'legacy@example.com',
        ]);
        $request = (new EventCancellationService())->requestByOrganizer(
            $event->id,
            7,
            'The event cannot take place because the venue closed.',
            'This event has been cancelled. We apologize for the disruption.'
        );

        $approved = (new EventCancellationService())->approve($request->id, 19);
        $refund = PaymentRefund::first();

        $this->assertSame('approved', $approved->status);
        $this->assertSame('cancelled', $event->fresh()->lifecycle_status);
        $this->assertNotNull($event->fresh()->cancelled_at);
        $this->assertSame('requested', $refund->status);
        $this->assertSame($intent->id, (int) $refund->payment_intent_id);
        $this->assertSame($approved->id, (int) $refund->event_cancellation_request_id);
        $this->assertSame(100.0, (float) $refund->amount);
        $this->assertSame(1, (int) $approved->queued_refund_count);
        $this->assertSame(1, (int) $approved->manual_review_count);
        $this->assertSame(50.0, (float) $approved->manual_review_amount);
        $this->assertDatabaseHas('event_cancellation_cases', [
            'event_cancellation_request_id' => $approved->id,
            'booking_id' => $eligibleBooking->id,
            'status' => 'refund_queued',
        ]);
        $this->assertDatabaseHas('event_cancellation_cases', [
            'event_cancellation_request_id' => $approved->id,
            'booking_id' => $manualBooking->id,
            'status' => 'manual_review',
        ]);
        $this->assertDatabaseHas('event_cancellation_notifications', [
            'event_cancellation_request_id' => $approved->id,
            'booking_id' => $eligibleBooking->id,
        ]);
        $this->assertDatabaseHas('event_cancellation_notifications', [
            'event_cancellation_request_id' => $approved->id,
            'booking_id' => $manualBooking->id,
        ]);
        $this->assertDatabaseHas('event_registration_submissions', [
            'public_id' => 'registration-submitted',
            'status' => 'cancelled',
        ]);
        $this->assertDatabaseHas('event_registration_submissions', [
            'public_id' => 'registration-rejected',
            'status' => 'rejected',
        ]);
        $this->assertDatabaseHas('event_registration_notifications', [
            'submission_id' => DB::table('event_registration_submissions')->where('public_id', 'registration-submitted')->value('id'),
            'kind' => 'cancelled',
            'status' => 'pending',
        ]);
        Queue::assertPushed(SendEventCancellationNotice::class, 2);
        Queue::assertPushed(SendEventRegistrationNotification::class, 1);
    }

    public function test_cross_organizer_cancellation_is_denied(): void
    {
        [$event] = $this->event();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('does not own this event');
        (new EventCancellationService())->requestByOrganizer(
            $event->id,
            8,
            'Attempt to cancel another organizer event.'
        );
    }

    public function test_payment_completed_after_approval_is_added_to_the_same_refund_ledger(): void
    {
        [$event] = $this->event();
        $service = new EventCancellationService();
        $request = $service->requestByOrganizer(
            $event->id,
            7,
            'The event was cancelled before the pending payment completed.'
        );
        $approved = $service->approve($request->id, 19);
        [$intent, $booking] = $this->paidBooking($event, 'paystack', 'late-payment-ref');

        $updated = $service->queueLateCompletedBooking($booking->id);

        $this->assertSame($approved->id, $updated->id);
        $this->assertDatabaseHas('payment_refunds', [
            'payment_intent_id' => $intent->id,
            'event_cancellation_request_id' => $approved->id,
            'status' => 'requested',
        ]);
        $this->assertDatabaseHas('event_cancellation_cases', [
            'event_cancellation_request_id' => $approved->id,
            'booking_id' => $booking->id,
            'status' => 'refund_queued',
        ]);
        $this->assertSame(1, (int) $updated->queued_refund_count);
        $this->assertSame(0, (int) $updated->manual_review_count);
        Queue::assertPushed(SendEventCancellationNotice::class);
    }

    public function test_checked_in_cancellation_case_flows_through_dispute_to_one_provider_refund(): void
    {
        [$event] = $this->event();
        [$intent, $booking] = $this->paidBooking($event, 'paystack', 'checked-in-cancel-ref');
        DB::table('booking_tickets')->insert([
            'public_id' => 'cancel-ticket-1', 'booking_id' => $booking->id,
            'event_id' => $event->id, 'sequence' => 1, 'status' => 'checked_in',
            'checked_in_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $cancellations = new EventCancellationService();
        $request = $cancellations->requestByOrganizer(
            $event->id,
            7,
            'The venue closed after entry because of an emergency.'
        );
        $approved = $cancellations->approve($request->id, 19);
        $case = EventCancellationCase::where('booking_id', $booking->id)->firstOrFail();

        $escalated = app(EventCancellationCaseService::class)->openCheckedInDispute(
            $case->id,
            19,
            'event_service_failure',
            'Scanner entry is valid, but the venue evacuation prevented delivery of the event.'
        );
        $this->assertSame('dispute_open', $escalated->status);
        $dispute = PaymentRefundDispute::findOrFail($escalated->payment_refund_dispute_id);

        $resolved = app(PaymentRefundDisputeService::class)->approveAndQueue(
            $dispute->id,
            19,
            'Emergency closure evidence and the scanner record were independently reviewed.'
        );

        $this->assertSame(PaymentRefundDispute::STATUS_REFUND_QUEUED, $resolved->status);
        $this->assertSame('refund_queued', $case->fresh()->status);
        $this->assertSame($resolved->payment_refund_id, $case->fresh()->payment_refund_id);
        $this->assertDatabaseCount('payment_refunds', 1);
        $this->assertSame(0.0, (float) $booking->fresh()->refunded_amount);
        $this->assertSame(0, (int) $approved->fresh()->manual_review_count);
        $this->assertSame(1, (int) $approved->fresh()->queued_refund_count);
        $this->assertSame($intent->id, (int) PaymentRefund::first()->payment_intent_id);
    }

    public function test_legacy_cancellation_case_records_external_refund_once_and_reconciles_finance(): void
    {
        Mail::fake();
        [$event] = $this->event();
        $organizer = new Organizer();
        $organizer->forceFill(['id' => 7, 'username' => 'organizer', 'amount' => 90])->save();
        Earning::create(['total_revenue' => 100, 'total_earning' => 10]);
        $booking = Booking::create([
            'booking_id' => 'legacy-external-refund', 'event_id' => $event->id, 'organizer_id' => 7,
            'price' => 90, 'tax' => 10, 'commission' => 0, 'paymentStatus' => 'completed',
            'scan_status' => 0, 'refunded_amount' => 0, 'email' => 'legacy@example.com',
        ]);
        $service = new EventCancellationService();
        $request = $service->requestByOrganizer(
            $event->id,
            7,
            'The event cannot proceed and this legacy payment needs reconciliation.'
        );
        $approved = $service->approve($request->id, 19);
        $case = EventCancellationCase::where('booking_id', $booking->id)->firstOrFail();

        $resolved = app(EventCancellationCaseService::class)->recordExternalRefund(
            $case->id,
            19,
            'BANK-REF-20261103-001',
            'The bank refund receipt and recipient credit confirmation were independently verified.'
        );

        $this->assertSame('external_refund_recorded', $resolved->status);
        $this->assertSame('BANK-REF-20261103-001', $resolved->external_refund_reference);
        $this->assertSame('refunded', $booking->fresh()->refund_status);
        $this->assertSame(100.0, (float) $booking->fresh()->refunded_amount);
        $this->assertSame(0.0, (float) Organizer::find(7)->amount);
        $this->assertSame(0.0, (float) Earning::first()->total_revenue);
        $this->assertDatabaseHas('transactions', [
            'gateway' => 'external_refund',
            'gateway_reference' => 'BANK-REF-20261103-001',
            'transcation_type' => 6,
        ]);
        $this->assertSame(0, (int) $approved->fresh()->manual_review_count);

        $notification = EventCancellationNotification::where('booking_id', $booking->id)->firstOrFail();
        (new SendEventCancellationNotice($notification->id))->handle();
        Mail::assertSent(EventCancellationNoticeMail::class, function ($mail): bool {
            return str_contains($mail->refundMessage, 'verified')
                && str_contains($mail->refundMessage, '100.00');
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('unresolved manual-review');
        app(EventCancellationCaseService::class)->recordExternalRefund(
            $case->id,
            19,
            'BANK-REF-20261103-002',
            'A repeated reconciliation must not reverse the same booking twice.'
        );
    }

    public function test_manual_case_can_retry_only_after_controlled_gateway_identity_is_repaired(): void
    {
        [$event] = $this->event();
        [$intent, $booking] = $this->paidBooking($event, 'paystack', 'repair-provider-ref');
        Transaction::query()->delete();
        $service = new EventCancellationService();
        $request = $service->requestByOrganizer(
            $event->id,
            7,
            'The cancelled event payment requires controlled ledger repair.'
        );
        $approved = $service->approve($request->id, 19);
        $case = EventCancellationCase::where('booking_id', $booking->id)->firstOrFail();
        $this->assertSame('manual_review', $case->status);

        try {
            app(EventCancellationCaseService::class)->retryControlledRefund($case->id, 19);
            $this->fail('Refund retry was accepted without its sale ledger identity.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('sale ledger identity', $exception->getMessage());
        }

        Transaction::create([
            'transcation_id' => $intent->reference,
            'gateway' => $intent->gateway,
            'gateway_reference' => $intent->reference,
            'booking_id' => $booking->id,
            'transcation_type' => 1,
            'payment_status' => 1,
            'grand_total' => 100,
        ]);
        $retried = app(EventCancellationCaseService::class)->retryControlledRefund($case->id, 19);

        $this->assertSame('refund_queued', $retried->status);
        $this->assertNotNull($retried->payment_refund_id);
        $this->assertDatabaseHas('payment_refunds', [
            'payment_intent_id' => $intent->id,
            'event_cancellation_request_id' => $approved->id,
            'status' => 'requested',
        ]);
        $this->assertSame(0, (int) $approved->fresh()->manual_review_count);
    }

    public function test_manual_case_can_close_without_refund_only_with_audited_resolution(): void
    {
        [$event] = $this->event();
        $booking = Booking::create([
            'booking_id' => 'no-refund-case', 'event_id' => $event->id, 'organizer_id' => 7,
            'price' => 45, 'tax' => 5, 'paymentStatus' => 'completed', 'scan_status' => 0,
            'refunded_amount' => 0, 'email' => 'legacy@example.com',
        ]);
        $service = new EventCancellationService();
        $request = $service->requestByOrganizer(
            $event->id,
            7,
            'The cancelled event contains a legacy manual-review payment.'
        );
        $approved = $service->approve($request->id, 19);
        $case = EventCancellationCase::where('booking_id', $booking->id)->firstOrFail();

        $resolved = app(EventCancellationCaseService::class)->resolveWithoutRefund(
            $case->id,
            19,
            'Finance verified that no customer funds were captured for this legacy booking.'
        );

        $this->assertSame('resolved_no_refund', $resolved->status);
        $this->assertSame(19, (int) $resolved->resolved_by_admin_id);
        $this->assertNotNull($resolved->resolved_at);
        $this->assertSame(0, (int) $approved->fresh()->manual_review_count);
        $this->assertSame(0.0, (float) $booking->fresh()->refunded_amount);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'event.cancellation_case_resolved_without_refund',
            'subject_id' => $case->id,
        ]);
    }

    public function test_approved_cancellation_notice_is_delivered_once_from_durable_ledger(): void
    {
        Mail::fake();
        [$event] = $this->event();
        $this->paidBooking($event, 'paystack', 'notice-ref');
        $service = new EventCancellationService();
        $request = $service->requestByOrganizer(
            $event->id,
            7,
            'The event was cancelled because the venue is unavailable.',
            'We are sorry that this event cannot proceed.'
        );
        $service->approve($request->id, 19);
        $notification = EventCancellationNotification::firstOrFail();

        $job = new SendEventCancellationNotice($notification->id);
        $job->handle();
        $job->handle();

        $this->assertSame('sent', $notification->fresh()->status);
        $this->assertNull($notification->fresh()->processing_at);
        $this->assertNotNull($notification->fresh()->sent_at);
        Mail::assertSent(EventCancellationNoticeMail::class, 1);
        Mail::assertSent(EventCancellationNoticeMail::class, fn ($mail) => $mail->hasTo('buyer@example.com'));
    }

    public function test_failed_cancellation_notice_handoff_is_durable_and_recovered(): void
    {
        $notification = EventCancellationNotification::create([
            'event_cancellation_request_id' => 81,
            'booking_id' => 91,
            'status' => 'pending',
        ]);
        $failing = new class extends EventCancellationNotificationDispatchService {
            protected function pushNoticeJob(int $notificationId): void
            {
                throw new \RuntimeException('queue unavailable');
            }
        };

        $this->assertFalse($failing->dispatchNotice((int) $notification->id));
        $notification->refresh();
        $this->assertSame('pending', $notification->status);
        $this->assertSame(1, $notification->dispatch_attempts);
        $this->assertSame('queue unavailable', $notification->dispatch_last_error);

        $this->artisan('events:dispatch-cancellation-notices')
            ->expectsOutputToContain('Cancellation notice dispatch attempted: 1 | failed: 0 | alerted: 0')
            ->assertSuccessful();

        $notification->refresh();
        $this->assertSame(2, $notification->dispatch_attempts);
        $this->assertNull($notification->dispatch_last_error);
        Queue::assertPushed(SendEventCancellationNotice::class, function ($job) use ($notification): bool {
            return $job->notificationId === $notification->id;
        });
    }

    private function event(): array
    {
        $event = Event::create([
            'organizer_id' => 7, 'status' => 1, 'lifecycle_status' => 'scheduled',
            'event_type' => 'venue',
        ]);
        EventContent::create(['event_id' => $event->id, 'title' => 'Test Event']);
        $ticket = Ticket::create([
            'event_id' => $event->id, 'event_type' => 'venue', 'pricing_type' => 'normal',
            'ticket_available_type' => 'limited', 'ticket_available' => 5, 'price' => 100,
        ]);
        return [$event, $ticket];
    }

    private function paidBooking(Event $event, string $gateway, string $reference): array
    {
        $booking = Booking::create([
            'booking_id' => 'booking-' . $reference, 'event_id' => $event->id, 'organizer_id' => 7,
            'price' => 90, 'tax' => 10, 'commission' => 0, 'paymentStatus' => 'completed',
            'scan_status' => 0, 'refunded_amount' => 0, 'email' => 'buyer@example.com',
        ]);
        $intent = PaymentIntent::create([
            'gateway' => $gateway, 'reference' => $reference, 'event_id' => $event->id,
            'booking_id' => $booking->id, 'status' => 'completed', 'amount' => 100,
            'currency' => 'NGN', 'email' => $booking->email, 'payload' => [],
        ]);
        Transaction::create([
            'transcation_id' => $reference, 'gateway' => $gateway,
            'gateway_reference' => $reference, 'booking_id' => $booking->id,
            'transcation_type' => 1, 'payment_status' => 1, 'grand_total' => 100,
        ]);
        return [$intent, $booking];
    }

    private function schema(): void
    {
        Schema::create('events', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('organizer_id')->nullable(); $table->boolean('status')->default(true);
            $table->string('lifecycle_status')->default('scheduled'); $table->timestamp('sales_closed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable(); $table->text('cancellation_reason')->nullable();
            $table->string('event_type')->nullable(); $table->timestamps();
        });
        Schema::create('tickets', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('event_id'); $table->string('event_type')->nullable();
            $table->string('pricing_type')->default('normal'); $table->string('ticket_available_type')->nullable();
            $table->integer('ticket_available')->nullable(); $table->decimal('price', 18, 2)->default(0);
            $table->longText('variations')->nullable(); $table->timestamps(); $table->softDeletes();
        });
        Schema::create('event_contents', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('event_id'); $table->unsignedBigInteger('event_category_id')->nullable();
            $table->string('title')->nullable(); $table->timestamps();
        });
        Schema::create('ticket_reservations', function (Blueprint $table): void {
            $table->id(); $table->string('token'); $table->unsignedBigInteger('event_id'); $table->unsignedBigInteger('ticket_id');
            $table->string('variation_name')->nullable(); $table->integer('quantity'); $table->string('status');
            $table->string('session_hash')->nullable(); $table->unsignedBigInteger('customer_id')->nullable();
            $table->timestamp('expires_at'); $table->timestamp('consumed_at')->nullable(); $table->timestamp('released_at')->nullable();
            $table->timestamps();
        });
        Schema::create('bookings', function (Blueprint $table): void {
            $table->id(); $table->string('booking_id')->nullable(); $table->unsignedBigInteger('event_id')->nullable();
            $table->unsignedBigInteger('organizer_id')->nullable(); $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('fname')->nullable(); $table->string('lname')->nullable(); $table->string('email')->nullable();
            $table->decimal('price', 18, 2)->default(0); $table->decimal('tax', 18, 2)->default(0);
            $table->decimal('commission', 18, 2)->default(0); $table->string('paymentStatus')->nullable();
            $table->integer('scan_status')->default(0); $table->decimal('refunded_amount', 18, 2)->default(0);
            $table->string('refund_status')->nullable(); $table->timestamps();
        });
        Schema::create('payment_intents', function (Blueprint $table): void {
            $table->id(); $table->string('gateway'); $table->string('reference'); $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('booking_id')->nullable(); $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('status'); $table->decimal('amount', 18, 2); $table->string('currency'); $table->string('email');
            $table->longText('payload'); $table->string('gateway_transaction_id')->nullable(); $table->text('last_error')->nullable();
            $table->timestamp('expires_at')->nullable(); $table->timestamp('processed_at')->nullable(); $table->timestamps();
        });
        Schema::create('transactions', function (Blueprint $table): void {
            $table->id(); $table->string('transcation_id')->nullable(); $table->string('gateway')->nullable();
            $table->string('gateway_reference')->nullable(); $table->string('booking_id')->nullable();
            $table->integer('transcation_type')->nullable(); $table->string('payment_status')->nullable();
            $table->string('payment_method')->nullable(); $table->decimal('grand_total', 18, 2)->default(0);
            $table->decimal('commission', 18, 2)->default(0); $table->decimal('tax', 18, 2)->default(0);
            $table->decimal('pre_balance', 18, 2)->default(0); $table->decimal('after_balance', 18, 2)->default(0);
            $table->string('gateway_type')->nullable(); $table->string('currency_symbol')->nullable();
            $table->string('currency_symbol_position')->nullable(); $table->timestamps();
            $table->unique(['gateway', 'gateway_reference']);
        });
        Schema::create('booking_tickets', function (Blueprint $table): void {
            $table->id(); $table->string('public_id')->nullable(); $table->unsignedBigInteger('booking_id');
            $table->unsignedBigInteger('event_id'); $table->integer('sequence')->default(1); $table->string('status')->default('issued');
            $table->timestamp('checked_in_at')->nullable(); $table->timestamps();
        });
        Schema::create('event_cancellation_requests', function (Blueprint $table): void {
            $table->id(); $table->uuid('public_id'); $table->unsignedBigInteger('event_id'); $table->unsignedBigInteger('organizer_id')->nullable();
            $table->unsignedBigInteger('requested_by_organizer_id')->nullable(); $table->unsignedBigInteger('requested_by_admin_id')->nullable();
            $table->unsignedBigInteger('reviewed_by_admin_id')->nullable(); $table->string('status'); $table->boolean('event_status_before_request');
            $table->text('reason'); $table->text('customer_message')->nullable(); $table->text('rejection_reason')->nullable();
            $table->integer('eligible_refund_count')->default(0); $table->decimal('eligible_refund_amount', 18, 2)->default(0);
            $table->integer('queued_refund_count')->default(0); $table->decimal('queued_refund_amount', 18, 2)->default(0);
            $table->integer('manual_review_count')->default(0); $table->decimal('manual_review_amount', 18, 2)->default(0);
            $table->timestamp('requested_at'); $table->timestamp('reviewed_at')->nullable(); $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable(); $table->timestamps();
        });
        Schema::create('payment_refunds', function (Blueprint $table): void {
            $table->id(); $table->uuid('public_id'); $table->unsignedBigInteger('payment_intent_id');
            $table->unsignedBigInteger('event_cancellation_request_id')->nullable();
            $table->unsignedBigInteger('payment_refund_dispute_id')->nullable()->unique();
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->unsignedBigInteger('booking_id')->nullable(); $table->unsignedBigInteger('organizer_id')->nullable();
            $table->unsignedBigInteger('requested_by_admin_id')->nullable(); $table->unsignedBigInteger('requested_by_organizer_id')->nullable();
            $table->unsignedBigInteger('reviewed_by_admin_id')->nullable(); $table->string('gateway'); $table->string('gateway_reference');
            $table->string('gateway_refund_id')->nullable(); $table->string('gateway_status')->nullable(); $table->decimal('amount', 18, 2);
            $table->string('currency'); $table->text('reason'); $table->text('rejection_reason')->nullable(); $table->string('status');
            $table->longText('gateway_response')->nullable(); $table->text('last_error')->nullable(); $table->timestamp('requested_at');
            $table->timestamp('reviewed_at')->nullable(); $table->timestamp('submitted_at')->nullable(); $table->timestamp('completed_at')->nullable();
            $table->timestamp('financial_applied_at')->nullable(); $table->timestamps();
        });
        Schema::create('event_cancellation_notifications', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('event_cancellation_request_id'); $table->unsignedBigInteger('booking_id');
            $table->string('status')->default('pending'); $table->integer('attempts')->default(0);
            $table->unsignedSmallInteger('dispatch_attempts')->default(0);
            $table->timestamp('dispatch_last_attempt_at')->nullable(); $table->text('dispatch_last_error')->nullable();
            $table->timestamp('processing_at')->nullable();
            $table->timestamp('sent_at')->nullable(); $table->timestamp('alerted_at')->nullable();
            $table->text('last_error')->nullable(); $table->timestamps();
            $table->unique(['event_cancellation_request_id', 'booking_id']);
        });
        Schema::create('event_cancellation_cases', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('event_cancellation_request_id'); $table->unsignedBigInteger('booking_id');
            $table->unsignedBigInteger('payment_intent_id')->nullable(); $table->unsignedBigInteger('payment_refund_id')->nullable();
            $table->unsignedBigInteger('payment_refund_dispute_id')->nullable(); $table->unsignedBigInteger('resolved_by_admin_id')->nullable();
            $table->string('external_refund_reference')->nullable()->unique();
            $table->string('status'); $table->decimal('amount', 18, 2)->default(0); $table->text('reason')->nullable();
            $table->text('resolution')->nullable(); $table->timestamp('resolved_at')->nullable();
            $table->timestamps(); $table->unique(['event_cancellation_request_id', 'booking_id']);
        });
        Schema::create('payment_refund_disputes', function (Blueprint $table): void {
            $table->id(); $table->uuid('public_id')->unique(); $table->unsignedBigInteger('payment_intent_id');
            $table->unsignedBigInteger('booking_id'); $table->unsignedBigInteger('organizer_id')->nullable();
            $table->unsignedBigInteger('requested_by_organizer_id')->nullable(); $table->unsignedBigInteger('requested_by_admin_id')->nullable();
            $table->unsignedBigInteger('reviewed_by_admin_id')->nullable(); $table->unsignedBigInteger('payment_refund_id')->nullable()->unique();
            $table->string('category'); $table->text('description'); $table->string('status');
            $table->decimal('requested_amount', 18, 2); $table->string('currency');
            $table->unsignedInteger('checked_in_ticket_count')->default(0); $table->text('resolution')->nullable();
            $table->timestamp('opened_at'); $table->timestamp('reviewed_at')->nullable(); $table->timestamp('closed_at')->nullable(); $table->timestamps();
        });
        Schema::create('organizers', function (Blueprint $table): void {
            $table->id(); $table->string('username')->nullable(); $table->decimal('amount', 18, 2)->default(0); $table->timestamps();
        });
        Schema::create('earnings', function (Blueprint $table): void {
            $table->id(); $table->decimal('total_revenue', 18, 2)->default(0);
            $table->decimal('total_earning', 18, 2)->default(0); $table->timestamps();
        });
        Schema::create('booking_deliveries', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('booking_id'); $table->string('kind'); $table->string('status');
            $table->integer('attempts')->default(0); $table->unsignedSmallInteger('dispatch_attempts')->default(0);
            $table->timestamp('dispatch_last_attempt_at')->nullable(); $table->text('dispatch_last_error')->nullable();
            $table->timestamp('processing_at')->nullable(); $table->timestamp('sent_at')->nullable();
            $table->text('last_error')->nullable(); $table->timestamp('alerted_at')->nullable(); $table->timestamps();
            $table->unique(['booking_id', 'kind']);
        });
        Schema::create('event_registration_submissions', function (Blueprint $table): void {
            $table->id(); $table->string('public_id')->unique(); $table->unsignedBigInteger('form_id');
            $table->unsignedBigInteger('event_id'); $table->unsignedBigInteger('organizer_id');
            $table->unsignedBigInteger('customer_id')->nullable(); $table->unsignedBigInteger('booking_id')->nullable();
            $table->unsignedInteger('form_version'); $table->string('status'); $table->text('primary_name');
            $table->text('primary_email'); $table->char('primary_email_hash', 64); $table->unsignedSmallInteger('attendee_count');
            $table->string('consent_version'); $table->timestamp('consented_at'); $table->char('source_ip_hash', 64)->nullable();
            $table->timestamp('submitted_at'); $table->unsignedBigInteger('reviewed_by_organizer_id')->nullable();
            $table->timestamp('reviewed_at')->nullable(); $table->text('review_note')->nullable(); $table->timestamps();
        });
        Schema::create('event_registration_notifications', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('submission_id'); $table->unsignedBigInteger('event_id');
            $table->string('kind'); $table->string('status')->default('pending'); $table->integer('attempts')->default(0);
            $table->unsignedSmallInteger('dispatch_attempts')->default(0);
            $table->timestamp('dispatch_last_attempt_at')->nullable(); $table->text('dispatch_last_error')->nullable();
            $table->timestamp('processing_at')->nullable(); $table->timestamp('sent_at')->nullable();
            $table->timestamp('alerted_at')->nullable(); $table->text('last_error')->nullable(); $table->timestamps();
            $table->unique(['submission_id', 'kind']);
        });
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id(); $table->string('actor_type'); $table->unsignedBigInteger('actor_id')->nullable(); $table->string('action');
            $table->string('subject_type')->nullable(); $table->unsignedBigInteger('subject_id')->nullable(); $table->json('before')->nullable();
            $table->json('after')->nullable(); $table->json('metadata')->nullable(); $table->string('request_id')->nullable();
            $table->string('ip_address')->nullable(); $table->text('user_agent')->nullable(); $table->timestamp('created_at')->nullable();
        });
    }
}
