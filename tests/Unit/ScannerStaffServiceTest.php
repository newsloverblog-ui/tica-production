<?php

namespace Tests\Unit;

use App\Http\Middleware\ScannerAccess;
use App\Http\Middleware\ScannerSupervisor;
use App\Models\Organizer;
use App\Models\ScannerStaff;
use App\Services\ScannerStaffService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class ScannerStaffServiceTest extends TestCase
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

    public function test_staff_credentials_are_hashed_and_lifecycle_is_audited(): void
    {
        $organizer = $this->organizer();
        Auth::guard('organizer')->login($organizer);
        $service = app(ScannerStaffService::class);
        $staff = $service->create($organizer->id, [
            'name' => 'Ada Gate', 'email' => 'ADA.GATE@example.com', 'password' => 'StrongPass8', 'role' => 'check_in',
        ]);

        $this->assertSame('ada.gate@example.com', $staff->email);
        $this->assertTrue(Hash::check('StrongPass8', $staff->password));
        $this->assertArrayNotHasKey('password', $staff->toArray());
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'scanner_staff.created')->count());

        $updated = $service->update($organizer->id, $staff->id, [
            'name' => 'Ada Supervisor', 'role' => 'supervisor', 'status' => 'inactive', 'password' => 'NewStrong9Pass',
        ]);
        $this->assertSame('supervisor', $updated->role);
        $this->assertSame('inactive', $updated->status);
        $this->assertNotNull($updated->revoked_at);
        $this->assertTrue(Hash::check('NewStrong9Pass', $updated->password));
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'scanner_staff.updated')->count());
    }

    public function test_staff_lifecycle_is_organizer_scoped(): void
    {
        $owner = $this->organizer();
        $other = $this->organizer();
        $staff = app(ScannerStaffService::class)->create($owner->id, [
            'name' => 'Main Gate', 'email' => 'main@example.com', 'password' => 'StrongPass8', 'role' => 'check_in',
        ]);

        $this->expectException(ModelNotFoundException::class);
        app(ScannerStaffService::class)->update($other->id, $staff->id, [
            'name' => 'Stolen', 'role' => 'supervisor', 'status' => 'active', 'password' => null,
        ]);
    }

    public function test_scanner_access_uses_owner_scope_and_revocation_is_immediate(): void
    {
        $organizer = $this->organizer();
        $staff = app(ScannerStaffService::class)->create($organizer->id, [
            'name' => 'North Gate', 'email' => 'north@example.com', 'password' => 'StrongPass8', 'role' => 'check_in',
        ]);
        Auth::guard('scanner')->login($staff);
        session()->put('scanner_auth_version', (int) $staff->auth_version);
        $request = Request::create('/scanner/workspace');
        $request->setLaravelSession(app('session')->driver());
        $response = app(ScannerAccess::class)->handle($request, function ($request) use ($organizer) {
            $this->assertSame($organizer->id, $request->attributes->get('scanner_organizer_id'));
            $this->assertSame('check_in', $request->attributes->get('scanner_access_role'));
            return response('ok');
        });
        $this->assertSame(200, $response->getStatusCode());

        app(ScannerStaffService::class)->update($organizer->id, $staff->id, [
            'name' => $staff->name, 'role' => 'check_in', 'status' => 'active', 'password' => 'RotatedPass9',
        ]);
        $this->assertGreaterThan((int) session('scanner_auth_version'), (int) $staff->fresh()->auth_version);
        Auth::guard('scanner')->setUser($staff->fresh());
        $deniedRequest = Request::create('/scanner/check-qrcode', 'POST', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
        $deniedRequest->setLaravelSession(app('session')->driver());
        $denied = app(ScannerAccess::class)->handle($deniedRequest, fn () => response('should-not-run'));
        $this->assertSame(401, $denied->getStatusCode());
        $this->assertFalse(Auth::guard('scanner')->check());
    }

    public function test_only_supervisor_or_organizer_can_review_conflicts(): void
    {
        $middleware = app(ScannerSupervisor::class);
        $checkIn = Request::create('/scanner/conflicts', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
        $checkIn->attributes->set('scanner_access_role', 'check_in');
        $this->assertSame(403, $middleware->handle($checkIn, fn () => response('ok'))->getStatusCode());

        foreach (['supervisor', 'organizer'] as $role) {
            $request = Request::create('/scanner/conflicts');
            $request->attributes->set('scanner_access_role', $role);
            $this->assertSame(200, $middleware->handle($request, fn () => response('ok'))->getStatusCode());
        }
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
        Schema::create('scanner_staff', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('organizer_id'); $table->string('name'); $table->string('email')->unique();
            $table->string('password'); $table->string('role'); $table->string('status'); $table->unsignedInteger('auth_version')->default(1);
            $table->timestamp('last_login_at')->nullable(); $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable(); $table->rememberToken(); $table->timestamps();
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
