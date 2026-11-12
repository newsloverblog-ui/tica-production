<?php

namespace App\Services;

use App\Models\EventRegistrationForm;
use App\Models\EventRegistrationSession;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EventRegistrationSessionService
{
    public function save(EventRegistrationForm $form, int $organizerId, array $data, ?EventRegistrationSession $session = null): EventRegistrationSession
    {
        $form->loadMissing('event');
        if ((int) $form->organizer_id !== $organizerId || (int) $form->event?->organizer_id !== $organizerId) {
            throw new AuthorizationException('You cannot manage another organizer’s agenda.');
        }
        if ($session && ((int) $session->form_id !== (int) $form->id || (int) $session->organizer_id !== $organizerId)) {
            throw new AuthorizationException('This session does not belong to the selected registration form.');
        }
        $title = trim((string) ($data['title'] ?? ''));
        $startsAt = Carbon::parse((string) ($data['starts_at'] ?? ''));
        $endsAt = Carbon::parse((string) ($data['ends_at'] ?? ''));
        $capacity = ($data['capacity'] ?? '') === '' ? null : (int) $data['capacity'];
        $ticketId = ($data['ticket_id'] ?? '') === '' ? null : (int) $data['ticket_id'];
        if ($title === '' || mb_strlen($title) > 191) throw ValidationException::withMessages(['title' => 'Enter a session title of 191 characters or fewer.']);
        if ($startsAt->isPast() || $endsAt->lessThanOrEqualTo($startsAt)) throw ValidationException::withMessages(['ends_at' => 'The session must end after it starts and cannot start in the past.']);
        $eventStartsAt = $form->event?->start_date
            ? Carbon::parse($form->event->start_date.' '.($form->event->start_time ?: '00:00:00')) : null;
        $eventEndsAt = $form->event?->end_date_time
            ? Carbon::parse($form->event->end_date_time)
            : ($form->event?->end_date ? Carbon::parse($form->event->end_date.' '.($form->event->end_time ?: '23:59:59')) : null);
        if (($eventStartsAt && $startsAt->lessThan($eventStartsAt)) || ($eventEndsAt && $endsAt->greaterThan($eventEndsAt))) {
            throw ValidationException::withMessages(['starts_at' => 'Session times must fall within the event schedule.']);
        }
        if ($capacity !== null && ($capacity < 1 || $capacity > 100000)) throw ValidationException::withMessages(['capacity' => 'Session capacity must be between 1 and 100,000.']);
        if ($ticketId && !\App\Models\Event\Ticket::query()->whereKey($ticketId)->where('event_id', $form->event_id)->exists()) {
            throw ValidationException::withMessages(['ticket_id' => 'Choose a registration type from this event.']);
        }

        return DB::transaction(function () use ($form, $organizerId, $session, $title, $startsAt, $endsAt, $capacity, $ticketId, $data): EventRegistrationSession {
            $locked = $session ? EventRegistrationSession::whereKey($session->id)->lockForUpdate()->firstOrFail() : null;
            $selected = $locked?->selections()->whereNull('cancelled_at')->count() ?? 0;
            if ($capacity !== null && $capacity < $selected) {
                throw ValidationException::withMessages(['capacity' => "Capacity cannot be lower than the {$selected} active selections."]);
            }
            if ($locked && $selected > 0) {
                $structuralChange = $locked->title !== $title
                    || !$locked->starts_at->equalTo($startsAt)
                    || !$locked->ends_at->equalTo($endsAt)
                    || (int) ($locked->ticket_id ?? 0) !== (int) ($ticketId ?? 0)
                    || (bool) $locked->is_required !== !empty($data['is_required'])
                    || (string) ($locked->description ?? '') !== trim((string) ($data['description'] ?? ''))
                    || (string) ($locked->location ?? '') !== trim((string) ($data['location'] ?? ''));
                if ($structuralChange || $capacity === null || ($locked->capacity !== null && $capacity < $locked->capacity)) {
                    throw ValidationException::withMessages([
                        'session' => 'A session with attendee selections can only have its capacity increased. Create a replacement session and contact attendees for other agenda changes.',
                    ]);
                }
            }
            $before = $locked?->only(['title', 'starts_at', 'ends_at', 'capacity', 'ticket_id', 'is_required']) ?: [];
            $locked ??= new EventRegistrationSession([
                'public_id' => (string) Str::ulid(), 'form_id' => $form->id,
                'event_id' => $form->event_id, 'organizer_id' => $organizerId,
            ]);
            $locked->forceFill([
                'ticket_id' => $ticketId, 'title' => $title,
                'description' => trim((string) ($data['description'] ?? '')) ?: null,
                'location' => trim((string) ($data['location'] ?? '')) ?: null,
                'starts_at' => $startsAt, 'ends_at' => $endsAt, 'capacity' => $capacity,
                'is_required' => !empty($data['is_required']), 'status' => 'published', 'cancelled_at' => null,
            ])->save();
            AuditLogger::record('event.registration_session.saved', $locked, $before, $locked->only([
                'title', 'starts_at', 'ends_at', 'capacity', 'ticket_id', 'is_required', 'status',
            ]), ['event_id' => $locked->event_id, 'form_id' => $locked->form_id]);
            return $locked;
        }, 3);
    }

    public function cancel(EventRegistrationSession $session, int $organizerId): EventRegistrationSession
    {
        if ((int) $session->organizer_id !== $organizerId) throw new AuthorizationException('You cannot cancel another organizer’s session.');
        return DB::transaction(function () use ($session): EventRegistrationSession {
            $locked = EventRegistrationSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'cancelled') return $locked;
            $active = $locked->selections()->whereNull('cancelled_at')->count();
            if ($active > 0) {
                throw ValidationException::withMessages(['session' => 'This session has attendee selections. Cancel the event or contact attendees before changing the published agenda.']);
            }
            $locked->forceFill(['status' => 'cancelled', 'cancelled_at' => now()])->save();
            AuditLogger::record('event.registration_session.cancelled', $locked, ['status' => 'published'], ['status' => 'cancelled']);
            return $locked;
        }, 3);
    }
}
