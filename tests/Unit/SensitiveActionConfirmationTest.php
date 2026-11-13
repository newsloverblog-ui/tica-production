<?php

namespace Tests\Unit;

use App\Models\Organizer;
use App\Services\SensitiveActionConfirmation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SensitiveActionConfirmationTest extends TestCase
{
  protected function setUp(): void
  {
    parent::setUp();
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    DB::purge('sqlite');
    DB::setDefaultConnection('sqlite');
    Schema::create('audit_logs', function (Blueprint $table) {
      $table->id();
      foreach (['actor_type', 'action', 'subject_type', 'request_id', 'ip_address'] as $c) $table->string($c)->nullable();
      foreach (['actor_id', 'subject_id'] as $c) $table->unsignedBigInteger($c)->nullable();
      foreach (['before', 'after', 'metadata', 'user_agent'] as $c) $table->text($c)->nullable();
      $table->timestamp('created_at')->nullable();
    });
    \Illuminate\Support\Facades\RateLimiter::clear('sensitive-confirm:payout-account:5');
  }

  public function test_right_password_passes(): void
  {
    app(SensitiveActionConfirmation::class)->confirm($this->organizer(), 'correct horse', null, 'payout-account');
    $this->assertTrue(true);
  }

  public function test_wrong_password_is_refused_and_logged(): void
  {
    try {
      app(SensitiveActionConfirmation::class)->confirm($this->organizer(), 'nope', null, 'payout-account');
      $this->fail('Wrong password accepted.');
    } catch (ValidationException $exception) {
      $this->assertArrayHasKey('password', $exception->errors());
    }
    $this->assertDatabaseHas('audit_logs', ['action' => 'organizer.sensitive_confirmation_failed']);
  }

  public function test_five_wrong_tries_lock_even_the_right_password_out(): void
  {
    $organizer = $this->organizer();
    $service = app(SensitiveActionConfirmation::class);
    for ($i = 0; $i < 5; $i++) {
      try { $service->confirm($organizer, 'nope', null, 'payout-account'); } catch (ValidationException) {}
    }

    try {
      $service->confirm($organizer, 'correct horse', null, 'payout-account');
      $this->fail('Locked confirmation should refuse the right password too.');
    } catch (ValidationException $exception) {
      $this->assertStringContainsString('Too many tries', $exception->errors()['password'][0]);
    }
  }

  private function organizer(): Organizer
  {
    $organizer = new Organizer();
    $organizer->forceFill(['id' => 5, 'email' => 'o@example.com', 'password' => Hash::make('correct horse')]);
    $organizer->exists = true;

    return $organizer;
  }
}
