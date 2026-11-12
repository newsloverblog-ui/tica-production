<?php

namespace App\Exports;

use App\Models\OperationalIncident;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class OperationalIncidentExport implements FromQuery, WithHeadings, WithMapping, WithCustomCsvSettings
{
    public function __construct(private readonly Builder $incidents) {}

    public function query(): Builder { return $this->incidents; }

    public function map($incident): array
    {
        /** @var OperationalIncident $incident */
        $delivery = $incident->delivery_status ?: [];

        return [
            (int) $incident->id,
            $this->safe($incident->public_id),
            $this->safe($incident->incident_key),
            $this->safe($incident->severity),
            $this->safe($incident->status),
            $this->safe($incident->title),
            (int) $incident->occurrence_count,
            $this->safe($delivery['email'] ?? 'not_configured'),
            $this->safe($delivery['webhook'] ?? 'not_configured'),
            $incident->first_seen_at?->toIso8601String(),
            $incident->last_seen_at?->toIso8601String(),
            $incident->last_alerted_at?->toIso8601String(),
            $incident->resolved_at?->toIso8601String(),
            $incident->resolved_by_admin_id ? (int) $incident->resolved_by_admin_id : null,
            $incident->created_at?->toIso8601String(),
            $incident->updated_at?->toIso8601String(),
        ];
    }

    public function headings(): array
    {
        return [
            'incident_id', 'incident_public_id', 'incident_key', 'severity', 'status',
            'title', 'occurrence_count', 'email_delivery_status', 'webhook_delivery_status',
            'first_seen_at', 'last_seen_at', 'last_alerted_at', 'resolved_at',
            'resolved_by_admin_id', 'created_at', 'updated_at',
        ];
    }

    public function getCsvSettings(): array { return ['use_bom' => true]; }

    private function safe(mixed $value): mixed
    {
        return is_string($value) && preg_match('/^[=+\-@]/u', $value) ? "'".$value : $value;
    }
}
