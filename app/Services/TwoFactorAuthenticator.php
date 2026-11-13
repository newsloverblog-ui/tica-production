<?php

namespace App\Services;

use App\Models\Organizer;
use App\Support\Totp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Authenticator-app two-factor for organisers. The secret is encrypted at
 * rest; recovery codes are stored only as keyed hashes (inside an encrypted
 * column) and shown once. Each code works once.
 */
class TwoFactorAuthenticator
{
  private const SETUP_KEY = 'totp.setup';
  private const LOGIN_KEY = 'totp.login';
  private const RECOVERY_COUNT = 8;

  public function __construct(private Totp $totp = new Totp())
  {
  }

  public function enabled(Organizer $organizer): bool
  {
    return $organizer->totp_confirmed_at !== null && (string) $organizer->totp_secret !== '';
  }

  /* --------------------------------------------------------------- setup */

  /** Step 1: password checked; a new secret is held in the session until confirmed. */
  public function startSetup(Request $request, Organizer $organizer): array
  {
    if ($this->enabled($organizer)) {
      throw ValidationException::withMessages(['code' => 'Two-factor is already on.']);
    }
    $secret = $this->totp->generateSecretKey();
    $request->session()->put(self::SETUP_KEY, ['organizer_id' => (int) $organizer->id, 'secret' => encrypt($secret), 'expires' => now()->addMinutes(15)->getTimestamp()]);
    $url = $this->totp->getQRCodeUrl('TICA', (string) $organizer->email, $secret);

    return [
      'secret' => trim(chunk_split($secret, 4, ' ')),
      'qr' => (string) QrCode::format('svg')->size(200)->margin(1)->errorCorrection('M')->generate($url),
    ];
  }

  /** Step 2: the first code from the app proves it's set up. Returns recovery codes (shown once). */
  public function confirmSetup(Request $request, Organizer $organizer, string $code): array
  {
    $setup = $request->session()->get(self::SETUP_KEY);
    if (!is_array($setup) || (int) $setup['organizer_id'] !== (int) $organizer->id || $setup['expires'] < time()) {
      throw ValidationException::withMessages(['code' => 'Setup timed out. Start again.']);
    }
    $secret = decrypt($setup['secret']);
    $step = $this->totp->verifyKeyNewer($secret, $code, null);
    if ($step === false) {
      throw ValidationException::withMessages(['code' => 'That code didn\'t work. Check your phone\'s time is set automatically and try the newest code.']);
    }
    $codes = $this->newRecoveryCodes();
    $organizer->forceFill([
      'totp_secret' => $secret,
      'totp_recovery_codes' => array_map(fn ($c) => $this->hashRecovery($c), $codes),
      'totp_confirmed_at' => now(),
      'totp_last_step' => $step,
    ])->save();
    $request->session()->forget(self::SETUP_KEY);
    AuditLogger::record('account.totp_enabled', $organizer);

    return $codes;
  }

  public function disable(Organizer $organizer): void
  {
    $organizer->forceFill(['totp_secret' => null, 'totp_recovery_codes' => null, 'totp_confirmed_at' => null, 'totp_last_step' => null])->save();
    AuditLogger::record('account.totp_disabled', $organizer);
  }

  public function regenerateRecoveryCodes(Organizer $organizer): array
  {
    $codes = $this->newRecoveryCodes();
    $organizer->forceFill(['totp_recovery_codes' => array_map(fn ($c) => $this->hashRecovery($c), $codes)])->save();
    AuditLogger::record('account.totp_recovery_codes_regenerated', $organizer);

    return $codes;
  }

  public function recoveryCodesLeft(Organizer $organizer): int
  {
    return count((array) ($organizer->totp_recovery_codes ?? []));
  }

  /* -------------------------------------------------------------- verify */

