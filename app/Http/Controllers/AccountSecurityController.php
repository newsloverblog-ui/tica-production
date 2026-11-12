<?php

namespace App\Http\Controllers;

use App\Services\AccountMfaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccountSecurityController extends Controller
{
    public function challenge(Request $request): View|RedirectResponse
    {
        if (!$request->session()->has('mfa.login.challenge')) {
            return redirect('/login')->with('alert', 'Start by signing in again.');
        }
        return view('auth.mfa-challenge');
    }

    public function verifyLogin(Request $request, AccountMfaService $mfa): RedirectResponse
    {
        $validated = $request->validate(['code' => ['required', 'digits:6']]);
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
        return view('auth.account-security', ['guard' => $guard, 'enabled' => $mfa->enabled($actor)]);
    }

    public function requestChange(Request $request, string $guard, AccountMfaService $mfa): JsonResponse|RedirectResponse
    {
        $this->actor($guard);
        $validated = $request->validate([
            'action' => ['required', Rule::in(['enable', 'disable'])],
            'current_password' => ['required', 'string', 'max:255'],
        ]);
        $mfa->beginSettings($request, $guard, $validated['action'], $validated['current_password']);
        $message = 'A 6-digit confirmation code was sent to your email.';
        return $request->expectsJson() ? response()->json(['message' => $message, 'code_required' => true]) : back()->with('success', $message);
    }

    public function verifyChange(Request $request, string $guard, AccountMfaService $mfa): JsonResponse|RedirectResponse
    {
        $this->actor($guard);
        $validated = $request->validate(['code' => ['required', 'digits:6']]);
        $enabled = $mfa->completeSettings($request, $guard, $validated['code']);
        $message = 'Two-step verification is now ' . ($enabled ? 'on.' : 'off.');
        return $request->expectsJson() ? response()->json(['message' => $message, 'enabled' => $enabled]) : back()->with('success', $message);
    }

    private function actor(string $guard)
    {
        abort_unless(in_array($guard, ['admin', 'organizer', 'customer'], true), 404);
        $actor = Auth::guard($guard)->user();
        abort_unless($actor, 401, 'Sign in to manage account security.');
        return $actor;
    }
}
