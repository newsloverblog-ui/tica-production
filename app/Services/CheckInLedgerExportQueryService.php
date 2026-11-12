<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class CheckInLedgerExportQueryService
{
    public function ledger(array $filters): Builder
    {
        $organizerId = (int) $filters['organizer_id'];
        $eventId = (int) $filters['event_id'];

        $accepted = DB::table('booking_tickets')
            ->join('bookings', 'bookings.id', '=', 'booking_tickets.booking_id')
            ->leftJoin('scanner_devices as accepted_device', 'accepted_device.id', '=', 'booking_tickets.checked_in_scanner_device_id')
            ->leftJoin('scanner_staff as accepted_staff', 'accepted_staff.id', '=', 'booking_tickets.checked_in_scanner_staff_id')
            ->where('booking_tickets.organizer_id', $organizerId)
            ->where('booking_tickets.event_id', $eventId)
            ->where('booking_tickets.id', '<=', (int) $filters['max_ticket_id'])
            ->whereNotNull('booking_tickets.checked_in_at')
            ->select([
                DB::raw("'accepted' as record_type"),
                'booking_tickets.id as record_id',
                'booking_tickets.public_id as ticket_public_id',
                'booking_tickets.booking_id as booking_record_id',
                'bookings.booking_id as booking_reference',
                'booking_tickets.event_id',
                'booking_tickets.ticket_id as ticket_type_id',
                'booking_tickets.variation_name',
                DB::raw("'accepted' as result"),
                'booking_tickets.checked_in_source as source',
                DB::raw('1 as occurrences'),
                'booking_tickets.checked_in_at as first_detected_at',
                'booking_tickets.checked_in_at as last_detected_at',
                'accepted_device.id as attempted_device_id',
                'accepted_device.public_id as attempted_device_public_id',
                'accepted_device.name as attempted_gate',
                'accepted_staff.id as attempted_staff_id',
                'accepted_staff.name as attempted_staff_name',
                'accepted_staff.role as attempted_staff_role',
                DB::raw('NULL as original_device_id'),
                DB::raw('NULL as original_device_public_id'),
                DB::raw('NULL as original_gate'),
                DB::raw('NULL as original_staff_id'),
                DB::raw('NULL as original_staff_name'),
                DB::raw('NULL as original_staff_role'),
                DB::raw('NULL as resolved_at'),
            ]);

        $duplicates = DB::table('scanner_check_in_conflicts')
            ->join('booking_tickets', 'booking_tickets.id', '=', 'scanner_check_in_conflicts.booking_ticket_id')
            ->join('bookings', 'bookings.id', '=', 'booking_tickets.booking_id')
            ->leftJoin('scanner_devices as attempted_device', 'attempted_device.id', '=', 'scanner_check_in_conflicts.attempted_scanner_device_id')
            ->leftJoin('scanner_staff as attempted_staff', 'attempted_staff.id', '=', 'scanner_check_in_conflicts.attempted_scanner_staff_id')
            ->leftJoin('scanner_devices as original_device', 'original_device.id', '=', 'scanner_check_in_conflicts.original_scanner_device_id')
            ->leftJoin('scanner_staff as original_staff', 'original_staff.id', '=', 'scanner_check_in_conflicts.original_scanner_staff_id')
            ->where('scanner_check_in_conflicts.organizer_id', $organizerId)
            ->where('scanner_check_in_conflicts.event_id', $eventId)
            ->where('scanner_check_in_conflicts.id', '<=', (int) $filters['max_conflict_id'])
            ->select([
                DB::raw("'duplicate_attempt' as record_type"),
                'scanner_check_in_conflicts.id as record_id',
                'booking_tickets.public_id as ticket_public_id',
                'booking_tickets.booking_id as booking_record_id',
                'bookings.booking_id as booking_reference',
                'scanner_check_in_conflicts.event_id',
                'booking_tickets.ticket_id as ticket_type_id',
                'booking_tickets.variation_name',
                DB::raw("'rejected_duplicate' as result"),
                'scanner_check_in_conflicts.source',
                'scanner_check_in_conflicts.occurrences',
                'scanner_check_in_conflicts.first_detected_at',
                'scanner_check_in_conflicts.last_detected_at',
                'attempted_device.id as attempted_device_id',
                'attempted_device.public_id as attempted_device_public_id',
                'attempted_device.name as attempted_gate',
                'attempted_staff.id as attempted_staff_id',
                'attempted_staff.name as attempted_staff_name',
                'attempted_staff.role as attempted_staff_role',
                'original_device.id as original_device_id',
                'original_device.public_id as original_device_public_id',
                'original_device.name as original_gate',
                'original_staff.id as original_staff_id',
                'original_staff.name as original_staff_name',
                'original_staff.role as original_staff_role',
                'scanner_check_in_conflicts.resolved_at',
            ]);

        return $accepted->unionAll($duplicates);
    }
}
