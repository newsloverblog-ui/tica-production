<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;

class AuditLogExportQueryService
{
    public function query(array $filters): Builder
    {
        return AuditLog::query()
            ->when(!empty($filters['max_audit_id']), fn (Builder $query) => $query->where('id', '<=', (int) $filters['max_audit_id']))
            ->when(!empty($filters['from_date']), fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['from_date']))
            ->when(!empty($filters['to_date']), fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['to_date']))
            ->when(!empty($filters['action']), fn (Builder $query) => $query->where('action', $filters['action']))
            ->when(!empty($filters['actor_type']), fn (Builder $query) => $query->where('actor_type', $filters['actor_type']));
    }
}
