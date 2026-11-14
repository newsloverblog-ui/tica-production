<?php

namespace App\Support\Integrations;

use App\Services\MonnifyGatewayClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * "Test connection" for each integration. Every test is read-only on the
 * provider's side (no charge, no payout, no message to a customer); the email
 * test sends one message to the admin who pressed the button.
 *
 * @return array{0:bool,1:string} [passed, plain-language result]
 */
class IntegrationTester
{
  public static function run(string $key, string $adminEmail): array
  {
    try {
      return match ($key) {
        'paystack' => self::paystack(),
        'flutterwave' => self::flutterwave(),
        'monnify' => self::monnify(),
        'payouts' => self::payouts(),
        'email' => self::email($adminEmail),
        'sms' => self::sms(),
        'push' => self::push(),
        'google_login' => self::google(),
        'facebook_login' => self::facebook(),
        'recaptcha' => self::recaptcha(),
        'storage' => self::storage(),
        default => [false, 'This integration has no test.'],
      };
    } catch (Throwable $e) {
      return [false, 'The test could not run: ' . mb_substr($e->getMessage(), 0, 200)];
    }
  }

  private static function val(string $key, string $field): string
  {
    $def = collect(IntegrationRegistry::get($key)['fields'])->firstWhere('name', $field);
    $value = Integrations::value($key, $def);
    if (($value === null || $value === '') && !empty($def['env'])) {
      $value = env($def['env']);
    }

    return trim((string) ($value ?? ($def['default'] ?? '')));
  }

  private static function http()
  {
    return Http::acceptJson()->connectTimeout(5)->timeout(12);
  }

  private static function paystack(): array
  {
    $secret = self::val('paystack', 'secret_key');
    if ($secret === '') {
      return [false, 'Add the secret key first.'];
    }
    $r = self::http()->withToken($secret)->get('https://api.paystack.co/balance');
    if ($r->status() === 401) {
      return [false, 'Paystack rejected this secret key.'];
    }
    if (!$r->successful()) {
      return [false, 'Paystack answered ' . $r->status() . ': ' . mb_substr((string) $r->json('message'), 0, 150)];
    }
    $mode = str_starts_with($secret, 'sk_test_') ? 'test mode' : 'live mode';

    return [true, "Paystack accepted the key ({$mode})."];
  }

  private static function flutterwave(): array
  {
    $secret = self::val('flutterwave', 'secret_key');
    if ($secret === '') {
      return [false, 'Add the secret key first.'];
    }
    $r = self::http()->withToken($secret)->get('https://api.flutterwave.com/v3/banks/NG');
    if (!$r->successful()) {
      return [false, 'Flutterwave rejected this secret key (' . $r->status() . ').'];
    }
    $hash = self::val('flutterwave', 'webhook_hash');

    return strlen($hash) >= 32
      ? [true, 'Flutterwave accepted the key, and the webhook hash is set.']
      : [false, 'Flutterwave accepted the key, but the webhook secret hash is missing or shorter than 32 characters, so payment confirmations would be refused.'];
  }

  private static function monnify(): array
  {
    $info = [
      'api_key' => self::val('monnify', 'api_key'),
      'secret_key' => self::val('monnify', 'secret_key'),
      'contract_code' => self::val('monnify', 'contract_code'),
      'mode' => self::val('monnify', 'mode') ?: 'live',
    ];
    if ($info['api_key'] === '' || $info['secret_key'] === '') {
      return [false, 'Add the API key and secret key first.'];
    }
    (new MonnifyGatewayClient($info))->verifyCredentials();

    return [true, 'Monnify accepted the keys (' . $info['mode'] . ' mode).' . (self::val('monnify', 'disbursement_wallet') === '' ? ' Add the disbursement wallet to send payouts with Monnify.' : '')];
  }

  private static function payouts(): array
  {
    $provider = self::val('payouts', 'provider') ?: 'paystack';
    [$ok, $message] = $provider === 'monnify' ? self::monnify() : self::paystack();

    return [$ok, 'Payouts use ' . ucfirst($provider) . '. ' . $message];
  }

  private static function email(string $to): array
  {
    foreach (['host', 'port', 'username', 'password', 'from_mail'] as $field) {
      if (self::val('email', $field) === '') {
        return [false, 'Fill in every field first.'];
      }
    }
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
      return [false, 'Your admin account has no email address to send the test to.'];
    }
    $previous = [config('mail.default'), config('mail.mailers.smtp'), config('mail.from')];
    try {
      $encryption = self::val('email', 'encryption');
      Integrations::applySmtp(self::val('email', 'host'), (int) self::val('email', 'port'), $encryption, self::val('email', 'username'), self::val('email', 'password'), self::val('email', 'from_mail'), self::val('email', 'from_name'));
      Mail::raw("This is a test from TICA Admin > Integrations.\n\nIf you can read this, email is working.", function ($m) use ($to) {
        $m->to($to)->subject('TICA email test');
      });
    } finally {
      config(['mail.default' => $previous[0], 'mail.mailers.smtp' => $previous[1], 'mail.from' => $previous[2]]);
      app()->forgetInstance('mail.manager');
      app()->forgetInstance('mailer');
    }

