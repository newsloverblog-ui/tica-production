<?php

namespace Tests\Unit;

use App\Models\Admin;
use App\Models\ApplicationMailDelivery;
use App\Models\Customer;
use App\Models\Organizer;
use App\Services\ApplicationMailService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class AccountMfaSecurityTest extends TestCase
{
    private string $sentCode = '';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]);
        config()->set('session.driver', 'array');
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        Schema::create('admins', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('role_id')->nullable(); $table->string('first_name')->nullable();
            $table->string('last_name')->nullable(); $table->string('username')->unique(); $table->string('email')->unique();
            $table->string('password'); $table->timestamp('two_factor_enabled_at')->nullable(); $table->integer('status')->default(1);
            $table->rememberToken(); $table->timestamps();
        });
        Schema::create('organizers', function (Blueprint $table) {
            $table->id(); $table->string('username')->unique(); $table->string('email')->unique(); $table->string('password');
            $table->timestamp('two_factor_enabled_at')->nullable(); $table->integer('status')->default(1); $table->rememberToken(); $table->timestamps();
        });
        Schema::create('customers', function (Blueprint $table) {
            $table->id(); $table->string('fname')->nullable(); $table->string('lname')->nullable(); $table->string('username')->unique();
            $table->string('email')->unique(); $table->string('password'); $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('two_factor_enabled_at')->nullable(); $table->integer('status')->default(1); $table->rememberToken(); $table->timestamps();
        });
        Schema::create('account_login_challenges', function (Blueprint $table) {
            $table->id(); $table->uuid('public_id')->unique(); $table->string('actor_type', 20); $table->unsignedBigInteger('actor_id');
            $table->string('purpose', 20); $table->char('email_hash', 64); $table->char('code_hash', 64); $table->char('session_hash', 64);
            $table->string('redirect_path', 500)->nullable(); $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at'); $table->timestamp('consumed_at')->nullable(); $table->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id(); $table->string('actor_type'); $table->unsignedBigInteger('actor_id')->nullable(); $table->string('action');
            $table->string('subject_type')->nullable(); $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('before')->nullable(); $table->json('after')->nullable(); $table->json('metadata')->nullable();
            $table->string('request_id')->nullable(); $table->string('ip_address')->nullable(); $table->text('user_agent')->nullable(); $table->timestamp('created_at')->nullable();
        });

        $mail = Mockery::mock(ApplicationMailService::class);
        $mail->shouldReceive('queueHtml')->andReturnUsing(function ($kind, $recipient, $subject, $body) {
            preg_match('/>(\d{6})</', $body, $match);
            $this->sentCode = $match[1] ?? '';
            return new ApplicationMailDelivery();
        });
        $this->app->instance(ApplicationMailService::class, $mail);
    }

    public function test_admin_can_enable_mfa_only_after_password_and_one_time_code(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'admin');

        $this->postJson('/account-security/admin/request', [
            'action' => 'enable', 'current_password' => 'Correct-horse-44',
        ])->assertOk()->assertJson(['code_required' => true]);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $this->sentCode);

        $this->postJson('/account-security/admin/verify', ['code' => '000000'])
            ->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->assertSame(1, (int) DB::table('account_login_challenges')->value('attempts'));

        $this->postJson('/account-security/admin/verify', ['code' => $this->sentCode])
            ->assertOk()->assertJson(['enabled' => true]);
        $this->assertNotNull($admin->fresh()->two_factor_enabled_at);

        $this->postJson('/account-security/admin/verify', ['code' => $this->sentCode])
            ->assertUnprocessable()->assertJsonValidationErrors('code');
    }

    public function test_enabled_admin_password_login_requires_bound_challenge_before_authentication(): void
    {
        $admin = $this->admin();
        $admin->forceFill(['two_factor_enabled_at' => now()])->save();

        $this->post('/admin/auth', ['username' => $admin->username, 'password' => 'Correct-horse-44'])
            ->assertRedirect('/login-verification');
        $this->assertGuest('admin');
        $this->get('/login-verification')->assertOk()->assertSee('Check your email');

        $this->post('/login-verification', ['code' => $this->sentCode])
            ->assertRedirect('/admin/dashboard');
        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertNotNull(DB::table('account_login_challenges')->value('consumed_at'));
    }

    public function test_settings_change_rejects_wrong_current_password(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'admin');
        $this->postJson('/account-security/admin/request', [
            'action' => 'enable', 'current_password' => 'wrong',
        ])->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->assertDatabaseCount('account_login_challenges', 0);
    }

    public function test_organizer_and_customer_have_the_same_protected_security_control(): void
    {
        $organizer = Organizer::create([
            'username' => 'tica-organizer', 'email' => 'organizer@example.test',
            'password' => Hash::make('Correct-horse-44'), 'status' => 1,
        ]);
        $this->enableFor($organizer, 'organizer');
        $this->assertNotNull($organizer->fresh()->two_factor_enabled_at);

        auth('organizer')->logout();
        $customer = Customer::create([
            'fname' => 'Tica', 'lname' => 'Buyer', 'username' => 'tica-customer',
            'email' => 'customer@example.test', 'password' => Hash::make('Correct-horse-44'),
            'email_verified_at' => now(), 'status' => 1,
        ]);
        $this->enableFor($customer, 'customer');
        $this->assertNotNull($customer->fresh()->two_factor_enabled_at);
    }

    private function admin(): Admin
    {
        return Admin::create([
            'first_name' => 'TICA', 'last_name' => 'Admin', 'username' => 'tica-admin',
            'email' => 'admin@example.test', 'password' => Hash::make('Correct-horse-44'), 'status' => 1,
        ]);
    }

    private function enableFor($actor, string $guard): void
    {
        $this->actingAs($actor, $guard);
        $this->postJson("/account-security/$guard/request", [
            'action' => 'enable', 'current_password' => 'Correct-horse-44',
        ])->assertOk();
        $this->postJson("/account-security/$guard/verify", ['code' => $this->sentCode])
            ->assertOk()->assertJson(['enabled' => true]);
    }
}
