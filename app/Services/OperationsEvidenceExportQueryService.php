<?php

namespace App\Services;

use App\Models\OperationalIncident;
use App\Models\WebhookReceipt;
use Illuminate\Database\Eloquent\Builder;

class OperationsEvidenceExportQueryService
{
    public function incidents(array $filters): Builder
    {
        return OperationalIncident::query()
            ->select([
                'id', 'public_id', 'incident_key', 'severity', 'status', 'title',
                'occurrence_count', 'delivery_status', 'first_seen_at', 'last_seen_at',
                'last_alerted_at', 'resolved_at', 'resolved_by_admin_id', 'created_at', 'updated_at',
            ])
            ->when(!empty($filters['max_incident_id']), fn (Builder $query) =>
                $query->where('id', '<=', (int) $filters['max_incident_id']))
            ->when(!empty($filters['status']), fn (Builder $query) =>
                $query->where('status', (string) $filters['status']))
            ->when(!empty($filters['severity']), fn (Builder $query) =>
                $query->where('severity', (string) $filters['severity']));
    }

    public function webhookReceipts(array $filters): Builder
    {
        return WebhookReceipt::query()
            ->select([
                'id', 'public_id', 'gateway', 'event_type', 'body_hash', 'request_id',
                'signature_present', 'signature_valid', 'outcome', 'http_status',
                'processing_ms', 'error_code', 'received_at', 'completed_at',
            ])
            ->when(!empty($filters['max_receipt_id']), fn (Builder $query) =>
                $query->where('id', '<=', (int) $filters['max_receipt_id']))
            ->whereBetween('received_at', [
                (string) $filters['from_at'],
                (string) $filters['to_at'],
            ])
            ->when(!empty($filters['gateway']), fn (Builder $query) =>
                $query->where('gateway', (string) $filters['gateway']))
            ->when(!empty($filters['outcome']), fn (Builder $query) =>
                $query->where('outcome', (string) $filters['outcome']));
    }
}
