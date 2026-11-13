<?php

namespace App\Http\Controllers\BackEnd;

use App\Http\Controllers\Controller;
use App\Models\BasicSettings\Basic;
use App\Models\BasicSettings\MailTemplate;
use App\Models\Withdraw;
use App\Models\PayoutDispute;
use App\Services\PayoutDisputeService;
use App\Services\WithdrawalDecisionService;
use App\Services\PayoutTransferService;
use App\Services\ApplicationMailService;
use App\Services\DataExportService;
use App\Services\WithdrawalExportQueryService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use RuntimeException;

class WithdrawController extends Controller
{
  //index
  public function index(Request $request)
  {
    $filters = $this->exportFilters($request);
    $collection = app(WithdrawalExportQueryService::class)->query($filters)
      ->with(['latestPayoutTransfer', 'latestPayoutDispute'])
      ->orderByDesc('id')->paginate(10)->withQueryString();
    $payoutDisputes = PayoutDispute::with(['withdrawal', 'organizer'])
      ->whereIn('status', [PayoutDispute::STATUS_OPEN, PayoutDispute::STATUS_UNDER_REVIEW])
      ->orderBy('opened_at')
      ->limit(100)
      ->get();
    $currencyInfo = $this->getCurrencyInfo();
    return view('backend.withdraw.history.index', compact('collection', 'currencyInfo', 'payoutDisputes'));
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

  //approve
  public function approve(Request $request, $id)
  {
    $validated = $request->validate([
      'payout_reference' => ['required', 'string', 'max:191'],
    ]);
    try {
      $withdraw = (new WithdrawalDecisionService())->markPaid(
        (int) $id,
        $validated['payout_reference'],
        (int) Auth::guard('admin')->id()
      );
    } catch (RuntimeException $exception) {
      return redirect()->back()->with('warning', $exception->getMessage());
    }
    \App\Support\AdminQueues::forget();
    \App\Support\OrganizerInbox::send((int) $withdraw->organizer_id, 'withdrawal.' . (request()->routeIs('*decline*') || $request->has('decision_reason') ? 'rejected' : 'paid'),
      $request->has('decision_reason') ? __('Withdrawal declined') : __('Withdrawal paid'),
      $request->has('decision_reason') ? (string) $request->input('decision_reason') : __('TICA marked your withdrawal as paid.'),
      url('/dashboard/payouts'));

    //mail sending
    // get the website title & mail's smtp information from db
    $info = Basic::select('website_title', 'base_currency_symbol_position', 'base_currency_symbol')
      ->first();

    //preparing mail info
    // get the mail template info from db
    $mailTemplate = MailTemplate::query()->where('mail_type', '=', 'withdraw_approve')->first();
    $mailData['subject'] = $mailTemplate->mail_subject;
    $mailBody = $mailTemplate->mail_body;

    // get the website title info from db
    $website_info = Basic::select('website_title')->first();

    $organizer = $withdraw->organizer()->first();

    // preparing dynamic data
    $organizerName = $organizer->username;
    $organizerEmail = $organizer->email;
    $organizer_amount = $organizer->amount;
    $withdraw_amount = $withdraw->amount;
    $total_charge = $withdraw->total_charge;
    $payable_amount = $withdraw->payable_amount;

    $method = $withdraw->method()->select('name')->first();

    $websiteTitle = $website_info->website_title;

    // replacing with actual data
    $mailBody = str_replace('{organizer_username}', $organizerName, $mailBody);
    $mailBody = str_replace('{withdraw_id}', $withdraw->withdraw_id, $mailBody);

    $mailBody = str_replace('{current_balance}', $info->base_currency_symbol . $organizer_amount, $mailBody);
    $mailBody = str_replace('{withdraw_amount}', $info->base_currency_symbol . $withdraw_amount, $mailBody);
    $mailBody = str_replace('{charge}', $info->base_currency_symbol . $total_charge, $mailBody);
    $mailBody = str_replace('{payable_amount}', $info->base_currency_symbol . $payable_amount, $mailBody);

    $mailBody = str_replace('{withdraw_method}', $method->name, $mailBody);
    $mailBody = str_replace('{website_title}', $websiteTitle, $mailBody);

    $mailData['body'] = $mailBody;

    $mailData['recipient'] = $organizerEmail;
    //preparing mail info end

    try {
      app(ApplicationMailService::class)->queueHtml(
        'withdrawal_paid', $mailData['recipient'], $mailData['subject'], $mailData['body'], [], null, $withdraw
      );
      Session::flash('success', 'Withdrawal marked paid; its notification was queued.');
    } catch (\Throwable $e) {
      report($e);
      Session::flash('warning', 'Withdrawal was marked paid, but its email could not be queued.');
    }
    return redirect()->back();
  }
  //decline
  public function decline(Request $request, $id)
  {
    $validated = $request->validate([
      'decision_reason' => ['required', 'string', 'max:2000'],
    ]);
    try {
      $withdraw = (new WithdrawalDecisionService())->reject(
        (int) $id,
        $validated['decision_reason'],
        (int) Auth::guard('admin')->id()
      );
    } catch (RuntimeException $exception) {
      return redirect()->back()->with('warning', $exception->getMessage());
    }
    \App\Support\AdminQueues::forget();
    \App\Support\OrganizerInbox::send((int) $withdraw->organizer_id, 'withdrawal.' . (request()->routeIs('*decline*') || $request->has('decision_reason') ? 'rejected' : 'paid'),
      $request->has('decision_reason') ? __('Withdrawal declined') : __('Withdrawal paid'),
      $request->has('decision_reason') ? (string) $request->input('decision_reason') : __('TICA marked your withdrawal as paid.'),
      url('/dashboard/payouts'));

    //mail sending
    // get the website title & mail's smtp information from db
    $info = Basic::select('website_title', 'base_currency_symbol_position', 'base_currency_symbol')
      ->first();

    //preparing mail info
    // get the mail template info from db
    $mailTemplate = MailTemplate::query()->where('mail_type', '=', 'withdraw_rejected')->first();
    $mailData['subject'] = $mailTemplate->mail_subject;
    $mailBody = $mailTemplate->mail_body;

    // get the website title info from db
    $website_info = Basic::select('website_title')->first();

    $organizer = $withdraw->organizer()->first();

    // preparing dynamic data
    $organizerName = $organizer->username;
    $organizerEmail = $organizer->email;
    $organizer_amount = $organizer->amount;

    $method = $withdraw->method()->select('name')->first();

    $websiteTitle = $website_info->website_title;

    // replacing with actual data
    $mailBody = str_replace('{organizer_username}', $organizerName, $mailBody);
    $mailBody = str_replace('{withdraw_id}', $withdraw->withdraw_id, $mailBody);

    $mailBody = str_replace('{current_balance}', $info->base_currency_symbol . $organizer_amount, $mailBody);
    $mailBody = str_replace('{website_title}', $websiteTitle, $mailBody);

    $mailData['body'] = $mailBody;

    $mailData['recipient'] = $organizerEmail;
    //preparing mail info end

    try {
      app(ApplicationMailService::class)->queueHtml(
        'withdrawal_rejected', $mailData['recipient'], $mailData['subject'], $mailData['body'], [], null, $withdraw
      );
      Session::flash('success', 'Withdrawal rejected, balance restored, and its notification queued.');
    } catch (\Throwable $e) {
      report($e);
      Session::flash('warning', 'The withdrawal was rejected, but its email could not be queued.');
    }
    return redirect()->back();
  }

  public function hold(Request $request, $id)
  {
    $validated = $request->validate([
      'decision_reason' => ['required', 'string', 'max:2000'],
    ]);
    try {
      (new WithdrawalDecisionService())->hold(
        (int) $id,
        $validated['decision_reason'],
        (int) Auth::guard('admin')->id()
      );
    } catch (RuntimeException $exception) {
      return redirect()->back()->with('warning', $exception->getMessage());
    }

    return redirect()->back()->with('success', 'Withdrawal placed on hold for finance review.');
  }

  public function submitPaystack($id)
  {
    try {
      $transfer = (new PayoutTransferService())->queue(
        (int) $id,
        (int) Auth::guard('admin')->id()
      );
    } catch (RuntimeException $exception) {
      return redirect()->back()->with('warning', $exception->getMessage());
    }

    return redirect()->back()->with(
      'success',
      'The payout was queued via ' . ucfirst((string) $transfer->gateway) . ' with reference ' . $transfer->reference . '. It is only marked paid after the provider confirms it.'
    );
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
      return back()->withInput()->with('warning', $exception->getMessage());
    }

    return back()->with('success', 'Payout dispute ' . $reviewed->public_id . ' is now ' . str_replace('_', ' ', $reviewed->status) . '.');
  }
}
