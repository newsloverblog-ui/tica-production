<?php

namespace App\Services;

use App\Models\Event\BookingTicket;
use App\Models\Event\Ticket;
use App\Models\TicketNumberAllocation;
use App\Models\TicketNumberSequence;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class TicketNumberAllocator
{
    public function allocate(BookingTicket $unit): ?string
    {
        if (!Schema::hasTable('ticket_number_sequences') || !Schema::hasColumn('booking_tickets', 'ticket_number')) {
            return null;
        }
        if ($unit->ticket_number) {
            return (string) $unit->ticket_number;
        }

        $ticket = Ticket::query()->whereKey($unit->ticket_id)->lockForUpdate()->first();
        if (!$ticket || !$ticket->numbering_enabled) {
            return null;
        }

        $eventScope = $ticket->numbering_scope === 'event';
        $scopeKey = $eventScope ? 'event' : 'ticket:' . $ticket->id;
        $sequence = TicketNumberSequence::query()
            ->where('event_id', $unit->event_id)
            ->where('scope_key', $scopeKey)
            ->lockForUpdate()
            ->first();
        if (!$sequence) {
            // INSERT ... ON CONFLICT/IGNORE keeps a PostgreSQL transaction
            // usable when two first purchases initialise the same sequence.
            // Catching a unique violation here would leave PostgreSQL's outer
            // transaction aborted and make the authoritative locked read fail.
            DB::table('ticket_number_sequences')->insertOrIgnore([
                'event_id' => $unit->event_id,
                'ticket_id' => $eventScope ? null : $ticket->id,
                'scope_key' => $scopeKey,
                'next_number' => max(1, (int) $ticket->number_start),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $sequence = TicketNumberSequence::query()
                ->where('event_id', $unit->event_id)
                ->where('scope_key', $scopeKey)
                ->lockForUpdate()
                ->firstOrFail();
        }

        $value = (int) $sequence->next_number;
        $number = $this->format($ticket, $value);
        if (BookingTicket::query()->where('event_id', $unit->event_id)->where('ticket_number', $number)->exists()) {
            throw new RuntimeException('The configured ticket number format produced a duplicate. Change the category prefix or format.');
        }

        $unit->forceFill(['ticket_number' => $number, 'ticket_number_value' => $value])->save();
        $sequence->forceFill(['next_number' => $value + 1])->save();
        TicketNumberAllocation::query()->create([
            'event_id' => $unit->event_id,
            'ticket_id' => $ticket->id,
            'booking_ticket_id' => $unit->id,
            'number_value' => $value,
            'ticket_number' => $number,
            'status' => 'allocated',
            'allocated_at' => now(),
        ]);

        return $number;
    }

    public function invalidate(BookingTicket $unit, string $status): void
    {
        if (!Schema::hasTable('ticket_number_allocations')) return;
        TicketNumberAllocation::query()->where('booking_ticket_id', $unit->id)->update([
            'status' => $status,
            'invalidated_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function format(Ticket $ticket, int $value): string
    {
        $prefix = strtoupper(trim((string) $ticket->number_prefix));
        if ($prefix === '') {
            $prefix = strtoupper(substr((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $ticket->title), 0, 4));
        }
        $prefix = preg_replace('/[^A-Z0-9_-]/', '', $prefix) ?: 'TKT';
        $padding = min(10, max(1, (int) $ticket->number_padding));
        $format = trim((string) $ticket->number_format) ?: '{PREFIX}-{NUMBER}';
        if (!str_contains($format, '{NUMBER}')) {
            throw new RuntimeException('Ticket number format must contain {NUMBER}.');
        }
        $number = str_replace(
            ['{PREFIX}', '{NUMBER}'],
            [$prefix, str_pad((string) $value, $padding, '0', STR_PAD_LEFT)],
            $format,
        );
        if (mb_strlen($number) > 80) {
            throw new RuntimeException('The configured ticket number is too long.');
        }

        return $number;
    }
}
