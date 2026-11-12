<?php

namespace Tests\Unit;

use App\Models\Earning;
use App\Models\Event;
use App\Models\Event\Booking;
use App\Models\Event\Ticket;
use App\Models\Organizer;
use App\Services\ManualBookingPaymentService;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ManualBookingPaymentServiceTest extends TestCase
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
        Earning::create(['total_revenue' => 100, 'total_earning' => 25]);
    }

    public function test_offline_approval_posts_every_financial_effect_exactly_once(): void
    {
        $organizer = $this->organizer(200);
        $event = $this->event($organizer->id, 'venue');
        $booking = $this->booking($event, $organizer, [
            'price' => 1000, 'tax' => 75, 'commission' => 100,
        ]);
        $service = app(ManualBookingPaymentService::class);

        $first = $service->transition($booking->id, 'completed', $organizer->id);
        $second = $service->transition($booking->id, 'completed', $organizer->id);

        $this->assertTrue($first->changed);
        $this->assertFalse($second->changed);
        $this->assertSame('completed', $booking->fresh()->paymentStatus);
        $this->assertSame(1100.0, (float) $organizer->fresh()->amount);
        $this->assertSame(1175.0, (float) Earning::firstOrFail()->total_revenue);
        $this->assertSame(200.0, (float) Earning::firstOrFail()->total_earning);
        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseHas('transactions', [
            'booking_id' => (string) $booking->id,
            'transcation_type' => 1,
            'payment_status' => '1',
            'gateway' => 'offline',
            'gateway_reference' => 'booking:'.$booking->id,
            'pre_balance' => 200,
            'after_balance' => 1100,
        ]);
        $this->assertDatabaseHas('booking_deliveries', [
            'booking_id' => $booking->id,
            'kind' => 'event_booking_confirmation',
            'status' => 'pending',
        ]);
        $this->assertDatabaseCount('booking_deliveries', 1);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'booking.payment_status_changed')->count());
    }

    public function test_approval_rolls_back_finance_when_fulfillment_record_cannot_commit(): void
    {
        $organizer = $this->organizer(200);
        $event = $this->event($organizer->id, 'venue');
        $booking = $this->booking($event, $organizer, [
            'price' => 1000, 'tax' => 75, 'commission' => 100,
        ]);
        Schema::drop('booking_deliveries');

        try {
            app(ManualBookingPaymentService::class)->transition($booking->id, 'completed');
            $this->fail('Approval must fail when its recoverable fulfillment record cannot commit.');
        } catch (\Illuminate\Database\QueryException) {
            // The missing delivery table simulates a failed fulfillment-ledger write.
        }

        $this->assertSame('pending', $booking->fresh()->paymentStatus);
        $this->assertSame(200.0, (float) $organizer->fresh()->amount);
        $this->assertSame(100.0, (float) Earning::firstOrFail()->total_revenue);
        $this->assertSame(25.0, (float) Earning::firstOrFail()->total_earning);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_rejection_restores_limited_inventory_exactly_once(): void
    {
        $organizer = $this->organizer();
        $event = $this->event($organizer->id, 'online');
        $ticket = Ticket::create([
            'event_id' => $event->id,
            'pricing_type' => 'normal',
            'ticket_available_type' => 'limited',
            'ticket_available' => 7,
        ]);
        $booking = $this->booking($event, $organizer, ['quantity' => 3]);
        $service = app(ManualBookingPaymentService::class);

        $first = $service->transition($booking->id, 'rejected', $organizer->id);
        $second = $service->transition($booking->id, 'rejected', $organizer->id);

        $this->assertTrue($first->changed);
        $this->assertFalse($second->changed);
        $this->assertSame('rejected', $booking->fresh()->paymentStatus);
        $this->assertSame(10, (int) $ticket->fresh()->ticket_available);
        $this->assertSame(100.0, (float) Earning::firstOrFail()->total_revenue);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseHas('booking_deliveries', [
            'booking_id' => $booking->id,
            'kind' => 'event_booking_rejection',
            'status' => 'pending',
        ]);
        $this->assertDatabaseCount('booking_deliveries', 1);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'booking.payment_status_changed')->count());
    }

    public function test_rejection_rolls_back_inventory_when_notification_record_cannot_commit(): void
    {
        $organizer = $this->organizer();
        $event = $this->event($organizer->id, 'online');
        $ticket = Ticket::create([
            'event_id' => $event->id,
            'pricing_type' => 'normal',
            'ticket_available_type' => 'limited',
            'ticket_available' => 7,
        ]);
        $booking = $this->booking($event, $organizer, ['quantity' => 3]);
        Schema::drop('booking_deliveries');

        try {
            app(ManualBookingPaymentService::class)->transition($booking->id, 'rejected');
            $this->fail('Rejection must fail when its durable notification record cannot commit.');
        } catch (\Illuminate\Database\QueryException) {
            // The missing table simulates a failed decision-notification ledger write.
        }

        $this->assertSame('pending', $booking->fresh()->paymentStatus);
        $this->assertSame(7, (int) $ticket->fresh()->ticket_available);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_rejection_restores_only_the_selected_variation(): void
    {
        $organizer = $this->organizer();
        $event = $this->event($organizer->id, 'venue');
        $ticket = Ticket::create([
            'event_id' => $event->id,
            'pricing_type' => 'variation',
            'ticket_available_type' => 'limited',
            'ticket_available' => 0,
            'variations' => json_encode([
                ['name' => 'Regular', 'ticket_available_type' => 'limited', 'ticket_available' => 4],
                ['name' => 'VIP', 'ticket_available_type' => 'limited', 'ticket_available' => 2],
            ]),
        ]);
        $booking = $this->booking($event, $organizer, [
            'quantity' => 2,
            'variation' => json_encode([[
                'ticket_id' => $ticket->id, 'name' => 'VIP', 'qty' => 2,
            ]]),
        ]);

        app(ManualBookingPaymentService::class)->transition($booking->id, 'rejected');

        $variations = json_decode($ticket->fresh()->variations, true);
        $this->assertSame(4, $variations[0]['ticket_available']);
        $this->assertSame(4, $variations[1]['ticket_available']);
    }

    public function test_rejection_restores_multiple_options_from_one_ticket_without_overwriting(): void
    {
        $organizer = $this->organizer();
        $event = $this->event($organizer->id, 'venue');
        $ticket = Ticket::create([
            'event_id' => $event->id,
            'pricing_type' => 'variation',
            'ticket_available_type' => 'limited',
            'ticket_available' => 0,
            'variations' => json_encode([
                ['name' => 'Regular', 'ticket_available_type' => 'limited', 'ticket_available' => 4],
                ['name' => 'VIP', 'ticket_available_type' => 'limited', 'ticket_available' => 2],
            ]),
        ]);
        $booking = $this->booking($event, $organizer, [
            'quantity' => 3,
            'variation' => json_encode([
                ['ticket_id' => $ticket->id, 'name' => 'Regular', 'qty' => 1],
                ['ticket_id' => $ticket->id, 'name' => 'VIP', 'qty' => 2],
            ]),
        ]);

        app(ManualBookingPaymentService::class)->transition($booking->id, 'rejected');

        $variations = json_decode($ticket->fresh()->variations, true);
        $this->assertSame(5, $variations[0]['ticket_available']);
        $this->assertSame(4, $variations[1]['ticket_available']);
    }

    public function test_rejection_fails_closed_when_selection_quantities_do_not_reconcile(): void
    {
        $organizer = $this->organizer();
        $event = $this->event($organizer->id, 'venue');
        $ticket = Ticket::create([
            'event_id' => $event->id,
            'pricing_type' => 'normal',
            'ticket_available_type' => 'limited',
            'ticket_available' => 7,
        ]);
        $booking = $this->booking($event, $organizer, [
            'quantity' => 3,
            'variation' => json_encode([[
                'ticket_id' => $ticket->id, 'name' => 'Regular', 'qty' => 2,
            ]]),
        ]);

        try {
            app(ManualBookingPaymentService::class)->transition($booking->id, 'rejected');
            $this->fail('Unreconciled inventory evidence must stop rejection.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('do not reconcile', $exception->getMessage());
        }

        $this->assertSame('pending', $booking->fresh()->paymentStatus);
        $this->assertSame(7, (int) $ticket->fresh()->ticket_available);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_rejection_fails_closed_for_duplicate_or_invalid_allocations(): void
    {
        $organizer = $this->organizer();
        $event = $this->event($organizer->id, 'venue');
        $ticket = Ticket::create([
            'event_id' => $event->id,
            'pricing_type' => 'normal',
            'ticket_available_type' => 'limited',
            'ticket_available' => 7,
        ]);
        $duplicate = $this->booking($event, $organizer, [
            'quantity' => 2,
            'variation' => json_encode([
                ['ticket_id' => $ticket->id, 'name' => 'Regular', 'qty' => 1],
                ['ticket_id' => $ticket->id, 'name' => 'Renamed', 'qty' => 1],
            ]),
        ]);

        try {
            app(ManualBookingPaymentService::class)->transition($duplicate->id, 'rejected');
            $this->fail('Duplicate ticket evidence must stop rejection.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('duplicate', $exception->getMessage());
        }

        $invalid = $this->booking($event, $organizer, [
            'quantity' => 1,
            'variation' => json_encode([[
                'ticket_id' => $ticket->id, 'name' => 'Regular', 'qty' => -1,
            ]]),
        ]);
        try {
            app(ManualBookingPaymentService::class)->transition($invalid->id, 'rejected');
            $this->fail('Invalid ticket evidence must stop rejection.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('ticket quantity is invalid', $exception->getMessage());
        }

        $this->assertSame('pending', $duplicate->fresh()->paymentStatus);
        $this->assertSame('pending', $invalid->fresh()->paymentStatus);
        $this->assertSame(7, (int) $ticket->fresh()->ticket_available);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_online_gateway_booking_cannot_be_manually_approved(): void
    {
        $organizer = $this->organizer();
        $event = $this->event($organizer->id, 'venue');
        $booking = $this->booking($event, $organizer, ['gatewayType' => 'online']);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Only offline bookings');
        app(ManualBookingPaymentService::class)->transition($booking->id, 'completed');
    }

    public function test_organizer_cannot_review_another_organizers_booking(): void
    {
        $owner = $this->organizer();
        $other = $this->organizer();
        $event = $this->event($owner->id, 'venue');
        $booking = $this->booking($event, $owner);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('cannot review');
        app(ManualBookingPaymentService::class)->transition($booking->id, 'completed', $other->id);
    }

    public function test_existing_financial_evidence_blocks_approval_without_partial_effects(): void
    {
        $organizer = $this->organizer(200);
        $event = $this->event($organizer->id, 'venue');
        $booking = $this->booking($event, $organizer, [
            'price' => 1000, 'tax' => 75, 'commission' => 100,
        ]);
        DB::table('transactions')->insert([
            'transcation_id' => 'legacy-conflict',
            'gateway' => 'paystack',
            'gateway_reference' => 'legacy-conflict',
            'booking_id' => (string) $booking->id,
            'transcation_type' => 1,
            'customer_id' => $booking->customer_id,
            'organizer_id' => $organizer->id,
            'payment_status' => '1',
            'payment_method' => 'Paystack',
            'grand_total' => 1000,
            'commission' => 100,
            'tax' => 75,
            'pre_balance' => 0,
            'after_balance' => 900,
            'gateway_type' => 'online',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            app(ManualBookingPaymentService::class)->transition($booking->id, 'completed');
            $this->fail('A conflicting ledger row must stop manual approval.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('requires finance review', $exception->getMessage());
        }

        $this->assertSame('pending', $booking->fresh()->paymentStatus);
        $this->assertSame(200.0, (float) $organizer->fresh()->amount);
        $this->assertSame(100.0, (float) Earning::firstOrFail()->total_revenue);
        $this->assertSame(25.0, (float) Earning::firstOrFail()->total_earning);
        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_existing_financial_evidence_blocks_rejection_without_rewriting_ledger_or_stock(): void
    {
        $organizer = $this->organizer();
        $event = $this->event($organizer->id, 'online');
        $ticket = Ticket::create([
            'event_id' => $event->id,
            'pricing_type' => 'normal',
            'ticket_available_type' => 'limited',
            'ticket_available' => 7,
        ]);
        $booking = $this->booking($event, $organizer, ['quantity' => 3]);
        DB::table('transactions')->insert([
            'transcation_id' => 'legacy-conflict',
            'gateway' => 'offline',
            'gateway_reference' => 'legacy-conflict',
            'booking_id' => (string) $booking->id,
            'transcation_type' => 1,
            'customer_id' => $booking->customer_id,
            'organizer_id' => $organizer->id,
            'payment_status' => '1',
            'payment_method' => 'Bank transfer',
            'grand_total' => 500,
            'commission' => 50,
            'tax' => 50,
            'pre_balance' => 0,
            'after_balance' => 450,
            'gateway_type' => 'offline',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            app(ManualBookingPaymentService::class)->transition($booking->id, 'rejected');
            $this->fail('Existing financial evidence must stop manual rejection.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('requires finance review', $exception->getMessage());
        }

        $this->assertSame('pending', $booking->fresh()->paymentStatus);
        $this->assertSame(7, (int) $ticket->fresh()->ticket_available);
        $this->assertSame('1', (string) DB::table('transactions')->value('payment_status'));
        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_repeated_completed_review_requires_exact_stable_financial_evidence(): void
    {
        $organizer = $this->organizer(900);
        $event = $this->event($organizer->id, 'venue');
        $booking = $this->booking($event, $organizer, [
            'paymentStatus' => 'completed',
            'price' => 1000,
            'tax' => 75,
            'commission' => 100,
        ]);

        try {
            app(ManualBookingPaymentService::class)->transition($booking->id, 'completed');
            $this->fail('Missing ledger history must not pass terminal review.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('no single paid ledger', $exception->getMessage());
        }

        DB::table('transactions')->insert([
            'transcation_id' => 'offline-booking-'.$booking->id,
            'gateway' => 'offline',
            'gateway_reference' => 'booking:'.$booking->id,
            'booking_id' => (string) $booking->id,
            'transcation_type' => 1,
            'customer_id' => $booking->customer_id,
            'organizer_id' => $organizer->id,
            'payment_status' => '1',
            'payment_method' => 'Different method',
            'grand_total' => 1000,
            'commission' => 100,
            'tax' => 75,
            'pre_balance' => 0,
            'after_balance' => 900,
            'gateway_type' => 'offline',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        try {
            app(ManualBookingPaymentService::class)->transition($booking->id, 'completed');
            $this->fail('Mismatched ledger history must not pass terminal review.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('does not reconcile', $exception->getMessage());
        }

        $this->assertSame(900.0, (float) $organizer->fresh()->amount);
        $this->assertSame(100.0, (float) Earning::firstOrFail()->total_revenue);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_terminal_online_booking_cannot_enter_manual_review(): void
    {
        $organizer = $this->organizer();
        $event = $this->event($organizer->id, 'venue');
        $booking = $this->booking($event, $organizer, [
            'gatewayType' => 'online',
            'paymentStatus' => 'completed',
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Only offline bookings');
        app(ManualBookingPaymentService::class)->transition($booking->id, 'completed');
    }

    private function organizer(float $amount = 0): Organizer
    {
        return Organizer::create([
            'email' => uniqid('organizer-', true).'@example.com',
            'username' => uniqid('organizer-', true),
            'password' => 'not-used', 'status' => 1, 'amount' => $amount,
        ]);
    }

    private function event(int $organizerId, string $eventType): Event
    {
        return Event::create([
            'organizer_id' => $organizerId,
            'status' => 1,
            'event_type' => $eventType,
        ]);
    }

    private function booking(Event $event, Organizer $organizer, array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'booking_id' => uniqid('booking-', true),
            'event_id' => $event->id,
            'organizer_id' => $organizer->id,
            'customer_id' => 44,
            'price' => 500,
            'tax' => 50,
            'commission' => 50,
            'quantity' => 1,
            'variation' => null,
            'paymentMethod' => 'Bank transfer',
            'gatewayType' => 'offline',
            'paymentStatus' => 'pending',
            'currencySymbol' => '₦',
            'currencySymbolPosition' => 'left',
        ], $overrides));
    }

    private function createSchema(): void
    {
        Schema::create('organizers', function (Blueprint $table): void {
            $table->id(); $table->string('email')->nullable(); $table->string('username')->nullable();
            $table->string('password')->nullable(); $table->integer('status')->default(1);
            $table->decimal('amount', 18, 2)->default(0); $table->timestamps();
        });
        Schema::create('events', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('organizer_id')->nullable();
            $table->integer('status')->default(1); $table->string('event_type')->nullable();
            $table->timestamps();
        });
        Schema::create('bookings', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('booking_id')->unique(); $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('organizer_id')->nullable(); $table->longText('variation')->nullable();
            $table->decimal('price', 18, 2)->default(0); $table->decimal('tax', 18, 2)->default(0);
            $table->decimal('commission', 18, 2)->default(0); $table->unsignedInteger('quantity')->default(1);
            $table->string('paymentMethod')->nullable(); $table->string('gatewayType')->nullable();
            $table->string('paymentStatus'); $table->string('currencySymbol')->nullable();
            $table->string('currencySymbolPosition')->nullable(); $table->string('invoice')->nullable();
            $table->timestamps();
        });
        Schema::create('tickets', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('event_id'); $table->string('pricing_type')->nullable();
            $table->string('ticket_available_type')->nullable(); $table->integer('ticket_available')->default(0);
            $table->longText('variations')->nullable(); $table->softDeletes(); $table->timestamps();
        });
        Schema::create('earnings', function (Blueprint $table): void {
            $table->id(); $table->decimal('total_revenue', 18, 2)->default(0);
            $table->decimal('total_earning', 18, 2)->default(0); $table->timestamps();
        });
        Schema::create('transactions', function (Blueprint $table): void {
            $table->id(); $table->string('transcation_id')->nullable(); $table->string('gateway')->nullable();
            $table->string('gateway_reference')->nullable(); $table->string('booking_id')->nullable();
            $table->integer('transcation_type'); $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('organizer_id')->nullable(); $table->string('payment_status');
            $table->string('payment_method')->nullable(); $table->decimal('grand_total', 18, 2);
            $table->decimal('commission', 18, 2)->default(0); $table->decimal('tax', 18, 2)->default(0);
            $table->decimal('pre_balance', 18, 2)->default(0); $table->decimal('after_balance', 18, 2)->nullable();
            $table->string('gateway_type')->nullable(); $table->string('currency_symbol')->nullable();
            $table->string('currency_symbol_position')->nullable(); $table->timestamps();
            $table->unique(['gateway', 'gateway_reference']);
        });
        Schema::create('booking_deliveries', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('booking_id'); $table->string('kind', 64);
            $table->string('status', 20)->default('pending'); $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable(); $table->timestamp('processing_at')->nullable();
            $table->timestamp('sent_at')->nullable(); $table->timestamp('alerted_at')->nullable();
            $table->timestamps(); $table->unique(['booking_id', 'kind']);
        });
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id(); $table->string('actor_type')->nullable(); $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action'); $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable(); $table->longText('before')->nullable();
            $table->longText('after')->nullable(); $table->longText('metadata')->nullable();
            $table->string('request_id')->nullable(); $table->string('ip_address')->nullable();
            $table->string('user_agent', 1000)->nullable(); $table->timestamp('created_at')->nullable();
        });
    }
}
