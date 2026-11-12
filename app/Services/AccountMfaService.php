<?php

namespace App\Services;

use App\Models\AccountLoginChallenge;
use App\Models\Admin;
use App\Models\Customer;
use App\Models\Organizer;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

    public function enabled(Authenticatable $actor): bool
    {
        return $actor->getAttribute('two_factor_enabled_at') !== null;
    }

    public function beginLogin(Request $request, string $guard, Authenticatable $actor, string $redirect): AccountLoginChallenge
    {
        $this->guardClass($guard);
        Auth::guard($guard)->logout();
        $request->session()->regenerate();
        $challenge = $this->issue($request, $guard, $actor, 'login', $this->safePath($redirect, $guard));
        $request->session()->put('mfa.login.challenge', $challenge->public_id);

        return $challenge;
    }

    public function beginSettings(Request $request, string $guard, string $action, string $password): AccountLoginChallenge
    {
        $actor = $this->authenticated($guard);
        if (!in_array($action, ['enable', 'disable'], true)) {
            throw ValidationException::withMessages(['action' => 'Choose a valid security action.']);
        }
        if (!Hash::check($password, (string) $actor->getAuthPassword())) {
            throw ValidationException::withMessages(['current_password' => 'The current password is not correct.']);
        }
        if ($action === 'enable' && $this->enabled($actor)) {
            throw ValidationException::withMessages(['action' => 'Two-step verification is already on.']);
        }
        if ($action === 'disable' && !$this->enabled($actor)) {
            throw ValidationException::withMessages(['action' => 'Two-step verification is already off.']);
        }
        $challenge = $this->issue($request, $guard, $actor, $action);
        $request->session()->put("mfa.settings.$guard", $challenge->public_id);

        return $challenge;
    }

    public function completeLogin(Request $request, string $code): string
    {
        $publicId = (string) $request->session()->get('mfa.login.challenge');
        $challenge = $this->consume($request, $publicId, $code, 'login');
        $class = $this->guardClass($challenge->actor_type);
        $actor = $class::query()->find($challenge->actor_id);
        if (!$actor || (int) $actor->status !== 1) {
            throw ValidationException::withMessages(['code' => 'This account is no longer available.']);
        }

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
        AuditLogger::record('account.mfa_login_completed', $actor, [], ['guard' => $challenge->actor_type]);

        return $challenge->redirect_path ?: $this->defaultPath($challenge->actor_type);
    }

    public function completeSettings(Request $request, string $guard, string $code): bool
    {
        $actor = $this->authenticated($guard);
        $publicId = (string) $request->session()->get("mfa.settings.$guard");
        $challenge = $this->consume($request, $publicId, $code);
        if ($challenge->actor_type !== $guard || (int) $challenge->actor_id !== (int) $actor->getAuthIdentifier()) {
            throw ValidationException::withMessages(['code' => 'That verification request does not belong to this account.']);
        }

        $enabled = $challenge->purpose === 'enable';
        $before = ['two_factor_enabled_at' => $actor->getAttribute('two_factor_enabled_at')];
        $actor->setAttribute('two_factor_enabled_at', $enabled ? now() : null);
        $actor->save();
        $request->session()->forget("mfa.settings.$guard");
        $request->session()->regenerate();
        AuditLogger::record($enabled ? 'account.mfa_enabled' : 'account.mfa_disabled', $actor, $before, ['two_factor_enabled_at' => $actor->getAttribute('two_factor_enabled_at')]);

        return $enabled;
    }

    private function issue(Request $request, string $guard, Authenticatable $actor, string $purpose, ?string $redirect = null): AccountLoginChallenge
    {
        $email = mb_strtolower(trim((string) $actor->getAttribute('email')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['email' => 'Add a valid email address before using two-step verification.']);
        }
        AccountLoginChallenge::query()
            ->where('actor_type', $guard)->where('actor_id', $actor->getAuthIdentifier())
            ->where('purpose', $purpose)->whereNull('consumed_at')->update(['consumed_at' => now()]);

        $publicId = (string) Str::uuid();
        $code = (string) random_int(100000, 999999);
        $challenge = AccountLoginChallenge::create([
            'public_id' => $publicId,
            'actor_type' => $guard,
            'actor_id' => $actor->getAuthIdentifier(),
            'purpose' => $purpose,
            'email_hash' => $this->hmac($email),
            'code_hash' => $this->hmac($publicId . '|' . $code),
            'session_hash' => $this->hmac($this->sessionBinding($request)),
            'redirect_path' => $redirect,
            'expires_at' => now()->addMinutes(10),
        ]);

        app(ApplicationMailService::class)->queueHtml(
            'account-security-code',
            $email,
            'Your TICA security code',
            '<p>Use this one-time code to confirm your TICA account:</p><p style="font-size:28px;font-weight:700;letter-spacing:6px">' . e($code) . '</p><p>It expires in 10 minutes. If you did not request it, do not share it with anyone.</p>',
            source: $challenge,
            dedupeKey: 'mfa:' . $publicId
        );
        AuditLogger::record('account.mfa_challenge_issued', $actor, [], ['guard' => $guard, 'purpose' => $purpose]);

        return $challenge;
    }

    private function consume(Request $request, string $publicId, string $code, ?string $purpose = null): AccountLoginChallenge
    {
        if ($publicId === '' || !preg_match('/^\d{6}$/', $code)) {
            throw ValidationException::withMessages(['code' => 'Enter the 6-digit code from your email.']);
        }

        $result = DB::transaction(function () use ($request, $publicId, $code, $purpose) {
            $challenge = AccountLoginChallenge::query()->where('public_id', $publicId)->lockForUpdate()->first();
            $invalid = !$challenge || $challenge->consumed_at || $challenge->expires_at->isPast()
                || $challenge->attempts >= 5 || !hash_equals((string) $challenge->session_hash, $this->hmac($this->sessionBinding($request)))
                || ($purpose !== null && $challenge->purpose !== $purpose);
            if ($invalid) {
                return ['error' => 'This verification request has expired. Start again.'];
            }
            if (!hash_equals((string) $challenge->code_hash, $this->hmac($challenge->public_id . '|' . $code))) {
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
