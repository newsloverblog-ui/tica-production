<?php

namespace App\Support\Integrations;

use App\Models\BasicSettings\Basic;
use App\Models\PaymentGateway\OnlineGateway;
use App\Support\TicaPlatform;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Reads, saves and applies integration settings. Secrets never leave the
 * server: views get "set" / "not set" and the last four characters only.
 */
class Integrations
{
  /** @var array<string, object>|null */
  private static ?array $rows = null;

  public static function storeReady(): bool
  {
    try {
      return Schema::hasTable('platform_integrations');
    } catch (Throwable) {
      return false;
    }
  }

  /** platform_integrations rows by key (one query per request). */
  public static function rows(): array
  {
    if (self::$rows !== null) {
      return self::$rows;
    }
    try {
      self::$rows = self::storeReady() ? DB::table('platform_integrations')->get()->keyBy('key')->all() : [];
    } catch (Throwable $e) {
      Log::warning('Integrations could not be read', ['error' => $e->getMessage()]);
      self::$rows = [];
    }

    return self::$rows;
  }

  public static function flush(): void
  {
    self::reset();
  }

  /** Decrypted settings saved in platform_integrations for one integration. */
  public static function stored(string $key): array
  {
    $row = self::rows()[$key] ?? null;
    if (!$row || $row->settings === null || $row->settings === '') {
      return [];
    }
    try {
      return json_decode(Crypt::decryptString($row->settings), true) ?: [];
    } catch (Throwable $e) {
      Log::warning('Integration settings could not be decrypted (APP_KEY changed?)', ['key' => $key]);
      return [];
    }
  }

  /** The current value of one field, from wherever it lives. */
  public static function value(string $key, array $field): mixed
  {
    $source = (string) $field['source'];
    if ($source === 'store') {
      return self::stored($key)[$field['name']] ?? null;
    }
    if (str_starts_with($source, 'gateway:')) {
      [$keyword, $info] = explode('.', substr($source, 8), 2);
      return self::gatewayInfo($keyword)[$info] ?? null;
    }
    if (str_starts_with($source, 'basic.')) {
      return self::basic()?->{substr($source, 6)};
    }
    if ($source === 'platform.payout_provider') {
      return TicaPlatform::payoutProvider();
    }

    return null;
  }

  public static function enabled(string $key): bool
  {
    $def = IntegrationRegistry::get($key);
    $source = (string) ($def['enabled'] ?? 'always');
    if ($source === 'always') {
      return true;
    }
    if ($source === 'store') {
      return (bool) (self::rows()[$key]->enabled ?? false);
    }
    if (str_starts_with($source, 'gateway:')) {
      return (int) (self::gateway(substr($source, 8))?->status ?? 0) === 1;
    }
    if (str_starts_with($source, 'basic.')) {
      return (int) (self::basic()?->{substr($source, 6)} ?? 0) === 1;
    }

    return false;
  }