  /** A 6-digit app code (never the same one twice) or an unused recovery code. */
  public function verify(Organizer $organizer, string $input): bool
  {
    if (!$this->enabled($organizer)) {
      return false;
    }
    $input = trim($input);

    return DB::transaction(function () use ($organizer, $input) {
      $fresh = Organizer::whereKey($organizer->id)->lockForUpdate()->firstOrFail();
      if (preg_match('/^\d{3}\s?\d{3}$/', $input)) {
        $step = $this->totp->verifyKeyNewer((string) $fresh->totp_secret, $input, $fresh->totp_last_step !== null ? (int) $fresh->totp_last_step : null);
        if ($step === false) {
          return false;
        }
        $fresh->forceFill(['totp_last_step' => $step])->save();
        $organizer->setRawAttributes($fresh->getAttributes(), true);

        return true;
      }

      $hash = $this->hashRecovery($input);
      $left = (array) ($fresh->totp_recovery_codes ?? []);
      foreach ($left as $i => $stored) {
        if (hash_equals((string) $stored, $hash)) {
          unset($left[$i]);
          $fresh->forceFill(['totp_recovery_codes' => array_values($left)])->save();
          $organizer->setRawAttributes($fresh->getAttributes(), true);
          AuditLogger::record('account.totp_recovery_code_used', $fresh, [], ['left' => count($left)]);

          return true;
        }
      }

      return false;
    }, 3);
  }

  /* --------------------------------------------------------------- login */

  /** Password was right; sign back out and ask for the app code. */
  public function beginLogin(Request $request, Organizer $organizer, string $redirect): void
  {
    Auth::guard('organizer')->logout();
    $request->session()->regenerate();
    $request->session()->put(self::LOGIN_KEY, [
      'organizer_id' => (int) $organizer->id,
      'redirect' => str_starts_with($redirect, '/') && !str_starts_with($redirect, '//') ? $redirect : '/dashboard',
      'expires' => now()->addMinutes(10)->getTimestamp(),
      'attempts' => 0,
    ]);
  }

  public function pendingLogin(Request $request): ?array
  {
    $pending = $request->session()->get(self::LOGIN_KEY);

    return is_array($pending) && $pending['expires'] >= time() ? $pending : null;
  }

  public function completeLogin(Request $request, string $code): string
  {
    $pending = $this->pendingLogin($request);
    if (!$pending) {
      $request->session()->forget(self::LOGIN_KEY);
      throw ValidationException::withMessages(['code' => 'This sign-in timed out. Sign in again.']);
    }
    if ($pending['attempts'] >= 5) {
      $request->session()->forget(self::LOGIN_KEY);
      throw ValidationException::withMessages(['code' => 'Too many wrong codes. Sign in again.']);
    }
    $organizer = Organizer::query()->whereKey($pending['organizer_id'])->where('status', 1)->first();
    if (!$organizer || !$this->verify($organizer, $code)) {
      $pending['attempts']++;
      $request->session()->put(self::LOGIN_KEY, $pending);
      if ($organizer) {
        AuditLogger::record('account.totp_login_failed', $organizer, [], ['attempt' => $pending['attempts']]);
      }
      throw ValidationException::withMessages(['code' => 'That code didn\'t work. Use the newest code from your app, or one of your recovery codes.']);
    }

    Auth::guard('organizer')->login($organizer);
    $request->session()->forget(self::LOGIN_KEY);
    $request->session()->regenerate();
    Auth::guard('scanner')->logout();
    $request->session()->forget('tica_team_member');
    $request->session()->put('secret_login', 0);
    AuditLogger::record('account.totp_login_completed', $organizer);

    return $pending['redirect'];
  }

  /* ------------------------------------------------------------- helpers */

  private function newRecoveryCodes(): array
  {
    return array_map(fn () => Str::lower(Str::random(5) . '-' . Str::random(5)), range(1, self::RECOVERY_COUNT));
  }

  private function hashRecovery(string $code): string
  {
    return hash_hmac('sha256', Str::lower(preg_replace('/\s+/', '', $code)), (string) config('app.key'));
  }
}
