<?php

namespace App\Http\Controllers\BackEnd\Organizer;

use App\Http\Controllers\Controller;
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
            'confirm_refund_request' => ['accepted'],
        ]);

        try {
            $refund = $refunds->requestByOrganizer(
                (int) $validated['payment_intent_id'],
                (float) $validated['amount'],
                $validated['reason'],
                (int) Auth::guard('organizer')->id()
            );
        } catch (RuntimeException $exception) {
            return back()->withInput()->with('warning', $exception->getMessage());
        }

        return back()->with(
            'success',
            'Refund request '.$refund->public_id.' is awaiting finance approval. No money moves until an administrator approves it and the provider confirms success.'
        );
    }
}