  /**
   * One integration as the admin sees it: fields with masked secrets, where
   * each value comes from, and an overall status.
   *
   * @return array<string, mixed>
   */
  public static function describe(string $key): array
  {
    $def = IntegrationRegistry::get($key);
    $fields = [];
    $missing = 0;
    $fromEnv = 0;
    foreach ($def['fields'] as $field) {
      $value = self::value($key, $field);
      $set = $value !== null && $value !== '' && $value !== false;
      $envSet = !empty($field['env']) && trim((string) env($field['env'], '')) !== '';
      if (!$set && $envSet) {
        $fromEnv++;
      }
      if (!$set && !$envSet && !empty($field['required'])) {
        $missing++;
      }
      $fields[] = $field + [
        'set' => $set,
        'env_set' => $envSet,
        'display' => $field['type'] === 'secret' ? null : ($set ? (string) (is_bool($value) ? (int) $value : $value) : (string) ($field['default'] ?? '')),
        'hint' => $field['type'] === 'secret' && $set ? '•••• ' . mb_substr((string) $value, -4) : null,
      ];
    }
    $row = self::rows()[$key] ?? null;
    $on = self::enabled($key);
    $needsStore = collect($def['fields'])->contains(fn ($f) => $f['source'] === 'store') || $def['enabled'] === 'store';
    $status = match (true) {
      $needsStore && !self::storeReady() && $fromEnv === 0 => ['needs_db', 'Needs the database update', ''],
      $missing > 0 && $fromEnv === 0 && !self::anySet($fields) => ['not_set', 'Not set up', ''],
      $missing > 0 => ['incomplete', 'Incomplete', 'orange'],
      !$on => ['off', 'Off', ''],
      $row && $row->test_ok === false => ['failing', 'Test failed', 'red'],
      !empty($def['apply_after_test']) && !($row && $row->test_ok) => ['untested', 'Waiting for a test', 'orange'],
      $fromEnv > 0 && !self::anySet($fields) => ['env', 'Set on the server', 'blue'],
      default => ['connected', 'Connected', 'green'],
    };
    $notes = [];
    foreach (($def['notes'] ?? []) as $note) {
      if (trim((string) env($note[0], '')) !== '') {
        $notes[] = $note[1];
      }
    }

    return [
      'key' => $key,
      'name' => $def['name'],
      'group' => $def['group'],
      'summary' => $def['summary'],
      'enabled' => $on,
      'toggleable' => $def['enabled'] !== 'always',
      'fields' => $fields,
      'status' => ['code' => $status[0], 'label' => $status[1], 'tone' => $status[2]],
      'tested_at' => $row->tested_at ?? null,
      'test_ok' => $row->test_ok ?? null,
      'test_message' => $row->test_message ?? null,
      'urls' => array_map(fn ($u) => ['label' => $u['label'], 'value' => isset($u['route']) ? route($u['route']) : url($u['url'])], $def['urls'] ?? []),
      'apply_after_test' => !empty($def['apply_after_test']),
      'generate' => $def['generate'] ?? null,
      'needs_store' => $needsStore,
      'notes' => $notes,
    ];
  }

  private static function anySet(array $fields): bool
  {
    return collect($fields)->contains(fn ($f) => $f['set']);
  }

  /**
   * Saves one integration. Blank secret fields keep what is saved.
   *
   * @param  array<string, mixed>  $input  field name => value
   */
  public static function save(string $key, array $input, ?bool $enabled, int $adminId): void
  {
    $def = IntegrationRegistry::get($key);
    $store = self::stored($key);
    $gatewayChanges = [];
    $basicChanges = [];
    foreach ($def['fields'] as $field) {
      $name = $field['name'];
      if (!array_key_exists($name, $input)) {
        continue;
      }
      $value = $input[$name];
      if ($field['type'] === 'secret' && ($value === null || trim((string) $value) === '')) {
        continue; // keep the saved secret
      }
      if ($field['type'] === 'toggle') {
        $value = (bool) $value;
      } elseif (is_string($value)) {
        $value = trim($value);
      }
      $source = (string) $field['source'];
      if ($source === 'store') {
        $store[$name] = $value;
      } elseif (str_starts_with($source, 'gateway:')) {
        [$keyword, $info] = explode('.', substr($source, 8), 2);
        $gatewayChanges[$keyword][$info] = $value;
      } elseif (str_starts_with($source, 'basic.')) {
        $basicChanges[substr($source, 6)] = $value === 'none' && $name === 'encryption' ? null : $value;
      } elseif ($source === 'platform.payout_provider') {
        TicaPlatform::savePayoutProvider((string) $value);
      }
    }

    DB::transaction(function () use ($key, $def, $store, $gatewayChanges, $basicChanges, $enabled, $adminId) {
      foreach ($gatewayChanges as $keyword => $changes) {
        $gateway = OnlineGateway::query()->where('keyword', $keyword)->first();
        if ($gateway) {
          $info = json_decode((string) $gateway->information, true) ?: [];
          $gateway->information = json_encode(array_merge($info, $changes));
          if ($enabled !== null && $def['enabled'] === 'gateway:' . $keyword) {
            $gateway->status = $enabled ? 1 : 0;
          }
          $gateway->save();
        }
      }
      if ($enabled !== null && str_starts_with((string) $def['enabled'], 'basic.')) {
        $basicChanges[substr((string) $def['enabled'], 6)] = $enabled ? 1 : 0;
      }
      if ($basicChanges !== []) {
        $basic = Basic::query()->firstOrNew(['uniqid' => 12345]);
        $basic->uniqid = 12345;
        foreach ($basicChanges as $column => $value) {
          $basic->{$column} = $value;
        }
        $basic->save();
      }
      if (self::storeReady()) {
        $existing = DB::table('platform_integrations')->where('key', $key)->first();
        $row = [
          'settings' => $store === [] ? null : Crypt::encryptString(json_encode($store)),
          'enabled' => $def['enabled'] === 'store' ? (bool) ($enabled ?? ($existing->enabled ?? false)) : (bool) ($existing->enabled ?? false),
          'updated_by_admin_id' => $adminId,
          'updated_at' => now(),
          // A change means the last test no longer proves anything.
          'tested_at' => null, 'test_ok' => null, 'test_message' => null,
        ];
        $existing
          ? DB::table('platform_integrations')->where('key', $key)->update($row)
          : DB::table('platform_integrations')->insert($row + ['key' => $key, 'created_at' => now()]);
      }
    });
    self::flush();
  }

