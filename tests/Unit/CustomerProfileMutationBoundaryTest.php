<?php

namespace Tests\Unit;

use App\Http\Controllers\FrontEnd\CustomerController;
use App\Models\Customer;
use App\Services\CustomerEmailVerificationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class CustomerProfileMutationBoundaryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->string('provider')->nullable();
            $table->string('provider_id')->nullable();
            $table->string('fname');
            $table->string('lname');
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->string('photo')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('country')->nullable();
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            $table->string('zip_code')->nullable();
            $table->string('password');
            $table->string('gender')->nullable();
            $table->boolean('status')->default(false);
            $table->timestamp('email_verified_at')->nullable();
            $table->string('verification_token')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('basic_settings', function (Blueprint $table): void {
            $table->id();
            $table->boolean('google_recaptcha_status')->default(false);
        });
        DB::table('basic_settings')->insert(['id' => 1, 'google_recaptcha_status' => false]);
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('actor_type');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->text('before')->nullable();
            $table->text('after')->nullable();
            $table->text('metadata')->nullable();
            $table->string('request_id')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function test_signup_ignores_privileged_and_identity_provider_fields(): void
    {
        $verifications = Mockery::mock(CustomerEmailVerificationService::class);
        $verifications->shouldReceive('request')->once()->with(Mockery::type(Customer::class));
        $this->app->instance(CustomerEmailVerificationService::class, $verifications);

        $request = $this->request('/customer/create', [
            'fname' => ' Ada ',
            'lname' => ' Lovelace ',
            'username' => 'ada',
            'email' => ' ADA@EXAMPLE.COM ',
            'password' => 'Strong-password-123!',
            'password_confirmation' => 'Strong-password-123!',
            'status' => 1,
            'email_verified_at' => now()->toDateTimeString(),
            'provider' => 'attacker',
            'provider_id' => 'privileged-id',
            'verification_token' => 'attacker-token',
        ]);

        (new CustomerController())->create($request);

        $customer = Customer::query()->sole();
        $this->assertSame('Ada', $customer->fname);
        $this->assertSame('ada@example.com', $customer->email);
        $this->assertFalse((bool) $customer->status);
        $this->assertNull($customer->email_verified_at);
        $this->assertNull($customer->provider);
        $this->assertNull($customer->provider_id);
        $this->assertNull($customer->verification_token);
    }

    public function test_profile_update_ignores_non_profile_fields(): void
    {
        $customer = $this->customer();
        Auth::guard('customer')->login($customer);
        $verifications = Mockery::mock(CustomerEmailVerificationService::class);
        $verifications->shouldNotReceive('request');

        $request = $this->request('/customer/update-profile', [
            'fname' => 'Grace',
            'lname' => 'Hopper',
            'username' => 'member',
            'email' => 'member@example.com',
            'phone' => '+2348000000000',
            'status' => 0,
            'password' => 'attacker-password',
            'provider' => 'attacker',
            'email_verified_at' => null,
            'verification_token' => 'attacker-token',
        ]);

        (new CustomerController())->update_profile($request, $verifications);

        $customer->refresh();
        $this->assertSame('Grace', $customer->fname);
        $this->assertTrue((bool) $customer->status);
        $this->assertTrue(Hash::check('Original-password-123!', $customer->password));
        $this->assertSame('local', $customer->provider);
        $this->assertNotNull($customer->email_verified_at);
        $this->assertNull($customer->verification_token);
    }

    public function test_email_change_requires_password_and_revokes_verification(): void
    {
        $customer = $this->customer();
        Auth::guard('customer')->login($customer);
        $verifications = Mockery::mock(CustomerEmailVerificationService::class);

        $missingPassword = $this->request('/customer/update-profile', [
            'fname' => 'Member',
            'lname' => 'Customer',
            'username' => 'member',
            'email' => 'new@example.com',
        ]);
        try {
            (new CustomerController())->update_profile($missingPassword, $verifications);
            $this->fail('Email change unexpectedly succeeded without the current password.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('current_password', $exception->errors());
        }
        $this->assertSame('member@example.com', $customer->fresh()->email);

        $verifications->shouldReceive('request')->once()->with(Mockery::on(
            fn (Customer $candidate): bool => $candidate->email === 'new@example.com'
                && $candidate->email_verified_at === null
        ));
        $valid = $this->request('/customer/update-profile', [
            'fname' => 'Member',
            'lname' => 'Customer',
            'username' => 'member',
            'email' => ' NEW@EXAMPLE.COM ',
            'current_password' => 'Original-password-123!',
        ]);

        $response = (new CustomerController())->update_profile($valid, $verifications);

        $customer->refresh();
        $this->assertSame('new@example.com', $customer->email);
        $this->assertNull($customer->email_verified_at);
        $this->assertFalse(Auth::guard('customer')->check());
        $this->assertSame(route('customer.login'), $response->getTargetUrl());
    }

    private function customer(): Customer
    {
        return Customer::create([
            'provider' => 'local',
            'fname' => 'Member',
            'lname' => 'Customer',
            'username' => 'member',
            'email' => 'member@example.com',
            'password' => Hash::make('Original-password-123!'),
            'status' => 1,
            'email_verified_at' => now(),
        ]);
    }

    private function request(string $uri, array $payload): Request
    {
        $request = Request::create($uri, 'POST', $payload);
        $request->setLaravelSession(app('session')->driver());

        return $request;
    }
}
