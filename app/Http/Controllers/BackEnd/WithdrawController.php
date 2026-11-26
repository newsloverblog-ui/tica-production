<?php

namespace App\Http\Controllers\BackEnd;

use App\Http\Controllers\Controller;
use App\Models\BasicSettings\Basic;
use App\Models\BasicSettings\MailTemplate;
use App\Models\PaystackDispute;
use App\Models\Withdraw;
use App\Models\PayoutDispute;
use App\Services\PayoutDisputeService;
use App\Services\WithdrawalDecisionService;
use App\Services\PayoutTransferService;
use App\Services\ApplicationMailService;
use App\Services\DataExportService;
use App\Services\WithdrawalExportQueryService;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class WithdrawController extends Controller
{
  //index
  public function index(Request $request)
  {
    $filters = $this->exportFilters($request);
    $query = app(WithdrawalExportQueryService::class)->query($filters);
    $collection = $this->withReviewDetails($query)
      ->when(!isset($filters['status']), fn (Builder $query) =>
        $query->orderByRaw('CASE WHEN status IN (?, ?) THEN 0 ELSE 1 END', [Withdraw::STATUS_PENDING, Withdraw::STATUS_HELD]))
      ->orderByDesc('id')->paginate(10)->withQueryString();
    $payoutDisputes = PayoutDispute::with(['withdrawal', 'organizer'])
      ->whereIn('status', [PayoutDispute::STATUS_OPEN, PayoutDispute::STATUS_UNDER_REVIEW])
      ->orderBy('opened_at')
      ->limit(100)
      ->get();
    $currencyInfo = $this->getCurrencyInfo();
    [$payoutReviews] = $this->releaseReviews($collection->getCollection());

    return view('backend.withdraw.history.index', compact('collection', 'currencyInfo', 'payoutDisputes', 'payoutReviews'));
  }

  public function review(int $id)
  {
    $item = $this->withReviewDetails(Withdraw::query())->findOrFail($id);
    [$reviews, $transfersEnabled] = $this->releaseReviews([$item], PaystackDispute::holdsOrganizer((int) $item->organizer_id));

    return view('backend.withdraw.history.review', [
      'item' => $item,
      'review' => $reviews[$id] ?? null,
      'transfersEnabled' => $transfersEnabled,
      'currencyInfo' => $this->getCurrencyInfo(),
    ]);
  }

  private function withReviewDetails(Builder $query): Builder
  {
    return $query->with([
      'latestPayoutTransfer',
      'method:id,name',
      'organizer:id,username,kyc_status',
      'payoutAccount' => fn ($accountQuery) => $accountQuery->select([
        'id', 'public_id', 'organizer_id', 'bank_name', 'account_last4', 'currency', 'provider',
        'status', 'is_default', 'resolved_account_name', 'bank_code', 'account_number_hash', 'verified_at', 'hold_until',
      ]),
    ]);
  }

  private function releaseReviews(iterable $withdrawals, bool $disputeHold = false): array
  {
    $payoutApprovalAvailable = Schema::hasColumn('withdraws', 'release_approval');
    $payoutAdmin = Auth::guard('admin')->user();
    $canManagePayout = (int) ($payoutAdmin?->status) === 1 && $payoutAdmin->hasPermission('Withdraw Method');
    $canApprovePayout = $canManagePayout && $payoutAdmin->hasPermission('Transaction');
    $transfersEnabled = (bool) config('payouts.paystack_transfers_enabled');
    $transfers = app(PayoutTransferService::class);
    $payoutReviews = [];

    foreach ($withdrawals as $withdrawal) {
      if (!in_array((int) $withdrawal->status, [Withdraw::STATUS_PENDING, Withdraw::STATUS_HELD], true)) {
        continue;
      }

      $account = $withdrawal->payoutAccount;
      $blocker = match (true) {
        !$payoutApprovalAvailable => __('Apply the payout approval database migration to approve this withdrawal.'),
        !$canManagePayout => __('Reviewing a payout requires an active admin with Withdraw Method permission.'),
        !$account => __('This withdrawal has no payout account attached. Finance needs to review the original request.'),
        (int) $account->organizer_id !== (int) $withdrawal->organizer_id
          => __('Finance needs to review the organiser and bank account on this request.'),
        $withdrawal->organizer?->kyc_status !== 'verified' => __('The organiser needs to complete identity verification before approval.'),
        $disputeHold => __('The organizer has an unresolved payment dispute. Finance must review it before releasing this payout.'),
        $account->status !== 'verified' => __('The attached payout account needs verified status before approval.'),
        !$account->is_default => __('The attached account must still be the organiser’s default payout account.'),
        !in_array($account->provider, ['paystack', 'monnify'], true) || strtoupper((string) $account->currency) !== 'NGN'
          => __('The attached account must support NGN payouts through Paystack or Monnify.'),
        $account->hold_until && now()->lt($account->hold_until)
          => __('The payout account is on a security hold until :time.', ['time' => $account->hold_until->format('j M Y, H:i T')]),
        default => null,
      };
      $fingerprint = $blocker === null ? $transfers->approvalFingerprint($withdrawal, $account) : null;
      $approval = $fingerprint !== null ? $transfers->currentApproval($withdrawal, $account) : null;
      $approved = $approval !== null;
      $canRelease = $approved && (int) $approval['admin_id'] !== (int) $payoutAdmin->id;

      $payoutReviews[$withdrawal->id] = [
        'approved' => $approved,
        'can_approve' => $fingerprint !== null && $canApprovePayout && !$approved,
        'can_queue' => $canRelease && $transfersEnabled,
        'can_record_external' => $canRelease && !$withdrawal->latestPayoutTransfer,
        'can_release' => $canRelease,
        'fingerprint' => $fingerprint,
        'expires_at' => $approval['expires_at'] ?? null,
        'blocker' => !$approved && !$canApprovePayout && $blocker === null
          ? __('Approving a withdrawal requires Transaction permission. Ask a finance admin to approve it.')
          : $blocker,
      ];
    }

    return [$payoutReviews, $transfersEnabled];
  }

  public function export(Request $request, DataExportService $exports)
  {
    $validated = $this->exportFilters($request, true);
    try {
      $exports->queueWithdrawals('admin', (int) Auth::guard('admin')->id(), null, $validated, $validated['format']);
    } catch (DomainException $exception) {
      return back()->withInput()->with('warning', $exception->getMessage());
    }

    return redirect()->route('admin.withdraw_exports.index')
      ->with('success', 'Withdrawal export queued. The private download will appear when processing finishes.');
  }

  private function exportFilters(Request $request, bool $requireFormat = false): array
  {
    return $request->validate([
      'search' => ['nullable', 'string', 'max:100'],
      'status' => ['nullable', 'integer', 'between:0,4'],
      'from_date' => ['nullable', 'date_format:Y-m-d'],
      'to_date' => ['nullable', 'date_format:Y-m-d'],
      'format' => [$requireFormat ? 'required' : 'nullable', 'in:csv,xlsx'],
    ]) + ['withdrawal_reference' => trim((string) $request->input('search', ''))];
  }
  //delete
  public function delete(Request $request)
  {
    return redirect()->back()->with('warning', 'Financial withdrawal records are retained for audit and cannot be deleted.');
  }

  public function approve(Request $request, $id)
  {
    $validated = $request->validateWithBag('external', [
      'payout_reference' => ['required', 'string', 'max:191'],
    ]);
    try {
      $withdrawal = app(PayoutTransferService::class)->recordExternalPayment(
        (int) $id, $validated['payout_reference'], (int) Auth::guard('admin')->id(),
      );
    } catch (RuntimeException $exception) {
      return $this->payoutFailure($exception, 'external');
    }

    return $this->notifyWithdrawalDecision($withdrawal, true);
  }

  public function decline(Request $request, $id, WithdrawalDecisionService $decisions)
  {
    $validated = $request->validateWithBag('reject', [
      'decision_reason' => ['required', 'string', 'max:2000'],
    ]);
    try {
      $withdrawal = $decisions->reject((int) $id, $validated['decision_reason'], (int) Auth::guard('admin')->id());
    } catch (RuntimeException $exception) {
      return $this->payoutFailure($exception, 'reject');
    }

    return $this->notifyWithdrawalDecision($withdrawal, false);
  }

  public function hold(Request $request, $id, WithdrawalDecisionService $decisions)
  {
    $validated = $request->validateWithBag('hold', [
      'decision_reason' => ['required', 'string', 'max:2000'],
    ]);
    try {
      $decisions->hold((int) $id, $validated['decision_reason'], (int) Auth::guard('admin')->id());
    } catch (RuntimeException $exception) {
      return $this->payoutFailure($exception, 'hold');
    }

    return $this->payoutNotice(__('Withdrawal placed on hold. Finance can reopen it for review.'));
  }

  public function submitPaystack($id, PayoutTransferService $transfers)
  {
    try {
      $transfer = $transfers->queue((int) $id, (int) Auth::guard('admin')->id());
    } catch (RuntimeException $exception) {
      return $this->payoutFailure($exception, 'release');
    }

    return $this->payoutNotice(__('Payout queued through :provider. The withdrawal moves to Paid after the provider confirms the transfer.', [
      'provider' => ucfirst((string) $transfer->gateway),
    ]));
  }

  private function notifyWithdrawalDecision(Withdraw $withdrawal, bool $paid)
  {
    $message = $paid
      ? __('Withdrawal marked paid.')
      : __('Withdrawal rejected. The reserved amount was returned to the organizer’s balance.');

    try {
      \App\Support\AdminQueues::forget();
      \App\Support\OrganizerInbox::send(
        (int) $withdrawal->organizer_id,
        $paid ? 'withdrawal.paid' : 'withdrawal.rejected',
        $paid ? __('Withdrawal paid') : __('Withdrawal declined'),
        $paid ? __('TICA marked your withdrawal as paid.') : (string) $withdrawal->decision_reason,
        url('/dashboard/payouts'),
      );

      $template = MailTemplate::query()->where('mail_type', $paid ? 'withdraw_approve' : 'withdraw_rejected')->firstOrFail();
      $settings = Basic::query()->first();
      $organizer = $withdrawal->organizer()->firstOrFail();
      $symbol = $settings->base_currency_symbol ?? '';
      $body = strtr($template->mail_body, [
        '{organizer_username}' => $organizer->username,
        '{withdraw_id}' => $withdrawal->withdraw_id,
        '{current_balance}' => $symbol . $organizer->amount,
        '{withdraw_amount}' => $symbol . $withdrawal->amount,
        '{charge}' => $symbol . $withdrawal->total_charge,
        '{payable_amount}' => $symbol . $withdrawal->payable_amount,
        '{withdraw_method}' => (string) $withdrawal->method()->value('name'),
        '{website_title}' => $settings->website_title ?? '',
      ]);

      app(ApplicationMailService::class)->queueHtml(
        $paid ? 'withdrawal_paid' : 'withdrawal_rejected', $organizer->email, $template->mail_subject, $body, [], null, $withdrawal,
      );
    } catch (\Throwable $exception) {
      report($exception);

      return $this->payoutNotice($message . ' ' . __('The organizer notification could not be queued.'), false);
    }

    return $this->payoutNotice($message);
  }

  private function payoutFailure(RuntimeException $exception, string $action)
  {
    $message = $exception->getMessage();
    if ($exception instanceof QueryException) {
      report($exception);
      $message = __('The withdrawal could not be updated. Reopen the review and try again.');
    }

    return $this->payoutNotice($message, false, $action)->withInput();
  }

  private function payoutNotice(string $message, bool $success = true, ?string $action = null)
  {
    return back()->with('payout_review_notice', compact('message', 'success', 'action'));
  }

  public function approveRelease(Request $request, int $id, PayoutTransferService $transfers)
  {
    $validated = $request->validate(
      ['review_fingerprint' => ['required', 'string', 'size:64']],
      ['review_fingerprint.*' => __('Reopen the withdrawal and review the amount and destination before approving.')]
    );
    try {
      $transfers->approveRelease($id, (int) Auth::guard('admin')->id(), $validated['review_fingerprint']);
    } catch (RuntimeException $exception) {
      return $this->payoutFailure($exception, 'approve');
    }

    return $this->payoutNotice(__('Withdrawal approved. A different authorized admin can release it before the approval expires.'));
  }

  public function reviewDispute(
    Request $request,
    PayoutDispute $dispute,
    PayoutDisputeService $disputes
  ) {
    $validated = $request->validate([
      'status' => ['required', 'string', 'in:under_review,resolved,rejected'],
      'resolution' => ['nullable', 'string', 'max:2000'],
    ]);

    try {
      $reviewed = $disputes->review(
        (int) $dispute->id,
        (int) Auth::guard('admin')->id(),
        $validated['status'],
        $validated['resolution'] ?? null
      );
    } catch (RuntimeException $exception) {
      return back()->withInput($request->only('status', 'resolution') + ['dispute_id' => $dispute->id])
        ->with('warning', $exception->getMessage());
    }

    return back()->with('success', 'Payout dispute ' . $reviewed->public_id . ' is now ' . str_replace('_', ' ', $reviewed->status) . '.');
  }
}
