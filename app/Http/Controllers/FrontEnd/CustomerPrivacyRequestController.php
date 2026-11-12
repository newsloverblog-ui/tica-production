<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use App\Models\CustomerPrivacyRequest;
use App\Services\CustomerPrivacyRequestService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CustomerPrivacyRequestController extends Controller
{
    public function store(Request $request, CustomerPrivacyRequestService $requests)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password:customer'],
            'acknowledge_retention' => ['accepted'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $privacyRequest = $requests->requestDeletion(
                (int) Auth::guard('customer')->id(),
                $validated['reason'] ?? null
            );
        } catch (DomainException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->route('customer.data-export.index')->with(
            'success',
            'Deletion request '.$privacyRequest->public_id.' has been recorded for review.'
        );
    }

    public function cancel(CustomerPrivacyRequest $privacyRequest, CustomerPrivacyRequestService $requests)
    {
        try {
            $requests->cancel($privacyRequest->id, (int) Auth::guard('customer')->id());
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Your pending deletion request has been cancelled.');
    }
}
