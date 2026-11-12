<?php

namespace Tests\Unit;

use App\Http\Middleware\VerifyScannerDevice;
use App\Models\Organizer;
use App\Models\ScannerDevice;
use App\Models\Event\BookingTicket;
use App\Services\ScannerConflictService;
use App\Services\ScannerDeviceService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class ScannerDeviceServiceTest extends TestCase
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

    public function test_enrollment_returns_secret_once_and_persists_only_its_hash(): void
    {
        $organizer = $this->organizer();
        Auth::guard('organizer')->login($organizer);

        [$device, $secret] = app(ScannerDeviceService::class)->enroll($organizer->id, 'Main entrance iPad');

        $this->assertSame('Main entrance iPad', $device->name);
        $this->assertNotSame($secret, $device->getRawOriginal('secret_hash'));
        $this->assertSame(hash('sha256', $secret), $device->getRawOriginal('secret_hash'));
        $this->assertArrayNotHasKey('secret_hash', $device->toArray());
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'scanner_device.enrolled')->count());
    }

    public function test_middleware_rejects_missing_wrong_and_cross_organizer_credentials(): void
    {
        $owner = $this->organizer();
        $other = $this->organizer();
        Auth::guard('organizer')->login($owner);
        [$device, $secret] = app(ScannerDeviceService::class)->enroll($owner->id, 'Gate A');
        $middleware = app(VerifyScannerDevice::class);

        $missing = $middleware->handle(Request::create('/scan'), fn () => response('ok'));
        $this->assertSame(401, $missing->getStatusCode());

        $wrong = Request::create('/scan', 'POST', [], [], [], [
            'HTTP_X_SCANNER_DEVICE' => $device->public_id,
            'HTTP_X_SCANNER_SECRET' => 'wrong-secret',
        ]);
        $this->assertSame(401, $middleware->handle($wrong, fn () => response('ok'))->getStatusCode());

        Auth::guard('organizer')->login($other);
        $crossOrganizer = Request::create('/scan', 'POST', [], [], [], [
            'HTTP_X_SCANNER_DEVICE' => $device->public_id,
            'HTTP_X_SCANNER_SECRET' => $secret,
        ]);
        $this->assertSame(401, $middleware->handle($crossOrganizer, fn () => response('ok'))->getStatusCode());
    }

    public function test_valid_device_is_attributed_and_revocation_takes_effect_immediately(): void
    {
        $organizer = $this->organizer();
        Auth::guard('organizer')->login($organizer);
        $service = app(ScannerDeviceService::class);
        [$device, $secret] = $service->enroll($organizer->id, 'North gate');
        $middleware = app(VerifyScannerDevice::class);
        $request = Request::create('/scan', 'POST', [], [], [], [
            'HTTP_X_SCANNER_DEVICE' => $device->public_id,
            'HTTP_X_SCANNER_SECRET' => $secret,
            'REMOTE_ADDR' => '127.0.0.9',
            'HTTP_USER_AGENT' => 'Test Scanner',
        ]);

        $response = $middleware->handle($request, function ($verifiedRequest) use ($device) {
            $this->assertSame($device->id, $verifiedRequest->attributes->get('scanner_device')->id);
            return response('ok');
        });
        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotNull($device->fresh()->last_seen_at);

        $service->revoke($organizer->id, $device->id);
        $this->assertSame('revoked', $device->fresh()->status);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'scanner_device.revoked')->count());
        $this->assertSame(401, $middleware->handle($request, fn () => response('ok'))->getStatusCode());
    }

    public function test_gate_conflict_is_deduplicated_counted_and_organizer_scoped_for_resolution(): void
    {
        $owner = $this->organizer();
        $other = $this->organizer();
        Auth::guard('organizer')->login($owner);
        [$main] = app(ScannerDeviceService::class)->enroll($owner->id, 'Main gate');
        [$vip] = app(ScannerDeviceService::class)->enroll($owner->id, 'VIP gate');
        $ticket = new BookingTicket([
            'public_id' => (string) Str::uuid(), 'organizer_id' => $owner->id, 'event_id' => 44,
        ]);
        $ticket->id = 91;
        $service = app(ScannerConflictService::class);

        $conflict = $service->record($ticket, $main, $vip, 'scanner');
        $service->record($ticket, $main, $vip, 'manual');
        $this->assertSame(2, $conflict->fresh()->occurrences);
        $this->assertSame(1, DB::table('scanner_check_in_conflicts')->count());

        try {
            $service->resolve($other->id, $conflict->id, 'Should not be allowed');
            $this->fail('Another organizer resolved a conflict they do not own.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $exception) {
            $this->assertNull($conflict->fresh()->resolved_at);
        }

        $resolved = $service->resolve($owner->id, $conflict->id, 'Guest was stopped and identity checked.');
        $this->assertNotNull($resolved->resolved_at);
        $this->assertSame('Guest was stopped and identity checked.', $resolved->resolution_note);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'scanner_check_in.conflict_resolved')->count());
    }

    private function organizer(): Organizer
    {
        return Organizer::create([
            'username' => (string) Str::uuid(), 'email' => Str::uuid() . '@example.com',
            'password' => 'x', 'status' => 1,
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('organizers', function (Blueprint $table) {
            $table->id(); $table->string('username'); $table->string('email'); $table->string('password');
            $table->integer('status'); $table->rememberToken(); $table->timestamps();
        });
        Schema::create('scanner_devices', function (Blueprint $table) {
            $table->id(); $table->uuid('public_id')->unique(); $table->unsignedBigInteger('organizer_id');
            $table->string('name'); $table->char('secret_hash', 64); $table->string('status')->default('active');
            $table->timestamp('last_seen_at')->nullable(); $table->string('last_ip')->nullable();
            $table->text('last_user_agent')->nullable(); $table->timestamp('revoked_at')->nullable();
            $table->unsignedBigInteger('revoked_by_organizer_id')->nullable(); $table->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id(); $table->string('actor_type'); $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action'); $table->string('subject_type')->nullable(); $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('before')->nullable(); $table->json('after')->nullable(); $table->json('metadata')->nullable();
            $table->string('request_id')->nullable(); $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable(); $table->timestamp('created_at')->nullable();
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
    }
}
