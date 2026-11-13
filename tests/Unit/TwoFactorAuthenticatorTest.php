<?php

namespace Tests\Unit;

use App\Models\Organizer;
use App\Services\SensitiveActionConfirmation;
use App\Services\TwoFactorAuthenticator;
use App\Support\Totp;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TwoFactorAuthenticatorTest extends TestCase
{
  public function test_totp_matches_the_rfc_6238_test_vectors(): void
  {
    $totp = new Totp();
    $secret = $totp->base32Encode('12345678901234567890');
    foreach ([59 => '287082', 1111111109 => '081804', 1111111111 => '050471', 1234567890 => '005924', 2000000000 => '279037'] as $time => $code) {
      $this->assertSame($code, $totp->oathTotp($secret, intdiv($time, 30)), "t=$time");
    }
  }

  public function test_setup_stores_an_encrypted_secret_and_hashed_recovery_codes(): void
  {
    [$organizer, $request] = $this->signedIn();
    $service = app(TwoFactorAuthenticator::class);
    $setup = $service->startSetup($request, $organizer);
    $this->assertStringContainsString('<svg', $setup['qr']);
    $secret = str_replace(' ', '', $setup['secret']);

    $codes = $service->confirmSetup($request, $organizer, $this->code($secret));

    $this->assertCount(8, $codes);
    $organizer->refresh();
    $this->assertTrue($service->enabled($organizer));
    $raw = DB::table('organizers')->where('id', $organizer->id)->first();
    $this->assertNotSame($secret, $raw->totp_secret, 'The secret is encrypted at rest.');
    $this->assertStringNotContainsString($codes[0], (string) $raw->totp_recovery_codes);
    $this->assertStringNotContainsString($codes[0], decrypt($raw->totp_recovery_codes, false), 'Only hashes are stored.');
  }

  public function test_a_wrong_first_code_does_not_turn_it_on(): void
  {
    [$organizer, $request] = $this->signedIn();
    $service = app(TwoFactorAuthenticator::class);
    $service->startSetup($request, $organizer);

    $this->expectException(ValidationException::class);
    try {
      $service->confirmSetup($request, $organizer, '000000');
    } finally {
      $this->assertFalse($service->enabled($organizer->fresh()));
    }
  }

  public function test_each_app_code_and_each_recovery_code_works_only_once(): void
  {
    [$organizer, , $secret, $codes] = $this->enabled();
    $service = app(TwoFactorAuthenticator::class);
    $code = $this->code($secret);

    $this->assertTrue($service->verify($organizer, $code));
    $this->assertFalse($service->verify($organizer, $code), 'Replay refused.');
    $this->assertTrue($service->verify($organizer, strtoupper($codes[2])));
    $this->assertFalse($service->verify($organizer, $codes[2]), 'Recovery code used up.');
    $this->assertSame(7, $service->recoveryCodesLeft($organizer->fresh()));
  }

  public function test_sign_in_waits_for_the_code_then_logs_in(): void
  {
    [$organizer, $request, $secret] = $this->enabled();
    $service = app(TwoFactorAuthenticator::class);
    Auth::guard('organizer')->login($organizer);

    $service->beginLogin($request, $organizer, '/dashboard');
    $this->assertFalse(Auth::guard('organizer')->check(), 'Password alone does not sign in.');

    try {
      $service->completeLogin($request, '000000');
      $this->fail('Wrong code accepted.');
    } catch (ValidationException) {
    }
    $this->assertFalse(Auth::guard('organizer')->check());

    $this->assertSame('/dashboard', $service->completeLogin($request, $this->code($secret)));
    $this->assertTrue(Auth::guard('organizer')->check());
  }

  public function test_five_wrong_codes_end_the_sign_in(): void
  {
    [$organizer, $request] = $this->enabled();
    $service = app(TwoFactorAuthenticator::class);
    $service->beginLogin($request, $organizer, '/dashboard');
    for ($i = 0; $i < 5; $i++) {
      try { $service->completeLogin($request, '000000'); } catch (ValidationException) {}
    }

    $this->expectExceptionMessage('Too many wrong codes');
    $service->completeLogin($request, '123456');
  }

  public function test_bank_changes_need_the_app_code_when_two_factor_is_on(): void
  {
    [$organizer, , $secret] = $this->enabled();
    $confirm = app(SensitiveActionConfirmation::class);

    try {
      $confirm->confirm($organizer, 'secret pass', null, 'payout-account');
      $this->fail('Password alone should not be enough.');
    } catch (ValidationException $exception) {
      $this->assertArrayHasKey('otp', $exception->errors());
    }
    $confirm->confirm($organizer, 'secret pass', $this->code($secret), 'payout-account');
    $this->assertTrue(true);
  }

  public function test_disable_clears_everything(): void
  {
    [$organizer] = $this->enabled();
    app(TwoFactorAuthenticator::class)->disable($organizer);

    $fresh = $organizer->fresh();
    $this->assertNull($fresh->totp_confirmed_at);
    $this->assertNull(DB::table('organizers')->where('id', $organizer->id)->value('totp_secret'));
  }

  /* ------------------------------------------------------------- helpers */

  protected function setUp(): void
  {
    parent::setUp();
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    DB::purge('sqlite');
    DB::setDefaultConnection('sqlite');
    Schema::create('organizers', function (Blueprint $table) {
      $table->id();
      $table->string('email');
      $table->string('username')->nullable();
      $table->string('password');
      $table->integer('status')->default(1);
      $table->text('totp_secret')->nullable();
      $table->text('totp_recovery_codes')->nullable();
      $table->timestamp('totp_confirmed_at')->nullable();
      $table->bigInteger('totp_last_step')->nullable();
      $table->timestamps();
    });
    Schema::create('audit_logs', function (Blueprint $table) {
      $table->id();
      foreach (['actor_type', 'action', 'subject_type', 'request_id', 'ip_address'] as $c) $table->string($c)->nullable();
      foreach (['actor_id', 'subject_id'] as $c) $table->unsignedBigInteger($c)->nullable();
      foreach (['before', 'after', 'metadata', 'user_agent'] as $c) $table->text($c)->nullable();
      $table->timestamp('created_at')->nullable();
    });
    \Illuminate\Support\Facades\RateLimiter::clear('sensitive-confirm:payout-account:1');
  }

  protected function tearDown(): void
  {
    Auth::guard('organizer')->logout();
    parent::tearDown();
  }

  private function signedIn(): array
  {
    $organizer = Organizer::query()->forceCreate(['email' => 'ada@example.com', 'username' => 'ada', 'password' => Hash::make('secret pass'), 'status' => 1]);
    $request = Request::create('/');
    $session = app('session')->driver();
    $session->start();
    $request->setLaravelSession($session);
    app()->instance('request', $request);

    return [$organizer, $request];
  }

  private function enabled(): array
  {
    [$organizer, $request] = $this->signedIn();
    $service = app(TwoFactorAuthenticator::class);
    $secret = str_replace(' ', '', $service->startSetup($request, $organizer)['secret']);
    // Confirm with the previous step's code so the current one is still unused.
    $totp = new Totp();
    $codes = $service->confirmSetup($request, $organizer, $totp->oathTotp($secret, intdiv(time(), 30) - 1));

    return [$organizer->fresh(), $request, $secret, $codes];
  }

  private function code(string $secret): string
  {
    return (new Totp())->oathTotp($secret, intdiv(time(), 30));
  }
}
