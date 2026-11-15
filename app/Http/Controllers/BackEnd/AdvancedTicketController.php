<?php

namespace App\Http\Controllers\BackEnd;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Event\BookingTicket;
use App\Models\Event\EventContent;
use App\Models\Event\Ticket;
use App\Models\Event\TicketContent;
use App\Models\Organizer;
use App\Models\OrganizerInfo;
use App\Models\TicketNumberSequence;
use App\Services\AuditLogger;
use App\Services\ComplimentaryTicketService;
use App\Services\TicketLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdvancedTicketController extends Controller
{
    public function index(Request $request)
    {
        $schemaReady = Schema::hasTable('booking_tickets')
            && Schema::hasColumn('booking_tickets', 'ticket_number')
            && Schema::hasColumn('tickets', 'seating_mode')
            && Schema::hasTable('ticket_number_sequences')
            && Schema::hasTable('event_seats')
            && Schema::hasTable('seat_reservations');
        $filters = $this->filters($request);
        if (! $schemaReady) {
            return view('backend.event.advanced-tickets', compact('schemaReady', 'filters') + [
                'units' => collect(), 'events' => collect(), 'ticketTypes' => collect(),
                'organizers' => collect(), 'eventNames' => collect(), 'ticketNames' => collect(),
                'organizerNames' => collect(), 'summary' => [], 'categoryRows' => collect(),
            ]);
        }

        $base = $this->query($filters);
        $units = (clone $base)->with('booking')->latest('booking_tickets.id')->paginate(40)->withQueryString();
        $eventIds = $units->getCollection()->pluck('event_id')->filter()->unique();
        $ticketIds = $units->getCollection()->pluck('ticket_id')->filter()->unique();
        $organizerIds = $units->getCollection()->pluck('organizer_id')->filter()->unique();
        $eventNames = EventContent::query()->whereIn('event_id', $eventIds)->orderBy('language_id')->get()
            ->unique('event_id')->pluck('title', 'event_id');
        $ticketNames = TicketContent::query()->whereIn('ticket_id', $ticketIds)->orderBy('language_id')->get()
            ->unique('ticket_id')->pluck('title', 'ticket_id');
        $organizerNames = OrganizerInfo::query()->whereIn('organizer_id', $organizerIds)->orderBy('language_id')->get()
            ->unique('organizer_id')->pluck('name', 'organizer_id');

        $events = EventContent::query()->join('events', 'events.id', '=', 'event_contents.event_id')
            ->orderByDesc('events.id')->get(['events.id', 'event_contents.title'])->unique('id')->values();
        $eventNames = $events->pluck('title', 'id')->union($eventNames);
        $ticketTypes = Ticket::query()->orderByDesc('event_id')->orderBy('id')->get([
            'id', 'event_id', 'title', 'seating_mode', 'ticket_available_type', 'ticket_available',
            'numbering_enabled', 'numbering_scope', 'number_prefix', 'sales_status',
        ]);
        $ticketTypeNames = TicketContent::query()->whereIn('ticket_id', $ticketTypes->pluck('id'))->orderBy('language_id')->get()
            ->unique('ticket_id')->pluck('title', 'ticket_id');
        $ticketTypes->each(fn (Ticket $ticket) => $ticket->setAttribute('display_name', $ticketTypeNames[$ticket->id] ?? $ticket->title ?? 'Ticket'));
        $organizers = Organizer::query()->where('status', 1)->orderBy('username')->get(['id', 'username', 'email']);
        $organizerDisplayNames = OrganizerInfo::query()->whereIn('organizer_id', $organizers->pluck('id'))->orderBy('language_id')->get()
            ->unique('organizer_id')->pluck('name', 'organizer_id');
        $organizers->each(fn (Organizer $organizer) => $organizer->setAttribute('display_name', $organizerDisplayNames[$organizer->id] ?? $organizer->username));

        $soldByTicket = BookingTicket::query()->selectRaw('ticket_id, COUNT(*) as aggregate')
            ->whereIn('status', ['issued', 'checked_in'])
            ->whereIn('ticket_id', $ticketTypes->pluck('id'))
            ->groupBy('ticket_id')->pluck('aggregate', 'ticket_id');
        $sequences = TicketNumberSequence::query()->whereIn('event_id', $ticketTypes->pluck('event_id'))
            ->get()->keyBy(fn (TicketNumberSequence $sequence) => $sequence->event_id . '|' . $sequence->scope_key);
        $categoryRows = $ticketTypes->map(function (Ticket $ticket) use ($soldByTicket, $sequences, $eventNames) {
            $sold = (int) ($soldByTicket[$ticket->id] ?? 0);
            $limited = $ticket->ticket_available_type === 'limited';
            $scope = $ticket->numbering_scope === 'event' ? 'event' : 'ticket:' . $ticket->id;
            $sequence = $sequences->get($ticket->event_id . '|' . $scope);

            return [
                'event' => $eventNames[$ticket->event_id] ?? __('Event #:id', ['id' => $ticket->event_id]),
                'ticket' => $ticket->display_name,
                'inventory' => $limited ? (int) $ticket->ticket_available + $sold : null,
                'sold' => $sold,
                'available' => $limited ? (int) $ticket->ticket_available : null,
                'numbering' => $ticket->numbering_enabled
                    ? (($ticket->number_prefix ?: 'TKT') . ' · ' . ($sequence ? __('next :number', ['number' => $sequence->next_number]) : __('not started')))
                    : __('Off'),
                'seating' => $ticket->seating_mode === 'reserved' ? __('Reserved') : __('General admission'),
                'status' => $ticket->sales_status ?: 'on_sale',
            ];
        });

        $summary = [
            'issued' => (clone $base)->where('booking_tickets.status', 'issued')->count(),
            'checkedIn' => (clone $base)->whereNotNull('booking_tickets.checked_in_at')->count(),
            'seated' => (clone $base)->whereNotNull('booking_tickets.seat_id')->count(),
            'cancelled' => (clone $base)->whereIn('booking_tickets.status', ['revoked', 'refunded'])->count(),
            'numbered' => (clone $base)->whereNotNull('booking_tickets.ticket_number')->count(),
        ];

        return view('backend.event.advanced-tickets', compact(
            'schemaReady', 'filters', 'units', 'events', 'ticketTypes', 'organizers',
            'eventNames', 'ticketNames', 'organizerNames', 'summary', 'categoryRows'
        ));
    }

    public function issue(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'event_id' => ['required', 'integer', Rule::exists('events', 'id')],
            'ticket_id' => ['required', 'integer', Rule::exists('tickets', 'id')],
            'name' => ['required', 'string', 'min:2', 'max:200'],
            'email' => ['required', 'email:rfc', 'max:191'],
            'seat_id' => ['nullable', 'integer', Rule::exists('event_seats', 'id')],
        ]);
        $event = Event::query()->findOrFail((int) $validated['event_id']);
        $ticket = Ticket::query()->whereKey((int) $validated['ticket_id'])->where('event_id', $event->id)->firstOrFail();
        try {
            $booking = app(ComplimentaryTicketService::class)->issue(
                $event,
                $ticket,
                $validated['name'],
                $validated['email'],
                'Admin complimentary',
                isset($validated['seat_id']) ? (int) $validated['seat_id'] : null,
            );
        } catch (\RuntimeException $exception) {
            throw ValidationException::withMessages(['ticket_id' => $exception->getMessage()]);
        }
        AuditLogger::record('admin.complimentary_ticket_issued', $booking, [], [
            'event_id' => $event->id, 'ticket_id' => $ticket->id,
        ]);

        return back()->with('success', 'Complimentary ticket issued and queued for delivery.');
    }

    public function revoke(Request $request, BookingTicket $ticket, TicketLifecycleService $lifecycle): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $lifecycle->revokeForAdmin($ticket->id, (int) Auth::guard('admin')->id(), $validated['reason']);
        return back()->with('success', 'Ticket cancelled. Its QR code and ticket number are no longer valid.');
    }

    public function reissue(Request $request, BookingTicket $ticket, TicketLifecycleService $lifecycle): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $lifecycle->reissueForAdmin($ticket->id, (int) Auth::guard('admin')->id(), $validated['reason']);
        return back()->with('success', 'Ticket QR credential reissued. The previous QR code is invalid.');
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $filename = 'tica-ticket-assignments-' . now()->format('Y-m-d') . '.csv';
        return response()->streamDownload(function () use ($filters): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Ticket number', 'Order', 'Attendee', 'Email', 'Event ID', 'Ticket ID', 'Section', 'Row', 'Seat', 'Status']);
            $this->query($filters)->with('booking')->orderBy('booking_tickets.id')->chunkById(500, function ($rows) use ($out): void {
                foreach ($rows as $unit) {
                    $values = [$unit->ticket_number, 'TICA-' . str_pad((string) $unit->booking_id, 6, '0', STR_PAD_LEFT), $unit->holder_name, $unit->holder_email, $unit->event_id, $unit->ticket_id, $unit->section_label, $unit->row_label, $unit->seat_label, $unit->status];
                    fputcsv($out, array_map(fn ($value) => preg_match('/^[=+\-@]/', (string) $value) ? "'" . $value : $value, $values));
                }
            }, 'booking_tickets.id', 'id');
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }

    private function filters(Request $request): array
    {
        return $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'event' => ['nullable', 'integer', 'min:1'],
            'ticket' => ['nullable', 'integer', 'min:1'],
            'organizer' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', Rule::in(['issued', 'checked_in', 'revoked', 'refunded'])],
        ]);
    }

    private function query(array $filters)
    {
        $q = trim((string) ($filters['q'] ?? ''));
        return BookingTicket::query()->join('bookings', 'bookings.id', '=', 'booking_tickets.booking_id')
            ->when(! empty($filters['event']), fn ($query) => $query->where('booking_tickets.event_id', (int) $filters['event']))
            ->when(! empty($filters['ticket']), fn ($query) => $query->where('booking_tickets.ticket_id', (int) $filters['ticket']))
            ->when(! empty($filters['organizer']), fn ($query) => $query->where('booking_tickets.organizer_id', (int) $filters['organizer']))
            ->when(($filters['status'] ?? '') === 'checked_in', fn ($query) => $query->whereNotNull('booking_tickets.checked_in_at'))
            ->when(in_array($filters['status'] ?? '', ['issued', 'revoked', 'refunded'], true), fn ($query) => $query->where('booking_tickets.status', $filters['status']))
            ->when($q !== '', function ($query) use ($q): void {
                $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
                $query->where(function ($where) use ($like): void {
                    $where->where('booking_tickets.ticket_number', 'like', $like)
                        ->orWhere('booking_tickets.holder_name', 'like', $like)
                        ->orWhere('booking_tickets.holder_email', 'like', $like)
                        ->orWhere('booking_tickets.section_label', 'like', $like)
                        ->orWhere('booking_tickets.row_label', 'like', $like)
                        ->orWhere('booking_tickets.seat_label', 'like', $like);
                });
            })
            ->select('booking_tickets.*');
    }
}
