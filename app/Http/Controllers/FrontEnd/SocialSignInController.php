<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use App\Models\Organizer;
use App\Models\OrganizerInfo;
use App\Services\AccountMfaService;
use App\Services\AccountTypeGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

/**
 * "Continue with Google" on the TICA log-in and sign-up pages, for attendees
 * and organisers. Keys and the on/off switch live in Admin > Integrations.
 */
class SocialSignInController extends Controller
{
  public function redirect(Request $request): RedirectResponse
  {
    if (!self::googleReady()) {
      return redirect('/login')->with('alert', __('Google sign-in is not available right now. Log in with your email instead.'));
    }
    $as = (string) $request->query('as');
    $request->session()->put('social_as', in_array($as, ['org', 'upgrade'], true) ? $as : 'att');

    return Socialite::driver('google')->scopes(['openid', 'email', 'profile'])->redirect();
  }

  /** Google sends everyone back here; the role chosen before decides the account type. */
  public function callback(Request $request)
  {
    $as = $request->session()->pull('social_as', 'att');
    if ($as === 'upgrade') {
      return $this->confirmUpgrade($request);
    }
    if ($as !== 'org') {
      return app(CustomerController::class)->handleGoogleCallback();
    }

    try {
      $google = Socialite::driver('google')->user();
    } catch (Throwable $e) {
      Log::warning('organizer.google_sign_in_failed', ['exception' => $e::class]);
      return redirect('/login')->with('alert', __('Google sign-in could not be completed. Please try again.'));
    }

    $email = mb_strtolower(trim((string) $google->getEmail()));
    $verified = (bool) data_get($google->user, 'email_verified', data_get($google->user, 'verified_email', false));
    if ($email === '' || !$verified) {
      return redirect('/login')->with('alert', __('Your Google account has no verified email address. Log in with your email instead.'));
    }

    $organizer = Organizer::query()->whereRaw('LOWER(email) = ?', [$email])->first();
    if (!$organizer) {
      // One email, one account type: never create an organiser for a customer's email.
      $conflict = app(AccountTypeGuard::class)->conflict(AccountTypeGuard::ORGANIZER, $email);
      if ($conflict) {
        rescue(fn () => \App\Services\AuditLogger::record('account_type.google_blocked', null, [], [], ['as' => 'organizer', 'existing' => $conflict['type']]), null, false);
        return redirect('/login?role=att')->with('alert', $conflict['message'])->with('switch_role', 'att');
      }
      return $this->createOrganizer($request, $email, (string) ($google->getName() ?: Str::before($email, '@')));
    }
    if (blank($organizer->provider_id) && \Illuminate\Support\Facades\Schema::hasColumn('organizers', 'provider_id')) {
      $organizer->forceFill(['provider' => 'google', 'provider_id' => (string) $google->getId()])->saveQuietly();
    }
    if ((int) $organizer->status !== 1) {
      return redirect('/login')->with('alert', __('Your organiser account is not active yet. We\'ll email you once it\'s approved.'));
    }
    if ($organizer->email_verified_at === null) {
      // Google has just proven the address belongs to them.
      $organizer->forceFill(['email_verified_at' => now()])->save();
    }

    return $this->signIn($request, $organizer);
  }

  /**
   * A Google-only customer confirms their organiser upgrade by signing in
   * with Google again. The same Google account must come back.
   */
  private function confirmUpgrade(Request $request): RedirectResponse
  {
    $customer = Auth::guard('customer')->user();
    if (!$customer) {
      return redirect('/login')->with('alert', __('Log in to your account first.'));
    }
    try {
      $google = Socialite::driver('google')->user();
    } catch (Throwable $e) {
      Log::warning('customer.upgrade_google_reauth_failed', ['exception' => $e::class]);
      return redirect()->route('customer.organizer_upgrade.show')->with('alert', __('Google could not confirm it\'s you. Please try again.'));
    }
    $sameAccount = AccountTypeGuard::normalize($google->getEmail()) === AccountTypeGuard::normalize($customer->email)
      && (blank($customer->provider_id) || hash_equals((string) $customer->provider_id, (string) $google->getId()));
    if (!$sameAccount) {
      return redirect()->route('customer.organizer_upgrade.show')->with('alert', __('That Google account does not match this TICA account.'));
    }
    $request->session()->put('organizer_upgrade_reauth', ['customer_id' => (int) $customer->id, 'at' => now()->getTimestamp()]);

    return redirect()->route('customer.organizer_upgrade.show')->with('success', __('Confirmed with Google. Finish your request below.'));
  }

  private function signIn(Request $request, Organizer $organizer): RedirectResponse
  {
    $mfa = app(AccountMfaService::class);
    if ($mfa->enabled($organizer)) {
      $mfa->beginLogin($request, 'organizer', $organizer, '/dashboard');
      return redirect()->route('mfa.login.challenge');
    }
    Auth::guard('scanner')->logout();
    Auth::guard('organizer')->login($organizer);
    $request->session()->regenerate();
    $request->session()->forget('tica_team_member');
    $request->session()->put('secret_login', 0);
    \App\Services\AuditLogger::record('organizer.google_sign_in', $organizer, [], []);

    return redirect('/dashboard');
  }

  private function createOrganizer(Request $request, string $email, string $name): RedirectResponse
  {
    $needsApproval = (int) DB::table('basic_settings')->where('uniqid', 12345)->value('organizer_admin_approval') === 1;
    $organizer = DB::transaction(function () use ($email, $name, $needsApproval) {
      $organizer = Organizer::create([
        'username' => $this->uniqueUsername(Str::before($email, '@')),
        'email' => $email,
        'password' => Hash::make(Str::random(48)),
        'status' => $needsApproval ? 0 : 1,
      ]);
      $organizer->forceFill(['email_verified_at' => now()])->save();
      OrganizerInfo::create([
        'organizer_id' => $organizer->id,
        'language_id' => (int) DB::table('languages')->where('is_default', 1)->value('id') ?: null,
        'name' => mb_substr($name, 0, 100),
      ]);

      return $organizer;
    });

    if ($needsApproval) {
      return redirect('/login')->with('success', __('Your organiser account is ready. We\'ll email you once it\'s approved.'));
    }
    $this->signIn($request, $organizer);

    return redirect('/create');
  }

  private function uniqueUsername(string $base): string
  {
    $base = Str::limit(Str::slug($base, '_') ?: 'organiser', 30, '');
    $name = $base;
    for ($i = 2; Organizer::query()->where('username', $name)->exists(); $i++) {
      $name = $base . $i;
    }

    return $name;
  }

  /** Google sign-in is on and has both keys (Admin > Integrations > Sign in with Google). */
  public static function googleReady(): bool
  {
    try {
      $basic = \App\Models\BasicSettings\Basic::query()->select('google_login_status')->first();
    } catch (Throwable) {
      return false;
    }

    return $basic
      && (int) $basic->google_login_status === 1
      && filled(config('services.google.client_id'))
      && filled(config('services.google.client_secret'));
  }
}
