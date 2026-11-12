<?php

namespace Tests\Unit;

use App\Http\Controllers\BackEnd\Organizer\OrganizerController;
use App\Models\Event\Booking;
use App\Models\Event\BookingTicket;
use App\Models\Organizer;
use App\Models\ScannerDevice;
use App\Models\ScannerStaff;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class IndividualTicketCheckInTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('tickets.signing_key', 'ticket-check-in-test-key');
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->createSchema();
    }

    public function test_units_check_in_independently_and_booking_completes_after_last_unit(): void
    {
        $organizer = $this->organizer();
        Auth::guard('organizer')->login($organizer);
        $booking = $this->booking($organizer->id);
        $first = $this->unit($booking, 1);
        $second = $this->unit($booking, 2);
        $controller = new OrganizerController();

        $response = $controller->check_qrcode($this->scanRequest($first));
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(1, $response->getData(true)['remaining']);
        $this->assertSame(0, (int) $booking->fresh()->scan_status);

        $duplicate = $controller->check_qrcode($this->scanRequest($first));
        $this->assertSame(409, $duplicate->getStatusCode());

        $response = $controller->check_qrcode($this->scanRequest($second));
        $this->assertSame(0, $response->getData(true)['remaining']);
        $this->assertSame(1, (int) $booking->fresh()->scan_status);
        $this->assertSame(2, DB::table('audit_logs')->where('action', 'ticket_unit.checked_in')->count());
        $this->assertSame(2, DB::table('funnel_analytics_events')->where('event_name', 'ticket_scanned')->count());
    }

    public function test_ticket_cannot_be_scanned_by_another_organizer(): void
    {
        $owner = $this->organizer();
        $other = $this->organizer();
        $unit = $this->unit($this->booking($owner->id, 1), 1);
        Auth::guard('organizer')->login($other);

        $response = (new OrganizerController())->check_qrcode($this->scanRequest($unit));
        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('issued', $unit->fresh()->status);
    }

    public function test_explicitly_empty_check_in_source_is_rejected_without_admitting_ticket(): void
    {
        $organizer = $this->organizer();
        Auth::guard('organizer')->login($organizer);
        $unit = $this->unit($this->booking($organizer->id, 1), 1);
        $request = $this->scanRequest($unit);
        $request->merge(['source' => null]);

        try {
            (new OrganizerController())->check_qrcode($request);
            $this->fail('An explicitly empty check-in source must fail validation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('source', $exception->errors());
        }

        $this->assertSame('issued', $unit->fresh()->status);
        $this->assertNull($unit->fresh()->checked_in_at);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseCount('funnel_analytics_events', 0);
    }

    public function test_unitless_or_partial_booking_cannot_bypass_admission_ledger(): void
    {
        $organizer = $this->organizer();
        Auth::guard('organizer')->login($organizer);
        $unitless = $this->booking($organizer->id, 1);
        $controller = new OrganizerController();

        $legacyRequest = Request::create('/organizer/check-qrcode', 'POST', [
            'booking_id' => signedTicketPayload($unitless->booking_id, $unitless->event_id),
        ]);
        $legacyRequest->attributes->set('scanner_device', $this->scannerDevice($organizer->id));
        $missingResponse = $controller->check_qrcode($legacyRequest);

        $this->assertSame(409, $missingResponse->getStatusCode());
        $this->assertSame('ticket_units_inconsistent', $missingResponse->getData(true)['code']);
        $this->assertSame(0, (int) $unitless->fresh()->scan_status);

        $partial = $this->booking($organizer->id, 2);
        $onlyUnit = $this->unit($partial, 1);
        $partialResponse = $controller->check_qrcode($this->scanRequest($onlyUnit));

        $this->assertSame(409, $partialResponse->getStatusCode());
        $this->assertSame('ticket_units_inconsistent', $partialResponse->getData(true)['code']);
        $this->assertSame('issued', $onlyUnit->fresh()->status);
        $this->assertSame(0, (int) $partial->fresh()->scan_status);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseCount('funnel_analytics_events', 0);
    }

    public function test_single_unit_legacy_qr_remains_compatible_after_backfill(): void
    {
        $organizer = $this->organizer();
        Auth::guard('organizer')->login($organizer);
        $booking = $this->booking($organizer->id, 1);
        $unit = $this->unit($booking, 1);
        $request = Request::create('/organizer/check-qrcode', 'POST', [
            'booking_id' => signedTicketPayload($booking->booking_id, $booking->event_id),
        ]);
        $request->attributes->set('scanner_device', $this->scannerDevice($organizer->id));

        $response = (new OrganizerController())->check_qrcode($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('checked_in', $unit->fresh()->status);
        $this->assertSame(1, (int) $booking->fresh()->scan_status);
    }

    public function test_manual_lookup_is_organizer_scoped_and_manual_entry_uses_same_check_in_path(): void
    {
        $owner = $this->organizer();
        $other = $this->organizer();
        $ownedUnit = $this->unit($this->booking($owner->id, 1), 1);
        $this->unit($this->booking($other->id, 1), 1);
        Auth::guard('organizer')->login($owner);
        $controller = new OrganizerController();

        $lookup = $controller->ticketLookup(Request::create('/organizer/scanner/ticket-lookup', 'POST', [
            'query' => 'ada@example.com',
        ]));
        $matches = $lookup->getData(true)['tickets'];
        $this->assertCount(1, $matches);
        $this->assertSame($ownedUnit->id, $matches[0]['id']);

        $manualRequest = Request::create('/organizer/scanner/manual-check-in', 'POST', [
            'ticket_unit_id' => $ownedUnit->id,
        ]);
        $manualRequest->attributes->set('scanner_device', $this->scannerDevice($owner->id));
        $staff = $this->scannerStaff($owner->id);
        $manualRequest->attributes->set('scanner_staff', $staff);
        $response = $controller->manualCheckIn($manualRequest);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('checked_in', $ownedUnit->fresh()->status);
        $this->assertSame($staff->id, $ownedUnit->fresh()->checked_in_scanner_staff_id);
        $this->assertSame('manual', $ownedUnit->fresh()->checked_in_source);

        $audit = DB::table('audit_logs')->where('action', 'ticket_unit.checked_in')->first();
        $metadata = json_decode($audit->metadata, true);
        $this->assertSame('manual', $metadata['source']);
        $this->assertSame('Test gate', $metadata['scanner_device_name']);
        $this->assertSame($staff->id, $metadata['scanner_staff_id']);
        $this->assertSame('Gate attendant', $metadata['scanner_staff_name']);

        $duplicateRequest = Request::create('/organizer/scanner/manual-check-in', 'POST', [
            'ticket_unit_id' => $ownedUnit->id,
        ]);
        $duplicateRequest->attributes->set('scanner_device', $this->scannerDevice($owner->id));
        $duplicate = $controller->manualCheckIn($duplicateRequest);
        $this->assertSame(409, $duplicate->getStatusCode());
    }

    public function test_second_gate_attempt_is_recorded_without_changing_first_admission(): void
    {
        $organizer = $this->organizer();
        Auth::guard('organizer')->login($organizer);
        $unit = $this->unit($this->booking($organizer->id, 1), 1);
        $mainGate = $this->device($organizer->id, 'Main gate');
        $vipGate = $this->device($organizer->id, 'VIP gate');
        $mainStaff = $this->scannerStaff($organizer->id);
        $vipStaff = $this->scannerStaff($organizer->id);
        $controller = new OrganizerController();

        $first = $controller->check_qrcode($this->scanRequestForDevice($unit, $mainGate, $mainStaff));
        $this->assertSame(200, $first->getStatusCode());
        $this->assertSame($mainGate->id, $unit->fresh()->checked_in_scanner_device_id);

        $duplicate = $controller->check_qrcode($this->scanRequestForDevice($unit, $vipGate, $vipStaff));
        $this->assertSame(409, $duplicate->getStatusCode());
        $this->assertTrue($duplicate->getData(true)['multi_gate_conflict']);
        $this->assertSame('Main gate', $duplicate->getData(true)['checked_in_gate']);
        $this->assertDatabaseHas('scanner_check_in_conflicts', [
            'booking_ticket_id' => $unit->id,
            'original_scanner_device_id' => $mainGate->id,
            'attempted_scanner_device_id' => $vipGate->id,
            'original_scanner_staff_id' => $mainStaff->id,
            'attempted_scanner_staff_id' => $vipStaff->id,
            'occurrences' => 1,
        ]);

        $controller->check_qrcode($this->scanRequestForDevice($unit, $vipGate, $vipStaff));
        $this->assertSame(2, (int) DB::table('scanner_check_in_conflicts')->value('occurrences'));

        $sameGate = $controller->check_qrcode($this->scanRequestForDevice($unit, $mainGate));
        $this->assertFalse($sameGate->getData(true)['multi_gate_conflict']);
        $this->assertSame(1, DB::table('scanner_check_in_conflicts')->count());
    }

    private function scanRequest(BookingTicket $unit): Request
    {
        return $this->scanRequestForDevice($unit, $this->scannerDevice($unit->organizer_id));
    }

    private function scanRequestForDevice(BookingTicket $unit, ScannerDevice $device, ?ScannerStaff $staff = null): Request
    {
        $request = Request::create('/organizer/check-qrcode', 'POST', [
            'booking_id' => signedTicketUnitPayload($unit->public_id, $unit->event_id),
        ]);
        $request->attributes->set('scanner_device', $device);
        if ($staff) {
            $request->attributes->set('scanner_staff', $staff);
        }
        return $request;
    }

    private function scannerDevice(int $organizerId): ScannerDevice
    {
        return $this->device($organizerId, 'Test gate');
    }

    private function scannerStaff(int $organizerId): ScannerStaff
    {
        return ScannerStaff::create([
            'organizer_id' => $organizerId,
            'name' => 'Gate attendant',
            'email' => Str::uuid().'@example.com',
            'password' => 'hash',
            'role' => 'check_in',
            'status' => 'active',
        ]);
    }

    private function device(int $organizerId, string $name): ScannerDevice
    {
        return ScannerDevice::firstOrCreate(
            ['organizer_id' => $organizerId, 'name' => $name],
            ['public_id' => (string) Str::uuid(), 'secret_hash' => hash('sha256', 'test-secret'), 'status' => 'active']
        );
    }

    private function organizer(): Organizer
    {
        return Organizer::create([
            'name' => 'Organizer', 'username' => (string) Str::uuid(),
            'email' => Str::uuid() . '@example.com', 'password' => 'x', 'status' => 1,
        ]);
    }

    private function booking(int $organizerId, int $quantity = 2): Booking
    {
        return Booking::create([
            'booking_id' => (string) Str::uuid(), 'event_id' => 50, 'organizer_id' => $organizerId,
            'fname' => 'Ada', 'lname' => 'Okafor', 'email' => 'ada@example.com', 'quantity' => $quantity,
            'scan_status' => 0, 'paymentStatus' => 'completed', 'refund_status' => null,
        ]);
    }

    private function unit(Booking $booking, int $sequence): BookingTicket
    {
        return BookingTicket::create([
            'public_id' => (string) Str::uuid(), 'booking_id' => $booking->id,
            'event_id' => $booking->event_id, 'organizer_id' => $booking->organizer_id,
            'sequence' => $sequence, 'holder_name' => 'Ada Okafor',
            'holder_email' => 'ada@example.com', 'status' => 'issued',
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('organizers', function (Blueprint $table) {
            $table->id(); $table->string('name')->nullable(); $table->string('username'); $table->string('email');
            $table->string('password'); $table->integer('status'); $table->rememberToken(); $table->timestamps();
        });
        Schema::create('bookings', function (Blueprint $table) {
            $table->id(); $table->string('booking_id'); $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('organizer_id')->nullable(); $table->string('fname')->nullable();
            $table->string('lname')->nullable(); $table->string('email')->nullable(); $table->unsignedInteger('quantity');
            $table->integer('scan_status')->default(0); $table->string('paymentStatus')->nullable();
            $table->string('refund_status')->nullable(); $table->timestamps();
        });
        Schema::create('booking_tickets', function (Blueprint $table) {
            $table->id(); $table->uuid('public_id')->unique(); $table->unsignedBigInteger('booking_id');
            $table->unsignedBigInteger('event_id'); $table->unsignedBigInteger('organizer_id')->nullable();
            $table->unsignedBigInteger('ticket_id')->nullable(); $table->unsignedInteger('sequence');
            $table->string('variation_name')->nullable(); $table->string('holder_name')->nullable();
            $table->string('holder_email')->nullable(); $table->string('status')->default('issued');
            $table->timestamp('checked_in_at')->nullable(); $table->unsignedBigInteger('checked_in_by_organizer_id')->nullable();
            $table->unsignedBigInteger('checked_in_scanner_device_id')->nullable();
            $table->unsignedBigInteger('checked_in_scanner_staff_id')->nullable();
            $table->string('checked_in_source', 32)->nullable();
            $table->timestamp('revoked_at')->nullable(); $table->string('revoked_reason')->nullable(); $table->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id(); $table->string('actor_type'); $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action'); $table->string('subject_type')->nullable(); $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('before')->nullable(); $table->json('after')->nullable(); $table->json('metadata')->nullable();
            $table->string('request_id')->nullable(); $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable(); $table->timestamp('created_at')->nullable();
        });
        Schema::create('scanner_devices', function (Blueprint $table) {
            $table->id(); $table->uuid('public_id')->unique(); $table->unsignedBigInteger('organizer_id');
            $table->string('name'); $table->char('secret_hash', 64); $table->string('status')->default('active');
            $table->timestamp('last_seen_at')->nullable(); $table->string('last_ip')->nullable();
            $table->text('last_user_agent')->nullable(); $table->timestamp('revoked_at')->nullable();
            $table->unsignedBigInteger('revoked_by_organizer_id')->nullable(); $table->timestamps();
        });
        Schema::create('scanner_staff', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('organizer_id'); $table->string('name');
            $table->string('email')->unique(); $table->string('password'); $table->string('role');
            $table->string('status'); $table->unsignedInteger('auth_version')->default(1);
            $table->timestamp('last_login_at')->nullable(); $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable(); $table->rememberToken(); $table->timestamps();
        });
        Schema::create('scanner_check_in_conflicts', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('organizer_id'); $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('booking_ticket_id'); $table->unsignedBigInteger('original_scanner_device_id');
            $table->unsignedBigInteger('attempted_scanner_device_id');
            $table->unsignedBigInteger('original_scanner_staff_id')->nullable();
            $table->unsignedBigInteger('attempted_scanner_staff_id')->nullable();
            $table->string('source');
            $table->unsignedInteger('occurrences')->default(1); $table->timestamp('first_detected_at');
            $table->timestamp('last_detected_at'); $table->timestamp('resolved_at')->nullable();
            $table->unsignedBigInteger('resolved_by_organizer_id')->nullable(); $table->string('resolution_note')->nullable();
            $table->timestamps();
        });
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
