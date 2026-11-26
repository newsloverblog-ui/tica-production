<?php

namespace App\Http\Controllers\BackEnd;

use App\Http\Controllers\Controller;
use App\Models\BasicSettings\Basic;
use App\Services\AuditLogger;
use App\Support\TicaCurrency;
use App\Support\TicaPlatform;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * One page for the choices that shape TICA for every organiser and buyer:
 * the booking fee, checkout, event features, currencies and organiser
 * sign-up.
 */
class TicaPlatformController extends Controller
{
  private const MAX_FIXED_FEE = '99999999.99';

  public function show(): View
  {
    $basic = Basic::query()->where('uniqid', 12345)->first();

    return view('backend.tica.platform-settings', [
      'basic' => $basic,
      'fixedFee' => Basic::fixedBookingFee($basic),
      'maxFixedFee' => self::MAX_FIXED_FEE,
      'fixedFeeEditable' => $basic && Schema::hasColumn('basic_settings', 'booking_fee_fixed'),
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
      'booking_fee_fixed' => ['sometimes', 'required', 'numeric', 'decimal:0,2', 'min:0', 'max:' . self::MAX_FIXED_FEE],
      'default_fee_payer' => ['required', Rule::in(['buyer', 'organizer'])],
      'guest_checkout' => ['nullable', 'boolean'],
      'features' => ['nullable', 'array'],
      'features.*' => ['nullable', 'boolean'],
      'currencies' => ['nullable', 'array'],
      'currencies.*' => ['string', Rule::in(array_keys(TicaCurrency::CURRENCIES))],
      'organizer_email_verification' => ['nullable', 'boolean'],
      'organizer_admin_approval' => ['nullable', 'boolean'],
      'customer_organizer_upgrade' => ['nullable', 'boolean'],
      'customer_organizer_upgrade_approval' => ['nullable', 'boolean'],
      'admin_approval_notice' => ['nullable', 'string', 'max:1000'],
    ], [
      'booking_fee_percentage.max' => __('Keep the booking fee at 30% or less.'),
    ]);

    $update = [
      'booking_fee_percentage' => round((float) $validated['booking_fee_percentage'], 2),
      'event_guest_checkout_status' => $request->boolean('guest_checkout') ? 1 : 0,
      'organizer_email_verification' => $request->boolean('organizer_email_verification') ? 1 : 0,
      'organizer_admin_approval' => $request->boolean('organizer_admin_approval') ? 1 : 0,
      'admin_approval_notice' => $validated['admin_approval_notice'] ?? null,
    ];
    // Present once the account-type update has run (2026_11_19_180000).
    if (Schema::hasColumn('basic_settings', 'customer_organizer_upgrade')) {
      $before = DB::table('basic_settings')->where('uniqid', 12345)->first(['customer_organizer_upgrade', 'customer_organizer_upgrade_approval']);
      $update['customer_organizer_upgrade'] = $request->boolean('customer_organizer_upgrade') ? 1 : 0;
      $update['customer_organizer_upgrade_approval'] = $request->boolean('customer_organizer_upgrade_approval') ? 1 : 0;
      if ($before && ((int) $before->customer_organizer_upgrade !== $update['customer_organizer_upgrade'] || (int) $before->customer_organizer_upgrade_approval !== $update['customer_organizer_upgrade_approval'])) {
        rescue(fn () => \App\Services\AuditLogger::record('settings.organizer_upgrades', null, (array) $before, [
          'customer_organizer_upgrade' => $update['customer_organizer_upgrade'],
          'customer_organizer_upgrade_approval' => $update['customer_organizer_upgrade_approval'],
        ]), null, false);
      }
    }
    if (isset($validated['booking_fee_fixed'])) {
      if (!Schema::hasColumn('basic_settings', 'booking_fee_fixed')) {
        return back()->withInput()->withErrors(['booking_fee_fixed' => __('Apply the booking fee database update before saving the fixed fee.')]);
      }
      $update['booking_fee_fixed'] = round((float) $validated['booking_fee_fixed'], 2);
    }

    DB::transaction(function () use ($update, $validated): void {
      $settings = Basic::query()->where('uniqid', 12345)->lockForUpdate()->firstOrFail();
      DB::table('basic_settings')->where('id', $settings->id)->update($update);
      TicaPlatform::save((array) ($validated['features'] ?? []), (array) ($validated['currencies'] ?? []), $validated['default_fee_payer']);

      $previousFee = Basic::fixedBookingFee($settings);
      if (isset($update['booking_fee_fixed']) && $previousFee !== $update['booking_fee_fixed']) {
        AuditLogger::record('settings.booking_fixed_fee_changed', $settings,
          ['booking_fee_fixed' => $previousFee], ['booking_fee_fixed' => $update['booking_fee_fixed']]);
      }
    }, 3);

    return redirect()->route('admin.tica.platform')->with('success', __('Platform settings saved. Organisers see the changes on their next page load.'));
  }
}
