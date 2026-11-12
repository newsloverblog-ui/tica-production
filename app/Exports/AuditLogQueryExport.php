<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AuditLogQueryExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(private readonly Builder $logs)
    {
    }

    public function query(): Builder
    {
        return $this->logs;
    }

    public function map($log): array
    {
        return [
            $log->id,
            optional($log->created_at)->toIso8601String(),
            $this->safe((string) $log->actor_type),
            $log->actor_id,
            $this->safe((string) $log->action),
            $this->safe((string) $log->subject_type),
            $log->subject_id,
            $this->json($log->before),
            $this->json($log->after),
            $this->json($log->metadata),
            $this->safe((string) $log->request_id),
            $this->safe((string) $log->ip_address),
            $this->safe((string) $log->user_agent),
        ];
    }

    public function headings(): array
    {
        return [
            'audit_id', 'occurred_at', 'actor_type', 'actor_id', 'action',
            'subject_type', 'subject_id', 'before', 'after', 'metadata',
            'request_id', 'ip_address', 'user_agent',
        ];
    }

    private function json(mixed $value): string
    {
        return $this->safe((string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function safe(string $value): string
    {
        return preg_match('/^[=+\-@]/u', $value) ? "'".$value : $value;
    }
}