    return [true, "Sent a test email to {$to}. If it arrived, emails are now sent through this server."];
  }

  private static function sms(): array
  {
    $key = self::val('sms', 'api_key');
    if ($key === '') {
      return [false, 'Add the API key first.'];
    }
    $base = rtrim(self::val('sms', 'base_url') ?: 'https://api.ng.termii.com', '/');
    $r = self::http()->get($base . '/api/get-balance', ['api_key' => $key]);
    if (!$r->successful()) {
      return [false, 'Termii rejected this API key (' . $r->status() . ').'];
    }
    $balance = $r->json('balance');

    return [true, 'Termii accepted the key.' . ($balance !== null ? ' Balance: ' . $r->json('currency', 'NGN') . ' ' . number_format((float) $balance, 2) . '.' : '')];
  }

  private static function push(): array
  {
    $public = self::val('push', 'public_key');
    $private = self::val('push', 'private_key');
    $subject = self::val('push', 'subject');
    if (strlen($public) < 40 || strlen($private) < 40) {
      return [false, 'Both keys must be filled in. Use "Generate keys" if you don\'t have them.'];
    }
    if ($public === $private) {
      return [false, 'The public and private keys must be different.'];
    }
    if (!preg_match('/^(mailto:[^@\s]+@[^@\s]+|https:\/\/\S+)$/', $subject)) {
      return [false, 'The contact must start with mailto: or https://.'];
    }

    return [true, 'The keys look right. Browsers that allowed notifications will receive them.'];
  }

  private static function google(): array
  {
    $id = self::val('google_login', 'client_id');
    if (!str_ends_with($id, '.apps.googleusercontent.com')) {
      return [false, 'The client ID should end with .apps.googleusercontent.com.'];
    }
    if (self::val('google_login', 'client_secret') === '') {
      return [false, 'Add the client secret.'];
    }
    // Google answers a bad client ID on its sign-in page with "invalid_client".
    $r = Http::connectTimeout(5)->timeout(12)->withoutRedirecting()->get('https://accounts.google.com/o/oauth2/v2/auth', [
      'client_id' => $id, 'redirect_uri' => url('login/google/callback'), 'response_type' => 'code', 'scope' => 'openid email profile',
    ]);
    if (str_contains((string) $r->body(), 'invalid_client') || str_contains((string) $r->header('Location'), 'invalid_client')) {
      return [false, 'Google does not recognise this client ID.'];
    }
    if (str_contains((string) $r->body(), 'redirect_uri_mismatch') || str_contains((string) $r->header('Location'), 'redirect_uri_mismatch')) {
      return [false, 'Google knows this client, but the redirect URI below is not added to it yet.'];
    }

    return [true, 'Google recognises this client ID. Try "Continue with Google" on the log-in page to finish checking.'];
  }

  private static function facebook(): array
  {
    $id = self::val('facebook_login', 'app_id');
    $secret = self::val('facebook_login', 'app_secret');
    if ($id === '' || $secret === '') {
      return [false, 'Add the app ID and secret first.'];
    }
    $r = self::http()->get('https://graph.facebook.com/oauth/access_token', ['client_id' => $id, 'client_secret' => $secret, 'grant_type' => 'client_credentials']);

    return $r->successful() && $r->json('access_token')
      ? [true, 'Facebook accepted the app ID and secret.']
      : [false, 'Facebook rejected the app ID or secret.'];
  }

  private static function recaptcha(): array
  {
    $secret = self::val('recaptcha', 'secret_key');
    if ($secret === '' || self::val('recaptcha', 'site_key') === '') {
      return [false, 'Add both keys first.'];
    }
    $r = Http::asForm()->connectTimeout(5)->timeout(12)->post('https://www.google.com/recaptcha/api/siteverify', ['secret' => $secret, 'response' => 'test']);
    $codes = (array) $r->json('error-codes', []);
    if (in_array('invalid-input-secret', $codes, true)) {
      return [false, 'Google rejected the secret key.'];
    }

    return [true, 'Google accepted the secret key. Open the sign-up page to check the site key shows the box.'];
  }

  private static function storage(): array
  {
    $url = rtrim(self::val('storage', 'url'), '/');
    $key = self::val('storage', 'service_key');
    $bucket = self::val('storage', 'bucket') ?: 'tica-public';
    if ($url === '' || $key === '') {
      return [false, 'Add the project URL and secret key first.'];
    }
    $r = self::http()->withHeaders(['apikey' => $key])->withToken($key)->get("{$url}/storage/v1/bucket/{$bucket}");
    if ($r->status() === 404 || $r->status() === 400) {
      return [false, "Supabase accepted the key, but there is no bucket called \"{$bucket}\"."];
    }
    if (!$r->successful()) {
      return [false, 'Supabase rejected the key (' . $r->status() . ').'];
    }
    if (!$r->json('public')) {
      return [false, "The bucket \"{$bucket}\" is private. Make it public so images can be shown."];
    }

    return [true, "Supabase accepted the key and the public bucket \"{$bucket}\" exists. New uploads go here."];
  }
}
