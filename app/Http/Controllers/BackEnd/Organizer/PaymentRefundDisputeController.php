<?php

namespace App\Http\Controllers\BackEnd\Organizer;

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
            $dispute = $disputes->openForOrganizer(
                (int) $validated['payment_intent_id'],
                (int) Auth::guard('organizer')->id(),
                (float) $validated['requested_amount'],
                $validated['category'],
                $validated['description']
            );
        } catch (RuntimeException $exception) {
            return back()->withInput()->with('warning', $exception->getMessage());
        }

        return back()->with(
            'success',
            'Refund dispute '.$dispute->public_id.' is awaiting finance review. No refund has been submitted yet.'
        );
    }
}
