<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Event\Ticket;
use App\Models\TicketReservation;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TicketRetirementService
{
    public function retire(array $ticketIds, ?int $organizerId = null): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ticketIds), fn ($id) => $id > 0)));
        if ($ids === []) {
            throw ValidationException::withMessages(['tickets' => ['Select at least one ticket type.']]);
        }

        return DB::transaction(function () use ($ids, $organizerId) {
            $tickets = Ticket::query()->whereIn('id', $ids)->lockForUpdate()->get()->keyBy('id');
            if ($tickets->count() !== count($ids)) {
                throw (new ModelNotFoundException())->setModel(Ticket::class, $ids);
            }

            $eventIds = $tickets->pluck('event_id')->map(fn ($id) => (int) $id)->unique()->values();
            $events = Event::query()->whereIn('id', $eventIds)->lockForUpdate()->get()->keyBy('id');
            if ($events->count() !== $eventIds->count()) {
                throw (new ModelNotFoundException())->setModel(Event::class, $eventIds->all());
            }

            if ($organizerId !== null) {
                foreach ($events as $event) {
                    if ((int) $event->organizer_id !== $organizerId) {
                        throw ValidationException::withMessages([
                            'tickets' => ['One or more selected ticket types do not belong to this organizer.'],
                        ]);
                    }
                }
            }

            if (TicketReservation::query()->whereIn('ticket_id', $ids)->where('status', 'active')->exists()) {
                throw ValidationException::withMessages([
                    'tickets' => ['A selected ticket type has an active checkout reservation. Wait for it to complete or expire before retiring it.'],
                ]);
            }

            foreach ($ids as $id) {
                /** @var Ticket $ticket */
                $ticket = $tickets->get($id);
                AuditLogger::record('ticket.retired', $ticket, [
                    'event_id' => (int) $ticket->event_id,
                    'pricing_type' => $ticket->pricing_type,
                    'price' => $ticket->price,
                ], ['deleted_at' => now()->toIso8601String()], [
                    'content_and_booking_history_preserved' => true,
                ]);
                $ticket->delete();
            }

            return count($ids);
        }, 3);
    }
}
