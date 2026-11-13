<?php

namespace App\Services\TicaUi;

use App\Models\Event\BookingTicket;
use App\Models\Event\EventContent;
use App\Models\Event\TicketContent;
use App\Models\PaymentIntent;
use App\Support\TicaCurrency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Bounded, organiser-scoped access to the orders and attendees directory.
 *
 * A stable keyset cursor keeps large workspaces responsive while new orders
 * arrive. Supporting data is resolved only for the rows in the current page;
 * no platform-wide ticket or payment-intent lists are loaded into memory.
 */
class OrganizerAttendeeDirectory
{
    public const PAGE_SIZE = 50;

    public const EXPORT_BATCH_SIZE = 500;

    /**
     * @return array{rows: array<int, array<string, mixed>>, more: bool, nextCursor: ?string}
     */
    public function page(
        int $organizerId,
        array $filters,
        int $limit = self::PAGE_SIZE,
        ?string $encodedCursor = null,
    ): array {
        $limit = max(1, min($limit, self::EXPORT_BATCH_SIZE));
        $cursor = $this->decodeCursor($encodedCursor);
        $query = $this->query($organizerId, $filters, $cursor);
        $tickets = $query->limit($limit + 1)->get();
        $more = $tickets->count() > $limit;
        $visible = $tickets->take($limit)->values();

        return [
            'rows' => $this->formatRows($visible),
            'more' => $more,
            'nextCursor' => $more && $visible->isNotEmpty()
                ? $this->encodeCursor($visible->last())
                : null,
        ];
    }

    /**
     * Iterate a complete export in bounded batches.
     *
     * @return \Generator<int, array<int, array<string, mixed>>>
     */
    public function batches(int $organizerId, array $filters): \Generator
    {
        $cursor = null;

        do {
            $page = $this->page($organizerId, $filters, static::EXPORT_BATCH_SIZE, $cursor);
            if ($page['rows'] !== []) {
                yield $page['rows'];
            }
            $cursor = $page['nextCursor'];
        } while ($cursor !== null);
    }

    private function query(int $organizerId, array $filters, ?array $cursor): Builder
    {
        $q = trim((string) ($filters['q'] ?? ''));
        $eventId = (int) ($filters['event'] ?? 0);
        $status = (string) ($filters['status'] ?? '');
        $ticketReference = $this->ticketReference($q);

        return BookingTicket::query()
            ->join('bookings', 'bookings.id', '=', 'booking_tickets.booking_id')
            ->where('bookings.organizer_id', $organizerId)
            ->when($eventId > 0, fn (Builder $builder) => $builder->where('booking_tickets.event_id', $eventId))
            ->when($q !== '', function (Builder $builder) use ($q, $ticketReference): void {
                $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
                $id = preg_match('/^#?\d+$/', $q) === 1
                    ? (int) ltrim($q, '#')
                    : 0;
                $builder->where(function (Builder $where) use ($like, $id, $ticketReference): void {
                    $where->where('booking_tickets.holder_name', 'like', $like)
                        ->orWhere('booking_tickets.holder_email', 'like', $like)
                        ->orWhere('bookings.email', 'like', $like)
                        ->orWhere('bookings.phone', 'like', $like)
                        ->orWhere('bookings.fname', 'like', $like)
                        ->orWhere('bookings.lname', 'like', $like)
                        ->orWhereRaw("CONCAT(bookings.fname, ' ', bookings.lname) LIKE ?", [$like]);
                    if ($id > 0) {
                        $where->orWhere('bookings.id', $id);
                    }
                    if ($ticketReference !== null) {
                        $where->orWhere(function (Builder $reference) use ($ticketReference): void {
                            $reference->where('bookings.id', $ticketReference['booking']);
                            if ($ticketReference['sequence'] !== null) {
                                $reference->where('booking_tickets.sequence', $ticketReference['sequence']);
                            }
                        });
                    }
                });
            })
            ->when($status === 'checked_in', fn (Builder $builder) => $builder->whereNotNull('booking_tickets.checked_in_at'))
            ->when($status === 'refunded', fn (Builder $builder) => $builder->where(fn (Builder $where) => $where
                ->whereIn('booking_tickets.status', ['refunded', 'revoked'])
                ->orWhere('bookings.paymentStatus', 'refunded')))
            ->when($status === 'guest', fn (Builder $builder) => $builder
                ->where('bookings.paymentStatus', 'free')
                ->where('bookings.paymentMethod', 'Guest'))
            ->when($status === 'paid', fn (Builder $builder) => $builder
                ->where('bookings.paymentStatus', 'completed')
                ->whereNull('booking_tickets.checked_in_at')
                ->where('booking_tickets.status', 'issued'))
            ->when($cursor !== null, function (Builder $builder) use ($cursor): void {
                $builder->where(function (Builder $where) use ($cursor): void {
                    $where->where('bookings.id', '<', $cursor['booking'])
                        ->orWhere(function (Builder $sameBooking) use ($cursor): void {
                            $sameBooking->where('bookings.id', $cursor['booking'])
                                ->where(function (Builder $position) use ($cursor): void {
                                    $position->where('booking_tickets.sequence', '>', $cursor['sequence'])
                                        ->orWhere(function (Builder $sameSequence) use ($cursor): void {
                                            $sameSequence->where('booking_tickets.sequence', $cursor['sequence'])
                                                ->where('booking_tickets.id', '>', $cursor['ticket']);
                                        });
                                });
                        });
                });
            })
            ->orderByDesc('bookings.id')
            ->orderBy('booking_tickets.sequence')
            ->orderBy('booking_tickets.id')
            ->select(
                'booking_tickets.*',
                'bookings.fname',
                'bookings.lname',
                'bookings.email as buyer_email',
                'bookings.paymentStatus',
                'bookings.paymentMethod',
                'bookings.price as order_price',
                'bookings.tax as order_tax',
                'bookings.quantity as order_quantity',
                'bookings.refund_status',
                'bookings.currencyText as order_currency',
            );
    }

