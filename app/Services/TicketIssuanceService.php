<?php

namespace App\Services;

use App\Models\Event\Booking;
use App\Models\Event\BookingTicket;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class TicketIssuanceService
{
    public function issueForBooking(Booking $booking, ?string $reservationToken = null): Collection
    {
        return DB::transaction(function () use ($booking, $reservationToken) {
            $booking = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            if (!in_array(strtolower((string) $booking->paymentStatus), ['1', 'paid', 'completed', 'free'], true)) {
                throw new RuntimeException('Individual tickets cannot be issued before payment is verified.');
            }
            $existing = BookingTicket::query()
                ->where('booking_id', $booking->id)
                ->orderBy('sequence')
                ->get();

            if ($existing->isNotEmpty()) {
                $issues = $this->integrityIssues($booking, $existing);
                if ($issues !== []) {
                    throw new RuntimeException(
                        'Existing booking ticket units are structurally inconsistent: '
                        .implode(', ', $issues)
                        .'. Manual review is required; no ticket units were changed.'
                    );
                }

                if ($reservationToken) {
                    app(SeatReservationService::class)->assign($reservationToken, $booking, $existing);
                }

                return $existing->fresh();
            }

            $allocations = $this->expectedAllocations($booking);
            $expectedQuantity = (int) $booking->quantity;
            if ($expectedQuantity < 1 || count($allocations) !== $expectedQuantity) {
                throw new RuntimeException('Booking ticket quantity does not match its ticket allocation.');
            }
            $ticketIds = collect($allocations)->pluck('ticket_id')->filter()->unique();
            $reservedTicketIds = $ticketIds->isEmpty()
                || !Schema::hasTable('tickets')
                || !Schema::hasColumn('tickets', 'seating_mode')
                ? collect()
                : \App\Models\Event\Ticket::query()
                    ->whereIn('id', $ticketIds)
                    ->where('seating_mode', 'reserved')
                    ->pluck('id');
            if ($reservedTicketIds->isNotEmpty() && ! $reservationToken) {
                throw new RuntimeException('Reserved-seat tickets require a valid seat selection before they can be issued.');
            }

            $initialStatus = (int) $booking->scan_status === 1 ? 'checked_in' : 'issued';
            $issued = collect();
            foreach ($allocations as $index => $allocation) {
                $unit = BookingTicket::create([
                    'public_id' => (string) Str::uuid(),
                    'booking_id' => $booking->id,
                    'event_id' => $booking->event_id,
                    'organizer_id' => $booking->organizer_id,
                    'ticket_id' => $allocation['ticket_id'],
                    'sequence' => $index + 1,
                    'variation_name' => $allocation['variation_name'],
                    'holder_name' => trim((string) $booking->fname . ' ' . (string) $booking->lname),
                    'holder_email' => $booking->email,
                    'status' => $initialStatus,
                    'checked_in_at' => $initialStatus === 'checked_in' ? ($booking->updated_at ?: now()) : null,
                ]);
                if (Schema::hasTable('tickets') && $unit->ticket_id) {
                    app(TicketNumberAllocator::class)->allocate($unit);
                }
                $issued->push($unit->fresh());
            }

            if ($reservationToken) {
                app(SeatReservationService::class)->assign($reservationToken, $booking, $issued);
                $issued = BookingTicket::query()->where('booking_id', $booking->id)->orderBy('sequence')->get();
            }

            AuditLogger::record('booking.tickets_issued', $booking, [], [
                'ticket_count' => $issued->count(),
                'initial_status' => $initialStatus,
            ]);

            return $issued;
        }, 3);
    }

    /**
     * Return immutable ticket/allocation snapshots in their expected unit order.
     *
     * @return array<int, array{ticket_id: int|null, variation_name: string|null}>
     */
    /** People covered by a ticket selection: tickets × people each ticket admits. */
    public static function peopleFor(?array $selections, int $fallback): int
    {
        if (!is_array($selections) || $selections === []) {
            return $fallback;
        }
        $admits = \App\Models\Event\Ticket::admitsFor(array_column($selections, 'ticket_id'));
        $people = 0;
        foreach ($selections as $selection) {
            $people += max(0, (int) ($selection['qty'] ?? 0)) * ($admits[(int) ($selection['ticket_id'] ?? 0)] ?? 1);
        }

        return $people > 0 ? $people : $fallback;
    }

    public function expectedAllocations(Booking $booking): array
    {
        $selections = json_decode((string) $booking->variation, true);
        $allocations = [];

        if (is_array($selections) && $selections !== []) {
            // A group ticket (a couple pass, a table for 10) issues one pass per person.
            $admits = \App\Models\Event\Ticket::admitsFor(array_column($selections, 'ticket_id'));
            foreach ($selections as $selection) {
                $quantity = (int) ($selection['qty'] ?? 0);
                if ($quantity < 1) {
                    throw new RuntimeException('Booking contains an invalid ticket allocation.');
                }
                $quantity *= $admits[(int) ($selection['ticket_id'] ?? 0)] ?? 1;

                for ($i = 0; $i < $quantity; $i++) {
                    $allocations[] = [
                        'ticket_id' => isset($selection['ticket_id']) ? (int) $selection['ticket_id'] : null,
                        'variation_name' => isset($selection['name']) ? (string) $selection['name'] : null,
                    ];
                }
            }

            return $allocations;
        }

        for ($i = 0; $i < (int) $booking->quantity; $i++) {
            $allocations[] = [
                'ticket_id' => $booking->ticket_id ? (int) $booking->ticket_id : null,
                'variation_name' => null,
            ];
        }

        return $allocations;
    }

    /**
     * Inspect an existing unit set without mutating it. Historical holder,
     * transfer, status and check-in fields are deliberately excluded: those
     * values can legitimately change after issue. The admission identity and
     * sequence cannot.
     *
     * @param  Collection<int, BookingTicket>  $units
     * @return array<int, string>
     */
    public function integrityIssues(Booking $booking, Collection $units): array
    {
        $issues = [];
        $quantity = (int) $booking->quantity;
        if ($quantity < 1) {
            $issues[] = 'invalid_quantity';
        }

        try {
            $allocations = $this->expectedAllocations($booking);
        } catch (RuntimeException) {
            $allocations = [];
            $issues[] = 'invalid_allocation';
        }

        if ($quantity >= 1 && count($allocations) !== $quantity) {
            $issues[] = 'allocation_quantity_mismatch';
        }

        if ($units->isEmpty()) {
            $issues[] = 'missing_units';

            return array_values(array_unique($issues));
        }

        if ($units->count() !== $quantity) {
            $issues[] = 'unit_count_mismatch';
        }

        $expectedSequences = $quantity >= 1 ? range(1, $quantity) : [];
        $actualSequences = $units->pluck('sequence')
            ->map(static fn ($sequence): int => (int) $sequence)
            ->values()
            ->all();
        if ($actualSequences !== $expectedSequences) {
            $issues[] = 'sequence_mismatch';
        }

        if ($units->contains(static fn (BookingTicket $unit): bool =>
            (int) $unit->event_id !== (int) $booking->event_id
        )) {
            $issues[] = 'event_identity_mismatch';
        }

        $bookingOrganizerId = $booking->organizer_id === null ? null : (int) $booking->organizer_id;
        if ($units->contains(static function (BookingTicket $unit) use ($bookingOrganizerId): bool {
            $unitOrganizerId = $unit->organizer_id === null ? null : (int) $unit->organizer_id;

            return $unitOrganizerId !== $bookingOrganizerId;
        })) {
            $issues[] = 'organizer_identity_mismatch';
        }

        if (
            $quantity >= 1
            && count($allocations) === $quantity
            && $units->count() === $quantity
            && $actualSequences === $expectedSequences
        ) {
            foreach ($units->values() as $index => $unit) {
                $expected = $allocations[$index];
                $unitTicketId = $unit->ticket_id === null ? null : (int) $unit->ticket_id;
                $expectedVariation = $expected['variation_name'] === null
                    ? null
                    : (string) $expected['variation_name'];
                $unitVariation = $unit->variation_name === null
                    ? null
                    : (string) $unit->variation_name;

                if ($unitTicketId !== $expected['ticket_id'] || $unitVariation !== $expectedVariation) {
                    $issues[] = 'allocation_identity_mismatch';
                    break;
                }
            }
        }

        return array_values(array_unique($issues));
    }
}
