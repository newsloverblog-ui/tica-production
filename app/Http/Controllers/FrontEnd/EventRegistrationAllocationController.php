<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use App\Models\EventRegistrationAllocation;
use App\Models\EventRegistrationAllocationClaim;
use App\Services\EventRegistrationAllocationService;
use App\Services\EventRegistrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EventRegistrationAllocationController extends Controller
{
    public function show(EventRegistrationAllocation $allocation, string $token, EventRegistrationAllocationService $allocations)
    {
        $allocation = $allocations->assertSponsorAccess($allocation, $token)
            ->load(['event.information', 'claims' => fn ($query) => $query->orderBy('id')]);
        return response()->view('frontend.event.registration-allocation', compact('allocation', 'token'))
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    public function invite(Request $request, EventRegistrationAllocation $allocation, string $token, EventRegistrationAllocationService $allocations)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email:rfc', 'max:191'],
            'source_attested' => ['accepted'],
        ]);
        $allocations->invite($allocation, $token, $validated, $request->ip());
        return back()->with('success', 'The attendee’s private registration link has been queued for delivery.');
    }

    public function claim(EventRegistrationAllocationClaim $claim, string $token, EventRegistrationAllocationService $allocations)
    {
        $claim = $allocations->assertClaimAccess($claim, $token)->load(
            'event.information', 'event.tickets', 'form.questions.ticket',
            'form.sessions.ticket', 'allocation'
        );
        $event = $claim->event;
        $form = $claim->form;
        $allocationClaim = $claim;
        return response()->view('frontend.event.registration', compact('event', 'form', 'allocationClaim', 'token'))
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    public function submit(
        Request $request,
        EventRegistrationAllocationClaim $claim,
        string $token,
        EventRegistrationAllocationService $allocations,
        EventRegistrationService $registrations,
    ) {
        $submission = $allocations->submitClaim(
            $claim, $token, $request->all(), Auth::guard('customer')->id(), $request->ip(), $registrations
        );
        $event = $claim->event()->with('information')->firstOrFail();
        return view('frontend.event.registration-complete', compact('event', 'submission'));
    }
}
