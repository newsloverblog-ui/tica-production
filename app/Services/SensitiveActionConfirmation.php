<?php

namespace App\Services;

use App\Models\Organizer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Re-checks the owner before money-related changes (payout account, and
 * later anything else sensitive): their password, and their authenticator
 * code when two-factor is on. Five wrong tries locks it for 15 minutes.
 */
class SensitiveActionConfirmation
{
  public function confirm(Organizer $organizer, string $password, ?string $otp, string $action): void
  {
    $key = 'sensitive-confirm:' . $action . ':' . $organizer->id;
    if (RateLimiter::tooManyAttempts($key, 5)) {
      $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);
      throw ValidationException::withMessages(['password' => 'Too many tries. Wait ' . $minutes . ' minute' . ($minutes === 1 ? '' : 's') . ' and try again.']);
    }

    if (!Hash::check($password, (string) $organizer->password)) {
      RateLimiter::hit($key, 900);
      AuditLogger::record('organizer.sensitive_confirmation_failed', $organizer, [], [], ['action' => $action, 'reason' => 'password']);
      throw ValidationException::withMessages(['password' => 'That password isn\'t right.']);
    }

    if ($this->needsAuthenticatorCode($organizer)) {
      if (trim((string) $otp) === '') {
        throw ValidationException::withMessages(['otp' => 'Enter the 6-digit code from your authenticator app.']);
      }
      if (!app(TwoFactorAuthenticator::class)->verify($organizer, (string) $otp)) {
        RateLimiter::hit($key, 900);
        AuditLogger::record('organizer.sensitive_confirmation_failed', $organizer, [], [], ['action' => $action, 'reason' => 'otp']);
        throw ValidationException::withMessages(['otp' => 'That code isn\'t right. Use the current code from your app, or a recovery code.']);
      }
    }

    RateLimiter::clear($key);
  }

  public function needsAuthenticatorCode(Organizer $organizer): bool
  {
    return !empty($organizer->totp_confirmed_at);
  }
}
