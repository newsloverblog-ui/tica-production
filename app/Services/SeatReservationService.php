<?php

namespace App\Services;

use App\Models\Event\Booking;
use App\Models\Event\BookingTicket;
use App\Models\Event\Ticket;
use App\Models\EventSeat;
use App\Models\SeatReservation;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class SeatReservationService
{
    public function reserve(
        string $token,
        int $eventId,
        array $seatIds,
        ?string $sessionId,
        ?int $customerId,
        ?string $buyerEmailHash = null,
    ): Collection {
        if (!Schema::hasTable('seat_reservations')) return collect();
        $seatIds = collect($seatIds)->map('intval')->filter()->unique()->sort()->values();
        if ($seatIds->isEmpty()) return collect();

        return DB::transaction(function () use ($token, $eventId, $seatIds, $sessionId, $customerId, $buyerEmailHash) {
            $this->releaseExpiredForSeats($seatIds->all());
            $seats = EventSeat::query()
                ->with(['section', 'row'])
                ->where('event_id', $eventId)
                ->whereIn('id', $seatIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            if ($seats->count() !== $seatIds->count()) {
                throw new RuntimeException('One or more selected seats no longer exist.');
            }

            foreach ($seats as $seat) {
                if ($seat->status !== 'available') {
                    throw new RuntimeException("Seat {$seat->label} is unavailable.");
                }
                $ticket = Ticket::query()->whereKey($seat->ticket_id ?: $seat->section?->ticket_id)->first();
                if (!$ticket || $ticket->seating_mode !== 'reserved') {
                    throw new RuntimeException("Seat {$seat->label} is not attached to a reserved ticket category.");
                }
                $hold = SeatReservation::query()
                    ->where('seat_id', $seat->id)
                    ->whereIn('status', ['active', 'pending_payment', 'consumed'])
                    ->latest('id')
                    ->lockForUpdate()
                    ->first();
                if ($hold && $hold->status === 'active' && $hold->expires_at->isFuture() && $hold->token !== $token) {
                    throw new RuntimeException("Seat {$seat->label} was just selected by another buyer.");
                }
                if ($hold && $hold->status === 'pending_payment' && $hold->token !== $token) {
                    throw new RuntimeException("Seat {$seat->label} is awaiting payment review for another buyer.");
                }
                if ($hold && $hold->status === 'consumed' && $hold->token !== $token) {
                    throw new RuntimeException("Seat {$seat->label} has already been sold.");
                }

                $attributes = [
                    'token' => $token,
                    'event_id' => $eventId,
                    'ticket_id' => $ticket->id,
                    'status' => 'active',
                    'session_hash' => $sessionId ? hash('sha256', $sessionId) : null,
                    'customer_id' => $customerId,
                    'buyer_email_hash' => $customerId ? null : $buyerEmailHash,
                    'booking_id' => null,
                    'booking_ticket_id' => null,
                    'expires_at' => now()->addMinutes($this->minutes($eventId)),
                    'consumed_at' => null,
                    'released_at' => null,
                ];
                $sameHold = $hold && $hold->token === $token && $hold->status === 'active';
                if ($sameHold) $hold->forceFill($attributes)->save();
                else SeatReservation::query()->create(['seat_id' => $seat->id] + $attributes);
            }

            return SeatReservation::query()->where('token', $token)->where('status', 'active')->with('seat')->get();
        }, 3);
    }

    public function assign(string $token, Booking $booking, Collection $units): void
    {
        if (!Schema::hasTable('seat_reservations') || !Schema::hasColumn('booking_tickets', 'seat_id')) return;
        $holds = SeatReservation::query()->where('token', $token)->orderBy('ticket_id')->orderBy('seat_id')->lockForUpdate()->get();
        if ($holds->isEmpty()) return;
        if ($holds->count() !== $units->count()) {
            throw new RuntimeException('The number of reserved seats does not match the purchased ticket quantity.');
        }

        $available = $units->groupBy(fn (BookingTicket $unit) => (int) $unit->ticket_id);
        foreach ($holds as $hold) {
            if ($hold->status === 'consumed' && $hold->booking_id === $booking->id) continue;
            if (!in_array($hold->status, ['active', 'expired', 'pending_payment'], true) || (int) $hold->event_id !== (int) $booking->event_id) {
                throw new RuntimeException('A selected seat is no longer reserved for this checkout.');
            }
            $customerMatches = $hold->customer_id !== null
                && $booking->customer_id !== null
                && (int) $hold->customer_id === (int) $booking->customer_id;
            $guestMatches = $hold->customer_id === null
                && $booking->customer_id === null
                && $hold->buyer_email_hash
                && $booking->buyer_email_hash
                && hash_equals((string) $hold->buyer_email_hash, (string) $booking->buyer_email_hash);
            if (!$customerMatches && !$guestMatches) {
                throw new RuntimeException('The selected seats do not belong to this buyer.');
            }
            /** @var BookingTicket|null $unit */
            $unit = $available->get((int) $hold->ticket_id)?->shift();
            if (!$unit) throw new RuntimeException('A selected seat does not match the purchased ticket category.');
            $seat = EventSeat::query()->with(['section', 'row'])->whereKey($hold->seat_id)->lockForUpdate()->firstOrFail();
            if ($seat->status !== 'available') throw new RuntimeException("Seat {$seat->label} is no longer available.");
            $competingHold = SeatReservation::query()
                ->where('seat_id', $seat->id)
                ->where('status', 'active')
                ->where('expires_at', '>', now())
                ->where('token', '!=', $token)
                ->lockForUpdate()
                ->exists();
            if ($competingHold) throw new RuntimeException("Seat {$seat->label} is reserved by another buyer.");
            $unit->forceFill([
                'seat_id' => $seat->id,
                'seat_label' => $seat->label,
                'row_label' => $seat->row?->label,
                'section_label' => $seat->section?->name,
            ])->save();
            $seat->forceFill(['status' => 'sold'])->save();
            $hold->forceFill([
                'status' => 'consumed', 'booking_id' => $booking->id,
                'booking_ticket_id' => $unit->id, 'consumed_at' => now(), 'released_at' => null,
            ])->save();
        }
    }

    /** Preserve a selected seat while an offline payment awaits review. */
    public function bindPendingPayment(string $token, Booking $booking): int
    {
        if (!Schema::hasTable('seat_reservations')) return 0;

        return DB::transaction(function () use ($token, $booking): int {
            $holds = SeatReservation::query()
                ->where('token', $token)
                ->where('event_id', $booking->event_id)
                ->orderBy('seat_id')
                ->lockForUpdate()
                ->get();
            if ($holds->isEmpty()) return 0;
            if ($holds->contains(fn (SeatReservation $hold): bool => $hold->status !== 'active')) {
                throw new RuntimeException('A selected seat is no longer available for payment review.');
            }

            SeatReservation::query()->whereIn('id', $holds->pluck('id'))->update([
                'status' => 'pending_payment',
                'booking_id' => $booking->id,
                'released_at' => null,
                'updated_at' => now(),
            ]);

            return $holds->count();
        }, 3);
    }

    public function replaceToken(
        string $oldToken,
        string $newToken,
        int $eventId,
        ?string $sessionId,
        ?int $customerId,
        ?string $buyerEmailHash,
        CarbonInterface $expiresAt,
    ): int {
        if (!Schema::hasTable('seat_reservations')) return 0;

        $holds = SeatReservation::query()
            ->where('token', $oldToken)
            ->where('event_id', $eventId)
            ->where('status', 'active')
            ->orderBy('seat_id')
            ->lockForUpdate()
            ->get();
        if ($holds->isEmpty()) return 0;

        $sessionHash = $sessionId ? hash('sha256', $sessionId) : null;
        foreach ($holds as $hold) {
            $sessionMatches = $hold->session_hash && $sessionHash
                && hash_equals((string) $hold->session_hash, $sessionHash);
            $customerMatches = $hold->customer_id !== null && $customerId !== null
                && (int) $hold->customer_id === $customerId;
            $guestMatches = $hold->customer_id === null && $customerId === null
                && $hold->buyer_email_hash && $buyerEmailHash
                && hash_equals((string) $hold->buyer_email_hash, $buyerEmailHash);
            if (!$sessionMatches && !$customerMatches && !$guestMatches) {
                throw new RuntimeException('The seat reservation does not belong to this checkout.');
            }
            $hold->forceFill([
                'token' => $newToken,
                'session_hash' => $sessionHash,
                'customer_id' => $customerId,
                'buyer_email_hash' => $customerId ? null : $buyerEmailHash,
                'expires_at' => $expiresAt,
            ])->save();
        }

        return $holds->count();
    }

    public function release(string $token, string $status = 'released'): int
    {
        if (!Schema::hasTable('seat_reservations')) return 0;
        return SeatReservation::query()->where('token', $token)->whereIn('status', ['active', 'pending_payment'])->update([
            'status' => $status, 'released_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function releaseExpired(int $limit = 500): int
    {
        if (!Schema::hasTable('seat_reservations')) return 0;
        return SeatReservation::query()->where('status', 'active')->where('expires_at', '<=', now())->limit($limit)->update([
            'status' => 'expired', 'released_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function releaseExpiredForSeats(array $seatIds): void
    {
        SeatReservation::query()->whereIn('seat_id', $seatIds)->where('status', 'active')->where('expires_at', '<=', now())->update([
            'status' => 'expired', 'released_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function minutes(int $eventId): int
    {
        $minutes = Schema::hasColumn('events', 'seat_reservation_minutes')
            ? (int) DB::table('events')->where('id', $eventId)->value('seat_reservation_minutes')
            : 15;
        return min(30, max(5, $minutes ?: 15));
    }
}
