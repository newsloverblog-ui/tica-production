<?php

namespace App\Services;

use App\Models\Event\Booking;
use App\Models\Event\Ticket;
use App\Models\TicketReservation;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class EventTicketPurchaseLimitService
{
    /**
     * Enforce the current catalogue limit for one ticket identity. A null
     * customer still receives the per-checkout limit; authenticated customers
     * are also checked against their existing bookings and active holds.
     */
    public function assertCanSelect(
        Ticket $ticket,
        ?string $variationName,
        int $selectedQuantity,
        ?int $customerId,
        bool $includeActiveReservations = false,
        ?string $buyerEmailHash = null,
    ): void {
        if ($selectedQuantity < 1) {
            throw new RuntimeException('The ticket selection contains an invalid quantity.');
        }

        $limit = $this->limit($ticket, $variationName);
        if ($limit === null) {
            return;
        }

        $buyerEmailHash = $customerId ? null : $this->validHashOrNull($buyerEmailHash);
        $previousQuantity = ($customerId || $buyerEmailHash)
            ? $this->bookedQuantity(
                (int) $ticket->event_id,
                (int) $ticket->id,
                $variationName,
                $customerId,
                $buyerEmailHash,
            )
            : 0;
        if ($includeActiveReservations && ($customerId || $buyerEmailHash)) {
            $previousQuantity += $this->activeReservationQuantity(
                (int) $ticket->event_id,
                (int) $ticket->id,
                $variationName,
                $customerId,
                $buyerEmailHash,
            );
        }

        if ($selectedQuantity > $limit || $previousQuantity > $limit - $selectedQuantity) {
            throw new RuntimeException('The ticket purchase limit has changed. Please review your selection.');
        }
    }

    public function buyerIdentityHash(?string $email): ?string
    {
        $email = mb_strtolower(trim((string) $email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return hash_hmac('sha256', $email, $this->identityHashKey());
    }

    public function buyerIdentityHashVersion(): int
    {
        return max(1, (int) config('tickets.buyer_identity_hash_version', 1));
    }

    public function limit(Ticket $ticket, ?string $variationName): ?int
    {
        if ($ticket->pricing_type !== 'variation') {
            return $ticket->max_ticket_buy_type === 'limited'
                ? max(0, (int) $ticket->max_buy_ticket)
                : null;
        }

        $name = trim((string) $variationName);
        $variations = json_decode((string) $ticket->variations, true);
        if ($name === '' || !is_array($variations)) {
            throw new RuntimeException('The selected ticket option is no longer available.');
        }

        $matches = array_values(array_filter(
            $variations,
            static fn ($variation): bool => is_array($variation)
                && hash_equals((string) ($variation['name'] ?? ''), $name)
        ));
        if (count($matches) !== 1) {
            throw new RuntimeException('The selected ticket option is ambiguous or unavailable.');
        }

        $variation = $matches[0];

        return ($variation['max_ticket_buy_type'] ?? null) === 'limited'
            ? max(0, (int) ($variation['v_max_ticket_buy'] ?? 0))
            : null;
    }

    private function bookedQuantity(
        int $eventId,
        int $ticketId,
        ?string $variationName,
        ?int $customerId,
        ?string $buyerEmailHash,
    ): int {
        if (!Schema::hasTable('bookings')) {
            return 0;
        }
        if (!$customerId && !Schema::hasColumn('bookings', 'buyer_email_hash')) {
            return 0;
        }

        $quantity = 0;
        $bookings = Booking::query()
            ->when(
                $customerId,
                fn ($query) => $query->where('customer_id', $customerId),
                fn ($query) => $query->where('buyer_email_hash', $buyerEmailHash),
            )
            ->where('event_id', $eventId)
            ->where('paymentStatus', '!=', 'rejected')
            ->get();

        foreach ($bookings as $booking) {
            $selections = json_decode((string) $booking->variation, true);
            if (is_array($selections) && $selections !== []) {
                foreach ($selections as $selection) {
                    if (!is_array($selection) || (int) ($selection['ticket_id'] ?? 0) !== $ticketId) {
                        continue;
                    }
                    if ($variationName !== null
                        && !hash_equals($variationName, (string) ($selection['name'] ?? ''))) {
                        continue;
                    }
                    $quantity += max(0, (int) ($selection['qty'] ?? 0));
                }
                continue;
            }

            // Compatibility for historical single-ticket online bookings that
            // predate the per-selection JSON snapshot.
            if ($variationName === null && (int) $booking->ticket_id === $ticketId) {
                $quantity += max(0, (int) $booking->quantity);
            }
        }

        return $quantity;
    }

    private function activeReservationQuantity(
        int $eventId,
        int $ticketId,
        ?string $variationName,
        ?int $customerId,
        ?string $buyerEmailHash,
    ): int {
        if (!Schema::hasTable('ticket_reservations')) {
            return 0;
        }
        if (!$customerId && !Schema::hasColumn('ticket_reservations', 'buyer_email_hash')) {
            return 0;
        }

        return (int) TicketReservation::query()
            ->when(
                $customerId,
                fn ($query) => $query->where('customer_id', $customerId),
                fn ($query) => $query->where('buyer_email_hash', $buyerEmailHash),
            )
            ->where('event_id', $eventId)
            ->where('ticket_id', $ticketId)
            ->where('status', 'active')
            ->when(
                $variationName === null,
                fn ($query) => $query->whereNull('variation_name'),
                fn ($query) => $query->where('variation_name', $variationName),
            )
            ->sum('quantity');
    }

    private function validHashOrNull(?string $hash): ?string
    {
        $hash = strtolower(trim((string) $hash));

        return preg_match('/\A[a-f0-9]{64}\z/', $hash) === 1 ? $hash : null;
    }

    private function identityHashKey(): string
    {
        return (string) (config('tickets.buyer_identity_hash_key') ?: config('app.key'));
    }
}
