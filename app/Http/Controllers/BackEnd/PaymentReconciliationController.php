<?php

namespace App\Http\Controllers\BackEnd;

use App\Http\Controllers\Controller;
use App\Models\GatewaySettlement;
use App\Models\PaymentReconciliationRun;
use Illuminate\Http\Request;

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

        return view('backend.admin.payment-reconciliation.index', compact('runs'));
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
