<?php

namespace App\Services;

use App\Models\EventGuestInvitation;
use Illuminate\Database\Eloquent\Builder;

class GuestInvitationExportQueryService
{
    public function query(array $filters, int $organizerId): Builder
    {
        return EventGuestInvitation::query()
            ->with('event.information')
            ->where('organizer_id', $organizerId)
            ->where('event_id', (int) ($filters['event_id'] ?? 0))
            ->whereNull('anonymized_at')
            ->when(!empty($filters['max_invitation_id']), fn (Builder $query) => $query->where('id', '<=', (int) $filters['max_invitation_id']))
            ->when(!empty($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(!empty($filters['from_date']), fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['from_date']))
            ->when(!empty($filters['to_date']), fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['to_date']))
            ->orderBy('id');
    }
}
