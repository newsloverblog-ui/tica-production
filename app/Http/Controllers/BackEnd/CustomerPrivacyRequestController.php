<?php

namespace App\Http\Controllers\BackEnd;

use App\Http\Controllers\Controller;
use App\Models\CustomerPrivacyRequest;
use App\Services\CustomerPrivacyRequestService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CustomerPrivacyRequestController extends Controller
{
    public function index(Request $request)
    {
        $status = trim((string) $request->query('status'));
        $requests = CustomerPrivacyRequest::query()
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->latest('submitted_at')
            ->paginate(25)
            ->withQueryString();

        return view('backend.end-user.customer.privacy-requests', compact('requests', 'status'));
    }

    public function update(
        Request $request,
        CustomerPrivacyRequest $privacyRequest,
        CustomerPrivacyRequestService $requests
    ) {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['under_review', 'rejected', 'fulfilled'])],
            'resolution' => ['required', 'string', 'min:20', 'max:4000'],
            'evidence_reference' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $requests->review(
                $privacyRequest->id,
                (int) Auth::guard('admin')->id(),
                $validated['status'],
                $validated['resolution'],
                $validated['evidence_reference'] ?? null
            );
        } catch (DomainException $exception) {
            return back()->withInput()->with('warning', $exception->getMessage());
        }

        return back()->with('success', 'Privacy request status updated.');
    }
}
