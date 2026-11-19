<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use App\Services\OrganizerUpgradeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

/**
 * "Become an Organizer" for signed-in customers. Exists only while
 * Admin > Platform settings allows customers to become organisers.
 */
class OrganizerUpgradeController extends Controller
{
  private const REAUTH_SECONDS = 600;

  public function __construct(private readonly OrganizerUpgradeService $upgrades)
  {
  }

  public function show(Request $request): View|RedirectResponse
  {
    abort_unless(OrganizerUpgradeService::available(), 404);
    $customer = Auth::guard('customer')->user();
    $pending = $this->upgrades->pending($customer);

    return view('auth.become-organizer', [
      'customer' => $customer,
      'pending' => $pending,
      'needsApproval' => \App\Services\AccountTypeGuard::upgradeNeedsApproval(),
      'googleOnly' => filled($customer->provider),
      'googleConfirmed' => $this->reauthenticated($request, (int) $customer->id),
      'googleReady' => SocialSignInController::googleReady(),
    ]);
  }

  public function store(Request $request): RedirectResponse
  {
    abort_unless(OrganizerUpgradeService::available(), 404);
    $customer = Auth::guard('customer')->user();
    $request->merge(['phone' => trim((string) $request->input('phone'))]);
    $validated = $request->validate([
      'organisation' => ['required', 'string', 'min:2', 'max:100'],
      'contact_name' => ['required', 'string', 'min:2', 'max:200'],
      'phone' => ['required', 'string', 'max:20', 'regex:/^[0-9+\s()-]{10,20}$/'],
      'current_password' => ['nullable', 'string', 'max:255'],
    ], [
      'organisation.required' => __('Enter your organisation or brand name.'),
      'contact_name.required' => __('Enter your full name.'),
      'phone.regex' => __('Enter a phone number with at least 10 digits.'),
    ]);

    if (!$this->reauthenticated($request, (int) $customer->id)) {
      $password = (string) ($validated['current_password'] ?? '');
      if ($password === '' || !Hash::check($password, (string) $customer->getAuthPassword())) {
        throw ValidationException::withMessages([
          'current_password' => filled($customer->provider)
            ? __('Enter your password, or confirm with Google instead.')
            : __('Enter your current password to confirm it\'s you.'),
        ]);
      }
    }

    try {
      $result = $this->upgrades->submit($customer, [
        'organisation' => trim($validated['organisation']),
        'contact_name' => trim($validated['contact_name']),
        'phone' => $this->phone($validated['phone']),
      ]);
    } catch (RuntimeException $e) {
      return back()->withInput()->with('alert', $e->getMessage());
    }
    $request->session()->forget('organizer_upgrade_reauth');

    if ($result['status'] === 'upgraded' && $result['organizer']) {
      Auth::guard('customer')->logout();
      Auth::guard('organizer')->login($result['organizer']);
      $request->session()->regenerate();
      $request->session()->put('secret_login', 0);

      return redirect('/dashboard')->with('success', __('Your account is now an organizer account. Your past tickets are under My tickets.'));
    }

    return redirect('/account')->with('success', __('Your request is under review. We\'ll email you once it\'s decided.'));
  }

  private function reauthenticated(Request $request, int $customerId): bool
  {
    $proof = $request->session()->get('organizer_upgrade_reauth');

    return is_array($proof)
      && (int) ($proof['customer_id'] ?? 0) === $customerId
      && now()->getTimestamp() - (int) ($proof['at'] ?? 0) <= self::REAUTH_SECONDS;
  }

  /** Same format as organiser sign-up: Nigerian numbers stored as +234… */
  private function phone(string $phone): string
  {
    $digits = preg_replace('/\D+/', '', $phone);
    if (str_starts_with($digits, '234')) {
      return '+' . $digits;
    }

    return '+234' . ltrim($digits, '0');
  }
}
