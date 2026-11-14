<?php

namespace App\Http\Controllers;

use App\Services\AccountMfaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class AccountSecurityController extends Controller
{
    public function challenge(Request $request, AccountMfaService $mfa): View|RedirectResponse
    {
        if (!$request->session()->has('mfa.login.challenge')) {
            return redirect('/login')->with('alert', 'Start by signing in again.');
        }
        return view('auth.mfa-challenge', ['method' => $mfa->pendingLoginMethod($request)]);
    }

    public function verifyLogin(Request $request, AccountMfaService $mfa): RedirectResponse
    {
        $validated = $request->validate(['code' => ['required', 'string', 'max:32']]);
        return redirect($mfa->completeLogin($request, $validated['code']))->with('success', 'Signed in securely.');
    }

    public function cancel(Request $request): RedirectResponse
    {
        $request->session()->forget('mfa.login.challenge');
        $request->session()->regenerate();
        return redirect('/login')->with('success', 'Sign-in cancelled.');
    }

    public function show(Request $request, string $guard, AccountMfaService $mfa): View
    {
        $actor = $this->actor($guard);
        $setup = $mfa->pendingSetup($request, $guard);
        return view('auth.account-security', [
            'guard' => $guard,
            'enabled' => $mfa->enabled($actor),
            'method' => $mfa->method($actor),
            'setup' => $setup,
            'qrSvg' => $setup ? QrCode::format('svg')->size(196)->margin(1)->generate($setup['uri']) : null,
            'maskedEmail' => $this->maskedEmail((string) $actor->getAttribute('email')),
            'legacyPending' => $request->session()->has("mfa.settings.$guard"),
            'recoveryCodes' => (array) session('mfa_recovery_codes', []),
        ]);
    }

    public function requestChange(Request $request, string $guard, AccountMfaService $mfa): JsonResponse|RedirectResponse
    {
        $this->actor($guard);
        $validated = $request->validate([
            'action' => ['required', Rule::in(['enable', 'disable'])],
            'current_password' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:32'],
        ]);
        $actor = $this->actor($guard);
        if ($validated['action'] === 'enable') {
            $mfa->beginSetup($request, $guard, $validated['current_password']);
            $message = 'Scan the QR code, then enter the code from your authenticator app.';
            return $request->expectsJson()
                ? response()->json(['message' => $message, 'setup_required' => true])
                : back()->with('success', $message);
        }
        if ($mfa->method($actor) === 'authenticator') {
            $request->validate(['code' => ['required', 'string', 'max:32']]);
            $mfa->disable($request, $guard, $validated['current_password'], (string) $validated['code']);
            $message = 'Two-step verification is now off.';
            return $request->expectsJson()
                ? response()->json(['message' => $message, 'enabled' => false])
                : back()->with('success', $message);
        }

        $mfa->beginLegacyDisable($request, $guard, $validated['current_password']);
        $message = 'A confirmation code was sent to ' . $this->maskedEmail((string) $actor->getAttribute('email')) . '.';
        return $request->expectsJson()
            ? response()->json(['message' => $message, 'code_required' => true])
            : back()->with('success', $message);
    }

    public function verifyChange(Request $request, string $guard, AccountMfaService $mfa): JsonResponse|RedirectResponse
    {
        $this->actor($guard);
        $validated = $request->validate(['code' => ['required', 'string', 'max:32']]);
        if ($request->session()->has("mfa.setup.$guard")) {
            $result = $mfa->completeSetup($request, $guard, $validated['code']);
            $message = 'Two-step verification is now on. Save your recovery codes.';
            return $request->expectsJson()
                ? response()->json(['message' => $message, 'enabled' => true, 'recovery_codes' => $result['recovery_codes']])
                : back()->with('success', $message)->with('mfa_recovery_codes', $result['recovery_codes']);
        }

        $mfa->completeLegacyDisable($request, $guard, $validated['code']);
        $message = 'Two-step verification is now off.';
        return $request->expectsJson()
            ? response()->json(['message' => $message, 'enabled' => false])
            : back()->with('success', $message);
    }

    public function cancelChange(Request $request, string $guard, AccountMfaService $mfa): RedirectResponse
    {
        $this->actor($guard);
        $mfa->cancelSetup($request, $guard);

        return back()->with('success', 'Security setup cancelled.');
    }

    private function actor(string $guard)
    {
        abort_unless(in_array($guard, ['admin', 'organizer', 'customer'], true), 404);
        $actor = Auth::guard($guard)->user();
        abort_unless($actor, 401, 'Sign in to manage account security.');
        return $actor;
    }

    private function maskedEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        if ($domain === '') {
            return 'your email';
        }
        $visible = mb_substr($local, 0, min(2, mb_strlen($local)));

        return $visible . str_repeat('•', max(3, mb_strlen($local) - mb_strlen($visible))) . '@' . $domain;
    }
}
