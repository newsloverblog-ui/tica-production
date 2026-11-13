<?php

namespace Tests\Unit;

use App\Http\Controllers\BackEnd\IntegrationController;
use App\Models\Admin;
use App\Support\Integrations\IntegrationRegistry;
use App\Support\Integrations\Integrations;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/** Admin > Integrations: one place for every outside service, secrets kept secret. */
class IntegrationsTest extends TestCase
{
  public function test_every_integration_is_well_formed(): void
  {
    foreach (IntegrationRegistry::all() as $key => $def) {
      $this->assertArrayHasKey($def['group'], IntegrationRegistry::GROUPS, $key);
      foreach ($def['fields'] as $field) {
        $this->assertContains($field['type'], ['text', 'secret', 'select', 'toggle', 'email', 'url'], "$key.{$field['name']}");
        $this->assertMatchesRegularExpression('/^(store|gateway:[a-z]+\.[a-z_]+|basic\.[a-z_]+|platform\.payout_provider)$/', $field['source'], "$key.{$field['name']}");
        if ($field['source'] === 'store') {
          $this->assertNotEmpty($field['config'] ?? null, "$key.{$field['name']} needs a config key to apply");
        }
        if ($field['type'] === 'select') {
          $this->assertNotEmpty($field['options'], "$key.{$field['name']}");
        }
      }
      foreach ($def['urls'] ?? [] as $url) {
        if (isset($url['route'])) {
          $this->assertTrue(Route::has($url['route']), "$key: route {$url['route']}");
        }
      }
    }
  }

  public function test_covers_payments_payouts_email_sms_push_signin_and_storage(): void
  {
    $keys = array_keys(IntegrationRegistry::all());
    foreach (['paystack', 'flutterwave', 'monnify', 'payouts', 'email', 'sms', 'push', 'google_login', 'facebook_login', 'recaptcha', 'storage'] as $key) {
      $this->assertContains($key, $keys);
    }
  }

  public function test_secrets_are_encrypted_masked_and_kept_when_left_blank(): void
  {
    Integrations::save('sms', ['api_key' => 'TLsecretKEY1234', 'sender_id' => 'TICA', 'channel' => 'dnd'], true, 1);
    $raw = (string) DB::table('platform_integrations')->where('key', 'sms')->value('settings');
    $this->assertStringNotContainsString('TLsecretKEY1234', $raw);

    $sms = Integrations::describe('sms');
    $key = collect($sms['fields'])->firstWhere('name', 'api_key');
    $this->assertNull($key['display'], 'A secret is never sent back to the page.');
    $this->assertSame('•••• 1234', $key['hint']);

    Integrations::save('sms', ['api_key' => '', 'sender_id' => 'TICANG'], true, 1);
    $this->assertSame('TLsecretKEY1234', Integrations::stored('sms')['api_key']);
    $this->assertSame('TICANG', Integrations::stored('sms')['sender_id']);
  }

  public function test_saved_values_override_server_settings_on_every_request(): void
  {
    config(['ticket_delivery.sms.api_key' => 'from-env', 'ticket_delivery.sms.enabled' => false]);
    Integrations::save('sms', ['api_key' => 'from-admin', 'sender_id' => 'TICA', 'channel' => 'generic'], true, 1);
    Integrations::hydrate();
    $this->assertSame('from-admin', config('ticket_delivery.sms.api_key'));
    $this->assertSame('generic', config('ticket_delivery.sms.channel'));
    $this->assertTrue(config('ticket_delivery.sms.enabled'));
  }

  public function test_storage_and_email_only_switch_over_after_a_passing_test(): void
  {
    config(['services.supabase.url' => 'https://old.supabase.co', 'mail.default' => 'log']);
    Integrations::save('storage', ['url' => 'https://new.supabase.co', 'service_key' => 'k', 'bucket' => 'tica-public'], true, 1);
    Integrations::save('email', ['host' => 'smtp.example.com', 'port' => '587', 'encryption' => 'tls', 'username' => 'u', 'password' => 'p', 'from_mail' => 'hi@tica.ng', 'from_name' => 'TICA'], true, 1);
    Integrations::hydrate();
    $this->assertSame('https://old.supabase.co', config('services.supabase.url'));
    $this->assertSame('log', config('mail.default'));

    Integrations::recordTest('storage', true, 'ok');
    Integrations::recordTest('email', true, 'ok');
    Integrations::hydrate();
    $this->assertSame('https://new.supabase.co', config('services.supabase.url'));
    $this->assertSame('smtp', config('mail.default'));
    $this->assertSame('smtp.example.com', config('mail.mailers.smtp.host'));
    $this->assertSame('hi@tica.ng', config('mail.from.address'));
  }

