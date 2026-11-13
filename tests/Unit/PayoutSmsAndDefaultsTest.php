<?php

namespace Tests\Unit;

use App\Contracts\SmsSender;
use App\Jobs\SendPayoutStatusNotification;
use App\Models\Organizer;
use App\Models\PayoutTransfer;
use App\Services\TermiiSmsSender;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PayoutSmsAndDefaultsTest extends TestCase
{
  public function test_payout_text_goes_only_to_organisers_who_turned_it_on(): void
  {
    $sms = $this->fakeSms();

    $this->runSms($this->organizer(['payout_sms' => true]), 'success');
    $this->runSms($this->organizer(['payout_sms' => false]), 'success');
    $this->runSms($this->organizer([]), 'success');

    $this->assertCount(1, $sms->sent);
    $this->assertStringContainsString('has been paid to your bank account', $sms->sent[0][1]);
  }

  public function test_failed_payout_text_says_so_and_queued_sends_nothing(): void
  {
    $sms = $this->fakeSms();
    $organizer = $this->organizer(['payout_sms' => true]);

    $this->runSms($organizer, 'queued');
    $this->runSms($organizer, 'failed');

    $this->assertCount(1, $sms->sent);
    $this->assertStringContainsString('did not go through', $sms->sent[0][1]);
  }

  public function test_a_failing_sms_provider_never_breaks_the_payout_email_flow(): void
  {
    $sms = $this->fakeSms();
    $sms->fail = true;

    $this->runSms($this->organizer(['payout_sms' => true]), 'success');

    $this->assertTrue(true, 'No exception escaped.');
  }

  public function test_termii_request_is_sent_from_the_server_with_a_normalised_number(): void
  {
    config()->set('ticket_delivery.sms.api_key', 'test-key');
    Http::fake(['*/api/sms/send' => Http::response(['message' => 'Successfully Sent'], 200)]);

    (new TermiiSmsSender())->send('0803 123 4567', 'Hello');

    Http::assertSent(fn (HttpRequest $request) => $request['to'] === '2348031234567' && $request['api_key'] === 'test-key' && $request['sms'] === 'Hello');
  }

  public function test_termii_refusal_is_an_error(): void
  {
    config()->set('ticket_delivery.sms.api_key', 'test-key');
    Http::fake(['*' => Http::response(['message' => 'Insufficient balance'], 400)]);

    $this->expectExceptionMessage('Insufficient balance');
    (new TermiiSmsSender())->send('08031234567', 'Hello');
  }

  public function test_settings_switches_name_their_own_preference(): void
  {
    $html = file_get_contents(resource_path('views/tica-ui/pages/dashboard.blade.php'));
    foreach (array_keys(\App\Services\TicaUi\OrganizerWorkspace::PREFERENCE_DEFAULTS) as $key) {
      $this->assertStringContainsString('data-pref="' . $key . '"', $html, $key);
    }
    $this->assertStringNotContainsString("keys[i]", file_get_contents(public_path('assets/tica-ui/js/tica.js')));
  }

  /* ------------------------------------------------------------- helpers */

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
  }

  private function fakeSms(): object
  {
    $sms = new class implements SmsSender {
      public array $sent = [];
      public bool $fail = false;
      public function configured(): bool { return true; }
      public function send(string $phone, string $message): void
      {
        if ($this->fail) throw new \RuntimeException('provider down');
        $this->sent[] = [$phone, $message];
      }
    };
    $this->app->instance(SmsSender::class, $sms);

    return $sms;
  }

  private function organizer(array $prefs): Organizer
  {
    $organizer = new Organizer();
    $organizer->forceFill(['id' => 3, 'email' => 'o@example.com', 'phone' => '08031234567', 'preferences' => json_encode($prefs)]);

    return $organizer;
  }

  private function runSms(Organizer $organizer, string $status): void
  {
    $transfer = new PayoutTransfer();
    $transfer->forceFill(['id' => 9, 'status' => $status, 'amount' => 9800, 'reference' => 'tica-payout-abcdef123456']);
    $job = new SendPayoutStatusNotification(9, $status);
    $method = new \ReflectionMethod($job, 'sendSms');
    $method->setAccessible(true);
    $method->invoke($job, $organizer, $transfer);
  }
}
