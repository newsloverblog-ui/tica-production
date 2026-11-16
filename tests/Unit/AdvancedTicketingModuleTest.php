<?php

namespace Tests\Unit;

use App\Models\Event\Booking;
use App\Models\Event\BookingTicket;
use App\Models\Event\Ticket;
use App\Models\EventSeat;
use App\Models\SeatReservation;
use App\Models\SeatingRow;
use App\Models\SeatingSection;
use App\Services\EventTicketPurchaseLimitService;
use App\Services\SeatReservationService;
use App\Services\TicketIssuanceService;
use App\Services\TicketNumberAllocator;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class AdvancedTicketingModuleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->createSchema();
    }

    public function test_verified_orders_receive_per_category_permanent_sequential_numbers(): void
    {
        $regular = $this->ticket('Regular', 'REG', 100);
        $vip = $this->ticket('VIP', 'VIP', 30);
        $booking = $this->booking([
            ['ticket_id' => $regular->id, 'name' => 'Regular', 'qty' => 2],
            ['ticket_id' => $vip->id, 'name' => 'VIP', 'qty' => 1],
        ], 3);

        $issued = (new TicketIssuanceService())->issueForBooking($booking);

        $this->assertSame(['REG-001', 'REG-002', 'VIP-001'], $issued->pluck('ticket_number')->all());
        $this->assertSame([1, 2, 1], $issued->pluck('ticket_number_value')->all());
        $this->assertDatabaseCount('ticket_number_allocations', 3);
        $this->assertSame(3, DB::table('ticket_number_allocations')->where('status', 'allocated')->count());

        $replayed = (new TicketIssuanceService())->issueForBooking($booking);
        $this->assertSame($issued->pluck('id')->all(), $replayed->pluck('id')->all());
        $this->assertDatabaseCount('ticket_number_allocations', 3);

        $next = (new TicketIssuanceService())->issueForBooking($this->booking([
            ['ticket_id' => $regular->id, 'name' => 'Regular', 'qty' => 1],
        ], 1));
        $this->assertSame('REG-003', $next->first()->ticket_number);
    }

    public function test_unverified_payment_cannot_allocate_a_ticket_number(): void
    {
        $regular = $this->ticket('Regular', 'REG', 5);
        $booking = $this->booking([
            ['ticket_id' => $regular->id, 'name' => 'Regular', 'qty' => 1],
        ], 1, 'pending');

        try {
            (new TicketIssuanceService())->issueForBooking($booking);
            $this->fail('Expected issuance before verified payment to be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('before payment is verified', $exception->getMessage());
        }

        $this->assertDatabaseCount('booking_tickets', 0);
        $this->assertDatabaseCount('ticket_number_allocations', 0);
    }

    public function test_database_scope_prevents_duplicate_visible_numbers_without_reusing_history(): void
    {
        $regular = $this->ticket('Regular', 'REG', 5);
        $first = (new TicketIssuanceService())->issueForBooking($this->booking([
            ['ticket_id' => $regular->id, 'name' => 'Regular', 'qty' => 1],
        ], 1))->first();

        try {
            DB::table('booking_tickets')->insert([
                'public_id' => (string) Str::uuid(), 'booking_id' => $first->booking_id,
                'event_id' => 41, 'ticket_id' => $regular->id, 'sequence' => 99,
                'ticket_number' => 'REG-001', 'status' => 'issued',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->fail('Expected the event ticket-number uniqueness constraint to reject a duplicate.');
        } catch (QueryException) {
            $this->assertDatabaseHas('ticket_number_allocations', [
                'booking_ticket_id' => $first->id,
                'ticket_number' => 'REG-001',
                'status' => 'allocated',
            ]);
        }
    }

    public function test_seat_holds_are_exclusive_expire_and_become_permanent_after_payment(): void
    {
        $ticket = $this->ticket('Reserved VIP', 'VIP', 2, 'reserved');
        [$section, $row, $seat] = $this->seat($ticket);
        $ownerHash = hash('sha256', 'owner@example.com');
        $service = new SeatReservationService();

        $service->reserve('first-token', 41, [$seat->id], 'first-session', null, $ownerHash);
        try {
            $service->reserve('second-token', 41, [$seat->id], 'second-session', null, hash('sha256', 'other@example.com'));
            $this->fail('Expected an active hold to block a second buyer.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('another buyer', $exception->getMessage());
        }

        SeatReservation::query()->where('token', 'first-token')->update(['expires_at' => now()->subSecond()]);
        $this->assertSame(1, $service->releaseExpired());
        $service->reserve('second-token', 41, [$seat->id], 'second-session', null, hash('sha256', 'other@example.com'));

        $booking = $this->booking([
            ['ticket_id' => $ticket->id, 'name' => 'Reserved VIP', 'qty' => 1],
        ], 1);
        $booking->forceFill(['buyer_email_hash' => hash('sha256', 'other@example.com')])->save();
        $unit = BookingTicket::create([
            'public_id' => (string) Str::uuid(), 'booking_id' => $booking->id,
            'event_id' => 41, 'organizer_id' => 7, 'ticket_id' => $ticket->id,
            'sequence' => 1, 'variation_name' => 'Reserved VIP', 'status' => 'issued',
        ]);

        DB::transaction(fn () => $service->assign('second-token', $booking, collect([$unit])));

        $this->assertSame('sold', $seat->fresh()->status);
        $this->assertSame($seat->id, $unit->fresh()->seat_id);
        $this->assertSame('01', $unit->fresh()->seat_label);
        $this->assertSame('A', $unit->fresh()->row_label);
        $this->assertSame('VIP', $unit->fresh()->section_label);
        $this->assertDatabaseHas('seat_reservations', [
            'token' => 'second-token', 'seat_id' => $seat->id,
            'booking_id' => $booking->id, 'booking_ticket_id' => $unit->id, 'status' => 'consumed',
        ]);
        $this->assertSame($section->id, $seat->section_id);
        $this->assertSame($row->id, $seat->row_id);
    }

    public function test_failed_payment_releases_the_selected_seat_for_another_checkout(): void
    {
        $ticket = $this->ticket('Reserved VIP', 'VIP', 2, 'reserved');
        [, , $seat] = $this->seat($ticket);
        $service = new SeatReservationService();

        $service->reserve('failed-payment-token', 41, [$seat->id], 'failed-session', null, hash('sha256', 'failed@example.com'));
        $this->assertSame(1, $service->release('failed-payment-token', 'payment_failed'));
        $this->assertDatabaseHas('seat_reservations', [
            'token' => 'failed-payment-token', 'seat_id' => $seat->id, 'status' => 'payment_failed',
        ]);

        $replacement = $service->reserve(
            'replacement-token',
            41,
            [$seat->id],
            'replacement-session',
            null,
            hash('sha256', 'replacement@example.com'),
        );

        $this->assertCount(1, $replacement);
        $this->assertSame('active', $replacement->first()->status);
    }

    public function test_offline_payment_review_preserves_and_assigns_the_exact_selected_seat(): void
    {
        $ticket = $this->ticket('Reserved VIP', 'VIP', 1, 'reserved');
        [, , $seat] = $this->seat($ticket);
        $token = (string) Str::uuid();
        $emailHash = hash('sha256', 'offline@example.com');
        $service = new SeatReservationService();
        $service->reserve($token, 41, [$seat->id], 'offline-session', null, $emailHash);

        $booking = $this->booking([
            ['ticket_id' => $ticket->id, 'name' => 'Reserved VIP', 'qty' => 1],
        ], 1, 'pending');
        $booking->forceFill([
            'buyer_email_hash' => $emailHash,
            'ticket_reservation_token' => $token,
        ])->save();

        $this->assertSame(1, $service->bindPendingPayment($token, $booking));
        SeatReservation::query()->where('token', $token)->update(['expires_at' => now()->subDay()]);
        $this->assertSame(0, $service->releaseExpired());
        $this->assertDatabaseHas('seat_reservations', [
            'token' => $token,
            'booking_id' => $booking->id,
            'status' => 'pending_payment',
        ]);

        $booking->forceFill(['paymentStatus' => 'completed'])->save();
        $unit = BookingTicket::create([
            'public_id' => (string) Str::uuid(), 'booking_id' => $booking->id,
            'event_id' => 41, 'organizer_id' => 7, 'ticket_id' => $ticket->id,
            'sequence' => 1, 'variation_name' => 'Reserved VIP', 'status' => 'issued',
        ]);
        $service->assign($token, $booking->fresh(), collect([$unit]));

        $this->assertSame($seat->id, $unit->fresh()->seat_id);
        $this->assertSame('sold', $seat->fresh()->status);
        $this->assertDatabaseHas('seat_reservations', [
            'token' => $token,
            'booking_id' => $booking->id,
            'booking_ticket_id' => $unit->id,
            'status' => 'consumed',
        ]);
    }

    public function test_cancelled_or_refunded_numbers_are_invalidated_and_never_reused(): void
    {
        $regular = $this->ticket('Regular', 'REG', 5);
        $first = (new TicketIssuanceService())->issueForBooking($this->booking([
            ['ticket_id' => $regular->id, 'name' => 'Regular', 'qty' => 1],
        ], 1))->first();

        (new TicketNumberAllocator())->invalidate($first, 'refunded');
        $this->assertDatabaseHas('ticket_number_allocations', [
            'booking_ticket_id' => $first->id,
            'ticket_number' => 'REG-001',
            'status' => 'refunded',
        ]);

        $next = (new TicketIssuanceService())->issueForBooking($this->booking([
            ['ticket_id' => $regular->id, 'name' => 'Regular', 'qty' => 1],
        ], 1))->first();

        $this->assertSame('REG-002', $next->ticket_number);
        $this->assertDatabaseCount('ticket_number_allocations', 2);
    }

    public function test_ticket_minimum_maximum_and_sales_window_are_enforced_server_side(): void
    {
        $ticket = $this->ticket('Regular', 'REG', 10);
        $ticket->forceFill([
            'min_buy_ticket' => 2,
            'max_ticket_buy_type' => 'limited',
            'max_buy_ticket' => 3,
            'sales_start_at' => now()->subMinute(),
            'sales_end_at' => now()->addHour(),
        ])->save();
        $limits = new EventTicketPurchaseLimitService();

        $limits->assertCanSelect($ticket->fresh(), null, 2, null);

        foreach ([[1, 'at least 2'], [4, 'purchase limit']] as [$quantity, $message]) {
            try {
                $limits->assertCanSelect($ticket->fresh(), null, $quantity, null);
                $this->fail('Expected the ticket quantity boundary to be rejected.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString($message, $exception->getMessage());
            }
        }

        $ticket->forceFill(['sales_start_at' => now()->addHour()])->save();
        try {
            $limits->assertCanSelect($ticket->fresh(), null, 2, null);
            $this->fail('Expected a future sales window to reject checkout.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('have not started', $exception->getMessage());
        }

        $ticket->forceFill(['sales_start_at' => now()->subHours(2), 'sales_end_at' => now()->subHour()])->save();
        try {
            $limits->assertCanSelect($ticket->fresh(), null, 2, null);
            $this->fail('Expected a closed sales window to reject checkout.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('have ended', $exception->getMessage());
        }
    }

    public function test_module_source_keeps_concurrency_locks_dynamic_ticket_fields_and_shared_admin_builder(): void
    {
        $allocator = file_get_contents(app_path('Services/TicketNumberAllocator.php'));
        $issuance = file_get_contents(app_path('Services/TicketIssuanceService.php'));
        $client = file_get_contents(public_path('assets/tica-ui/js/tica.js'));
        $styles = file_get_contents(public_path('assets/tica-ui/css/tica.css'));
        $adminRoutes = file_get_contents(base_path('routes/admin.php'));
        $organizerController = file_get_contents(app_path('Http/Controllers/FrontEnd/TicaWorkspaceController.php'));
        $adminController = file_get_contents(app_path('Http/Controllers/BackEnd/AdvancedTicketController.php'));
        $builderController = file_get_contents(app_path('Http/Controllers/FrontEnd/TicaOrganizerController.php'));
        $quoteService = file_get_contents(app_path('Services/EventCheckoutQuoteService.php'));
        $manualPayment = file_get_contents(app_path('Services/ManualBookingPaymentService.php'));

        $this->assertStringContainsString('lockForUpdate()', $allocator);
        $this->assertStringContainsString('insertOrIgnore', $allocator);
        $this->assertStringContainsString("DB::transaction", $issuance);
        $this->assertStringContainsString("['logo','attendee','category','number','section','row','seat','date','venue','status']", $client);
        $this->assertStringContainsString('ce-design-logo', $client);
        $this->assertStringContainsString('ce-design-section', $client);
        $this->assertStringContainsString('ce-design-row', $client);
        $this->assertStringContainsString("show('logo')&&e.image", $client);
        $this->assertStringContainsString('label[for^="c-teb"]:not([for^="c-tebe"])', $client);
        $this->assertStringContainsString("inf.seatingMode==='reserved'?'Choose your seat from the live seating map.'", $client);
        $this->assertStringContainsString("if(S.stage==='details'&&reservedCount()>0)S.stage='choose'", $client);
        $this->assertStringContainsString('.page[data-page=dashboard] .tk{display:grid;grid-template-columns:minmax(0,1fr)', $styles);
        $this->assertStringContainsString('.page[data-page=dashboard] .tk .t-toolbar>*{min-width:0;width:100%}', $styles);
        $this->assertStringContainsString("'seatingMode' =>", file_get_contents(app_path('Services/TicaUi/OrganizerWorkspace.php')));
        $this->assertStringContainsString('ComplimentaryTicketService::class', $organizerController);
        $this->assertStringContainsString('ComplimentaryTicketService::class', $adminController);
        $this->assertStringContainsString("'tiers.*.design_settings.show_logo'", $builderController);
        $this->assertStringContainsString("'tiers.*.design_settings.show_section'", $builderController);
        $this->assertStringContainsString("'tiers.*.design_settings.show_row'", $builderController);
        $this->assertStringContainsString("'price_override' => null", $builderController);
        $this->assertStringContainsString('$seat->section?->price_override', $quoteService);
        $this->assertStringContainsString('$booking->ticket_reservation_token ?: null', $manualPayment);
        $this->assertStringContainsString("'payment_rejected'", $manualPayment);
        $this->assertStringContainsString("\$show('logo')", file_get_contents(resource_path('views/frontend/event/invoice.blade.php')));
        $this->assertStringContainsString("TicaUiController@adminCreate", $adminRoutes);
        $this->assertStringContainsString("TicaOrganizerController@updateAdminEvent", $adminRoutes);
        $this->assertStringContainsString("TicketNumberAllocator::class)->invalidate", file_get_contents(app_path('Services/PaymentRefundService.php')));
        $this->assertStringContainsString("TicketNumberAllocator::class)->invalidate", file_get_contents(app_path('Services/TicketLifecycleService.php')));
        $this->assertStringContainsString("app(TicketReservationService::class)->release", file_get_contents(app_path('Services/StripePaymentRecoveryService.php')));
        $this->assertStringContainsString("app(TicketReservationService::class)->release", file_get_contents(app_path('Services/MonnifyPaymentRecoveryService.php')));
    }

    private function ticket(string $title, string $prefix, int $available, string $seatingMode = 'general_admission'): Ticket
    {
        return Ticket::create([
            'event_id' => 41, 'title' => $title, 'pricing_type' => 'normal',
            'ticket_available_type' => 'limited', 'ticket_available' => $available,
            'max_ticket_buy_type' => 'unlimited', 'min_buy_ticket' => 1,
            'sales_status' => 'on_sale', 'visibility' => 'public',
            'numbering_enabled' => true, 'numbering_scope' => 'category',
            'number_prefix' => $prefix, 'number_start' => 1, 'number_padding' => 3,
            'number_format' => '{PREFIX}-{NUMBER}', 'seating_mode' => $seatingMode,
        ]);
    }

    private function booking(array $selections, int $quantity, string $paymentStatus = 'completed'): Booking
    {
        return Booking::create([
            'booking_id' => (string) Str::uuid(), 'event_id' => 41, 'organizer_id' => 7,
            'fname' => 'Ada', 'lname' => 'Okafor', 'email' => 'ada@example.com',
            'variation' => json_encode($selections), 'quantity' => $quantity,
            'scan_status' => 0, 'paymentStatus' => $paymentStatus,
        ]);
    }

    private function seat(Ticket $ticket): array
    {
        $section = SeatingSection::create([
            'event_id' => 41, 'name' => 'VIP', 'kind' => 'seated',
            'ticket_id' => $ticket->id, 'sort_order' => 1,
        ]);
        $row = SeatingRow::create([
            'event_id' => 41, 'section_id' => $section->id, 'label' => 'A', 'sort_order' => 1,
        ]);
        $seat = EventSeat::create([
            'public_id' => (string) Str::uuid(), 'event_id' => 41,
            'section_id' => $section->id, 'row_id' => $row->id, 'ticket_id' => $ticket->id,
            'label' => '01', 'status' => 'available', 'sort_order' => 1,
        ]);

        return [$section, $row, $seat];
    }

    private function createSchema(): void
    {
        Schema::create('events', function (Blueprint $table): void {
            $table->id(); $table->unsignedSmallInteger('seat_reservation_minutes')->default(15); $table->timestamps();
        });
        DB::table('events')->insert(['id' => 41, 'seat_reservation_minutes' => 15, 'created_at' => now(), 'updated_at' => now()]);

        Schema::create('tickets', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('event_id'); $table->string('title')->nullable();
            $table->string('pricing_type')->default('normal'); $table->string('ticket_available_type')->default('limited');
            $table->unsignedInteger('ticket_available')->nullable(); $table->string('max_ticket_buy_type')->default('unlimited');
            $table->unsignedInteger('max_buy_ticket')->nullable(); $table->unsignedInteger('min_buy_ticket')->default(1);
            $table->timestamp('sales_start_at')->nullable(); $table->timestamp('sales_end_at')->nullable();
            $table->string('visibility')->default('public'); $table->string('sales_status')->default('on_sale');
            $table->boolean('numbering_enabled')->default(true); $table->string('numbering_scope')->default('category');
            $table->string('number_prefix')->nullable(); $table->unsignedBigInteger('number_start')->default(1);
            $table->unsignedTinyInteger('number_padding')->default(3); $table->string('number_format')->default('{PREFIX}-{NUMBER}');
            $table->string('seating_mode')->default('general_admission'); $table->json('design_settings')->nullable();
            $table->text('variations')->nullable(); $table->timestamps(); $table->softDeletes();
        });
        Schema::create('bookings', function (Blueprint $table): void {
            $table->id(); $table->uuid('booking_id')->unique(); $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('event_id'); $table->unsignedBigInteger('organizer_id')->nullable();
            $table->unsignedBigInteger('ticket_id')->nullable(); $table->string('fname')->nullable();
            $table->string('lname')->nullable(); $table->string('email')->nullable(); $table->char('buyer_email_hash', 64)->nullable();
            $table->text('variation')->nullable(); $table->unsignedInteger('quantity'); $table->integer('scan_status')->default(0);
            $table->string('paymentStatus')->nullable(); $table->timestamps();
            $table->uuid('ticket_reservation_token')->nullable();
        });
        Schema::create('booking_tickets', function (Blueprint $table): void {
            $table->id(); $table->uuid('public_id')->unique(); $table->unsignedBigInteger('booking_id');
            $table->unsignedBigInteger('event_id'); $table->unsignedBigInteger('organizer_id')->nullable();
            $table->unsignedBigInteger('ticket_id')->nullable(); $table->unsignedInteger('sequence');
            $table->string('variation_name')->nullable(); $table->string('holder_name')->nullable();
            $table->string('holder_email')->nullable(); $table->string('status')->default('issued');
            $table->string('ticket_number', 80)->nullable(); $table->unsignedBigInteger('ticket_number_value')->nullable();
            $table->unsignedBigInteger('seat_id')->nullable()->unique(); $table->string('seat_label', 40)->nullable();
            $table->string('row_label', 40)->nullable(); $table->string('section_label', 120)->nullable();
            $table->timestamp('checked_in_at')->nullable(); $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_reason')->nullable(); $table->timestamps();
            $table->unique(['booking_id', 'sequence']); $table->unique(['event_id', 'ticket_number']);
        });
        Schema::create('ticket_number_sequences', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('event_id'); $table->unsignedBigInteger('ticket_id')->nullable();
            $table->string('scope_key'); $table->unsignedBigInteger('next_number'); $table->timestamps();
            $table->unique(['event_id', 'scope_key']);
        });
        Schema::create('ticket_number_allocations', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('event_id'); $table->unsignedBigInteger('ticket_id');
            $table->unsignedBigInteger('booking_ticket_id')->unique(); $table->unsignedBigInteger('number_value');
            $table->string('ticket_number'); $table->string('status')->default('allocated');
            $table->timestamp('allocated_at'); $table->timestamp('invalidated_at')->nullable(); $table->timestamps();
            $table->unique(['event_id', 'ticket_number']); $table->unique(['ticket_id', 'number_value']);
        });
        Schema::create('seating_sections', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('event_id'); $table->string('name'); $table->string('kind')->default('seated');
            $table->unsignedBigInteger('ticket_id')->nullable(); $table->decimal('price_override', 18, 2)->nullable();
            $table->unsignedInteger('capacity')->nullable(); $table->unsignedInteger('sort_order')->default(0);
            $table->json('layout')->nullable(); $table->timestamps();
        });
        Schema::create('seating_rows', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('event_id'); $table->unsignedBigInteger('section_id');
            $table->string('label'); $table->unsignedInteger('sort_order')->default(0); $table->timestamps();
        });
        Schema::create('event_seats', function (Blueprint $table): void {
            $table->id(); $table->uuid('public_id')->unique(); $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('section_id'); $table->unsignedBigInteger('row_id')->nullable();
            $table->unsignedBigInteger('ticket_id')->nullable(); $table->string('label');
            $table->string('status')->default('available'); $table->boolean('is_accessible')->default(false);
            $table->decimal('price_override', 18, 2)->nullable(); $table->unsignedInteger('sort_order')->default(0); $table->timestamps();
        });
        Schema::create('seat_reservations', function (Blueprint $table): void {
            $table->id(); $table->uuid('token'); $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('ticket_id'); $table->unsignedBigInteger('seat_id'); $table->string('status')->default('active');
            $table->string('session_hash', 64)->nullable(); $table->unsignedBigInteger('customer_id')->nullable();
            $table->char('buyer_email_hash', 64)->nullable(); $table->unsignedBigInteger('booking_id')->nullable();
            $table->unsignedBigInteger('booking_ticket_id')->nullable()->unique(); $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable(); $table->timestamp('released_at')->nullable(); $table->timestamps();
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