  public function test_a_change_clears_the_last_test(): void
  {
    Integrations::save('sms', ['api_key' => 'a', 'sender_id' => 'TICA', 'channel' => 'dnd'], true, 1);
    Integrations::recordTest('sms', true, 'ok');
    Integrations::save('sms', ['sender_id' => 'OTHER'], true, 1);
    $this->assertNull(DB::table('platform_integrations')->where('key', 'sms')->value('test_ok'));
  }

  public function test_gateway_keys_merge_and_the_switch_follows(): void
  {
    Integrations::save('monnify', ['api_key' => 'MK_NEW', 'secret_key' => '', 'contract_code' => '123', 'mode' => 'live'], true, 1);
    $gateway = \App\Models\PaymentGateway\OnlineGateway::query()->where('keyword', 'monnify')->first();
    $info = json_decode((string) $gateway->information, true);
    $this->assertSame('MK_NEW', $info['api_key']);
    $this->assertSame('old-secret', $info['secret_key'], 'A blank secret keeps the saved one.');
    $this->assertSame(1, (int) $gateway->status);
  }

  public function test_only_a_super_admin_can_open_integrations(): void
  {
    $this->actingAsAdmin(roleId: 3);
    $this->expectException(HttpException::class);
    app(IntegrationController::class)->index();
  }

  public function test_changing_a_secret_needs_the_admin_password(): void
  {
    $this->actingAsAdmin();
    $request = Request::create('/admin/integrations/sms', 'POST', ['f_api_key' => 'new-key', 'f_sender_id' => 'TICA', 'f_channel' => 'dnd', 'enabled' => '1']);
    app()->instance('request', $request);
    try {
      app(IntegrationController::class)->update($request, 'sms');
      $this->fail('Saved a secret without the password.');
    } catch (ValidationException $e) {
      $this->assertArrayHasKey('current_password', $e->errors());
    }
    $this->assertSame([], Integrations::stored('sms'));
  }

  public function test_a_key_in_the_wrong_format_is_refused_in_plain_words(): void
  {
    $this->actingAsAdmin();
    $request = Request::create('/admin/integrations/paystack', 'POST', ['f_secret_key' => 'pk_live_wrongkind', 'current_password' => 'right-password']);
    app()->instance('request', $request);
    try {
      app(IntegrationController::class)->update($request, 'paystack');
      $this->fail('Accepted a public key as the secret key.');
    } catch (ValidationException $e) {
      $this->assertStringContainsString('Secret key', implode(' ', $e->errors()['f_secret_key']));
    }
  }

  protected function setUp(): void
  {
    parent::setUp();
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    DB::purge('sqlite');
    DB::setDefaultConnection('sqlite');
    Schema::create('basic_settings', function (Blueprint $t) {
      $t->id();
      $t->integer('uniqid')->nullable();
      foreach (['smtp_host', 'smtp_port', 'encryption', 'smtp_username', 'smtp_password', 'from_mail', 'from_name', 'google_client_id', 'google_client_secret', 'facebook_app_id', 'facebook_app_secret', 'google_recaptcha_site_key', 'google_recaptcha_secret_key', 'tica_platform', 'website_title', 'timezone'] as $c) {
        $t->text($c)->nullable();
      }
      foreach (['smtp_status', 'google_login_status', 'facebook_login_status', 'google_recaptcha_status'] as $c) {
        $t->integer($c)->default(0);
      }
      $t->timestamp('updated_at')->nullable();
    });
    DB::table('basic_settings')->insert(['uniqid' => 12345]);
    Schema::create('online_gateways', function (Blueprint $t) { $t->id(); $t->string('name')->nullable(); $t->string('keyword'); $t->text('information')->nullable(); $t->integer('status')->default(0); $t->timestamps(); });
    $monnify = new \App\Models\PaymentGateway\OnlineGateway();
    $monnify->forceFill(['name' => 'Monnify', 'keyword' => 'monnify', 'status' => 0, 'information' => json_encode(['api_key' => 'MK_OLD', 'secret_key' => 'old-secret'])])->save();
    Schema::create('audit_logs', function (Blueprint $t) { $t->id(); foreach (['actor_type', 'actor_id', 'action', 'subject_type', 'subject_id', 'before', 'after', 'metadata', 'request_id', 'ip_address', 'user_agent'] as $c) { $t->text($c)->nullable(); } $t->timestamp('created_at')->nullable(); });
    (require base_path('database/migrations/2026_11_13_220000_create_platform_integrations_table.php'))->up();
    Integrations::reset();
  }

  private function actingAsAdmin(?int $roleId = null): void
  {
    $admin = new Admin();
    $admin->forceFill(['id' => 1, 'role_id' => $roleId, 'email' => 'admin@tica.ng', 'password' => Hash::make('right-password')]);
    $admin->exists = true;
    Auth::guard('admin')->setUser($admin);
  }
}
