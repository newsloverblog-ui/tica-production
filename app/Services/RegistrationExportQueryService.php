<?php

namespace App\Services;

use App\Models\EventRegistrationSubmission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class RegistrationExportQueryService
{
    public function query(array $filters, int $organizerId): Builder
    {
        $query = EventRegistrationSubmission::query()
            ->with([
                'event.information',
                'attendees.ticket',
                'attendees.bookingTicket.checkedInScannerDevice',
                'attendees.answers',
                'answers',
            ]);
        if (Schema::hasTable('event_registration_session_selections')) {
            $query->with('attendees.sessionSelections.session');
        }

        return $query
            ->where('organizer_id', $organizerId)
            ->where('event_id', (int) ($filters['event_id'] ?? 0))
            ->whereNull('anonymized_at')
            ->when(!empty($filters['max_submission_id']), fn (Builder $query) => $query->where('id', '<=', (int) $filters['max_submission_id']))
            ->when(!empty($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(!empty($filters['from_date']), fn (Builder $query) => $query->whereDate('submitted_at', '>=', $filters['from_date']))
            ->when(!empty($filters['to_date']), fn (Builder $query) => $query->whereDate('submitted_at', '<=', $filters['to_date']))
            ->orderBy('id');
    }
}
