<?php

namespace Tests\Unit;

use App\Models\Admin;
use App\Models\ApplicationMailDelivery;
use App\Models\Customer;
use App\Models\Organizer;
use App\Services\ApplicationMailService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class AccountMfaSecurityTest extends TestCase
{
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
            $table->string('password'); $table->timestamp('two_factor_enabled_at')->nullable();
            $table->text('two_factor_secret')->nullable(); $table->text('two_factor_recovery_codes')->nullable();
            $table->integer('status')->default(1); $table->rememberToken(); $table->timestamps();
        });
        Schema::create('organizers', function (Blueprint $table) {
            $table->id(); $table->string('username')->unique(); $table->string('email')->unique(); $table->string('password');
            $table->timestamp('two_factor_enabled_at')->nullable(); $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable(); $table->integer('status')->default(1);
            $table->rememberToken(); $table->timestamps();
        });
        Schema::create('customers', function (Blueprint $table) {
            $table->id(); $table->string('fname')->nullable(); $table->string('lname')->nullable(); $table->string('username')->unique();
            $table->string('email')->unique(); $table->string('password'); $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('two_factor_enabled_at')->nullable(); $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable(); $table->integer('status')->default(1);
            $table->rememberToken(); $table->timestamps();
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
            $table->string('request_id')->nullable(); $table->string('ip_address')->nullable(); $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        $mail = Mockery::mock(ApplicationMailService::class);
        $mail->shouldReceive('queueHtml')->zeroOrMoreTimes()->andReturn(new ApplicationMailDelivery());
        $this->app->instance(ApplicationMailService::class, $mail);
    }

    public function test_admin_setup_requires_password_and_a_valid_authenticator_code(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'admin');

        $this->postJson('/account-security/admin/request', [
            'action' => 'enable', 'current_password' => 'wrong',
        ])->assertUnprocessable()->assertJsonValidationErrors('current_password');

        $this->postJson('/account-security/admin/request', [
            'action' => 'enable', 'current_password' => 'Correct-horse-44',
        ])->assertOk()->assertJson(['setup_required' => true]);

        $secret = $this->pendingSecret('admin');
        $this->get('/account-security/admin')->assertOk()->assertSee('Connect an authenticator app');
        $this->postJson('/account-security/admin/verify', ['code' => '000000'])
            ->assertUnprocessable()->assertJsonValidationErrors('code');

        $response = $this->postJson('/account-security/admin/verify', ['code' => $this->totp($secret)])
            ->assertOk()->assertJson(['enabled' => true]);
        $codes = $response->json('recovery_codes');
        $this->assertCount(8, $codes);

        $fresh = $admin->fresh();
        $this->assertNotNull($fresh->two_factor_enabled_at);
        $this->assertSame($secret, $fresh->two_factor_secret);
        $raw = DB::table('admins')->where('id', $admin->id)->first();
        $this->assertStringNotContainsString($secret, (string) $raw->two_factor_secret);
        $this->assertStringNotContainsString($codes[0], (string) $raw->two_factor_recovery_codes);
    }

    public function test_authenticator_login_is_session_bound_and_accepts_current_totp(): void
    {
        $admin = $this->admin();
        [$secret] = $this->enableFor($admin, 'admin');
        auth('admin')->logout();

        $this->post('/admin/auth', ['username' => $admin->username, 'password' => 'Correct-horse-44'])
            ->assertRedirect('/login-verification');
        $this->assertGuest('admin');
        $this->get('/login-verification')->assertOk()->assertSee('Enter your verification code');
        $this->post('/login-verification', ['code' => '000000'])
            ->assertSessionHasErrors('code');
        $this->assertSame(1, (int) DB::table('account_login_challenges')->value('attempts'));

        $this->post('/login-verification', ['code' => $this->totp($secret)])
            ->assertRedirect('/admin/dashboard');
        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertNotNull(DB::table('account_login_challenges')->value('consumed_at'));
    }

    public function test_recovery_code_is_consumed_once(): void
    {
        $admin = $this->admin();
        [, $codes] = $this->enableFor($admin, 'admin');
        auth('admin')->logout();

        $this->post('/admin/auth', ['username' => $admin->username, 'password' => 'Correct-horse-44']);
        $this->post('/login-verification', ['code' => $codes[0]])->assertRedirect('/admin/dashboard');
        $this->assertCount(7, $admin->fresh()->two_factor_recovery_codes);

        auth('admin')->logout();
        $this->post('/admin/auth', ['username' => $admin->username, 'password' => 'Correct-horse-44']);
        $this->post('/login-verification', ['code' => $codes[0]])->assertSessionHasErrors('code');
        $this->assertGuest('admin');
    }

    public function test_turning_off_requires_both_password_and_an_enrolled_factor(): void
    {
        $admin = $this->admin();
        [$secret] = $this->enableFor($admin, 'admin');

        $this->postJson('/account-security/admin/request', [
            'action' => 'disable', 'current_password' => 'wrong', 'code' => $this->totp($secret),
        ])->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->postJson('/account-security/admin/request', [
            'action' => 'disable', 'current_password' => 'Correct-horse-44', 'code' => '000000',
        ])->assertUnprocessable()->assertJsonValidationErrors('code');

        $this->travel(11)->minutes();
        $this->postJson('/account-security/admin/request', [
            'action' => 'disable', 'current_password' => 'Correct-horse-44', 'code' => $this->totp($secret),
        ])->assertOk()->assertJson(['enabled' => false]);
        $fresh = $admin->fresh();
        $this->assertNull($fresh->two_factor_enabled_at);
        $this->assertNull($fresh->two_factor_secret);
        $this->assertNull($fresh->two_factor_recovery_codes);
    }

    public function test_organizer_and_customer_receive_the_same_protected_flow(): void
    {
        $organizer = Organizer::create([
            'username' => 'tica-organizer', 'email' => 'organizer@example.test',
            'password' => Hash::make('Correct-horse-44'), 'status' => 1,
        ]);
        $this->enableFor($organizer, 'organizer');
        $this->assertNotNull($organizer->fresh()->two_factor_secret);

        auth('organizer')->logout();
        $customer = Customer::create([
            'fname' => 'Tica', 'lname' => 'Buyer', 'username' => 'tica-customer',
            'email' => 'customer@example.test', 'password' => Hash::make('Correct-horse-44'),
            'email_verified_at' => now(), 'status' => 1,
        ]);
        $this->enableFor($customer, 'customer');
        $this->assertNotNull($customer->fresh()->two_factor_secret);
    }

    private function admin(): Admin
    {
        return Admin::create([
            'first_name' => 'TICA', 'last_name' => 'Admin', 'username' => 'tica-admin',
            'email' => 'admin@example.test', 'password' => Hash::make('Correct-horse-44'), 'status' => 1,
        ]);
    }

    private function enableFor($actor, string $guard): array
    {
        $this->actingAs($actor, $guard);
        $this->postJson("/account-security/$guard/request", [
            'action' => 'enable', 'current_password' => 'Correct-horse-44',
        ])->assertOk()->assertJson(['setup_required' => true]);
        $secret = $this->pendingSecret($guard);
        $response = $this->postJson("/account-security/$guard/verify", ['code' => $this->totp($secret)])
            ->assertOk()->assertJson(['enabled' => true]);

        return [$secret, $response->json('recovery_codes')];
    }

    private function pendingSecret(string $guard): string
    {
        $pending = session("mfa.setup.$guard");
        $this->assertIsArray($pending);

        return Crypt::decryptString($pending['secret']);
    }

    private function totp(string $secret, ?int $timestamp = null): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split(strtoupper(rtrim($secret, '='))) as $character) {
            $bits .= str_pad(decbin((int) strpos($alphabet, $character)), 5, '0', STR_PAD_LEFT);
        }
        $key = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) $key .= chr(bindec($chunk));
        }
        $counter = intdiv($timestamp ?? now()->getTimestamp(), 30);
        $binaryCounter = pack('N2', ($counter >> 32) & 0xffffffff, $counter & 0xffffffff);
        $hash = hash_hmac('sha1', $binaryCounter, $key, true);
        $offset = ord($hash[19]) & 0x0f;
        $value = (unpack('N', substr($hash, $offset, 4))[1] ?? 0) & 0x7fffffff;

        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }
}
