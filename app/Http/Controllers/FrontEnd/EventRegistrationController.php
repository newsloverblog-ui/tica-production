<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventRegistrationForm;
use App\Services\EventRegistrationService;
use App\Services\EventRegistrationEmbedService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;

class EventRegistrationController extends Controller
{
    public function show(Event $event)
    {
        $form = $this->publishedForm($event);
        $event->load('information', 'tickets');

        return view('frontend.event.registration', compact('event', 'form') + ['embedMode' => false]);
    }

    public function store(Request $request, Event $event, EventRegistrationService $registrations)
    {
        $submission = $registrations->submit(
            $event,
            $request->all(),
            Auth::guard('customer')->id(),
            $request->ip()
        );
        $event->load('information');

        return view('frontend.event.registration-complete', compact('event', 'submission') + ['embedMode' => false]);
    }

    public function showEmbedded(Request $request, Event $event, EventRegistrationEmbedService $embeds)
    {
        $form = $this->publishedForm($event);
        $form->loadMissing('embedSetting');
        $setting = $embeds->enabledSetting($form);
        $embeds->applyFramePolicy($request, $setting);
        $event->load('information', 'tickets');

        return view('frontend.event.registration', [
            'event' => $event,
            'form' => $form,
            'embedMode' => true,
            'embedSetting' => $setting,
            'registrationAction' => $this->signedEmbedAction($event),
        ]);
    }

    public function storeEmbedded(
        Request $request,
        Event $event,
        EventRegistrationService $registrations,
        EventRegistrationEmbedService $embeds,
    ) {
        $form = $this->publishedForm($event);
        $form->loadMissing('embedSetting');
        $setting = $embeds->enabledSetting($form);
        $embeds->applyFramePolicy($request, $setting);

        try {
            $submission = $registrations->submit($event, $request->all(), null, $request->ip());
        } catch (ValidationException $exception) {
            $request->session()->flashInput($request->input());
            $errors = new ViewErrorBag();
            $errors->put('default', new MessageBag($exception->errors()));
            $event->load('information', 'tickets');

            return response()->view('frontend.event.registration', [
                'event' => $event,
                'form' => $form,
                'embedMode' => true,
                'embedSetting' => $setting,
                'registrationAction' => $this->signedEmbedAction($event),
                'errors' => $errors,
            ], 422);
        }

        $event->load('information');

        return response()->view('frontend.event.registration-complete', [
            'event' => $event,
            'submission' => $submission,
            'embedMode' => true,
            'embedSetting' => $setting,
        ], 201);
    }

    private function publishedForm(Event $event): EventRegistrationForm
    {
        abort_unless(($event->registration_mode ?: 'ticketing') === 'corporate'
            && (int) $event->status === 1
            && ($event->lifecycle_status ?: 'scheduled') === 'scheduled'
            && !$event->cancelled_at, 404);

        return EventRegistrationForm::with(['questions.ticket', 'sessions' => fn ($query) => $query->where('status', 'published')->with('ticket')])
            ->where('event_id', $event->id)
            ->where('status', 'published')
            ->firstOrFail();
    }

    private function signedEmbedAction(Event $event): string
    {
        return URL::temporarySignedRoute(
            'event.registration.embed.store',
            now()->addHours(4),
            ['event' => $event]
        );
    }
}
