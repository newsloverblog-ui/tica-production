<?php

namespace App\Http\Controllers\BackEnd;

use App\Http\Controllers\Controller;
use App\Support\TicaCurrency;
use App\Support\TicaPlatform;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * One page for the choices that shape TICA for every organiser and buyer:
 * the booking fee, checkout, event features, currencies and organiser
 * sign-up.
 */
class TicaPlatformController extends Controller
{
  public function show(): View
  {
    $basic = DB::table('basic_settings')->where('uniqid', 12345)->first();

    return view('backend.tica.platform-settings', [
      'basic' => $basic,
      'platform' => TicaPlatform::all(),
      'features' => TicaPlatform::FEATURES,
      'currencies' => TicaCurrency::CURRENCIES,
      'paystack' => TicaCurrency::PAYSTACK,
      'base' => TicaCurrency::base(),
      'eventsByCurrency' => DB::table('events')->whereNotNull('currency')->groupBy('currency')->selectRaw('currency, count(*) as total')->pluck('total', 'currency')->all(),
    ]);
  }

  public function update(Request $request): RedirectResponse
  {
    $validated = $request->validate([
      'booking_fee_percentage' => ['required', 'numeric', 'min:0', 'max:30'],
      'default_fee_payer' => ['required', Rule::in(['buyer', 'organizer'])],
      'guest_checkout' => ['nullable', 'boolean'],
      'features' => ['nullable', 'array'],
      'features.*' => ['nullable', 'boolean'],
      'currencies' => ['nullable', 'array'],
      'currencies.*' => ['string', Rule::in(array_keys(TicaCurrency::CURRENCIES))],
      'organizer_email_verification' => ['nullable', 'boolean'],
      'organizer_admin_approval' => ['nullable', 'boolean'],
      'admin_approval_notice' => ['nullable', 'string', 'max:1000'],
    ], [
      'booking_fee_percentage.max' => __('Keep the booking fee at 30% or less.'),
    ]);

    DB::table('basic_settings')->where('uniqid', 12345)->update([
      'booking_fee_percentage' => round((float) $validated['booking_fee_percentage'], 2),
      'event_guest_checkout_status' => $request->boolean('guest_checkout') ? 1 : 0,
      'organizer_email_verification' => $request->boolean('organizer_email_verification') ? 1 : 0,
      'organizer_admin_approval' => $request->boolean('organizer_admin_approval') ? 1 : 0,
      'admin_approval_notice' => $validated['admin_approval_notice'] ?? null,
    ]);
    TicaPlatform::save((array) ($validated['features'] ?? []), (array) ($validated['currencies'] ?? []), $validated['default_fee_payer']);

    return redirect()->route('admin.tica.platform')->with('success', __('Platform settings saved. Organisers see the changes on their next page load.'));
  }
}