  public static function recordTest(string $key, bool $ok, string $message): void
  {
    if (!self::storeReady()) {
      return;
    }
    $row = ['tested_at' => now(), 'test_ok' => $ok, 'test_message' => mb_substr($message, 0, 500), 'updated_at' => now()];
    DB::table('platform_integrations')->where('key', $key)->exists()
      ? DB::table('platform_integrations')->where('key', $key)->update($row)
      : DB::table('platform_integrations')->insert($row + ['key' => $key, 'enabled' => false, 'created_at' => now()]);
    self::flush();
  }

  /**
   * Applies saved values on every request (called from RuntimeConfigurationService).
   * A value saved here wins over the server variable; nothing saved = no change.
   */
  public static function hydrate(): void
  {
    try {
      foreach (IntegrationRegistry::all() as $key => $def) {
        $row = self::rows()[$key] ?? null;
        if (!empty($def['apply_after_test']) && !($row && $row->test_ok)) {
          continue;
        }
        $stored = self::stored($key);
        foreach ($def['fields'] as $field) {
          if ($field['source'] !== 'store' || empty($field['config'])) {
            continue;
          }
          $value = $stored[$field['name']] ?? null;
          if ($value !== null && $value !== '') {
            Config::set($field['config'], $value);
          }
        }
        if (!empty($def['enabled_config']) && $row) {
          Config::set($def['enabled_config'], (bool) $row->enabled);
        }
      }
      self::hydrateMail();
    } catch (Throwable $e) {
      Log::warning('Integrations could not be applied', ['error' => $e->getMessage()]);
    }
  }

  /** SMTP from Admin > Integrations > Email, only once a test email went through. */
  private static function hydrateMail(): void
  {
    $row = self::rows()['email'] ?? null;
    $basic = self::basic();
    if (!$row || !$row->test_ok || !$basic || (int) $basic->smtp_status !== 1 || trim((string) $basic->smtp_host) === '') {
      return;
    }
    self::applySmtp($basic->smtp_host, (int) $basic->smtp_port, $basic->encryption, $basic->smtp_username, $basic->smtp_password, $basic->from_mail, $basic->from_name);
  }

  public static function applySmtp(?string $host, int $port, ?string $encryption, ?string $username, ?string $password, ?string $fromMail, ?string $fromName): void
  {
    Config::set('mail.default', 'smtp');
    Config::set('mail.mailers.smtp', array_merge((array) config('mail.mailers.smtp'), [
      'transport' => 'smtp',
      'host' => $host,
      'port' => $port ?: 587,
      'encryption' => in_array($encryption, ['tls', 'ssl'], true) ? $encryption : null,
      'scheme' => $encryption === 'ssl' ? 'smtps' : null,
      'username' => $username,
      'password' => $password,
    ]));
    if (filled($fromMail)) {
      Config::set('mail.from', ['address' => $fromMail, 'name' => $fromName ?: config('app.name')]);
    }
    app()->forgetInstance('mail.manager');
    app()->forgetInstance('mailer');
  }

  private static ?Basic $basic = null;

  private static function basic(): ?Basic
  {
    try {
      return self::$basic ??= Basic::query()->first();
    } catch (Throwable) {
      return null;
    }
  }

  private static function gateway(string $keyword): ?OnlineGateway
  {
    try {
      return OnlineGateway::query()->where('keyword', $keyword)->first();
    } catch (Throwable) {
      return null;
    }
  }

  private static function gatewayInfo(string $keyword): array
  {
    return json_decode((string) self::gateway($keyword)?->information, true) ?: [];
  }

  /** For tests and after saving: forget per-request caches. */
  public static function reset(): void
  {
    self::$rows = null;
    self::$basic = null;
  }
}
