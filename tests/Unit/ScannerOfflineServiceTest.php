<?php

namespace Tests\Unit;

use App\Http\Controllers\BackEnd\Organizer\ScannerOfflineController;
use App\Models\Event;
use App\Models\Event\Booking;
use App\Models\Event\BookingTicket;
use App\Models\Event\EventContent;
use App\Models\Organizer;
use App\Models\ScannerDevice;
use App\Models\ScannerStaff;
use App\Services\ScannerOfflineService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Tests\TestCase;

class ScannerOfflineServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]);
        config()->set('tickets.signing_key', 'offline-test-signing-key');
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->createSchema();
    }

    public function test_manifest_contains_only_current_paid_issued_admissions_as_hashes(): void
    {
        [$organizer, $event, $device] = $this->fixture();
        $eligible = $this->ticket($this->booking($organizer, $event, 'completed'), 'issued');
        $this->ticket($this->booking($organizer, $event, 'pending'), 'issued');
        $this->ticket($this->booking($organizer, $event, 'completed', 'refunded'), 'issued');
        $this->ticket($this->booking($organizer, $event, 'free'), 'revoked');

        $result = app(ScannerOfflineService::class)->createManifest($organizer->id, $event->id, $device);
        $expectedPayload = signedTicketUnitPayload($eligible->public_id, $event->id);

        $this->assertSame(1, $result['manifest']['ticket_count']);
        $this->assertSame([hash('sha256', $expectedPayload)], $result['admissions']);
        $this->assertDatabaseMissing('scanner_offline_manifest_items', ['payload_hash' => $expectedPayload]);
        $this->assertDatabaseHas('scanner_offline_manifest_items', ['payload_hash' => hash('sha256', $expectedPayload)]);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'scanner_offline.manifest_created')->count());
    }

    public function test_sync_is_device_scoped_idempotent_and_replays_the_authoritative_result(): void
    {
        [$organizer, $event, $device] = $this->fixture();
        $ticket = $this->ticket($this->booking($organizer, $event, 'completed'), 'issued');
        $service = app(ScannerOfflineService::class);
        $manifest = $service->createManifest($organizer->id, $event->id, $device);
        $payload = signedTicketUnitPayload($ticket->public_id, $event->id);
        $scan = [
            'client_scan_id' => (string) Str::uuid(),
            'payload' => $payload,
            'scanned_at' => now()->addSecond()->toIso8601String(),
        ];
        $calls = 0;
        $checkIn = function (string $received) use (&$calls, $payload) {
            $calls++;
            $this->assertSame($payload, $received);
            return response()->json(['message' => 'Verified', 'alert_type' => 'success']);
        };

        $first = $service->sync($organizer->id, $device, null, $manifest['manifest']['id'], [$scan], $checkIn);
        $second = $service->sync($organizer->id, $device, null, $manifest['manifest']['id'], [$scan], $checkIn);

        $this->assertSame('accepted', $first['results'][0]['result']);
        $this->assertFalse($first['results'][0]['replayed']);
        $this->assertSame('accepted', $second['results'][0]['result']);
        $this->assertTrue($second['results'][0]['replayed']);
        $this->assertSame(1, $calls);
        $this->assertSame(1, DB::table('scanner_offline_sync_items')->count());
    }

    public function test_sync_rejects_payload_not_in_snapshot_without_calling_check_in(): void
    {
        [$organizer, $event, $device] = $this->fixture();
        $this->ticket($this->booking($organizer, $event, 'completed'), 'issued');
        $service = app(ScannerOfflineService::class);
        $manifest = $service->createManifest($organizer->id, $event->id, $device);
        $called = false;

        $result = $service->sync($organizer->id, $device, null, $manifest['manifest']['id'], [[
            'client_scan_id' => (string) Str::uuid(),
            'payload' => signedTicketUnitPayload((string) Str::uuid(), $event->id),
            'scanned_at' => now()->addSecond()->toIso8601String(),
        ]], function () use (&$called) {
            $called = true;
            return response()->json(['message' => 'should not run']);
        });

        $this->assertSame('rejected', $result['results'][0]['result']);
        $this->assertSame(422, $result['results'][0]['http_status']);
        $this->assertFalse($called);
    }

    public function test_manifest_cannot_be_used_by_another_enrolled_device(): void
    {
        [$organizer, $event, $device] = $this->fixture();
        $ticket = $this->ticket($this->booking($organizer, $event, 'completed'), 'issued');
        $other = ScannerDevice::create([
            'public_id' => (string) Str::uuid(), 'organizer_id' => $organizer->id,
            'name' => 'Other gate', 'secret_hash' => hash('sha256', 'secret'), 'status' => 'active',
        ]);
        $service = app(ScannerOfflineService::class);
        $manifest = $service->createManifest($organizer->id, $event->id, $device);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $service->sync($organizer->id, $other, null, $manifest['manifest']['id'], [[
            'client_scan_id' => (string) Str::uuid(),
            'payload' => signedTicketUnitPayload($ticket->public_id, $event->id),
            'scanned_at' => now()->toIso8601String(),
        ]], fn () => response()->json(['message' => 'no']));
    }

    public function test_sync_controller_replays_queue_through_authoritative_check_in(): void
    {
        [$organizer, $event, $device] = $this->fixture();
        $ticket = $this->ticket($this->booking($organizer, $event, 'completed'), 'issued');
        $service = app(ScannerOfflineService::class);
        $manifest = $service->createManifest($organizer->id, $event->id, $device);
        $request = Request::create('/scanner/offline/sync', 'POST', [
            'manifest_id' => $manifest['manifest']['id'],
            'scans' => [[
                'client_scan_id' => (string) Str::uuid(),
                'payload' => signedTicketUnitPayload($ticket->public_id, $event->id),
                'scanned_at' => now()->addSecond()->toIso8601String(),
            ]],
        ]);
        $request->attributes->set('scanner_organizer_id', $organizer->id);
        $request->attributes->set('scanner_device', $device);
        $staff = ScannerStaff::create([
            'organizer_id' => $organizer->id, 'name' => 'Offline attendant',
            'email' => Str::uuid().'@example.com', 'password' => 'hash',
            'role' => 'check_in', 'status' => 'active',
        ]);
        $request->attributes->set('scanner_staff', $staff);
        $request->attributes->set('scanner_access_role', 'organizer');

        $response = app(ScannerOfflineController::class)->sync($request, $service);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('accepted', $response->getData(true)['results'][0]['result']);
        $this->assertSame('checked_in', $ticket->fresh()->status);
        $this->assertSame($device->id, $ticket->fresh()->checked_in_scanner_device_id);
        $this->assertSame($staff->id, $ticket->fresh()->checked_in_scanner_staff_id);
        $this->assertSame('offline_sync', $ticket->fresh()->checked_in_source);
        $this->assertSame(1, $ticket->booking->fresh()->scan_status);
    }

    public function test_scan_made_before_expiry_can_sync_during_grace_period(): void
    {
        [$organizer, $event, $device] = $this->fixture();
        $ticket = $this->ticket($this->booking($organizer, $event, 'completed'), 'issued');
        $service = app(ScannerOfflineService::class);
        $created = $service->createManifest($organizer->id, $event->id, $device);
        DB::table('scanner_offline_manifests')->where('public_id', $created['manifest']['id'])->update([
            'status' => 'expired', 'generated_at' => now()->subHours(2), 'expires_at' => now()->subHour(),
        ]);

        $result = $service->sync($organizer->id, $device, null, $created['manifest']['id'], [[
            'client_scan_id' => (string) Str::uuid(),
            'payload' => signedTicketUnitPayload($ticket->public_id, $event->id),
            'scanned_at' => now()->subMinutes(90)->toIso8601String(),
        ]], fn () => response()->json(['message' => 'Verified']));

        $this->assertSame('accepted', $result['results'][0]['result']);
    }

    private function fixture(): array
    {
        $organizer = Organizer::create([
            'username' => (string) Str::uuid(), 'email' => Str::uuid() . '@example.com',
            'password' => 'x', 'status' => 1,
        ]);
        Auth::guard('organizer')->login($organizer);
        $event = Event::create([
            'organizer_id' => $organizer->id, 'status' => 1, 'start_date' => now()->addDay()->toDateString(),
        ]);
        EventContent::create(['event_id' => $event->id, 'title' => 'Offline Test Event']);
        $device = ScannerDevice::create([
            'public_id' => (string) Str::uuid(), 'organizer_id' => $organizer->id,
            'name' => 'Main gate', 'secret_hash' => hash('sha256', 'secret'), 'status' => 'active',
        ]);
        return [$organizer, $event, $device];
    }

    private function booking(Organizer $organizer, Event $event, string $paymentStatus, ?string $refund = null): Booking
    {
        return Booking::create([
            'booking_id' => (string) Str::uuid(), 'event_id' => $event->id,
            'organizer_id' => $organizer->id, 'quantity' => 1,
            'paymentStatus' => $paymentStatus, 'refund_status' => $refund, 'scan_status' => 0,
        ]);
    }

    private function ticket(Booking $booking, string $status): BookingTicket
    {
        return BookingTicket::create([
            'public_id' => (string) Str::uuid(), 'booking_id' => $booking->id,
            'event_id' => $booking->event_id, 'organizer_id' => $booking->organizer_id,
            'sequence' => 1, 'status' => $status,
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('organizers', function (Blueprint $table) {
            $table->id(); $table->string('username'); $table->string('email'); $table->string('password');
            $table->integer('status'); $table->rememberToken(); $table->timestamps();
        });
        Schema::create('events', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('organizer_id'); $table->integer('status');
            $table->date('start_date')->nullable(); $table->timestamps();
        });
        Schema::create('event_contents', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('event_id'); $table->string('title'); $table->timestamps();
        });
        Schema::create('bookings', function (Blueprint $table) {
            $table->id(); $table->string('booking_id'); $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('organizer_id'); $table->unsignedInteger('quantity');
            $table->integer('scan_status')->default(0); $table->string('paymentStatus')->nullable();
            $table->string('refund_status')->nullable(); $table->timestamps();
        });
        Schema::create('booking_tickets', function (Blueprint $table) {
            $table->id(); $table->uuid('public_id')->unique(); $table->unsignedBigInteger('booking_id');
            $table->unsignedBigInteger('event_id'); $table->unsignedBigInteger('organizer_id');
            $table->unsignedInteger('sequence'); $table->string('status');
            $table->timestamp('checked_in_at')->nullable(); $table->unsignedBigInteger('checked_in_by_organizer_id')->nullable();
            $table->unsignedBigInteger('checked_in_scanner_device_id')->nullable(); $table->timestamp('revoked_at')->nullable();
            $table->unsignedBigInteger('checked_in_scanner_staff_id')->nullable();
            $table->string('checked_in_source', 32)->nullable();
            $table->string('revoked_reason')->nullable(); $table->timestamps();
        });
        Schema::create('scanner_devices', function (Blueprint $table) {
            $table->id(); $table->uuid('public_id')->unique(); $table->unsignedBigInteger('organizer_id');
            $table->string('name'); $table->char('secret_hash', 64); $table->string('status');
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
        Schema::create('scanner_offline_manifests', function (Blueprint $table) {
            $table->id(); $table->uuid('public_id')->unique(); $table->unsignedBigInteger('organizer_id');
            $table->unsignedBigInteger('event_id'); $table->unsignedBigInteger('scanner_device_id');
            $table->unsignedBigInteger('scanner_staff_id')->nullable(); $table->string('status');
            $table->char('snapshot_hash', 64); $table->unsignedInteger('ticket_count');
            $table->timestamp('generated_at'); $table->timestamp('expires_at');
            $table->timestamp('last_synced_at')->nullable(); $table->timestamp('revoked_at')->nullable(); $table->timestamps();
        });
        Schema::create('scanner_offline_manifest_items', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('scanner_offline_manifest_id');
            $table->unsignedBigInteger('booking_ticket_id'); $table->char('payload_hash', 64); $table->timestamps();
        });
        Schema::create('scanner_offline_sync_items', function (Blueprint $table) {
            $table->id(); $table->uuid('client_scan_id'); $table->unsignedBigInteger('scanner_offline_manifest_id');
            $table->unsignedBigInteger('organizer_id'); $table->unsignedBigInteger('scanner_device_id');
            $table->unsignedBigInteger('scanner_staff_id')->nullable(); $table->unsignedBigInteger('booking_ticket_id')->nullable();
            $table->char('payload_hash', 64); $table->string('result'); $table->unsignedSmallInteger('http_status')->nullable();
            $table->json('response')->nullable(); $table->timestamp('client_scanned_at'); $table->timestamp('received_at'); $table->timestamps();
            $table->unique(['scanner_device_id', 'client_scan_id']);
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
