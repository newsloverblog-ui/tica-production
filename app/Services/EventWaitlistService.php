<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Event\Booking;
use App\Models\Event\Ticket;
use App\Models\Event\TicketContent;
use App\Models\EventWaitlistEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class EventWaitlistService
{
    public function register(
        Event $event,
        int $ticketId,
        ?string $variationName,
        string $name,
        string $email,
        ?int $customerId = null
    ): EventWaitlistEntry {
        $email = $this->normalizeEmail($email);
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        $variationName = $this->normalizeVariation($variationName);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Enter a valid email address.');
        }
        if ($name === '') {
            throw new RuntimeException('Enter your name.');
        }

        return DB::transaction(function () use ($event, $ticketId, $variationName, $name, $email, $customerId) {
            $lockedEvent = Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            if (!$lockedEvent->isBookable() || ($lockedEvent->end_date_time && now()->gte($lockedEvent->end_date_time))) {
                throw new RuntimeException('The waiting list is closed for this event.');
            }

            $ticket = Ticket::whereKey($ticketId)
                ->where('event_id', $lockedEvent->id)
                ->lockForUpdate()
                ->firstOrFail();
            $this->assertSelectionExists($ticket, $variationName);
            if ($this->availableQuantity($ticket, $variationName) > 0) {
                throw new RuntimeException('This ticket is available now. Please continue to checkout.');
            }

            $emailHash = hash('sha256', $email);
            $selectionKey = $this->selectionKey($lockedEvent->id, $ticket->id, $variationName, $emailHash);
            $entry = EventWaitlistEntry::where('selection_key', $selectionKey)->lockForUpdate()->first();
            $values = [
                'event_id' => $lockedEvent->id,
                'ticket_id' => $ticket->id,
                'variation_name' => $variationName,
                'selection_key' => $selectionKey,
                'customer_id' => $customerId,
                'name' => $name,
                'email' => $email,
                'email_hash' => $emailHash,
                'consent_version' => (string) config('tickets.waitlist_consent_version', '2026-11'),
                'consented_at' => now(),
                'alerted_at' => null,
                'last_error' => null,
            ];

            if (!$entry) {
                $entry = EventWaitlistEntry::create($values + [
                    'public_id' => (string) Str::uuid(),
                    'status' => 'active',
                    'joined_at' => now(),
                ]);
            } elseif (in_array($entry->status, ['unsubscribed', 'expired', 'failed', 'converted'], true)) {
                $entry->forceFill($values + [
                    'status' => 'active',
                    'joined_at' => now(),
                    'notification_pending_at' => null,
                    'notified_at' => null,
                    'notification_expires_at' => null,
                    'unsubscribed_at' => null,
                    'converted_at' => null,
                    'processing_at' => null,
                    'attempts' => 0,
                ])->save();
            }

            AuditLogger::record('event.waitlist.joined', $entry, [], [
                'event_id' => $lockedEvent->id,
                'ticket_id' => $ticket->id,
                'variation_name' => $variationName,
                'customer_id' => $customerId,
                'consent_version' => $entry->consent_version,
            ]);

            return $entry->fresh();
        }, 3);
    }

    public function unsubscribe(EventWaitlistEntry $entry): void
    {
        DB::transaction(function () use ($entry): void {
            $locked = EventWaitlistEntry::whereKey($entry->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'unsubscribed') {
                return;
            }
            $before = ['status' => $locked->status];
            $locked->forceFill([
                'status' => 'unsubscribed',
                'unsubscribed_at' => now(),
                'notification_expires_at' => null,
            ])->save();
            AuditLogger::record('event.waitlist.unsubscribed', $locked, $before, [
                'status' => 'unsubscribed',
                'event_id' => $locked->event_id,
                'ticket_id' => $locked->ticket_id,
            ]);
        }, 3);
    }

    public function notifyAvailable(?int $ticketId = null, ?string $variationName = null): int
    {
        if (!Schema::hasTable('event_waitlist_entries')) {
            return 0;
        }

        EventWaitlistEntry::whereIn('status', ['notification_pending', 'notified'])
            ->whereNotNull('notification_expires_at')
            ->where('notification_expires_at', '<=', now())
            ->update(['status' => 'expired', 'updated_at' => now()]);

        $groups = EventWaitlistEntry::query()
            ->where('status', 'active')
            ->when($ticketId, fn ($query) => $query->where('ticket_id', $ticketId))
            ->when($variationName !== null, fn ($query) => $query->where('variation_name', $this->normalizeVariation($variationName)))
            ->select(['ticket_id', 'variation_name'])
            ->distinct()
            ->limit((int) config('tickets.waitlist_dispatch_limit', 200))
            ->get();

        $queued = 0;
        $dispatchLimit = (int) config('tickets.waitlist_dispatch_limit', 200);
        foreach ($groups as $group) {
            if ($queued >= $dispatchLimit) {
                break;
            }
            $queued += $this->notifySelection(
                (int) $group->ticket_id,
                $group->variation_name,
                $dispatchLimit - $queued
            );
        }

        return $queued;
    }

    public function markConvertedForBooking(Booking $booking): int
    {
        if (!Schema::hasTable('event_waitlist_entries') || !$booking->email) {
            return 0;
        }
        $ticketSelections = $this->bookingSelections($booking);
        if (!$ticketSelections) {
            return 0;
        }

        return EventWaitlistEntry::query()
            ->where('event_id', $booking->event_id)
            ->where('email_hash', hash('sha256', $this->normalizeEmail((string) $booking->email)))
            ->whereIn('status', ['active', 'notification_pending', 'notified', 'expired'])
            ->where(function ($query) use ($ticketSelections): void {
                foreach ($ticketSelections as [$ticketId, $variationName]) {
                    $query->orWhere(function ($selection) use ($ticketId, $variationName): void {
                        $selection->where('ticket_id', $ticketId);
                        $variationName === null
                            ? $selection->whereNull('variation_name')
                            : $selection->where('variation_name', $variationName);
                    });
                }
            })
            ->update([
                'status' => 'converted',
                'converted_at' => now(),
                'notification_expires_at' => null,
                'updated_at' => now(),
            ]);
    }

    public function availableQuantity(Ticket $ticket, ?string $variationName = null): int
    {
        if ($ticket->pricing_type === 'variation') {
            foreach (json_decode($ticket->variations, true) ?: [] as $variation) {
                if ((string) ($variation['name'] ?? '') !== (string) $this->normalizeVariation($variationName)) {
                    continue;
                }
                return ($variation['ticket_available_type'] ?? null) === 'limited'
                    ? max(0, (int) ($variation['ticket_available'] ?? 0))
                    : PHP_INT_MAX;
            }
            return 0;
        }

        return $ticket->ticket_available_type === 'limited'
            ? max(0, (int) $ticket->ticket_available)
            : PHP_INT_MAX;
    }

    public function soldOutSelections(Event $event): array
    {
        if (!$event->isBookable()) {
            return [];
        }
        $selections = [];
        $tickets = Ticket::where('event_id', $event->id)->orderBy('id')->get();
        $titles = TicketContent::whereIn('ticket_id', $tickets->pluck('id'))
            ->orderBy('language_id')
            ->get()
            ->unique('ticket_id')
            ->pluck('title', 'ticket_id');

        foreach ($tickets as $ticket) {
            $title = trim((string) ($titles[$ticket->id] ?? 'Event ticket'));
            if ($ticket->pricing_type === 'variation') {
                foreach (json_decode($ticket->variations, true) ?: [] as $variation) {
                    if (($variation['ticket_available_type'] ?? null) === 'limited'
                        && (int) ($variation['ticket_available'] ?? 0) < 1) {
                        $selections[] = [
                            'ticket_id' => $ticket->id,
                            'variation_name' => (string) ($variation['name'] ?? ''),
                            'label' => $title . ' — ' . (string) ($variation['name'] ?? 'Option'),
                        ];
                    }
                }
            } elseif ($ticket->ticket_available_type === 'limited' && (int) $ticket->ticket_available < 1) {
                $selections[] = [
                    'ticket_id' => $ticket->id,
                    'variation_name' => null,
                    'label' => $title,
                ];
            }
        }

        return $selections;
    }

    private function notifySelection(int $ticketId, ?string $variationName, int $dispatchLimit): int
    {
        $entryIds = [];
        $queued = DB::transaction(function () use ($ticketId, $variationName, $dispatchLimit, &$entryIds): int {
            $ticket = Ticket::whereKey($ticketId)->lockForUpdate()->first();
            if (!$ticket) {
                return 0;
            }
            $event = Event::whereKey($ticket->event_id)->lockForUpdate()->first();
            if (!$event || !$event->isBookable()) {
                return 0;
            }

            $available = $this->availableQuantity($ticket, $variationName);
            if ($available < 1) {
                return 0;
            }

            $inFlight = EventWaitlistEntry::where('ticket_id', $ticket->id)
                ->when($variationName === null,
                    fn ($query) => $query->whereNull('variation_name'),
                    fn ($query) => $query->where('variation_name', $variationName))
                ->whereIn('status', ['notification_pending', 'notified'])
                ->where('notification_expires_at', '>', now())
                ->count();
            $slots = $available === PHP_INT_MAX
                ? $dispatchLimit
                : max(0, $available - $inFlight);
            $slots = min($slots, $dispatchLimit);
            if ($slots < 1) {
                return 0;
            }

            $entries = EventWaitlistEntry::where('ticket_id', $ticket->id)
                ->when($variationName === null,
                    fn ($query) => $query->whereNull('variation_name'),
                    fn ($query) => $query->where('variation_name', $variationName))
                ->where('status', 'active')
                ->orderBy('joined_at')
                ->orderBy('id')
                ->limit($slots)
                ->lockForUpdate()
                ->get();
            $expiresAt = now()->addMinutes((int) config('tickets.waitlist_notification_minutes', 30));
            foreach ($entries as $entry) {
                $entry->forceFill([
                    'status' => 'notification_pending',
                    'notification_pending_at' => now(),
                    'notification_expires_at' => $expiresAt,
                    'processing_at' => null,
                    'last_error' => null,
                    'alerted_at' => null,
                ])->save();
                $entryIds[] = (int) $entry->id;
            }

            return $entries->count();
        }, 3);

        foreach ($entryIds as $entryId) {
            app(WaitlistNotificationDispatchService::class)->dispatchNotification($entryId);
        }

        return $queued;
    }

    private function bookingSelections(Booking $booking): array
    {
        if ($booking->ticket_id) {
            return [[(int) $booking->ticket_id, null]];
        }
        $selections = [];
        foreach (json_decode((string) $booking->variation, true) ?: [] as $selection) {
            if (!empty($selection['ticket_id'])) {
                $selections[] = [
                    (int) $selection['ticket_id'],
                    $this->normalizeVariation($selection['name'] ?? null),
                ];
            }
        }
        return $selections;
    }

    private function assertSelectionExists(Ticket $ticket, ?string $variationName): void
    {
        if ($ticket->pricing_type !== 'variation') {
            if ($variationName !== null) {
                throw new RuntimeException('The selected ticket option is invalid.');
            }
            return;
        }
        foreach (json_decode($ticket->variations, true) ?: [] as $variation) {
            if ((string) ($variation['name'] ?? '') === (string) $variationName) {
                return;
            }
        }
        throw new RuntimeException('The selected ticket option no longer exists.');
    }

    private function selectionKey(int $eventId, int $ticketId, ?string $variationName, string $emailHash): string
    {
        return hash('sha256', implode('|', [$eventId, $ticketId, $variationName ?? '', $emailHash]));
    }

    private function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    private function normalizeVariation(?string $variationName): ?string
    {
        $variationName = trim((string) $variationName);
        return $variationName === '' ? null : $variationName;
    }
}
