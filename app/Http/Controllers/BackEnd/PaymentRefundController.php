<?php

namespace App\Http\Controllers\BackEnd;

use App\Http\Controllers\Controller;
use App\Models\PaymentRefund;
use App\Services\PaymentRefundService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class PaymentRefundController extends Controller
{
    public function store(Request $request, PaymentRefundService $refunds)
    {
        $validated = $request->validate([
            'payment_intent_id' => ['required', 'integer', 'exists:payment_intents,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        try {
            $refund = $refunds->request(
                (int) $validated['payment_intent_id'],
                (float) $validated['amount'],
                $validated['reason'],
                (int) Auth::guard('admin')->id()
            );
        } catch (RuntimeException $exception) {
            return back()->with('warning', $exception->getMessage())->withInput();
        }

        return back()->with(
            'success',
            'Refund ' . $refund->public_id . ' was queued for controlled ' . ucfirst($refund->gateway) . ' submission.'
        );
    }

    public function approve(PaymentRefund $refund, PaymentRefundService $refunds)
    {
        try {
            $approved = $refunds->approveOrganizerRequest(
                (int) $refund->id,
                (int) Auth::guard('admin')->id()
            );
        } catch (RuntimeException $exception) {
            return back()->with('warning', $exception->getMessage());
        }

        return back()->with(
            'success',
            'Refund '.$approved->public_id.' was approved and queued for controlled provider submission.'
        );
    }

    public function reject(Request $request, PaymentRefund $refund, PaymentRefundService $refunds)
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        try {
            $rejected = $refunds->rejectOrganizerRequest(
                (int) $refund->id,
                (int) Auth::guard('admin')->id(),
                $validated['rejection_reason']
            );
        } catch (RuntimeException $exception) {
            return back()->with('warning', $exception->getMessage());
        }

        return back()->with('success', 'Refund '.$rejected->public_id.' was rejected with an audit note.');
    }
}
