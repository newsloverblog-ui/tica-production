<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use App\Models\EventGuestInvitation;
use App\Services\EventGuestListService;
use Illuminate\Http\Request;

class EventGuestRsvpController extends Controller
{
    public function show(EventGuestInvitation $invitation, string $token)
    {
        abort_unless(hash_equals((string) $invitation->token_hash, hash('sha256', $token)), 404);
        $invitation->load('event.information');

        return view('frontend.event.guest-rsvp', compact('invitation'));
    }

    public function respond(Request $request, EventGuestInvitation $invitation, string $token, EventGuestListService $guestList)
    {
        $validated = $request->validate([
            'response' => ['required', 'in:accepted,declined'],
            'rsvp_consent' => ['nullable', 'boolean'],
            'marketing_consent' => ['nullable', 'boolean'],
        ]);
        $updated = $guestList->respond(
            $invitation,
            $token,
            $validated['response'],
            $request->boolean('rsvp_consent'),
            $request->boolean('marketing_consent')
        );
        $updated->load('event.information');

        return view('frontend.event.guest-rsvp-complete', ['invitation' => $updated]);
    }
}
