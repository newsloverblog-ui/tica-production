<?php

namespace App\Services;

use App\Models\Event\BookingTicket;
use App\Models\ScannerCheckInConflict;
use App\Models\ScannerDevice;
use App\Models\ScannerStaff;
use Illuminate\Support\Facades\DB;

class ScannerConflictService
{
    public function record(
        BookingTicket $ticket,
        ScannerDevice $original,
        ScannerDevice $attempted,
        string $source,
        ?ScannerStaff $attemptedStaff = null
    ): ?ScannerCheckInConflict
    {
        if ($original->id === $attempted->id || $ticket->organizer_id !== $attempted->organizer_id) {
            return null;
        }

        $conflict = ScannerCheckInConflict::where('booking_ticket_id', $ticket->id)
            ->where('original_scanner_device_id', $original->id)
            ->where('attempted_scanner_device_id', $attempted->id)
            ->lockForUpdate()
            ->first();

        if ($conflict) {
            $conflict->update([
                'occurrences' => $conflict->occurrences + 1,
                'last_detected_at' => now(),
                'source' => $source,
                'attempted_scanner_staff_id' => $attemptedStaff?->id,
            ]);
        } else {
            $conflict = ScannerCheckInConflict::create([
                'organizer_id' => $ticket->organizer_id,
                'event_id' => $ticket->event_id,
                'booking_ticket_id' => $ticket->id,
                'original_scanner_device_id' => $original->id,
                'attempted_scanner_device_id' => $attempted->id,
                'original_scanner_staff_id' => $ticket->checked_in_scanner_staff_id,
                'attempted_scanner_staff_id' => $attemptedStaff?->id,
                'source' => $source,
                'occurrences' => 1,
                'first_detected_at' => now(),
                'last_detected_at' => now(),
            ]);
        }

        AuditLogger::record('scanner_check_in.conflict_detected', $conflict, [], [
            'ticket_public_id' => $ticket->public_id,
            'event_id' => $ticket->event_id,
            'original_device' => $original->only(['id', 'public_id', 'name']),
            'attempted_device' => $attempted->only(['id', 'public_id', 'name']),
            'original_scanner_staff_id' => $conflict->original_scanner_staff_id,
            'attempted_scanner_staff_id' => $conflict->attempted_scanner_staff_id,
            'occurrences' => $conflict->occurrences,
            'source' => $source,
        ]);

        return $conflict;
    }

    public function resolve(int $organizerId, int $conflictId, string $note): ScannerCheckInConflict
    {
        return DB::transaction(function () use ($organizerId, $conflictId, $note) {
            $conflict = ScannerCheckInConflict::whereKey($conflictId)
                ->where('organizer_id', $organizerId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($conflict->resolved_at) {
                return $conflict;
            }

            $conflict->update([
                'resolved_at' => now(),
                'resolved_by_organizer_id' => $organizerId,
                'resolution_note' => trim($note),
            ]);

            AuditLogger::record('scanner_check_in.conflict_resolved', $conflict, [], [
                'resolution_note' => $conflict->resolution_note,
                'occurrences' => $conflict->occurrences,
            ]);

            return $conflict;
        });
    }
}
