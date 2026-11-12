<?php

namespace App\Http\Controllers\BackEnd;

use App\Http\Controllers\Controller;
use App\Models\EventCancellationRequest;
use App\Models\EventCancellationCase;
use App\Models\PaymentRefundDispute;
use App\Services\EventCancellationCaseService;
use App\Services\EventCancellationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class EventCancellationController extends Controller
{
    public function index()
    {
        $requests = EventCancellationRequest::with([
            'event.information', 'organizer', 'cases.booking.ticketUnits',
            'cases.paymentIntent', 'cases.paymentRefundDispute',
        ])
            ->orderByRaw("CASE WHEN status = 'approval_pending' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->paginate(25);

        return view('backend.event.cancellations', compact('requests'));
    }

    public function store(Request $request, int $event, EventCancellationService $service)
    {
        $validated = $this->requestData($request);
        try {
            $service->requestByAdmin(
                $event,
                (int) Auth::guard('admin')->id(),
                $validated['reason'],
                $validated['customer_message'] ?? null
            );
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage())->withInput();
        }

        return redirect()->route('admin.event_cancellations.index')
            ->with('success', 'Cancellation review created and ticket sales paused.');
    }

    public function approve(
        EventCancellationRequest $cancellation,
        EventCancellationService $service
    ) {
        try {
            $service->approve($cancellation->id, (int) Auth::guard('admin')->id());
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Event cancelled. Eligible gateway refunds were queued and attendee notices were scheduled.');
    }

    public function reject(
        Request $request,
        EventCancellationRequest $cancellation,
        EventCancellationService $service
    ) {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);
        try {
            $service->reject(
                $cancellation->id,
                (int) Auth::guard('admin')->id(),
                $validated['rejection_reason']
            );
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Cancellation rejected and the prior event visibility restored.');
    }

    public function openCaseDispute(
        Request $request,
        EventCancellationCase $case,
        EventCancellationCaseService $cases
    ) {
        $validated = $request->validate([
            'category' => ['required', 'string', 'in:'.implode(',', PaymentRefundDispute::CATEGORIES)],
            'description' => ['required', 'string', 'min:20', 'max:2000'],
            'confirm_dispute' => ['accepted'],
        ]);

        try {
            $cases->openCheckedInDispute(
                (int) $case->id,
                (int) Auth::guard('admin')->id(),
                $validated['category'],
                $validated['description']
            );
        } catch (RuntimeException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'The cancellation case was escalated to checked-in refund review.');
    }

    public function resolveCase(
        Request $request,
        EventCancellationCase $case,
        EventCancellationCaseService $cases
    ) {
        $validated = $request->validate([
            'outcome' => ['required', 'string', 'in:no_refund,external_refund,retry_provider'],
            'resolution' => ['required', 'string', 'min:10', 'max:2000'],
            'external_refund_reference' => ['nullable', 'string', 'max:191'],
            'confirm_resolution' => ['accepted'],
        ]);

        try {
            if ($validated['outcome'] === 'retry_provider') {
                $cases->retryControlledRefund(
                    (int) $case->id,
                    (int) Auth::guard('admin')->id()
                );
                $message = 'The repaired controlled provider refund was queued.';
            } elseif ($validated['outcome'] === 'external_refund') {
                $cases->recordExternalRefund(
                    (int) $case->id,
                    (int) Auth::guard('admin')->id(),
                    (string) ($validated['external_refund_reference'] ?? ''),
                    $validated['resolution']
                );
                $message = 'The externally completed refund was reconciled into the cancellation ledger.';
            } else {
                $cases->resolveWithoutRefund(
                    (int) $case->id,
                    (int) Auth::guard('admin')->id(),
                    $validated['resolution']
                );
                $message = 'The cancellation case was closed without a refund and the resolution was audited.';
            }
        } catch (RuntimeException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return back()->with('success', $message);
    }

    private function requestData(Request $request): array
    {
        return $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
            'customer_message' => ['nullable', 'string', 'max:3000'],
            'confirm' => ['accepted'],
        ]);
    }
}
