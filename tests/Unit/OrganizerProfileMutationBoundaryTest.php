<?php

namespace Tests\Unit;

use App\Http\Controllers\BackEnd\Organizer\OrganizerController;
use App\Models\Organizer;
use App\Services\OrganizerEmailVerificationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class OrganizerProfileMutationBoundaryTest extends TestCase
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

        Schema::create('organizers', function (Blueprint $table): void {
            $table->id();
            $table->string('photo')->nullable();
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('username')->unique();
            $table->string('password');
            $table->boolean('status')->default(false);
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('facebook')->nullable();
            $table->string('twitter')->nullable();
            $table->string('linkedin')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('kyc_status')->nullable();
            $table->string('verified_legal_name')->nullable();
            $table->timestamp('kyc_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('organizer_infos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('language_id');
            $table->unsignedBigInteger('organizer_id');
            $table->string('name');
            $table->string('designation')->nullable();
            $table->string('country')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('zip_code')->nullable();
            $table->string('address')->nullable();
            $table->text('details')->nullable();
            $table->timestamps();
        });
        Schema::create('languages', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
        DB::table('languages')->insert([
            'id' => 1, 'name' => 'English', 'code' => 'en', 'is_default' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        Schema::create('basic_settings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('uniqid')->nullable();
            $table->boolean('google_recaptcha_status')->default(false);
            $table->boolean('organizer_email_verification')->default(true);
            $table->boolean('organizer_admin_approval')->default(false);
        });
        DB::table('basic_settings')->insert([
            'id' => 1, 'uniqid' => 12345, 'google_recaptcha_status' => false,
            'organizer_email_verification' => true, 'organizer_admin_approval' => false,
        ]);
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id(); $table->string('actor_type'); $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action'); $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable(); $table->text('before')->nullable();
            $table->text('after')->nullable(); $table->text('metadata')->nullable();
            $table->string('request_id')->nullable(); $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable(); $table->timestamp('created_at')->nullable();
        });
    }

    public function test_registration_cannot_assign_finance_kyc_or_verification_fields(): void
    {
        $verifications = Mockery::mock(OrganizerEmailVerificationService::class);
        $verifications->shouldReceive('request')->once()->with(Mockery::type(Organizer::class));
        $this->app->instance(OrganizerEmailVerificationService::class, $verifications);

        (new OrganizerController())->create($this->request('/organizer/create', [
            'name' => ' Event Company ',
            'username' => 'events',
            'email' => ' EVENTS@EXAMPLE.COM ',
            'password' => 'Strong-password-123!',
            'password_confirmation' => 'Strong-password-123!',
            'status' => 1,
            'amount' => 999999,
            'email_verified_at' => now()->toDateTimeString(),
            'kyc_status' => 'verified',
            'verified_legal_name' => 'Injected Ltd',
            'kyc_verified_at' => now()->toDateTimeString(),
        ]));

        $organizer = Organizer::query()->sole();
        $this->assertSame('events@example.com', $organizer->email);
        $this->assertFalse((bool) $organizer->status);
        $this->assertSame(0.0, (float) $organizer->amount);
        $this->assertNull($organizer->email_verified_at);
        $this->assertNull($organizer->kyc_status);
        $this->assertNull($organizer->verified_legal_name);
        $this->assertNull($organizer->kyc_verified_at);
        $this->assertSame('Event Company', DB::table('organizer_infos')->value('name'));
    }

    public function test_profile_update_cannot_mutate_finance_status_or_kyc(): void
    {
        $organizer = $this->organizer();
        Auth::guard('organizer')->login($organizer);
        $verifications = Mockery::mock(OrganizerEmailVerificationService::class);
        $verifications->shouldNotReceive('request');

        $response = (new OrganizerController())->update_profile($this->request('/organizer/profile', [
            'email' => 'organizer@example.com', 'username' => 'organizer', 'phone' => '+2348000000000',
            'en_name' => 'Updated Company', 'status' => 0, 'amount' => 999999,
            'kyc_status' => 'rejected', 'verified_legal_name' => 'Injected Ltd',
            'email_verified_at' => null, 'password' => 'attacker-password',
        ]), $verifications);

        $organizer->refresh();
        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue((bool) $organizer->status);
        $this->assertSame(125.0, (float) $organizer->amount);
        $this->assertSame('verified', $organizer->kyc_status);
        $this->assertSame('Verified Company Ltd', $organizer->verified_legal_name);
        $this->assertNotNull($organizer->email_verified_at);
        $this->assertTrue(Hash::check('Original-password-123!', $organizer->password));
    }

    public function test_email_change_is_unique_password_confirmed_and_revokes_verification(): void
    {
        $organizer = $this->organizer();
        Organizer::create([
            'email' => 'taken@example.com', 'username' => 'taken',
            'password' => Hash::make('password'), 'status' => 1,
        ]);
        Auth::guard('organizer')->login($organizer);
        $verifications = Mockery::mock(OrganizerEmailVerificationService::class);

        $duplicate = (new OrganizerController())->update_profile($this->request('/organizer/profile', [
            'email' => 'taken@example.com', 'username' => 'organizer',
            'current_password' => 'Original-password-123!', 'en_name' => 'Company',
        ]), $verifications);
        $this->assertSame(400, $duplicate->getStatusCode());
        $this->assertArrayHasKey('email', $duplicate->getData(true)['errors']);
        $this->assertSame('organizer@example.com', $organizer->fresh()->email);

        $verifications->shouldReceive('request')->once()->with(Mockery::on(
            fn (Organizer $candidate): bool => $candidate->email === 'new@example.com'
                && $candidate->email_verified_at === null
        ));
        $changed = (new OrganizerController())->update_profile($this->request('/organizer/profile', [
            'email' => ' NEW@EXAMPLE.COM ', 'username' => 'organizer',
            'current_password' => 'Original-password-123!', 'en_name' => 'Company',
        ]), $verifications);

        $organizer->refresh();
        $this->assertSame('new@example.com', $organizer->email);
        $this->assertNull($organizer->email_verified_at);
        $this->assertFalse(Auth::guard('organizer')->check());
        $this->assertSame(route('organizer.login'), $changed->getData(true)['redirect']);
    }

    private function organizer(): Organizer
    {
        $organizer = Organizer::create([
            'email' => 'organizer@example.com', 'username' => 'organizer',
            'password' => Hash::make('Original-password-123!'), 'status' => 1,
            'amount' => 125, 'kyc_status' => 'verified',
            'verified_legal_name' => 'Verified Company Ltd', 'kyc_verified_at' => now(),
            'email_verified_at' => now(),
        ]);
        DB::table('organizer_infos')->insert([
            'organizer_id' => $organizer->id, 'language_id' => 1, 'name' => 'Company',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $organizer;
    }

    private function request(string $uri, array $payload): Request
    {
        $request = Request::create($uri, 'POST', $payload);
        $request->setLaravelSession(app('session')->driver());

        return $request;
    }
}
