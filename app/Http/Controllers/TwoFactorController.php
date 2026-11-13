<?php

namespace App\Http\Controllers;

use App\Services\SensitiveActionConfirmation;
use App\Services\TwoFactorAuthenticator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Organiser authenticator-app 2FA: the sign-in code page and the Settings modal actions. */
class TwoFactorController extends Controller
{
  public function __construct(private TwoFactorAuthenticator $twoFactor)
  {
  }

  /* -------------------------------------------------------------- sign-in */

  public function challenge(Request $request): View|RedirectResponse
  {
    if (!$this->twoFactor->pendingLogin($request)) {
      return redirect('/login')->with('alert', 'Start by signing in again.');
    }

    return view('auth.totp-challenge');
  }

  public function verify(Request $request): RedirectResponse
  {
    $validated = $request->validate(['code' => ['required', 'string', 'max:20']], ['code.required' => 'Enter the code from your app.']);

    return redirect($this->twoFactor->completeLogin($request, $validated['code']))->with('success', 'Signed in.');
  }

  /* -------------------------------------------------------------- settings */

  public function start(Request $request, SensitiveActionConfirmation $confirm): JsonResponse
  {
    $organizer = $this->owner();
    $validated = $request->validate(['password' => ['required', 'string', 'max:200']], ['password.required' => 'Enter your password.']);
    $confirm->confirm($organizer, $validated['password'], null, 'two-factor');

    return response()->json($this->twoFactor->startSetup($request, $organizer));
  }

  public function confirm(Request $request): JsonResponse
  {
    $validated = $request->validate(['code' => ['required', 'string', 'max:20']], ['code.required' => 'Enter the 6-digit code from your app.']);
    $codes = $this->twoFactor->confirmSetup($request, $this->owner(), $validated['code']);

    return response()->json(['message' => 'Two-factor is on.', 'recovery_codes' => $codes]);
  }

  public function disable(Request $request, SensitiveActionConfirmation $confirm): JsonResponse
  {
    $organizer = $this->owner();
    $validated = $request->validate(['password' => ['required', 'string', 'max:200'], 'otp' => ['required', 'string', 'max:20']], ['otp.required' => 'Enter a code from your app or a recovery code.']);
    $confirm->confirm($organizer, $validated['password'], $validated['otp'], 'two-factor');
    $this->twoFactor->disable($organizer);

    return response()->json(['message' => 'Two-factor is off.']);
  }

  public function regenerate(Request $request, SensitiveActionConfirmation $confirm): JsonResponse
  {
    $organizer = $this->owner();
    $validated = $request->validate(['password' => ['required', 'string', 'max:200'], 'otp' => ['required', 'string', 'max:20']], ['otp.required' => 'Enter a code from your app.']);
    $confirm->confirm($organizer, $validated['password'], $validated['otp'], 'two-factor');

    return response()->json(['message' => 'New recovery codes made. The old ones no longer work.', 'recovery_codes' => $this->twoFactor->regenerateRecoveryCodes($organizer)]);
  }

  private function owner(): \App\Models\Organizer
  {
    $organizer = Auth::guard('organizer')->user();
    abort_unless($organizer, 401);
    // Team members act as the owner account; only the owner manages its security.
    abort_unless(\App\Support\TeamPermissions::can('settings.manage'), 403, 'Your team role doesn\'t include this.');

    return $organizer;
  }
}
