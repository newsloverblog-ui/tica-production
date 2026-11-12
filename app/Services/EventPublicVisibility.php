<?php

namespace App\Services;

use App\Models\Event;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Schema;

class EventPublicVisibility
{
    public const PUBLIC_LIFECYCLES = ['cancellation_pending', 'cancelled'];

    /**
     * Keep inactive drafts and archived events private while preserving the
     * public cancellation notice buyers rely on after an event is withdrawn.
     */
    public function apply(EloquentBuilder|QueryBuilder $query, string $table = 'events'): EloquentBuilder|QueryBuilder
    {
        // Deployments can briefly serve the new application release before the
        // migration adding lifecycle_status has completed. Preserve the legacy
        // public rule during that window instead of turning event pages into a
        // database error. Once the column exists, cancellation notices become
        // publicly addressable through the expanded rule below.
        if (! Schema::hasColumn($table, 'lifecycle_status')) {
            return $query->where($table . '.status', 1);
        }

        return $query->where(function ($visibility) use ($table): void {
            $visibility
                ->where($table . '.status', 1)
                ->orWhereIn($table . '.lifecycle_status', self::PUBLIC_LIFECYCLES);
        });
    }

    public function allows(Event $event): bool
    {
        return (int) $event->status === 1
            || in_array((string) $event->lifecycle_status, self::PUBLIC_LIFECYCLES, true);
    }
}
