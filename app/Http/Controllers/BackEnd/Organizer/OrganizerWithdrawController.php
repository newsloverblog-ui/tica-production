<?php

namespace App\Http\Controllers\BackEnd\Organizer;

use App\Http\Controllers\Controller;
use App\Http\Requests\WithdrawRequest;
use App\Models\Language;
use App\Models\OrganizerPayoutAccount;
use App\Models\PayoutDispute;
use App\Models\Withdraw;
use App\Models\WithdrawMethodInput;
use App\Models\WithdrawPaymentMethod;
use App\Services\WithdrawalService;
use App\Services\WithdrawalCancellationService;
use App\Services\PayoutDisputeService;
use App\Services\DataExportService;
use App\Services\WithdrawalExportQueryService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class OrganizerWithdrawController extends Controller
{
  public function index(Request $request)
  {
    $filters = $this->exportFilters($request);
    $organizerId = (int) Auth::guard('organizer')->id();
    $currencyInfo = $this->getCurrencyInfo();
    $collection = app(WithdrawalExportQueryService::class)->query($filters, $organizerId)
      ->with('latestPayoutDispute')->orderByDesc('id')->get();
    return view('organizer.withdraw.index', compact('collection', 'currencyInfo'));
  }

  public function export(Request $request, DataExportService $exports)
  {
    $validated = $this->exportFilters($request, true);
    $organizerId = (int) Auth::guard('organizer')->id();
    try {
      $exports->queueWithdrawals('organizer', $organizerId, $organizerId, $validated, $validated['format']);
    } catch (DomainException $exception) {
      return back()->withInput()->with('warning', $exception->getMessage());
    }

    return redirect()->route('organizer.withdraw_exports.index')
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
  //create
  public function create()
  {
    $information = [];
    $language = Language::where('code', request()->input('language'))->firstOrFail();
    $lang_id = $language->id;
    $lang = Language::where('id', $lang_id)->firstOrFail();
    $methods = WithdrawPaymentMethod::where('status', '=', 1)->get();
    $payoutAccount = OrganizerPayoutAccount::where('organizer_id', Auth::guard('organizer')->id())
      ->where('status', 'verified')->where('is_default', true)->first();
    $information['lang'] = $lang;
    $information['methods'] = $methods;
    $information['payoutAccount'] = $payoutAccount;
    return view('organizer.withdraw.create', $information);
  }
  //get_inputs
  public function get_inputs($id)
  {
    $data = WithdrawMethodInput::with('options')->where('withdraw_payment_method_id', $id)->orderBy('order_number', 'asc')->get();

    return $data;
  }
  //balance_calculation
  public function balance_calculation($method, $amount)
  {
    if (Auth::guard('organizer')->user()->amount < $amount) {
      return 'error';
    }
    $method = WithdrawPaymentMethod::find($method);
    if (!$method) {
      return 'error';
    }
    try {
      [$total_charge, $receive_balance] = (new WithdrawalService())->charges($method, (float) $amount);
    } catch (ValidationException $exception) {
      return 'error';
    }
    $user_balance = Auth::guard('organizer')->user()->amount - $amount;


    return ['total_charge' => round($total_charge, 2), 'receive_balance' => round($receive_balance, 2), 'user_balance' => round($user_balance, 2)];
  }
  //send_request
  public function send_request(WithdrawRequest $request)
  {
    $amount = (float) $request->withdraw_amount;
    $currencyInfo = $this->getCurrencyInfo();
    (new WithdrawalService())->request(
      (int) Auth::guard('organizer')->id(),
      (int) $request->withdraw_method,
      $amount,
      $request->additional_reference,
      (string) $currencyInfo->base_currency_symbol,
      (string) $currencyInfo->base_currency_text_position
    );

    Session::flash('success', 'Withdraw Request Send Successfully!');

    return Response::json(['status' => 'success'], 200);
  }

  public function openDispute(Request $request, Withdraw $withdrawal, PayoutDisputeService $disputes)
  {
    $validated = $request->validate([
      'category' => ['required', 'string', 'in:' . implode(',', PayoutDispute::CATEGORIES)],
      'description' => ['required', 'string', 'min:20', 'max:2000'],
      'confirm_dispute' => ['accepted'],
    ]);

    try {
      $dispute = $disputes->open(
        (int) $withdrawal->id,
        (int) Auth::guard('organizer')->id(),
        $validated['category'],
        $validated['description']
      );
    } catch (\RuntimeException $exception) {
      return back()->withInput()->with('warning', $exception->getMessage());
    }

    return back()->with('success', 'Payout dispute ' . $dispute->public_id . ' was opened for finance review.');
  }

  //Delete
  public function Delete(Request $request, WithdrawalCancellationService $cancellations)
  {
    $request->validate(['id' => ['required', 'integer']]);
    try {
      $cancelled = $cancellations->cancel((int) $request->id, (int) Auth::guard('organizer')->id());
    } catch (RuntimeException $exception) {
      return redirect()->back()->with('warning', $exception->getMessage());
    }

    return redirect()->back()->with(
      $cancelled ? 'success' : 'warning',
      $cancelled ? 'Withdrawal request cancelled and balance restored.' : 'Only pending withdrawal requests can be cancelled.'
    );
  }

  //bulkDelete
  public function bulkDelete(Request $request, WithdrawalCancellationService $cancellations)
  {
    $validated = $request->validate([
      'ids' => ['required', 'array', 'min:1'],
      'ids.*' => ['required', 'integer', 'distinct'],
    ]);
    try {
      $cancelledCount = $cancellations->cancelMany(
        $validated['ids'],
        (int) Auth::guard('organizer')->id()
      );
    } catch (RuntimeException $exception) {
      Session::flash('warning', $exception->getMessage());
      return Response::json(['status' => 'success'], 200);
    }

    Session::flash('success', $cancelledCount . ' pending withdrawal request(s) cancelled and balance restored.');

    return Response::json(['status' => 'success'], 200);
  }
}
