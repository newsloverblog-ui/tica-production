<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventWaitlistEntry;
use App\Services\EventWaitlistService;
use Illuminate\Http\Request;
use RuntimeException;

class EventWaitlistController extends Controller
{
    public function store(Request $request, Event $event, EventWaitlistService $waitlist)
    {
        $validated = $request->validate([
            'ticket_id' => ['required', 'integer'],
            'variation_name' => ['nullable', 'string', 'max:191'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'waitlist_consent' => ['accepted'],
        ], [
            'waitlist_consent.accepted' => 'Please agree to receive availability emails for this ticket.',
        ]);

        try {
            $waitlist->register(
                $event,
                (int) $validated['ticket_id'],
                $validated['variation_name'] ?? null,
                $validated['name'],
                $validated['email'],
                auth('customer')->id()
            );
        } catch (RuntimeException $exception) {
            return back()->withInput()->withErrors(['waitlist' => $exception->getMessage()]);
        }

        return back()->with('success', 'You are on the waiting list. We will email you if this ticket becomes available.');
    }

    public function confirmUnsubscribe(EventWaitlistEntry $waitlist)
    {
        return view('frontend.event.waitlist-unsubscribe', compact('waitlist'));
    }

    public function unsubscribe(EventWaitlistEntry $waitlist, EventWaitlistService $service)
    {
        $service->unsubscribe($waitlist);
        return view('frontend.event.waitlist-unsubscribe', [
            'waitlist' => $waitlist->fresh(),
            'unsubscribed' => true,
        ]);
    }
}