    /** @return array{booking: int, sequence: ?int}|null */
    private function ticketReference(string $query): ?array
    {
        if (preg_match('/^#?TICA-(\d{1,20})(?:-(\d{1,10}))?$/i', $query, $matches) !== 1) {
            return null;
        }

        $booking = (int) $matches[1];
        $sequence = isset($matches[2]) ? (int) $matches[2] : null;

        if ($booking < 1 || ($sequence !== null && $sequence < 1)) {
            return null;
        }

        return ['booking' => $booking, 'sequence' => $sequence];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function formatRows(Collection $tickets): array
    {
        if ($tickets->isEmpty()) {
            return [];
        }

        $eventIds = $tickets->pluck('event_id')->filter()->unique()->values();
        $ticketIds = $tickets->pluck('ticket_id')->filter()->unique()->values();
        $bookingIds = $tickets->pluck('booking_id')->filter()->unique()->values();

        $titles = EventContent::query()
            ->whereIn('event_id', $eventIds)
            ->orderBy('language_id')
            ->get(['event_id', 'title'])
            ->unique('event_id')
            ->pluck('title', 'event_id');
        $ticketTitles = TicketContent::query()
            ->whereIn('ticket_id', $ticketIds)
            ->orderBy('language_id')
            ->get(['ticket_id', 'title'])
            ->unique('ticket_id')
            ->pluck('title', 'ticket_id');
        $refundable = PaymentIntent::query()
            ->whereIn('booking_id', $bookingIds)
            ->where('status', 'completed')
            ->pluck('id', 'booking_id');

        return $tickets->map(function ($ticket) use ($titles, $ticketTitles, $refundable): array {
            $quantity = max(1, (int) $ticket->order_quantity);
            $orderTotal = round((float) $ticket->order_price + (float) $ticket->order_tax, 2);
            $statusLabel = match (true) {
                in_array($ticket->status, ['refunded', 'revoked'], true) || $ticket->paymentStatus === 'refunded' => 'Refunded',
                in_array((string) $ticket->refund_status, ['approval_pending', 'pending', 'processing'], true) => 'Refund requested',
                $ticket->checked_in_at !== null => 'Checked in',
                $ticket->paymentStatus === 'free' && $ticket->paymentMethod === 'Guest' => 'Guest',
                $ticket->paymentStatus === 'free' => 'Free',
                default => 'Paid',
            };

            return [
                'bookingId' => (int) $ticket->booking_id,
                'name' => $ticket->holder_name ?: trim($ticket->fname . ' ' . $ticket->lname),
                'email' => $ticket->holder_email ?: $ticket->buyer_email,
                'buyerEmail' => (string) $ticket->buyer_email,
                'tier' => $ticket->variation_name ?: ($ticketTitles[$ticket->ticket_id] ?? __('General admission')),
                'event' => (string) ($titles[$ticket->event_id] ?? ''),
                'ref' => 'TICA-' . str_pad((string) $ticket->booking_id, 6, '0', STR_PAD_LEFT) . '-' . $ticket->sequence,
                'amount' => round($orderTotal / $quantity, 2),
                'orderTotal' => $orderTotal,
                'orderTickets' => round((float) $ticket->order_price, 2),
                'cur' => TicaCurrency::isBase($ticket->order_currency) ? null : [
                    'code' => TicaCurrency::normalize($ticket->order_currency),
                    'symbol' => TicaCurrency::symbol($ticket->order_currency),
                    'position' => 'left',
                ],
                'status' => $statusLabel,
                'answer' => (string) ($ticket->attendee_answer ?? ''),
                'refundable' => $statusLabel === 'Paid' && isset($refundable[$ticket->booking_id]),
            ];
        })->values()->all();
    }

    private function encodeCursor(object $ticket): string
    {
        $json = json_encode([
            'b' => (int) $ticket->booking_id,
            's' => (int) $ticket->sequence,
            't' => (int) $ticket->id,
        ], JSON_THROW_ON_ERROR);

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    /** @return array{booking: int, sequence: int, ticket: int}|null */
    private function decodeCursor(?string $encoded): ?array
    {
        if ($encoded === null || $encoded === '') {
            return null;
        }

        $decoded = base64_decode(strtr($encoded, '-_', '+/'), true);
        $value = $decoded === false ? null : json_decode($decoded, true);
        if (! is_array($value)
            || ! isset($value['b'], $value['s'], $value['t'])
            || ! is_numeric($value['b'])
            || ! is_numeric($value['s'])
            || ! is_numeric($value['t'])
            || (int) $value['b'] < 1
            || (int) $value['s'] < 1
            || (int) $value['t'] < 1) {
            throw ValidationException::withMessages(['cursor' => 'The attendee page has expired. Refresh and try again.']);
        }

        return [
            'booking' => (int) $value['b'],
            'sequence' => (int) $value['s'],
            'ticket' => (int) $value['t'],
        ];
    }
}
