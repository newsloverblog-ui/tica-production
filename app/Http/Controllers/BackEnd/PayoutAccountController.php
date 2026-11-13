<?php

namespace App\Http\Controllers\BackEnd;

use App\Exceptions\PayoutProviderUnavailable;
use App\Http\Controllers\Controller;
use App\Models\Organizer;
use App\Models\OrganizerPayoutAccount;
use App\Services\AuditLogger;
use App\Services\OrganizerPayoutAccountService;
use App\Services\Payouts\BankDirectory;
use App\Services\Payouts\PayoutProviders;
use App\Support\TicaPlatform;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use RuntimeException;
use Throwable;

/** Admin: organiser payout accounts, the review queue, and which provider sends payouts. */
class PayoutAccountController extends Controller
{
  public const FILTERS = [
    'needs_review' => 'Needs review',
    'verified' => 'Verified',
    'rejected' => 'Rejected',
    'locked' => 'Locked',
    'all' => 'All',
  ];

  public function index(Request $request)
  {
    $filter = array_key_exists((string) $request->query('status'), self::FILTERS) ? (string) $request->query('status') : 'needs_review';
    $search = trim((string) $request->query('q'));

    $query = OrganizerPayoutAccount::query()->latest('id');
    if ($filter !== 'all') {
      $query->where('status', $filter);
    }
    if ($search !== '') {
      $ids = Organizer::query()->where('email', 'like', '%' . $search . '%')->orWhere('username', 'like', '%' . $search . '%')
        ->orWhere('verified_legal_name', 'like', '%' . $search . '%')->limit(200)->pluck('id');
      $query->where(function ($inner) use ($ids, $search) {
        $inner->whereIn('organizer_id', $ids)->orWhere('resolved_account_name', 'like', '%' . $search . '%')->orWhere('account_last4', $search);
      });
    }

    $accounts = $query->paginate(25)->withQueryString();
    $organizers = Organizer::query()->whereIn('id', $accounts->pluck('organizer_id'))->get()->keyBy('id');
    $counts = OrganizerPayoutAccount::query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

    return view('backend.payout-accounts.index', [
      'accounts' => $accounts,
      'organizers' => $organizers,
      'filter' => $filter,
      'filters' => self::FILTERS,
      'counts' => $counts,
      'search' => $search,
      'provider' => TicaPlatform::payoutProvider(),
      'transfersEnabled' => (bool) config('payouts.paystack_transfers_enabled'),
    ]);
  }

  public function review(Request $request, string $account, OrganizerPayoutAccountService $service)
  {
    $validated = $request->validate([
      'decision' => ['required', Rule::in(['approve', 'reject'])],
      'reason' => ['nullable', 'string', 'max:500', 'required_if:decision,reject'],
    ], ['reason.required_if' => 'Give a reason so the organiser knows what to fix.']);
    try {
      $service->review($account, $validated['decision'] === 'approve', (int) Auth::guard('admin')->id(), $validated['reason'] ?? null);
    } catch (RuntimeException $exception) {
      return back()->with('warning', $exception->getMessage());
    }

    return back()->with('success', $validated['decision'] === 'approve'
      ? 'Approved. Payouts to this account start after its 24-hour hold.'
      : 'Rejected. The organiser has been emailed your reason.');
  }

  public function reverify(string $account, OrganizerPayoutAccountService $service)
  {
    try {
      $result = $service->reverify($account);
    } catch (PayoutProviderUnavailable $exception) {
      return back()->with('warning', $exception->getMessage());
    } catch (RuntimeException $exception) {
      return back()->with('warning', 'The bank no longer recognises this account: ' . $exception->getMessage());
    }

    $message = 'The bank says this account belongs to ' . $result['name'] . '. '
      . ($result['matches'] ? 'That matches the verified name.' : 'That does not match the verified name.')
      . ($result['changed'] ? ' The name has changed since it was saved.' : '');

    return back()->with($result['matches'] && !$result['changed'] ? 'success' : 'warning', $message);
  }

  public function lock(string $account, OrganizerPayoutAccountService $service)
  {
    $service->lock($account);

    return back()->with('success', 'Account locked. No payouts will go to it.');
  }

  public function provider(Request $request, BankDirectory $banks)
  {
    $validated = $request->validate(['provider' => ['required', Rule::in(['paystack', 'monnify'])]]);
    $before = TicaPlatform::payoutProvider();
    TicaPlatform::savePayoutProvider($validated['provider']);
    $banks->forget($validated['provider']);
    AuditLogger::record('platform.payout_provider_changed', null, ['provider' => $before], ['provider' => $validated['provider']]);

    return back()->with('success', 'New payout accounts will now be checked with ' . ucfirst($validated['provider'])
      . '. Existing accounts keep paying out through the provider they were verified with.');
  }

  public function test(Request $request, PayoutProviders $providers)
  {
    $validated = $request->validate(['provider' => ['required', Rule::in(['paystack', 'monnify'])]]);
    try {
      $providers->for($validated['provider'])->testConnection();
    } catch (Throwable $exception) {
      return back()->with('warning', ucfirst($validated['provider']) . ' connection failed: ' . $exception->getMessage());
    }

    return back()->with('success', ucfirst($validated['provider']) . ' connected. Keys work and the bank list loads.');
  }
}
