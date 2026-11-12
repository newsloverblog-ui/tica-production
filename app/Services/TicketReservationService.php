<?php

namespace App\Services;

use App\Models\Event\Ticket;
use App\Models\Event;
use App\Models\TicketReservation;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class TicketReservationService
{
    public function replace(
        int $eventId,
        array $selections,
        ?string $existingToken,
        ?string $sessionId,
        ?int $customerId,
        ?string $buyerEmailHash = null,
    ): string {
        if (!$existingToken) {
            return $this->reserve(
                $eventId,
                $selections,
                $sessionId,
                $customerId,
                buyerEmailHash: $buyerEmailHash,
            );
        }
        if (!$selections) {
            throw new RuntimeException('Select at least one ticket.');
        }

        usort($selections, fn ($a, $b) => ((int) $a['ticket_id']) <=> ((int) $b['ticket_id']));
        $token = (string) Str::uuid();
        $expiresAt = now()->addMinutes(max(1, (int) config('tickets.reservation_minutes', 15)));

        DB::transaction(function () use (
            $eventId,
            $selections,
            $existingToken,
            $sessionId,
            $customerId,
            $token,
            $expiresAt,
            $buyerEmailHash,
        ): void {
            $this->assertEventBookable($eventId);
            $existing = TicketReservation::query()
                ->where('token', $existingToken)
                ->orderBy('ticket_id')
                ->lockForUpdate()
                ->get();

            if ($existing->isEmpty()) {
                throw new RuntimeException('Your previous ticket reservation no longer exists. Please select tickets again.');
            }
            if ($existing->contains(fn (TicketReservation $reservation): bool => (int) $reservation->event_id !== $eventId)) {
                throw new RuntimeException('The ticket reservation belongs to another event.');
            }
            if ($existing->pluck('status')->unique()->count() !== 1 || $existing->first()->status !== 'active') {
                throw new RuntimeException('Your previous ticket reservation is no longer active. Please select tickets again.');
            }

            $sessionHash = $sessionId ? hash('sha256', $sessionId) : null;
            foreach ($existing as $reservation) {
                $sessionMatches = $reservation->session_hash !== null
                    && $sessionHash !== null
                    && hash_equals((string) $reservation->session_hash, $sessionHash);
                $customerMatches = $reservation->customer_id !== null
                    && $customerId !== null
                    && (int) $reservation->customer_id === $customerId;
                if (!$sessionMatches && !$customerMatches) {
                    throw new RuntimeException('The ticket reservation does not belong to this checkout.');
                }

                $ticket = Ticket::query()
                    ->whereKey($reservation->ticket_id)
                    ->where('event_id', $eventId)
                    ->lockForUpdate()
                    ->firstOrFail();
                $this->restore($ticket, $reservation->variation_name, (int) $reservation->quantity);
            }

            TicketReservation::query()->whereIn('id', $existing->pluck('id'))->update([
                'status' => 'replaced',
                'released_at' => now(),
            ]);
            $this->createLocked(
                $eventId,
                $selections,
                $sessionId,
                $customerId,
                $token,
                $expiresAt,
                $buyerEmailHash,
            );
        }, 3);

        return $token;
    }

    public function reserve(
        int $eventId,
        array $selections,
        ?string $sessionId,
        ?int $customerId,
        ?CarbonInterface $expiresAt = null,
        bool $allowCorporate = false,
        ?string $buyerEmailHash = null,
    ): string
    {
        if (!$selections) {
            throw new RuntimeException('Select at least one ticket.');
        }

        usort($selections, fn ($a, $b) => ((int) $a['ticket_id']) <=> ((int) $b['ticket_id']));
        $token = (string) Str::uuid();
        $expiresAt ??= now()->addMinutes(max(1, (int) config('tickets.reservation_minutes', 15)));

        DB::transaction(function () use ($eventId, $selections, $sessionId, $customerId, $token, $expiresAt, $allowCorporate, $buyerEmailHash) {
            $this->assertEventBookable($eventId, $allowCorporate);
            $this->createLocked(
                $eventId,
                $selections,
                $sessionId,
                $customerId,
                $token,
                $expiresAt,
                $buyerEmailHash,
            );
        }, 3);

        return $token;
    }

    public function consume(
        string $token,
        int $eventId,
        bool $allowCorporate = false,
        ?int $expectedCustomerId = null,
        ?string $expectedBuyerEmailHash = null,
    ): void
    {
        DB::transaction(function () use (
            $token,
            $eventId,
            $allowCorporate,
            $expectedCustomerId,
            $expectedBuyerEmailHash,
        ) {
            $this->assertEventBookable($eventId, $allowCorporate);
            $reservations = TicketReservation::where('token', $token)
                ->where('event_id', $eventId)
                ->orderBy('ticket_id')
                ->lockForUpdate()
                ->get();

            if ($reservations->isEmpty()) {
                throw new RuntimeException('Your ticket reservation is no longer active. Please select tickets again.');
            }

            foreach ($reservations as $reservation) {
                $customerMatches = $expectedCustomerId !== null
                    && $reservation->customer_id !== null
                    && (int) $reservation->customer_id === $expectedCustomerId;
                $guestMatches = $expectedCustomerId === null
                    && $expectedBuyerEmailHash !== null
                    && $reservation->buyer_email_hash !== null
                    && hash_equals((string) $reservation->buyer_email_hash, $expectedBuyerEmailHash);
                if (($expectedCustomerId !== null || $expectedBuyerEmailHash !== null)
                    && !$customerMatches
                    && !$guestMatches) {
                    throw new RuntimeException('The ticket reservation does not belong to this buyer.');
                }
            }

            if ($reservations->every(fn ($reservation) => $reservation->status === 'consumed')) {
                return;
            }

            $statuses = $reservations->pluck('status')->unique();
            if ($statuses->count() !== 1) {
                throw new RuntimeException('The ticket reservation is in an inconsistent state.');
            }

            // A verified payment may arrive just after the hold timeout. If the
            // expiry worker already restored the stock, reacquire it under the
            // same ticket locks. This keeps late payment fulfilment safe without
            // double-deducting active reservations.
            if ($statuses->first() !== 'active') {
                foreach ($reservations as $reservation) {
                    $ticket = Ticket::where('id', $reservation->ticket_id)
                        ->where('event_id', $eventId)
                        ->lockForUpdate()
                        ->firstOrFail();
                    app(EventTicketPurchaseLimitService::class)->assertCanSelect(
                        $ticket,
                        $reservation->variation_name,
                        (int) $reservation->quantity,
                        $reservation->customer_id ? (int) $reservation->customer_id : null,
                        true,
                        $reservation->buyer_email_hash,
                    );
                    $this->deduct($ticket, $reservation->variation_name, (int) $reservation->quantity);
                }
            }

            TicketReservation::whereIn('id', $reservations->pluck('id'))->update([
                'status' => 'consumed',
                'consumed_at' => now(),
                'released_at' => null,
            ]);
        }, 3);
    }

    public function release(string $token, string $reason = 'released'): int
    {
        $restoredSelections = [];
        $released = DB::transaction(function () use ($token, $reason, &$restoredSelections) {
            $reservations = TicketReservation::where('token', $token)
                ->where('status', 'active')
                ->orderBy('ticket_id')
                ->lockForUpdate()
                ->get();

            foreach ($reservations as $reservation) {
                $ticket = Ticket::where('id', $reservation->ticket_id)->lockForUpdate()->first();
                if ($ticket) {
                    $this->restore($ticket, $reservation->variation_name, (int) $reservation->quantity);
                    $restoredSelections[] = [$ticket->id, $reservation->variation_name];
                }
                $reservation->update(['status' => $reason, 'released_at' => now()]);
            }

            return $reservations->count();
        }, 3);

        if ($released > 0 && Schema::hasTable('event_waitlist_entries')) {
            $waitlist = app(EventWaitlistService::class);
            foreach (collect($restoredSelections)->unique(fn ($selection) => implode('|', $selection)) as $selection) {
                $waitlist->notifyAvailable((int) $selection[0], $selection[1]);
            }
        }

        return $released;
    }

    public function releaseExpired(int $limit = 200): int
    {
        $tokens = TicketReservation::where('status', 'active')
            ->where('expires_at', '<=', now())
            ->limit($limit)
            ->pluck('token')
            ->unique();

        $released = 0;
        foreach ($tokens as $token) {
            $released += $this->release($token, 'expired');
        }

        return $released;
    }

    public function releaseForEvent(int $eventId, string $reason = 'event_cancelled'): int
    {
        $tokens = TicketReservation::where('event_id', $eventId)
            ->where('status', 'active')
            ->orderBy('id')
            ->pluck('token')
            ->unique();

        $released = 0;
        foreach ($tokens as $token) {
            $released += $this->release((string) $token, $reason);
        }

        return $released;
    }

    private function createLocked(
        int $eventId,
        array $selections,
        ?string $sessionId,
        ?int $customerId,
        string $token,
        CarbonInterface $expiresAt,
        ?string $buyerEmailHash,
    ): void {
        foreach ($selections as $selection) {
            $quantity = (int) ($selection['qty'] ?? 0);
            if ($quantity < 1) {
                throw new RuntimeException('Invalid ticket quantity.');
            }

            $ticket = Ticket::where('id', $selection['ticket_id'])
                ->where('event_id', $eventId)
                ->lockForUpdate()
                ->firstOrFail();

            $variationName = $ticket->pricing_type === 'variation'
                ? (string) ($selection['name'] ?? '')
                : null;
            app(EventTicketPurchaseLimitService::class)->assertCanSelect(
                $ticket,
                $variationName,
                $quantity,
                $customerId,
                true,
                $buyerEmailHash,
            );
            $this->deduct($ticket, $variationName, $quantity);

            $attributes = [
                'token' => $token,
                'event_id' => $eventId,
                'ticket_id' => $ticket->id,
                'variation_name' => $variationName,
                'quantity' => $quantity,
                'status' => 'active',
                'session_hash' => $sessionId ? hash('sha256', $sessionId) : null,
                'customer_id' => $customerId,
                'expires_at' => $expiresAt,
            ];
            if (Schema::hasColumn('ticket_reservations', 'buyer_email_hash')) {
                $attributes['buyer_email_hash'] = $customerId ? null : $buyerEmailHash;
            }
            if (Schema::hasColumn('ticket_reservations', 'buyer_email_hash_version')) {
                $attributes['buyer_email_hash_version'] = $customerId || !$buyerEmailHash
                    ? null
                    : app(EventTicketPurchaseLimitService::class)->buyerIdentityHashVersion();
            }
            TicketReservation::create($attributes);
        }
    }

    private function deduct(Ticket $ticket, ?string $variationName, int $quantity): void
    {
        if ($ticket->pricing_type === 'variation') {
            $variations = json_decode($ticket->variations, true) ?: [];
            $matched = false;
            foreach ($variations as &$variation) {
                if ((string) $variation['name'] !== (string) $variationName) {
                    continue;
                }
                $matched = true;
                if (($variation['ticket_available_type'] ?? null) === 'limited') {
                    $available = (int) ($variation['ticket_available'] ?? 0);
                    if ($available < $quantity) {
                        throw new RuntimeException('The selected ticket is no longer available in the requested quantity.');
                    }
                    $variation['ticket_available'] = $available - $quantity;
                }
                break;
            }
            unset($variation);
            if (!$matched) {
                throw new RuntimeException('The selected ticket option no longer exists.');
            }
            $ticket->variations = json_encode($variations);
            $ticket->save();
            return;
        }

        if ($ticket->ticket_available_type === 'limited') {
            $available = (int) $ticket->ticket_available;
            if ($available < $quantity) {
                throw new RuntimeException('The selected ticket is no longer available in the requested quantity.');
            }
            $ticket->ticket_available = $available - $quantity;
            $ticket->save();
        }
    }

    private function assertEventBookable(int $eventId, bool $allowCorporate = false): void
    {
        // Compatibility guard for isolated inventory tools/tests that predate
        // the event table. Production readiness requires the full schema.
        if (!Schema::hasTable('events')) {
            return;
        }

        $event = Event::whereKey($eventId)->lockForUpdate()->firstOrFail();
        $corporateRegistrationOpen = $allowCorporate
            && ($event->registration_mode ?: 'ticketing') === 'corporate'
            && (int) $event->status === 1
            && ($event->lifecycle_status ?: 'scheduled') === 'scheduled'
            && !$event->cancelled_at;
        if (!$event->isBookable() && !$corporateRegistrationOpen) {
            throw new RuntimeException('Ticket sales are closed for this event.');
        }
    }

    private function restore(Ticket $ticket, ?string $variationName, int $quantity): void
    {
        if ($ticket->pricing_type === 'variation') {
            $variations = json_decode($ticket->variations, true) ?: [];
            foreach ($variations as &$variation) {
                if ((string) $variation['name'] === (string) $variationName
                    && ($variation['ticket_available_type'] ?? null) === 'limited') {
                    $variation['ticket_available'] = (int) ($variation['ticket_available'] ?? 0) + $quantity;
                    break;
                }
            }
            unset($variation);
            $ticket->variations = json_encode($variations);
            $ticket->save();
            return;
        }

        if ($ticket->ticket_available_type === 'limited') {
            $ticket->ticket_available = (int) $ticket->ticket_available + $quantity;
            $ticket->save();
        }
    }
}

