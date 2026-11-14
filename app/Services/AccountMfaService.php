<?php

namespace App\Services;

use App\Models\AccountLoginChallenge;
use App\Models\Admin;
use App\Models\Customer;
use App\Models\Organizer;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccountMfaService
{
    private const ACTORS = [
        'admin' => Admin::class,
        'organizer' => Organizer::class,
        'customer' => Customer::class,
    ];

    private const SETUP_TTL_SECONDS = 600;

    public function enabled(Authenticatable $actor): bool
    {
        return $actor->getAttribute('two_factor_enabled_at') !== null;
    }

    /** Authenticator is current; email remains only for accounts enrolled before this upgrade. */
    public function method(Authenticatable $actor): ?string
    {
        if (!$this->enabled($actor)) {
            return null;
        }

        return trim((string) $actor->getAttribute('two_factor_secret')) !== '' ? 'authenticator' : 'email';
    }

    public function beginLogin(Request $request, string $guard, Authenticatable $actor, string $redirect): AccountLoginChallenge
    {
        $this->guardClass($guard);
        Auth::guard($guard)->logout();
        $request->session()->regenerate();
        $challenge = $this->issueLoginChallenge($request, $guard, $actor, $this->safePath($redirect, $guard));
        $request->session()->put('mfa.login.challenge', $challenge->public_id);

        return $challenge;
    }

    public function pendingLoginMethod(Request $request): string
    {
        $publicId = (string) $request->session()->get('mfa.login.challenge');
        $challenge = AccountLoginChallenge::query()->where('public_id', $publicId)->first();
        if (!$challenge || $challenge->consumed_at || $challenge->expires_at->isPast()) {
            return 'authenticator';
        }
        $class = $this->guardClass($challenge->actor_type);
        $actor = $class::query()->find($challenge->actor_id);

        return $actor && $this->method($actor) === 'email' ? 'email' : 'authenticator';
    }

    /** Start binding a TOTP authenticator after re-entering the account password. */
    public function beginSetup(Request $request, string $guard, string $password): array
    {
        $actor = $this->authenticated($guard);
        $this->requirePassword($actor, $password);
        if ($this->method($actor) === 'authenticator') {
            throw ValidationException::withMessages(['action' => 'An authenticator app is already connected.']);
        }

        $secret = $this->base32Encode(random_bytes(20));
        $request->session()->put("mfa.setup.$guard", [
            'actor_id' => (int) $actor->getAuthIdentifier(),
            'guard' => $guard,
            'secret' => Crypt::encryptString($secret),
            'expires_at' => now()->addSeconds(self::SETUP_TTL_SECONDS)->getTimestamp(),
            'binding' => $this->hmac($this->sessionBinding($request)),
        ]);
        AuditLogger::record('account.mfa_setup_started', $actor, [], ['guard' => $guard]);

        return ['secret' => $secret, 'uri' => $this->provisioningUri($actor, $secret)];
    }

    public function pendingSetup(Request $request, string $guard): ?array
    {
        $actor = $this->authenticated($guard);
        $pending = $request->session()->get("mfa.setup.$guard");
        if (!is_array($pending)
            || ($pending['guard'] ?? null) !== $guard
            || (int) ($pending['actor_id'] ?? 0) !== (int) $actor->getAuthIdentifier()
            || (int) ($pending['expires_at'] ?? 0) < now()->getTimestamp()
            || !hash_equals((string) ($pending['binding'] ?? ''), $this->hmac($this->sessionBinding($request)))) {
            $request->session()->forget("mfa.setup.$guard");
            return null;
        }

        try {
            $secret = Crypt::decryptString((string) ($pending['secret'] ?? ''));
        } catch (\Throwable) {
            $request->session()->forget("mfa.setup.$guard");
            return null;
        }

        return ['secret' => $secret, 'uri' => $this->provisioningUri($actor, $secret)];
    }

    /** Verify the first authenticator code, then turn MFA on and issue one-time recovery codes. */
    public function completeSetup(Request $request, string $guard, string $code): array
    {
        $actor = $this->authenticated($guard);
        $setup = $this->pendingSetup($request, $guard);
        if (!$setup) {
            throw ValidationException::withMessages(['code' => 'This setup has expired. Start again.']);
        }
        if (!$this->verifyTotp($setup['secret'], $code)) {
            throw ValidationException::withMessages(['code' => 'That code is not correct. Check your phone and try again.']);
        }

        $plainRecoveryCodes = $this->makeRecoveryCodes();
        $before = ['two_factor_enabled_at' => $actor->getAttribute('two_factor_enabled_at'), 'method' => $this->method($actor)];
        $actor->setAttribute('two_factor_secret', $setup['secret']);
        $actor->setAttribute('two_factor_recovery_codes', array_map(fn (string $value) => $this->recoveryHash($value), $plainRecoveryCodes));
        $actor->setAttribute('two_factor_enabled_at', now());
        $actor->save();
        $request->session()->forget("mfa.setup.$guard");
        $request->session()->regenerate();
        AuditLogger::record('account.mfa_enabled', $actor, $before, ['two_factor_enabled_at' => $actor->getAttribute('two_factor_enabled_at'), 'method' => 'authenticator']);
        $this->notifyFactorChange($actor, $guard, true);

        return ['enabled' => true, 'recovery_codes' => $plainRecoveryCodes];
    }

    /** Disable authenticator MFA only after both password and an enrolled factor are verified. */
    public function disable(Request $request, string $guard, string $password, string $code): bool
    {
        $actor = $this->authenticated($guard);
        $this->requirePassword($actor, $password);
        if ($this->method($actor) !== 'authenticator') {
            throw ValidationException::withMessages(['action' => 'No authenticator app is connected to this account.']);
        }
        if (!$this->verifyActorFactor($actor, $code, true)) {
            throw ValidationException::withMessages(['code' => 'Enter a current authenticator code or an unused recovery code.']);
        }

        $before = ['two_factor_enabled_at' => $actor->getAttribute('two_factor_enabled_at'), 'method' => 'authenticator'];
        $actor->setAttribute('two_factor_enabled_at', null);
        $actor->setAttribute('two_factor_secret', null);
        $actor->setAttribute('two_factor_recovery_codes', null);
        $actor->save();
        $request->session()->forget("mfa.setup.$guard");
        $request->session()->regenerate();
        AuditLogger::record('account.mfa_disabled', $actor, $before, ['two_factor_enabled_at' => null]);
        $this->notifyFactorChange($actor, $guard, false);

        return false;
    }

    /** Legacy email-protected accounts can turn protection off while they move to an authenticator app. */
    public function beginLegacyDisable(Request $request, string $guard, string $password): AccountLoginChallenge
    {
        $actor = $this->authenticated($guard);
        $this->requirePassword($actor, $password);
        if ($this->method($actor) !== 'email') {
            throw ValidationException::withMessages(['action' => 'This account does not use email verification.']);
        }
        $challenge = $this->issueEmailChallenge($request, $guard, $actor, 'disable');
        $request->session()->put("mfa.settings.$guard", $challenge->public_id);

        return $challenge;
    }

    public function completeLegacyDisable(Request $request, string $guard, string $code): bool
    {
        $actor = $this->authenticated($guard);
        $publicId = (string) $request->session()->get("mfa.settings.$guard");
        $challenge = $this->consumeEmailChallenge($request, $publicId, $code, 'disable');
        if ($challenge->actor_type !== $guard || (int) $challenge->actor_id !== (int) $actor->getAuthIdentifier()) {
            throw ValidationException::withMessages(['code' => 'That verification request does not belong to this account.']);
        }

        $before = ['two_factor_enabled_at' => $actor->getAttribute('two_factor_enabled_at'), 'method' => 'email'];
        $actor->setAttribute('two_factor_enabled_at', null);
        $actor->setAttribute('two_factor_secret', null);
        $actor->setAttribute('two_factor_recovery_codes', null);
        $actor->save();
        $request->session()->forget("mfa.settings.$guard");
        $request->session()->regenerate();
        AuditLogger::record('account.mfa_disabled', $actor, $before, ['two_factor_enabled_at' => null]);
        $this->notifyFactorChange($actor, $guard, false);

        return false;
    }

    public function cancelSetup(Request $request, string $guard): void
    {
        $this->authenticated($guard);
        $request->session()->forget(["mfa.setup.$guard", "mfa.settings.$guard"]);
    }

    public function completeLogin(Request $request, string $code): string
    {
        $publicId = (string) $request->session()->get('mfa.login.challenge');
        $result = DB::transaction(function () use ($request, $publicId, $code) {
            $challenge = AccountLoginChallenge::query()->where('public_id', $publicId)->lockForUpdate()->first();
            $invalid = !$challenge || $challenge->consumed_at || $challenge->expires_at->isPast()
                || $challenge->attempts >= 5 || !hash_equals((string) $challenge->session_hash, $this->hmac($this->sessionBinding($request)))
                || $challenge->purpose !== 'login';
            if ($invalid) {
                return ['error' => 'This sign-in request has expired. Start again.'];
            }
            $class = $this->guardClass($challenge->actor_type);
            $actor = $class::query()->whereKey($challenge->actor_id)->lockForUpdate()->first();
            if (!$actor || (int) $actor->status !== 1) {
                return ['error' => 'This account is no longer available.'];
            }
            $valid = $this->method($actor) === 'authenticator'
                ? $this->verifyActorFactor($actor, $code, true)
                : $this->verifyEmailCode($challenge, $code);
            if (!$valid) {
                $challenge->increment('attempts');
                return ['error' => $challenge->attempts >= 5 ? 'Too many attempts. Start again.' : 'That code is not correct.'];
            }
            $challenge->forceFill(['consumed_at' => now()])->save();

            return ['challenge' => $challenge, 'actor' => $actor];
        }, 3);
        if (isset($result['error'])) {
            throw ValidationException::withMessages(['code' => $result['error']]);
        }

        /** @var AccountLoginChallenge $challenge */
        $challenge = $result['challenge'];
        $actor = $result['actor'];
        Auth::guard($challenge->actor_type)->login($actor);
        $request->session()->forget('mfa.login.challenge');
        $request->session()->regenerate();
        if ($challenge->actor_type === 'organizer') {
            Auth::guard('scanner')->logout();
            $request->session()->forget('tica_team_member');
            $request->session()->put('secret_login', 0);
        } elseif ($challenge->actor_type === 'customer') {
            $bookingId = (int) $request->session()->pull('claim_booking_id', 0);
            if ($bookingId > 0) {
                app(GuestTicketClaimService::class)->claim($bookingId, $actor);
            }
        }
        AuditLogger::record('account.mfa_login_completed', $actor, [], ['guard' => $challenge->actor_type, 'method' => $this->method($actor)]);

        return $challenge->redirect_path ?: $this->defaultPath($challenge->actor_type);
    }

    private function issueLoginChallenge(Request $request, string $guard, Authenticatable $actor, string $redirect): AccountLoginChallenge
    {
        if ($this->method($actor) === 'email') {
            return $this->issueEmailChallenge($request, $guard, $actor, 'login', $redirect);
        }

        return $this->createChallenge($request, $guard, $actor, 'login', $this->hmac(Str::random(64)), $redirect);
    }

    private function issueEmailChallenge(Request $request, string $guard, Authenticatable $actor, string $purpose, ?string $redirect = null): AccountLoginChallenge
    {
        $email = mb_strtolower(trim((string) $actor->getAttribute('email')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['email' => 'Add a valid email address before using email verification.']);
        }
        $code = (string) random_int(100000, 999999);
        $challenge = $this->createChallenge($request, $guard, $actor, $purpose, $this->hmac('pending'), $redirect, true);
        $challenge->forceFill(['code_hash' => $this->hmac($challenge->public_id . '|' . $code)])->save();

        app(ApplicationMailService::class)->queueHtml(
            'account-security-code',
            $email,
            'Your TICA security code',
            '<p>Use this one-time code to confirm your TICA account:</p><p style="font-size:28px;font-weight:700;letter-spacing:6px">' . e($code) . '</p><p>It expires in 10 minutes. If you did not request it, do not share it with anyone.</p>',
            source: $challenge,
            dedupeKey: 'mfa:' . $challenge->public_id
        );

        return $challenge;
    }

    private function createChallenge(Request $request, string $guard, Authenticatable $actor, string $purpose, string $codeHash, ?string $redirect = null, bool $emailMethod = false): AccountLoginChallenge
    {
        AccountLoginChallenge::query()
            ->where('actor_type', $guard)->where('actor_id', $actor->getAuthIdentifier())
            ->where('purpose', $purpose)->whereNull('consumed_at')->update(['consumed_at' => now()]);
        $email = mb_strtolower(trim((string) $actor->getAttribute('email')));
        $challenge = AccountLoginChallenge::create([
            'public_id' => (string) Str::uuid(),
            'actor_type' => $guard,
            'actor_id' => $actor->getAuthIdentifier(),
            'purpose' => $purpose,
            'email_hash' => $this->hmac($email),
            'code_hash' => $codeHash,
            'session_hash' => $this->hmac($this->sessionBinding($request)),
            'redirect_path' => $redirect,
            'expires_at' => now()->addMinutes(10),
        ]);
        AuditLogger::record('account.mfa_challenge_issued', $actor, [], ['guard' => $guard, 'purpose' => $purpose, 'method' => $emailMethod ? 'email' : 'authenticator']);

        return $challenge;
    }

    private function consumeEmailChallenge(Request $request, string $publicId, string $code, string $purpose): AccountLoginChallenge
    {
        if ($publicId === '' || !preg_match('/^\d{6}$/', $code)) {
            throw ValidationException::withMessages(['code' => 'Enter the 6-digit code from your email.']);
        }
        $result = DB::transaction(function () use ($request, $publicId, $code, $purpose) {
            $challenge = AccountLoginChallenge::query()->where('public_id', $publicId)->lockForUpdate()->first();
            $invalid = !$challenge || $challenge->consumed_at || $challenge->expires_at->isPast()
                || $challenge->attempts >= 5 || !hash_equals((string) $challenge->session_hash, $this->hmac($this->sessionBinding($request)))
                || $challenge->purpose !== $purpose;
            if ($invalid) {
                return ['error' => 'This verification request has expired. Start again.'];
            }
            if (!$this->verifyEmailCode($challenge, $code)) {
                $challenge->increment('attempts');
                return ['error' => $challenge->attempts >= 5 ? 'Too many attempts. Start again.' : 'That code is not correct.'];
            }
            $challenge->forceFill(['consumed_at' => now()])->save();
            return ['challenge' => $challenge];
        }, 3);
        if (isset($result['error'])) {
            throw ValidationException::withMessages(['code' => $result['error']]);
        }

        return $result['challenge'];
    }

    private function verifyEmailCode(AccountLoginChallenge $challenge, string $code): bool
    {
        return preg_match('/^\d{6}$/', $code) === 1
            && hash_equals((string) $challenge->code_hash, $this->hmac($challenge->public_id . '|' . $code));
    }

    private function verifyActorFactor(Authenticatable $actor, string $code, bool $consumeRecovery): bool
    {
        $secret = trim((string) $actor->getAttribute('two_factor_secret'));
        if ($secret !== '' && $this->verifyTotp($secret, $code)) {
            return true;
        }
        $normalized = $this->normalizeRecoveryCode($code);
        if ($normalized === '') {
            return false;
        }
        $hashes = array_values(array_filter((array) $actor->getAttribute('two_factor_recovery_codes'), 'is_string'));
        $wanted = $this->recoveryHash($normalized);
        foreach ($hashes as $index => $hash) {
            if (hash_equals($hash, $wanted)) {
                if ($consumeRecovery) {
                    unset($hashes[$index]);
                    $actor->setAttribute('two_factor_recovery_codes', array_values($hashes));
                    $actor->save();
                }
                return true;
            }
        }

        return false;
    }

    private function verifyTotp(string $secret, string $code): bool
    {
        $code = preg_replace('/\D/', '', $code) ?? '';
        if (!preg_match('/^\d{6}$/', $code)) {
            return false;
        }
        $counter = intdiv(now()->getTimestamp(), 30);
        foreach ([-1, 0, 1] as $offset) {
            if (hash_equals($this->totpAt($secret, $counter + $offset), $code)) {
                return true;
            }
        }

        return false;
    }

    private function totpAt(string $secret, int $counter): string
    {
        $key = $this->base32Decode($secret);
        $binaryCounter = pack('N2', ($counter >> 32) & 0xffffffff, $counter & 0xffffffff);
        $hash = hash_hmac('sha1', $binaryCounter, $key, true);
        $offset = ord($hash[19]) & 0x0f;
        $value = (unpack('N', substr($hash, $offset, 4))[1] ?? 0) & 0x7fffffff;

        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private function provisioningUri(Authenticatable $actor, string $secret): string
    {
        $label = rawurlencode('TICA:' . mb_strtolower((string) $actor->getAttribute('email')));

        return 'otpauth://totp/' . $label . '?secret=' . rawurlencode($secret) . '&issuer=TICA&algorithm=SHA1&digits=6&period=30';
    }

    private function makeRecoveryCodes(): array
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $value = '';
            for ($j = 0; $j < 8; $j++) {
                $value .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $codes[] = substr($value, 0, 4) . '-' . substr($value, 4);
        }

        return $codes;
    }

    private function recoveryHash(string $code): string
    {
        return $this->hmac('recovery|' . $this->normalizeRecoveryCode($code));
    }

    private function normalizeRecoveryCode(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Z0-9]/i', '', trim($code)) ?? '');
    }

    private function base32Encode(string $bytes): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }
        $encoded = '';
        foreach (str_split($bits, 5) as $chunk) {
            $encoded .= $alphabet[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
        }

        return $encoded;
    }

    private function base32Decode(string $value): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split(strtoupper(rtrim($value, '='))) as $character) {
            $position = strpos($alphabet, $character);
            if ($position === false) {
                return '';
            }
            $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }
        $decoded = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $decoded .= chr(bindec($chunk));
            }
        }

        return $decoded;
    }

    private function requirePassword(Authenticatable $actor, string $password): void
    {
        if (!Hash::check($password, (string) $actor->getAuthPassword())) {
            throw ValidationException::withMessages(['current_password' => 'The current password is not correct.']);
        }
    }

    private function notifyFactorChange(Authenticatable $actor, string $guard, bool $enabled): void
    {
        $email = mb_strtolower(trim((string) $actor->getAttribute('email')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        try {
            app(ApplicationMailService::class)->queueHtml(
                'account-security-changed',
                $email,
                'Your TICA account security changed',
                '<p>Authenticator-app two-step verification was ' . ($enabled ? 'turned on' : 'turned off') . ' for your TICA account.</p><p>If this was not you, reset your password and contact TICA support immediately.</p>',
                dedupeKey: 'mfa-change:' . $guard . ':' . $actor->getAuthIdentifier() . ':' . now()->getTimestamp()
            );
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    private function authenticated(string $guard): Authenticatable
    {
        $this->guardClass($guard);
        $actor = Auth::guard($guard)->user();
        abort_unless($actor, 401, 'Sign in to manage account security.');

        return $actor;
    }

    private function guardClass(string $guard): string
    {
        abort_unless(isset(self::ACTORS[$guard]), 404);
        return self::ACTORS[$guard];
    }

    private function hmac(string $value): string
    {
        return hash_hmac('sha256', $value, (string) config('app.key'));
    }

    private function sessionBinding(Request $request): string
    {
        $key = 'mfa.session_binding';
        $binding = (string) $request->session()->get($key, '');
        if ($binding === '') {
            $binding = Str::random(64);
            $request->session()->put($key, $binding);
        }

        return $binding;
    }

    private function safePath(string $path, string $guard): string
    {
        if (!Str::startsWith($path, '/') || Str::startsWith($path, '//')) {
            return $this->defaultPath($guard);
        }
        $blocked = ['admin' => '#^/(organizer|customer|account|dashboard)(/|$)#', 'organizer' => '#^/(admin|customer|account)(/|$)#', 'customer' => '#^/(admin|organizer|dashboard|create|checkin)(/|$)#'];
        return preg_match($blocked[$guard], $path) ? $this->defaultPath($guard) : mb_substr($path, 0, 500);
    }

    private function defaultPath(string $guard): string
    {
        return ['admin' => '/admin/dashboard', 'organizer' => '/dashboard', 'customer' => '/account'][$guard];
    }
}
