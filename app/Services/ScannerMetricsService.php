<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Event\BookingTicket;
use Illuminate\Validation\ValidationException;

class ScannerMetricsService
{
    public function forEvent(int $organizerId, int $eventId): array
    {
        $event = Event::query()
            ->with('information')
            ->whereKey($eventId)
            ->where('organizer_id', $organizerId)
            ->first();

        if (!$event) {
            throw ValidationException::withMessages([
                'event_id' => 'This event is not available to the signed-in organizer.',
            ]);
        }

        $eligible = BookingTicket::query()
            ->where('booking_tickets.organizer_id', $organizerId)
            ->where('booking_tickets.event_id', $eventId)
            ->whereIn('booking_tickets.status', ['issued', 'checked_in'])
            ->whereHas('booking', function ($query) {
                $query->whereIn('paymentStatus', ['completed', 'free', '1'])
                    ->where(function ($refund) {
                        $refund->whereNull('refund_status')->orWhere('refund_status', '!=', 'refunded');
                    });
            });

        $counts = (clone $eligible)
            ->selectRaw("SUM(CASE WHEN booking_tickets.status = 'checked_in' THEN 1 ELSE 0 END) AS scanned")
            ->selectRaw("SUM(CASE WHEN booking_tickets.status = 'issued' THEN 1 ELSE 0 END) AS remaining")
            ->selectRaw('COUNT(*) AS total')
            ->first();

        $gates = (clone $eligible)
            ->where('booking_tickets.status', 'checked_in')
            ->leftJoin('scanner_devices', 'scanner_devices.id', '=', 'booking_tickets.checked_in_scanner_device_id')
            ->selectRaw("COALESCE(scanner_devices.name, 'Unassigned / legacy') AS gate")
            ->selectRaw('COUNT(*) AS scanned')
            ->groupByRaw("COALESCE(scanner_devices.name, 'Unassigned / legacy')")
            ->orderByDesc('scanned')
            ->orderBy('gate')
            ->get()
            ->map(fn ($row) => ['gate' => (string) $row->gate, 'scanned' => (int) $row->scanned])
            ->values()
            ->all();

        return [
            'event' => [
                'id' => (int) $event->id,
                'title' => optional($event->information)->title ?: 'Event #' . $event->id,
            ],
            'total' => (int) ($counts->total ?? 0),
            'scanned' => (int) ($counts->scanned ?? 0),
            'remaining' => (int) ($counts->remaining ?? 0),
            'per_gate' => $gates,
            'refreshed_at' => now()->toIso8601String(),
        ];
    }
}
