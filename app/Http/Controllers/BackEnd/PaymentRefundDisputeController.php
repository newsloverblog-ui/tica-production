<?php

namespace App\Http\Controllers\BackEnd;

use App\Http\Controllers\Controller;
use App\Models\PaymentRefundDispute;
use App\Services\PaymentRefundDisputeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class PaymentRefundDisputeController extends Controller
{
    public function store(Request $request, PaymentRefundDisputeService $disputes)
    {
        $validated = $request->validate([
            'payment_intent_id' => ['required', 'integer', 'exists:payment_intents,id'],
            'requested_amount' => ['required', 'numeric', 'min:0.01'],
            'category' => ['required', 'string', 'in:'.implode(',', PaymentRefundDispute::CATEGORIES)],
            'description' => ['required', 'string', 'min:20', 'max:2000'],
            'confirm_dispute' => ['accepted'],
        ]);

        try {
            $dispute = $disputes->openByAdmin(
                (int) $validated['payment_intent_id'],
                (int) Auth::guard('admin')->id(),
                (float) $validated['requested_amount'],
                $validated['category'],
                $validated['description']
            );
        } catch (RuntimeException $exception) {
            return back()->withInput()->with('warning', $exception->getMessage());
        }

        return back()->with('success', 'Refund dispute '.$dispute->public_id.' was opened for finance review.');
    }

    public function review(
        Request $request,
        PaymentRefundDispute $dispute,
        PaymentRefundDisputeService $disputes
    ) {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:under_review,rejected'],
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

        return back()->with('success', 'Refund dispute '.$reviewed->public_id.' was updated.');
    }

    public function approve(
        Request $request,
        PaymentRefundDispute $dispute,
        PaymentRefundDisputeService $disputes
    ) {
        $validated = $request->validate([
            'resolution' => ['required', 'string', 'min:10', 'max:2000'],
            'confirm_approval' => ['accepted'],
        ]);

        try {
            $approved = $disputes->approveAndQueue(
                (int) $dispute->id,
                (int) Auth::guard('admin')->id(),
                $validated['resolution']
            );
        } catch (RuntimeException $exception) {
            return back()->withInput()->with('warning', $exception->getMessage());
        }

        return back()->with(
            'success',
            'Refund dispute '.$approved->public_id.' was approved and its provider refund was queued.'
        );
    }
}
