<?php

namespace App\Http\Controllers\BackEnd;

use App\Http\Controllers\Controller;
use App\Models\WebhookReceipt;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class WebhookOperationsController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'gateway' => ['nullable', 'in:paystack,flutterwave'],
            'outcome' => ['nullable', 'in:received,processed,duplicate,ignored,rejected,failed'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $to = CarbonImmutable::parse($validated['to'] ?? now()->toDateString())->endOfDay();
        $from = CarbonImmutable::parse($validated['from'] ?? $to->subDay()->toDateString())->startOfDay();
        if ($from->gt($to)) {
            [$from, $to] = [$to->startOfDay(), $from->endOfDay()];
        }
        if ($from->diffInDays($to) > 30) {
            $from = $to->subDays(30)->startOfDay();
        }

        $base = WebhookReceipt::query()->whereBetween('received_at', [$from, $to])
            ->when($validated['gateway'] ?? null, fn ($query, $gateway) => $query->where('gateway', $gateway))
            ->when($validated['outcome'] ?? null, fn ($query, $outcome) => $query->where('outcome', $outcome));
        $counts = (clone $base)->selectRaw('outcome, COUNT(*) as aggregate')
            ->groupBy('outcome')->pluck('aggregate', 'outcome')->map(fn ($value) => (int) $value);
        $receipts = (clone $base)->latest('received_at')->paginate(50)->withQueryString();

        return view('backend.admin.webhook-operations.index', compact('from', 'to', 'counts', 'receipts'));
    }
}
