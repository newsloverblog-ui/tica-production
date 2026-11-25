<?php

namespace App\Http\Controllers\BackEnd;

use App\Http\Controllers\Controller;
use App\Models\GatewaySettlement;
use App\Models\PaymentReconciliationRun;
use App\Models\PaystackDispute;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PaymentReconciliationController extends Controller
{
    public function index(Request $request)
    {
        $runs = PaymentReconciliationRun::query()
            ->when(in_array($request->input('gateway'), ['paystack', 'flutterwave'], true), function ($query) use ($request) {
                $query->where('gateway', $request->input('gateway'));
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->when($request->filled('date'), function ($query) use ($request) {
                $query->whereDate('reconciliation_date', $request->date);
            })
            ->latest('started_at')
            ->paginate(25)
            ->withQueryString();

        $disputes = PaystackDispute::available()
            ? PaystackDispute::latest('updated_at')
                ->paginate(15, ['id', 'provider_dispute_id', 'status', 'booking_id', 'currency', 'amount_minor', 'exception', 'reviewed_at'], 'disputes_page')
                ->withQueryString()
            : null;

        return view('backend.admin.payment-reconciliation.index', compact('runs', 'disputes'));
    }

    public function acknowledgeDispute(Request $request, PaystackDispute $dispute)
    {
        $validated = $request->validate([
            'review_reference' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z][a-zA-Z0-9._-]{2,99}$/'],
        ]);
        DB::transaction(function () use ($dispute, $validated): void {
            $dispute = PaystackDispute::whereKey($dispute->id)->lockForUpdate()->firstOrFail();
            abort_unless($dispute->status === 'charged_back', 409, 'The provider outcome must be confirmed before acknowledging a loss.');
            if (!$dispute->reviewed_at) {
                $dispute->update([
                    'review_reference' => $validated['review_reference'],
                    'reviewed_by_admin_id' => Auth::guard('admin')->id(),
                    'reviewed_at' => now(),
                ]);
                AuditLogger::record('payment.paystack_dispute_reconciled', $dispute, [], [
                    'review_reference' => $validated['review_reference'],
                ]);
            }
        }, 3);

        return back()->with('success', 'Finance review recorded.');
    }

    public function show(Request $request, PaymentReconciliationRun $run)
    {
        $entries = $run->entries()
            ->when($request->filled('result'), function ($query) use ($request) {
                $query->where('result', $request->result);
            })
            ->orderByRaw("CASE WHEN result = 'matched' THEN 1 ELSE 0 END")
            ->orderBy('reference')
            ->paginate(50)
            ->withQueryString();

        return view('backend.admin.payment-reconciliation.show', compact('run', 'entries'));
    }

    public function settlements(Request $request)
    {
        $settlements = GatewaySettlement::query()
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('reconciliation_status', $request->status);
            })
            ->when($request->filled('date'), function ($query) use ($request) {
                $query->whereDate('settlement_date', $request->date);
            })
            ->latest('settlement_date')
            ->paginate(25)
            ->withQueryString();

        return view('backend.admin.payment-reconciliation.settlements', compact('settlements'));
    }

    public function settlementShow(Request $request, GatewaySettlement $settlement)
    {
        $entries = $settlement->entries()
            ->when($request->filled('result'), function ($query) use ($request) {
                $query->where('result', $request->result);
            })
            ->orderByRaw("CASE WHEN result = 'matched' THEN 1 ELSE 0 END")
            ->orderBy('reference')
            ->paginate(50)
            ->withQueryString();

        return view('backend.admin.payment-reconciliation.settlement-show', compact('settlement', 'entries'));
    }
}
