<?php

namespace Tests\Unit;

use App\Models\Event\Booking;
use App\Models\Event\BookingTicket;
use App\Services\TicketIssuanceService;
use App\Services\TicketUnitIntegrityService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class TicketIssuanceServiceTest extends TestCase
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

    public function test_one_traceable_unit_is_issued_per_selected_admission(): void
    {
        $booking = $this->booking([
            ['ticket_id' => 10, 'name' => 'Regular', 'qty' => 2],
            ['ticket_id' => 11, 'name' => 'VIP', 'qty' => 1],
        ], 3);

        $service = new TicketIssuanceService();
        $units = $service->issueForBooking($booking);

        $this->assertCount(3, $units);
        $this->assertSame([1, 2, 3], $units->pluck('sequence')->all());
        $this->assertSame(['Regular', 'Regular', 'VIP'], $units->pluck('variation_name')->all());
        $this->assertSame([10, 10, 11], $units->pluck('ticket_id')->all());
        $this->assertSame(3, $units->pluck('public_id')->unique()->count());
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'booking.tickets_issued')->count());

        $this->assertCount(3, $service->issueForBooking($booking));
        $this->assertDatabaseCount('booking_tickets', 3);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_legacy_scanned_booking_backfills_as_checked_in(): void
    {
        $booking = $this->booking(null, 2, 1);
        $units = (new TicketIssuanceService())->issueForBooking($booking);

        $this->assertSame(['checked_in', 'checked_in'], $units->pluck('status')->all());
        $this->assertNotNull($units->first()->checked_in_at);
    }

    public function test_quantity_mismatch_does_not_partially_issue_units(): void
    {
        $booking = $this->booking([['ticket_id' => 10, 'name' => 'Regular', 'qty' => 1]], 2);

        try {
            (new TicketIssuanceService())->issueForBooking($booking);
            $this->fail('Mismatched ticket allocations must be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('does not match', $exception->getMessage());
        }

        $this->assertDatabaseCount('booking_tickets', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_exact_existing_unit_set_is_idempotent(): void
    {
        $booking = $this->booking([
            ['ticket_id' => 10, 'name' => 'Regular', 'qty' => 1],
            ['ticket_id' => 11, 'name' => 'VIP', 'qty' => 1],
        ], 2);
        $service = new TicketIssuanceService();
        $issued = $service->issueForBooking($booking);

        $again = $service->issueForBooking($booking);

        $this->assertSame($issued->pluck('id')->all(), $again->pluck('id')->all());
        $this->assertDatabaseCount('booking_tickets', 2);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_partial_existing_unit_set_is_rejected_without_mutation(): void
    {
        $booking = $this->booking([['ticket_id' => 10, 'name' => 'Regular', 'qty' => 2]], 2);
        $this->unit($booking, ['ticket_id' => 10, 'variation_name' => 'Regular']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('unit_count_mismatch');

        try {
            (new TicketIssuanceService())->issueForBooking($booking);
        } finally {
            $this->assertDatabaseCount('booking_tickets', 1);
            $this->assertDatabaseCount('audit_logs', 0);
        }
    }

    public function test_existing_unit_with_wrong_admission_identity_is_rejected(): void
    {
        $booking = $this->booking([['ticket_id' => 10, 'name' => 'Regular', 'qty' => 1]], 1);
        $this->unit($booking, [
            'event_id' => 999,
            'organizer_id' => 888,
            'ticket_id' => 777,
            'variation_name' => 'Wrong',
        ]);

        try {
            (new TicketIssuanceService())->issueForBooking($booking);
            $this->fail('Contradictory ticket-unit identity must be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('event_identity_mismatch', $exception->getMessage());
            $this->assertStringContainsString('organizer_identity_mismatch', $exception->getMessage());
            $this->assertStringContainsString('allocation_identity_mismatch', $exception->getMessage());
        }

        $this->assertDatabaseCount('booking_tickets', 1);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_live_integrity_inspection_reports_missing_partial_and_contradictory_history(): void
    {
        $valid = $this->booking(null, 1);
        (new TicketIssuanceService())->issueForBooking($valid);

        $missing = $this->booking(null, 1);
        $partial = $this->booking([['ticket_id' => 10, 'name' => 'Regular', 'qty' => 2]], 2);
        $this->unit($partial, ['ticket_id' => 10, 'variation_name' => 'Regular']);
        $contradictory = $this->booking(null, 1);
        $this->unit($contradictory, ['event_id' => 999]);

        $report = (new TicketUnitIntegrityService(new TicketIssuanceService()))->inspect();

        $this->assertSame(4, $report['total_bookings']);
        $this->assertSame(3, $report['affected_bookings']);
        $this->assertSame(1, $report['issue_counts']['missing_units']);
        $this->assertSame(1, $report['issue_counts']['unit_count_mismatch']);
        $this->assertSame(1, $report['issue_counts']['sequence_mismatch']);
        $this->assertSame(1, $report['issue_counts']['event_identity_mismatch']);
        $this->assertSame(
            [$missing->id, $partial->id, $contradictory->id],
            array_column($report['samples'], 'id')
        );
    }

    public function test_read_only_audit_command_fails_until_missing_units_are_backfilled(): void
    {
        $booking = $this->booking(null, 1);

        $this->assertSame(1, Artisan::call('tickets:audit-booking-units', [
            '--json' => true,
            '--sample' => 1,
        ]));
        $this->assertStringContainsString('"affected_bookings": 1', Artisan::output());

        (new TicketIssuanceService())->issueForBooking($booking);

        $this->assertSame(0, Artisan::call('tickets:audit-booking-units', [
            '--json' => true,
            '--sample' => 1,
        ]));
        $this->assertStringContainsString('"affected_bookings": 0', Artisan::output());
    }

    private function booking(?array $variation, int $quantity, int $scanStatus = 0): Booking
    {
        return Booking::create([
            'booking_id' => uniqid('booking-', true),
            'event_id' => 7,
            'organizer_id' => 8,
            'ticket_id' => 9,
            'fname' => 'Ada',
            'lname' => 'Okafor',
            'email' => 'ada@example.com',
            'variation' => $variation ? json_encode($variation) : null,
            'quantity' => $quantity,
            'scan_status' => $scanStatus,
            'paymentStatus' => 'completed',
        ]);
    }

    private function unit(Booking $booking, array $overrides = []): BookingTicket
    {
        return BookingTicket::create(array_merge([
            'public_id' => uniqid('ticket-', true),
            'booking_id' => $booking->id,
            'event_id' => $booking->event_id,
            'organizer_id' => $booking->organizer_id,
            'ticket_id' => $booking->ticket_id,
            'sequence' => 1,
            'variation_name' => null,
            'holder_name' => 'Ada Okafor',
            'holder_email' => 'ada@example.com',
            'status' => 'issued',
        ], $overrides));
    }

    private function createSchema(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id(); $table->string('booking_id'); $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('organizer_id')->nullable(); $table->unsignedBigInteger('ticket_id')->nullable();
            $table->string('fname')->nullable(); $table->string('lname')->nullable(); $table->string('email')->nullable();
            $table->text('variation')->nullable(); $table->unsignedInteger('quantity'); $table->integer('scan_status')->default(0);
            $table->string('paymentStatus')->nullable(); $table->timestamps();
        });
        Schema::create('booking_tickets', function (Blueprint $table) {
            $table->id(); $table->uuid('public_id')->unique(); $table->unsignedBigInteger('booking_id')->index();
            $table->unsignedBigInteger('event_id'); $table->unsignedBigInteger('organizer_id')->nullable();
            $table->unsignedBigInteger('ticket_id')->nullable(); $table->unsignedInteger('sequence');
            $table->string('variation_name')->nullable(); $table->string('holder_name')->nullable();
            $table->string('holder_email')->nullable(); $table->string('status')->default('issued');
            $table->timestamp('checked_in_at')->nullable(); $table->unsignedBigInteger('checked_in_by_organizer_id')->nullable();
            $table->timestamp('revoked_at')->nullable(); $table->string('revoked_reason')->nullable(); $table->timestamps();
            $table->unique(['booking_id', 'sequence']);
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id(); $table->string('actor_type'); $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action'); $table->string('subject_type')->nullable(); $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('before')->nullable(); $table->json('after')->nullable(); $table->json('metadata')->nullable();
            $table->string('request_id')->nullable(); $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable(); $table->timestamp('created_at')->nullable();
        });
    }
}
