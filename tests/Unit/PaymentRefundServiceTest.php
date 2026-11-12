<?php

namespace Tests\Unit;

use App\Exceptions\GatewayOutcomeUnknownException;
use App\Models\Earning;
use App\Models\Event\Booking;
use App\Models\Event\BookingTicket;
use App\Models\PaymentIntent;
use App\Models\PaymentRefund;
use App\Models\PaymentRefundDispute;
use App\Models\Transaction;
use App\Services\PaymentRefundService;
use App\Services\PaymentRefundDisputeService;
use App\Services\PaystackGatewayClient;
use App\Services\FlutterwaveGatewayClient;
use App\Services\IncidentAlertService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class PaymentRefundServiceTest extends TestCase
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

    public function test_refund_requests_cannot_exceed_remaining_payment_amount(): void
    {
        [$intent] = $this->payment(100);
        $service = new PaymentRefundService();

        $firstRefund = $service->request($intent->id, 70, 'Customer cancellation approved.', 1);
        $firstRefund->update(['status' => 'processed']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('remaining refundable amount');
        $service->request($intent->id, 40, 'Second refund exceeds payment.', 1);
    }

    public function test_a_second_refund_cannot_overlap_an_unresolved_refund(): void
    {
        [$intent] = $this->payment(100);
        $service = new PaymentRefundService();

        $service->request($intent->id, 25, 'First partial refund.', 1);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('still in progress');
        $service->request($intent->id, 25, 'Overlapping partial refund.', 1);
    }

    public function test_organizer_request_requires_booking_ownership_and_waits_for_admin_approval(): void
    {
        [$intent, $booking] = $this->payment(100, 'paystack', '778899', 7);
        $service = new PaymentRefundService();

        $request = $service->requestByOrganizer(
            $intent->id,
            40,
            'Attendee cancellation approved by the event organizer.',
            7
        );

        $this->assertSame('approval_pending', $request->status);
        $this->assertSame(7, (int) $request->requested_by_organizer_id);
        $this->assertNull($request->requested_by_admin_id);
        $this->assertNull($request->submitted_at);
        $this->assertSame(0.0, (float) $booking->fresh()->refunded_amount);

        $approved = $service->approveOrganizerRequest($request->id, 19);

        $this->assertSame('requested', $approved->status);
        $this->assertSame(19, (int) $approved->requested_by_admin_id);
        $this->assertSame(19, (int) $approved->reviewed_by_admin_id);
        $this->assertNotNull($approved->reviewed_at);
        $this->assertNull($approved->submitted_at);
        $this->assertSame(0.0, (float) $booking->fresh()->refunded_amount);
    }

    public function test_organizer_cannot_request_refund_for_another_organizers_booking(): void
    {
        [$intent] = $this->payment(100, 'paystack', '778899', 7);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('does not own this booking');
        (new PaymentRefundService())->requestByOrganizer(
            $intent->id,
            25,
            'Attempt against another organizer booking.',
            8
        );
    }

    public function test_rejected_organizer_request_releases_the_refundable_amount(): void
    {
        [$intent] = $this->payment(100, 'paystack', '778899', 7);
        $service = new PaymentRefundService();
        $request = $service->requestByOrganizer(
            $intent->id,
            60,
            'Organizer submitted a customer cancellation request.',
            7
        );

        $rejected = $service->rejectOrganizerRequest(
            $request->id,
            19,
            'The supplied cancellation evidence could not be verified.'
        );

        $this->assertSame('rejected', $rejected->status);
        $this->assertSame(19, (int) $rejected->reviewed_by_admin_id);
        $this->assertSame(
            'The supplied cancellation evidence could not be verified.',
            $rejected->rejection_reason
        );
        $this->assertSame(100.0, $service->remainingRefundableAmount($intent));
    }

    public function test_any_checked_in_ticket_unit_blocks_automated_refund(): void
    {
        [$intent, $booking] = $this->payment(100);
        BookingTicket::create([
            'public_id' => 'ticket-unit-1', 'booking_id' => $booking->id, 'event_id' => $booking->event_id,
            'sequence' => 1, 'status' => 'checked_in', 'checked_in_at' => now(),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('checked-in ticket');
        (new PaymentRefundService())->request($intent->id, 100, 'Refund after admission.', 1);
    }

    public function test_checked_in_refund_requires_a_reviewed_dispute_before_provider_queueing(): void
    {
        [$intent, $booking] = $this->payment(100, 'paystack', '778899', 7);
        $ticket = BookingTicket::create([
            'public_id' => 'ticket-unit-dispute-1', 'booking_id' => $booking->id,
            'event_id' => $booking->event_id, 'sequence' => 1,
            'status' => 'checked_in', 'checked_in_at' => now(),
        ]);
        $service = new PaymentRefundDisputeService(new PaymentRefundService());

        $dispute = $service->openForOrganizer(
            $intent->id,
            7,
            80,
            'entry_issue',
            'The attendee was admitted briefly before the venue evacuation ended the event.'
        );

        $this->assertSame(PaymentRefundDispute::STATUS_OPEN, $dispute->status);
        $this->assertSame(1, (int) $dispute->checked_in_ticket_count);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'refund.dispute_opened',
            'subject_id' => $dispute->id,
        ]);

        $approved = $service->approveAndQueue(
            $dispute->id,
            19,
            'Venue incident evidence and the immutable scanner entry were independently reviewed.'
        );
        $refund = PaymentRefund::findOrFail($approved->payment_refund_id);

        $this->assertSame(PaymentRefundDispute::STATUS_REFUND_QUEUED, $approved->status);
        $this->assertSame('requested', $refund->status);
        $this->assertSame($dispute->id, (int) $refund->payment_refund_dispute_id);
        $this->assertSame(0.0, (float) $booking->fresh()->refunded_amount);
        $this->assertSame('checked_in', $ticket->fresh()->status);

        $approvedAgain = $service->approveAndQueue(
            $dispute->id,
            19,
            'A repeated approval must return the original controlled refund without duplication.'
        );
        $this->assertSame($refund->id, (int) $approvedAgain->payment_refund_id);
        $this->assertDatabaseCount('payment_refunds', 1);
    }

    public function test_refund_dispute_enforces_ownership_and_closed_decisions_are_immutable(): void
    {
        [$intent, $booking] = $this->payment(100, 'paystack', '778899', 7);
        BookingTicket::create([
            'public_id' => 'ticket-unit-dispute-2', 'booking_id' => $booking->id,
            'event_id' => $booking->event_id, 'sequence' => 1,
            'status' => 'checked_in', 'checked_in_at' => now(),
        ]);
        $service = new PaymentRefundDisputeService(new PaymentRefundService());

        try {
            $service->openForOrganizer(
                $intent->id,
                8,
                50,
                'other',
                'This organizer must not be allowed to dispute another organizer booking.'
            );
            $this->fail('Cross-organizer dispute access was accepted.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('does not own', $exception->getMessage());
        }

        $dispute = $service->openForOrganizer(
            $intent->id,
            7,
            50,
            'other',
            'Finance should review this exceptional checked-in ticket request carefully.'
        );
        $rejected = $service->review(
            $dispute->id,
            19,
            PaymentRefundDispute::STATUS_REJECTED,
            'The evidence does not justify refunding a ticket after completed admission.'
        );

        $this->assertSame(PaymentRefundDispute::STATUS_REJECTED, $rejected->status);
        $this->assertDatabaseCount('payment_refunds', 0);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('closed refund dispute');
        $service->review(
            $dispute->id,
            19,
            PaymentRefundDispute::STATUS_UNDER_REVIEW,
            'A closed decision must not be reopened in place.'
        );
    }

    public function test_refund_requires_exact_gateway_ledger_identity(): void
    {
        [$intent] = $this->payment(100);
        Transaction::where('transcation_type', 1)->update([
            'gateway' => null,
            'gateway_reference' => null,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('sale ledger identity is missing or mismatched');
        (new PaymentRefundService())->request($intent->id, 25, 'Unsafe legacy mapping.', 1);
    }

    public function test_submission_records_gateway_pending_state_without_applying_money_early(): void
    {
        [$intent, $booking] = $this->payment(100);
        $refund = (new PaymentRefundService())->request(
            $intent->id, 25, 'Partial customer service refund.', 1
        );
        $client = Mockery::mock(PaystackGatewayClient::class);
        $client->shouldReceive('createRefund')->once()->andReturn([
            'status' => true,
            'message' => 'Refund has been queued for processing',
            'data' => ['id' => 9001, 'status' => 'pending'],
        ]);

        $result = (new PaymentRefundService())->submit($refund, $client);

        $this->assertSame('pending', $result->status);
        $this->assertSame('9001', $result->gateway_refund_id);
        $this->assertNull($result->financial_applied_at);
        $this->assertSame(0.0, (float) $booking->fresh()->refunded_amount);
    }

    public function test_indeterminate_gateway_response_requires_verification_without_applying_money(): void
    {
        [$intent, $booking] = $this->payment(100);
        $refund = (new PaymentRefundService())->request(
            $intent->id, 25, 'Partial customer service refund.', 1
        );
        $client = Mockery::mock(PaystackGatewayClient::class);
        $client->shouldReceive('createRefund')->once()
            ->andThrow(new GatewayOutcomeUnknownException('upstream timeout'));

        $result = (new PaymentRefundService())->submit($refund, $client);

        $this->assertSame('verification_required', $result->status);
        $this->assertNull($result->financial_applied_at);
        $this->assertSame(0.0, (float) $booking->fresh()->refunded_amount);
    }

    public function test_indeterminate_paystack_refund_is_recovered_by_original_transaction_identity(): void
    {
        [$intent, $booking] = $this->payment(100, 'paystack', '778899');
        $service = new PaymentRefundService();
        $refund = $service->request($intent->id, 25, 'Approved partial refund.', 1);
        $refund->update([
            'status' => 'verification_required',
            'last_error' => 'Submission outcome was unknown.',
        ]);
        $client = Mockery::mock(PaystackGatewayClient::class);
        $client->shouldReceive('listRefundsForTransaction')->once()->with('778899')->andReturn([
            'status' => true,
            'data' => [[
                'id' => 9004,
                'transaction' => 778899,
                'amount' => 2500,
                'currency' => 'NGN',
                'status' => 'processed',
            ]],
        ]);

        $result = $service->recoverUnknownPaystackSubmission($refund, $client);

        $this->assertSame('processed', $result->status);
        $this->assertSame('9004', $result->gateway_refund_id);
        $this->assertNotNull($result->financial_applied_at);
        $this->assertSame(25.0, (float) $booking->fresh()->refunded_amount);
        $this->assertSame(75.0, (float) Earning::first()->total_revenue);
        $this->assertSame(1, Transaction::where('transcation_type', 6)->count());
    }

    public function test_paystack_refund_reconciliation_rejects_wrong_provider_identity(): void
    {
        [$intent, $booking] = $this->payment(100, 'paystack', '778899');
        $service = new PaymentRefundService();
        $refund = $service->request($intent->id, 25, 'Approved partial refund.', 1);
        $refund->update(['status' => 'processing', 'gateway_refund_id' => '9005']);
        $alerts = Mockery::mock(IncidentAlertService::class);
        $alerts->shouldReceive('alert')->once();
        $this->app->instance(IncidentAlertService::class, $alerts);

        $result = $service->recordGatewayStatus($refund, 'processed', [
            'id' => 9999,
            'amount' => 2500,
            'currency' => 'NGN',
            'status' => 'processed',
        ]);

        $this->assertSame('verification_required', $result->status);
        $this->assertNull($result->financial_applied_at);
        $this->assertSame(0.0, (float) $booking->fresh()->refunded_amount);
        $this->assertSame(100.0, (float) Earning::first()->total_revenue);
        $this->assertSame(0, Transaction::where('transcation_type', 6)->count());
    }

    public function test_stale_unknown_refund_recovery_alerts_without_guessing_or_resubmitting(): void
    {
        config()->set('operations.refund_recovery_alert_minutes', 30);
        [$intent, $booking] = $this->payment(100, 'paystack', '778899');
        $service = new PaymentRefundService();
        $refund = $service->request($intent->id, 25, 'Approved partial refund.', 1);
        $refund->update([
            'status' => 'verification_required',
            'requested_at' => now()->subMinutes(31),
        ]);
        $client = Mockery::mock(PaystackGatewayClient::class);
        $client->shouldReceive('listRefundsForTransaction')->once()->with('778899')->andReturn([
            'status' => true,
            'data' => [],
        ]);
        $alerts = Mockery::mock(IncidentAlertService::class);
        $alerts->shouldReceive('alert')->once();
        $this->app->instance(IncidentAlertService::class, $alerts);

        $result = $service->recoverUnknownPaystackSubmission($refund, $client);

        $this->assertSame('verification_required', $result->status);
        $this->assertNull($result->gateway_refund_id);
        $this->assertNull($result->financial_applied_at);
        $this->assertSame(0.0, (float) $booking->fresh()->refunded_amount);
        $this->assertSame(100.0, (float) Earning::first()->total_revenue);
    }

    public function test_provider_confirmed_event_refund_stays_retryable_when_local_ledger_apply_fails(): void
    {
        [$intent, $booking] = $this->payment(100, 'paystack', '778899');
        $service = new PaymentRefundService();
        $refund = $service->request($intent->id, 25, 'Approved partial refund.', 1);
        $refund->update(['status' => 'processing', 'gateway_refund_id' => '9006']);
        $booking->delete();
        $alerts = Mockery::mock(IncidentAlertService::class);
        $alerts->shouldReceive('alert')->once();
        $this->app->instance(IncidentAlertService::class, $alerts);

        $result = $service->recordGatewayStatus($refund, 'processed', [
            'id' => 9006,
            'amount' => 2500,
            'currency' => 'NGN',
            'status' => 'processed',
        ]);

        $this->assertSame('needs_attention', $result->status);
        $this->assertNull($result->financial_applied_at);
        $this->assertSame(100.0, (float) Earning::first()->total_revenue);
        $this->assertSame(0, Transaction::where('transcation_type', 6)->count());
    }

    public function test_processed_webhook_applies_financial_reversal_exactly_once(): void
    {
        [$intent, $booking] = $this->payment(100);
        $ticketUnit = BookingTicket::create([
            'public_id' => 'ticket-unit-refundable', 'booking_id' => $booking->id,
            'event_id' => $booking->event_id, 'sequence' => 1, 'status' => 'issued',
        ]);
        $service = new PaymentRefundService();
        $refund = $service->request($intent->id, 100, 'Event cancelled; full refund.', 1);
        $client = Mockery::mock(PaystackGatewayClient::class);
        $client->shouldReceive('createRefund')->once()->andReturn([
            'status' => true,
            'data' => ['id' => 9002, 'status' => 'pending'],
        ]);
        $service->submit($refund, $client);

        $webhook = [
            'transaction_reference' => 'paystack_ref_1',
            'refund_reference' => 'refund_9002',
            'amount' => 10000,
            'currency' => 'NGN',
            'status' => 'processed',
        ];
        $service->handleWebhook('refund.processed', $webhook);
        $service->handleWebhook('refund.processed', $webhook);

        $booking->refresh();
        $this->assertSame(100.0, (float) $booking->refunded_amount);
        $this->assertSame('refunded', $booking->refund_status);
        $this->assertSame(0.0, (float) Earning::first()->total_revenue);
        $this->assertSame(0.0, (float) Earning::first()->total_earning);
        $this->assertSame(1, Transaction::where('transcation_type', 6)->count());
        $this->assertNotNull(PaymentRefund::find($refund->id)->financial_applied_at);
        $this->assertSame('refunded', $ticketUnit->fresh()->status);
    }

    public function test_flutterwave_refund_stays_in_processing_until_a_terminal_success_status(): void
    {
        [$intent, $booking] = $this->payment(100, 'flutterwave', '456');
        $service = new PaymentRefundService();
        $refund = $service->request($intent->id, 100, 'Event cancelled; full refund.', 1);
        $client = Mockery::mock(FlutterwaveGatewayClient::class);
        $client->shouldReceive('createRefund')->once()->with(456, '100.00', 'Event cancelled; full refund.')
            ->andReturn([
                'status' => 'success',
                'data' => ['id' => 987, 'status' => 'completed', 'amount_refunded' => 100],
            ]);

        $submitted = $service->submit($refund, $client);

        $this->assertSame('processing', $submitted->status, (string) $submitted->last_error);
        $this->assertNull($submitted->financial_applied_at);
        $this->assertSame(0.0, (float) $booking->fresh()->refunded_amount);

        $processed = $service->handleFlutterwaveWebhook([
            'id' => 987, 'status' => 'completed-mpgs', 'amount_refunded' => 100,
        ]);
        $this->assertSame('processed', $processed->status);
        $this->assertSame(100.0, (float) $booking->fresh()->refunded_amount);
        $this->assertSame('Flutterwave Refund', Transaction::where('transcation_type', 6)->value('payment_method'));
    }

    public function test_flutterwave_refund_recovers_and_validates_missing_provider_transaction_id(): void
    {
        [$intent] = $this->payment(100, 'flutterwave');
        $service = new PaymentRefundService();
        $refund = $service->request($intent->id, 25, 'Approved partial customer refund.', 1);
        $client = Mockery::mock(FlutterwaveGatewayClient::class);
        $client->shouldReceive('verifyByReference')->once()->with('flutterwave_ref_1')->andReturn([
            'status' => 'success',
            'data' => [
                'id' => 456, 'status' => 'successful', 'tx_ref' => 'flutterwave_ref_1',
                'amount' => 100, 'currency' => 'NGN', 'customer' => ['email' => 'buyer@example.com'],
            ],
        ]);
        $client->shouldReceive('createRefund')->once()->with(456, Mockery::on(
            fn ($amount) => (float) $amount === 25.0
        ), 'Approved partial customer refund.')
            ->andReturn(['status' => 'success', 'data' => ['id' => 988, 'status' => 'processing']]);

        $submitted = $service->submit($refund, $client);

        $this->assertSame('processing', $submitted->status, (string) $submitted->last_error);
        $this->assertSame('456', $intent->fresh()->gateway_transaction_id);
    }

    public function test_flutterwave_refund_webhook_rejects_amount_or_transaction_mismatch(): void
    {
        [$intent] = $this->payment(100, 'flutterwave', '456');
        $service = new PaymentRefundService();
        $refund = $service->request($intent->id, 25, 'Approved partial customer refund.', 1);
        $refund->update(['status' => 'processing', 'gateway_refund_id' => '989']);
        $alerts = Mockery::mock(IncidentAlertService::class);
        $alerts->shouldReceive('alert')->once();
        $this->app->instance(IncidentAlertService::class, $alerts);

        $result = $service->handleFlutterwaveWebhook([
            'id' => 989,
            'status' => 'completed-mpgs',
            'amount_refunded' => 30,
            'tx_id' => 999,
        ]);

        $this->assertNull($result);
        $this->assertSame('processing', $refund->fresh()->status);
        $this->assertNull($refund->fresh()->financial_applied_at);
    }

    public function test_terminal_refund_status_cannot_be_downgraded_by_a_late_provider_update(): void
    {
        [$intent] = $this->payment(100, 'flutterwave', '456');
        $service = new PaymentRefundService();
        $refund = $service->request($intent->id, 25, 'Approved partial customer refund.', 1);
        $refund->update(['status' => 'processing', 'gateway_refund_id' => '990']);

        $processed = $service->recordGatewayStatus($refund, 'completed-mpgs', ['id' => 990]);
        $late = $service->recordGatewayStatus($processed, 'processing', ['id' => 990]);

        $this->assertSame('processed', $late->status);
        $this->assertNotNull($late->financial_applied_at);
        $this->assertSame(25.0, (float) $late->fresh()->amount);
    }

    private function payment(
        float $amount,
        string $gateway = 'paystack',
        ?string $gatewayTransactionId = null,
        ?int $organizerId = null
    ): array
    {
        $booking = Booking::create([
            'booking_id' => 'booking-1', 'event_id' => 5, 'organizer_id' => $organizerId,
            'customer_id' => 2, 'price' => 90, 'tax' => 10, 'commission' => 0,
            'paymentStatus' => 'completed', 'scan_status' => 0,
            'refunded_amount' => 0, 'currencySymbol' => '₦',
            'currencySymbolPosition' => 'left',
        ]);
        $reference = $gateway . '_ref_1';
        $intent = PaymentIntent::create([
            'gateway' => $gateway, 'reference' => $reference,
            'gateway_transaction_id' => $gatewayTransactionId,
            'event_id' => 5, 'booking_id' => $booking->id, 'status' => 'completed',
            'amount' => $amount, 'currency' => 'NGN', 'email' => 'buyer@example.com',
            'payload' => ['event_id' => 5], 'processed_at' => now(),
        ]);
        Transaction::create([
            'transcation_id' => $reference, 'gateway' => $gateway,
            'gateway_reference' => $reference, 'booking_id' => $booking->id,
            'transcation_type' => 1, 'payment_method' => ucfirst($gateway),
            'payment_status' => 1, 'grand_total' => 90,
        ]);
        Earning::create(['total_revenue' => $amount, 'total_earning' => $amount]);
        return [$intent, $booking];
    }

    private function createSchema(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id(); $table->string('booking_id')->nullable(); $table->unsignedBigInteger('event_id')->nullable();
            $table->unsignedBigInteger('organizer_id')->nullable(); $table->unsignedBigInteger('customer_id')->nullable();
            $table->decimal('price', 18, 2)->default(0); $table->decimal('tax', 18, 2)->default(0);
            $table->decimal('commission', 18, 2)->default(0); $table->string('paymentStatus')->nullable();
            $table->integer('scan_status')->default(0); $table->decimal('refunded_amount', 18, 2)->default(0);
            $table->string('refund_status')->nullable(); $table->string('currencySymbol')->nullable();
            $table->string('currencySymbolPosition')->nullable(); $table->timestamps();
        });
        Schema::create('payment_intents', function (Blueprint $table) {
            $table->id(); $table->string('gateway'); $table->string('reference'); $table->string('gateway_transaction_id')->nullable(); $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('customer_id')->nullable(); $table->unsignedBigInteger('booking_id')->nullable();
            $table->string('status'); $table->decimal('amount', 18, 2); $table->string('currency', 8);
            $table->string('email'); $table->longText('payload'); $table->text('last_error')->nullable();
            $table->timestamp('last_verified_at')->nullable(); $table->timestamp('expires_at')->nullable();
            $table->timestamp('processed_at')->nullable(); $table->timestamps();
        });
        Schema::create('booking_tickets', function (Blueprint $table) {
            $table->id(); $table->string('public_id')->unique(); $table->unsignedBigInteger('booking_id');
            $table->unsignedBigInteger('event_id'); $table->unsignedBigInteger('organizer_id')->nullable();
            $table->unsignedBigInteger('ticket_id')->nullable(); $table->unsignedInteger('sequence');
            $table->string('variation_name')->nullable(); $table->string('holder_name')->nullable();
            $table->string('holder_email')->nullable(); $table->string('status')->default('issued');
            $table->timestamp('checked_in_at')->nullable(); $table->unsignedBigInteger('checked_in_by_organizer_id')->nullable();
            $table->timestamp('revoked_at')->nullable(); $table->string('revoked_reason')->nullable(); $table->timestamps();
        });
        Schema::create('transactions', function (Blueprint $table) {
            $table->id(); $table->string('transcation_id')->nullable(); $table->string('gateway', 40)->nullable();
            $table->string('gateway_reference', 191)->nullable(); $table->string('booking_id')->nullable();
            $table->integer('transcation_type')->nullable(); $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('organizer_id')->nullable(); $table->string('payment_status')->nullable();
            $table->string('payment_method')->nullable(); $table->decimal('grand_total', 18, 2)->default(0);
            $table->decimal('commission', 18, 2)->default(0); $table->decimal('tax', 18, 2)->default(0);
            $table->decimal('pre_balance', 18, 2)->default(0); $table->decimal('after_balance', 18, 2)->default(0);
            $table->string('gateway_type')->nullable(); $table->string('currency_symbol')->nullable();
            $table->string('currency_symbol_position')->nullable(); $table->timestamps();
            $table->unique(['gateway', 'gateway_reference']);
        });
        Schema::create('payment_refunds', function (Blueprint $table) {
            $table->id(); $table->uuid('public_id'); $table->unsignedBigInteger('payment_intent_id');
            $table->unsignedBigInteger('payment_refund_dispute_id')->nullable()->unique();
            $table->unsignedBigInteger('transaction_id')->nullable(); $table->unsignedBigInteger('booking_id')->nullable();
            $table->unsignedBigInteger('organizer_id')->nullable(); $table->unsignedBigInteger('requested_by_admin_id')->nullable();
            $table->unsignedBigInteger('requested_by_organizer_id')->nullable();
            $table->unsignedBigInteger('reviewed_by_admin_id')->nullable();
            $table->string('gateway'); $table->string('gateway_reference'); $table->string('gateway_refund_id')->nullable();
            $table->string('gateway_status')->nullable(); $table->decimal('amount', 18, 2); $table->string('currency');
            $table->text('reason'); $table->text('rejection_reason')->nullable(); $table->string('status'); $table->longText('gateway_response')->nullable();
            $table->text('last_error')->nullable(); $table->timestamp('requested_at'); $table->timestamp('reviewed_at')->nullable(); $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable(); $table->timestamp('financial_applied_at')->nullable(); $table->timestamps();
        });
        Schema::create('payment_refund_disputes', function (Blueprint $table) {
            $table->id(); $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('payment_intent_id'); $table->unsignedBigInteger('booking_id');
            $table->unsignedBigInteger('organizer_id')->nullable();
            $table->unsignedBigInteger('requested_by_organizer_id')->nullable();
            $table->unsignedBigInteger('requested_by_admin_id')->nullable();
            $table->unsignedBigInteger('reviewed_by_admin_id')->nullable();
            $table->unsignedBigInteger('payment_refund_id')->nullable()->unique();
            $table->string('category'); $table->text('description'); $table->string('status');
            $table->decimal('requested_amount', 18, 2); $table->string('currency');
            $table->unsignedInteger('checked_in_ticket_count')->default(0);
            $table->text('resolution')->nullable(); $table->timestamp('opened_at');
            $table->timestamp('reviewed_at')->nullable(); $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('earnings', function (Blueprint $table) {
            $table->id(); $table->decimal('total_revenue', 18, 2)->default(0);
            $table->decimal('total_earning', 18, 2)->default(0); $table->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id(); $table->string('actor_type'); $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action'); $table->string('subject_type')->nullable(); $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('before')->nullable(); $table->json('after')->nullable(); $table->json('metadata')->nullable();
            $table->string('request_id')->nullable(); $table->string('ip_address')->nullable(); $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }
}
