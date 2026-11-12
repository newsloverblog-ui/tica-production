<?php

namespace App\Services;

use App\Models\EventCancellationCase;
use Illuminate\Database\Eloquent\Builder;

class EventCancellationCaseExportQueryService
{
    public function cases(array $filters): Builder
    {
        return EventCancellationCase::query()
            ->leftJoin('bookings', 'bookings.id', '=', 'event_cancellation_cases.booking_id')
            ->select([
                'event_cancellation_cases.id',
                'event_cancellation_cases.event_cancellation_request_id',
                'event_cancellation_cases.booking_id',
                'bookings.booking_id as booking_reference',
                'event_cancellation_cases.payment_intent_id',
                'event_cancellation_cases.payment_refund_id',
                'event_cancellation_cases.payment_refund_dispute_id',
                'event_cancellation_cases.resolved_by_admin_id',
                'event_cancellation_cases.external_refund_reference',
                'event_cancellation_cases.status',
                'event_cancellation_cases.amount',
                'event_cancellation_cases.resolved_at',
                'event_cancellation_cases.created_at',
                'event_cancellation_cases.updated_at',
            ])
            ->where(
                'event_cancellation_cases.event_cancellation_request_id',
                (int) $filters['cancellation_request_id']
            )
            ->where('event_cancellation_cases.id', '<=', (int) $filters['max_case_id'])
            ->when(!empty($filters['status']), fn (Builder $query) =>
                $query->where('event_cancellation_cases.status', (string) $filters['status']));
    }
}
