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
        $this->assertSame('TICA Test Night', $response->getData(true)['event']);
        $this->assertSame('REG-001', $response->getData(true)['ticket_number']);
        $this->assertSame('01', $response->getData(true)['seat']);
        $this->assertSame('A', $response->getData(true)['row']);
        $this->assertSame('Regular', $response->getData(true)['section']);
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

    public function test_organizer_can_check_in_tickets_from_two_owned_events_without_an_enrolled_device(): void
    {
        $owner = $this->organizer();
        Auth::guard('organizer')->login($owner);
        $first = $this->unit($this->booking($owner->id, 1), 1);
        $second = $this->unit($this->booking($owner->id, 1, 51), 1);
        $controller = new OrganizerController();

        $request = $this->organizerScanRequest('scan', [
            'booking_id' => signedTicketUnitPayload($second->public_id, $second->event_id),
            'event_id' => $first->event_id,
        ]);
        $this->assertSame(404, $controller->check_qrcode($request)->getStatusCode());
        $this->assertSame('issued', $second->fresh()->status);

        $scan = $this->organizerScanRequest('scan', [
            'booking_id' => signedTicketUnitPayload($first->public_id, $first->event_id),
            'event_id' => $first->event_id,
        ]);
        $this->assertSame(200, $controller->check_qrcode($scan)->getStatusCode());
        $this->assertSame(409, $controller->check_qrcode($scan)->getStatusCode());

        $lookup = $this->organizerScanRequest('lookup', ['query' => 'Ada', 'event_id' => $second->event_id]);
        $this->assertSame([$second->id], array_column($controller->ticketLookup($lookup)->getData(true)['tickets'], 'id'));
        $manual = $this->organizerScanRequest('manual', ['ticket_unit_id' => $second->id, 'event_id' => $second->event_id]);
        $this->assertSame(200, $controller->manualCheckIn($manual)->getStatusCode());

        $this->assertSame((int) $owner->id, (int) $second->fresh()->checked_in_by_organizer_id);
        $this->assertNull($second->fresh()->checked_in_scanner_device_id);
        $this->assertSame('manual', $second->fresh()->checked_in_source);
        $this->assertDatabaseCount('scanner_devices', 0);
    }

    public function test_organizer_online_scanner_refuses_an_event_owned_by_someone_else(): void
    {
        $owner = $this->organizer();
        $other = $this->organizer();
        $unit = $this->unit($this->booking($owner->id, 1), 1);
        Auth::guard('organizer')->login($other);
        $request = $this->organizerScanRequest('scan', [
            'booking_id' => signedTicketUnitPayload($unit->public_id, $unit->event_id),
            'event_id' => $unit->event_id,
        ]);

        try {
            (new OrganizerController())->check_qrcode($request);
            $this->fail('Only the event organizer may scan this ticket.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertSame('issued', $unit->fresh()->status);
    }

    public function test_missing_event_selection_is_rejected_without_admitting_ticket(): void
    {
        $organizer = $this->organizer();
        Auth::guard('organizer')->login($organizer);
        $unit = $this->unit($this->booking($organizer->id, 1), 1);
        $request = $this->scanRequest($unit);
        $request->merge(['event_id' => null]);

        try {
            (new OrganizerController())->check_qrcode($request);
            $this->fail('An explicitly empty event assignment must fail validation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('event_id', $exception->errors());
        }

        $this->assertSame('issued', $unit->fresh()->status);
        $this->assertNull($unit->fresh()->checked_in_at);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseCount('funnel_analytics_events', 0);
    }

    public function test_wrong_event_gate_or_admission_window_leaves_ticket_issued(): void
    {
        $organizer = $this->organizer();
        Auth::guard('organizer')->login($organizer);
        $unit = $this->unit($this->booking($organizer->id, 1), 1);
        $controller = new OrganizerController();
        $device = $this->scannerDevice($organizer->id);

        $wrongEvent = $this->scanRequestForDevice($unit, $device);
        $wrongEvent->merge(['booking_id' => signedTicketUnitPayload($unit->public_id, 51)]);
        $this->assertSame(404, $controller->check_qrcode($wrongEvent)->getStatusCode());

        $device->forceFill(['admission_ticket_id' => 123])->save();
        $this->assertSame(403, $controller->check_qrcode($this->scanRequestForDevice($unit, $device))->getStatusCode());

        $device->forceFill(['admission_ticket_id' => null])->save();
        DB::table('events')->where('id', 50)->update(['start_date' => now()->addDays(2)->toDateString(), 'end_date' => now()->addDays(2)->toDateString()]);
        $this->assertSame(403, $controller->check_qrcode($this->scanRequestForDevice($unit, $device))->getStatusCode());
        $this->assertSame('issued', $unit->fresh()->status);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_unitless_or_partial_booking_cannot_bypass_admission_ledger(): void
    {
        $organizer = $this->organizer();
        Auth::guard('organizer')->login($organizer);
        $unitless = $this->booking($organizer->id, 1);
        $controller = new OrganizerController();

        $legacyRequest = Request::create('/organizer/check-qrcode', 'POST', [
            'booking_id' => signedTicketPayload($unitless->booking_id, $unitless->event_id),
            'event_id' => $unitless->event_id,
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
            'event_id' => $booking->event_id,
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

        $lookup = $controller->ticketLookup($this->lookupRequest($ownedUnit, 'ada@example.com'));
        $matches = $lookup->getData(true)['tickets'];
        $this->assertCount(1, $matches);
        $this->assertSame($ownedUnit->id, $matches[0]['id']);

        $numberLookup = $controller->ticketLookup($this->lookupRequest($ownedUnit, 'REG-001'));
        $numberMatch = $numberLookup->getData(true)['tickets'][0];
        $this->assertSame('REG-001', $numberMatch['ticket_number']);
        $this->assertSame('01', $numberMatch['seat']);
        $this->assertSame('A', $numberMatch['row']);
        $this->assertSame('Regular', $numberMatch['section']);

        $manualRequest = Request::create('/organizer/scanner/manual-check-in', 'POST', [
            'ticket_unit_id' => $ownedUnit->id,
            'event_id' => $ownedUnit->event_id,
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
            'event_id' => $ownedUnit->event_id,
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

    public function test_revoked_and_refunded_ticket_units_are_rejected_at_the_gate(): void
    {
        $organizer = $this->organizer();
        Auth::guard('organizer')->login($organizer);
        $controller = new OrganizerController();

        foreach (['revoked', 'refunded'] as $status) {
            $unit = $this->unit($this->booking($organizer->id, 1), 1);
            $unit->forceFill(['status' => $status])->save();

            $response = $controller->check_qrcode($this->scanRequest($unit));

            $this->assertSame(422, $response->getStatusCode());
            $this->assertStringContainsString($status, $response->getData(true)['message']);
            $this->assertNull($unit->fresh()->checked_in_at);
        }
    }

    private function organizerScanRequest(string $action, array $input): Request
    {
        $name = 'organizer.scanner.online.' . $action;
        $request = Request::create(route($name), 'POST', $input);
        $request->setRouteResolver(fn () => app('router')->getRoutes()->getByName($name));

        return $request;
    }

    private function scanRequest(BookingTicket $unit): Request
    {
        return $this->scanRequestForDevice($unit, $this->scannerDevice(Auth::guard('organizer')->id()));
    }

    private function lookupRequest(BookingTicket $unit, string $query): Request
    {
        $request = Request::create('/scanner/ticket-lookup', 'POST', ['query' => $query, 'event_id' => $unit->event_id]);
        $request->attributes->set('scanner_device', $this->scannerDevice($unit->organizer_id));
        return $request;
    }

    private function scanRequestForDevice(BookingTicket $unit, ScannerDevice $device, ?ScannerStaff $staff = null): Request
    {
        $request = Request::create('/organizer/check-qrcode', 'POST', [
            'booking_id' => signedTicketUnitPayload($unit->public_id, $unit->event_id),
            'event_id' => $unit->event_id,
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
            'auth_version' => 1,
        ]);
    }

    private function device(int $organizerId, string $name): ScannerDevice
    {
        $device = ScannerDevice::firstOrCreate(
            ['organizer_id' => $organizerId, 'name' => $name],
            ['public_id' => (string) Str::uuid(), 'secret_hash' => hash('sha256', 'test-secret'), 'status' => 'active']
        );
        $device->forceFill(['event_id' => 50])->save();
        return $device;
    }

    private function organizer(): Organizer
    {
        return Organizer::create([
            'name' => 'Organizer', 'username' => (string) Str::uuid(),
            'email' => Str::uuid() . '@example.com', 'password' => 'x', 'status' => 1,
        ]);
    }

    private function booking(int $organizerId, int $quantity = 2, int $eventId = 50): Booking
    {
        DB::table('events')->insertOrIgnore([
            'id' => $eventId, 'organizer_id' => $organizerId, 'status' => 1,
            'start_date' => now()->subDay()->toDateString(), 'start_time' => '00:00:00',
            'end_date' => now()->addDay()->toDateString(), 'end_time' => '23:59:59', 'timezone' => config('app.timezone'),
        ]);
        DB::table('event_contents')->updateOrInsert(
            ['event_id' => $eventId, 'language_id' => 1],
            ['title' => 'TICA Test Night']
        );

        return Booking::create([
            'booking_id' => (string) Str::uuid(), 'event_id' => $eventId, 'organizer_id' => $organizerId,
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
            'ticket_number' => 'REG-' . str_pad((string) $sequence, 3, '0', STR_PAD_LEFT),
            'seat_label' => str_pad((string) $sequence, 2, '0', STR_PAD_LEFT),
            'row_label' => 'A', 'section_label' => 'Regular',
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
        Schema::create('event_contents', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('event_id'); $table->unsignedBigInteger('language_id');
            $table->string('title');
        });
        Schema::create('events', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('organizer_id'); $table->integer('status');
            $table->date('start_date'); $table->time('start_time'); $table->date('end_date'); $table->time('end_time');
            $table->string('timezone');
        });
        Schema::create('booking_tickets', function (Blueprint $table) {
            $table->id(); $table->uuid('public_id')->unique(); $table->unsignedBigInteger('booking_id');
            $table->unsignedBigInteger('event_id'); $table->unsignedBigInteger('organizer_id')->nullable();
            $table->unsignedBigInteger('ticket_id')->nullable(); $table->unsignedInteger('sequence');
            $table->string('variation_name')->nullable(); $table->string('holder_name')->nullable();
            $table->string('holder_email')->nullable(); $table->string('status')->default('issued');
            $table->string('ticket_number', 80)->nullable(); $table->string('seat_label', 40)->nullable();
            $table->string('row_label', 40)->nullable(); $table->string('section_label', 120)->nullable();
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
        (require database_path('migrations/2026_11_25_121000_scope_scanner_devices_to_events.php'))->up();
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
