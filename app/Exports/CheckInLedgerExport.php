<?php

namespace App\Exports;

use Illuminate\Database\Query\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class CheckInLedgerExport implements FromQuery, WithHeadings, WithMapping, WithCustomCsvSettings
{
    public function __construct(private readonly Builder $ledger) {}

    public function query(): Builder
    {
        return $this->ledger;
    }

    public function map($row): array
    {
        return [
            $this->safe($row->record_type), (int) $row->record_id,
            $this->safe($row->ticket_public_id), (int) $row->booking_record_id,
            $this->safe($row->booking_reference), (int) $row->event_id,
            $row->ticket_type_id ? (int) $row->ticket_type_id : null,
            $this->safe($row->variation_name), $this->safe($row->result),
            $this->safe($row->source), (int) $row->occurrences,
            $row->first_detected_at, $row->last_detected_at,
            $row->attempted_device_id ? (int) $row->attempted_device_id : null,
            $this->safe($row->attempted_device_public_id), $this->safe($row->attempted_gate),
            $row->attempted_staff_id ? (int) $row->attempted_staff_id : null,
            $this->safe($row->attempted_staff_name), $this->safe($row->attempted_staff_role),
            $row->original_device_id ? (int) $row->original_device_id : null,
            $this->safe($row->original_device_public_id), $this->safe($row->original_gate),
            $row->original_staff_id ? (int) $row->original_staff_id : null,
            $this->safe($row->original_staff_name), $this->safe($row->original_staff_role),
            $row->resolved_at,
        ];
    }

    public function headings(): array
    {
        return [
            'record_type', 'record_id', 'ticket_public_id', 'booking_record_id',
            'booking_reference', 'event_id', 'ticket_type_id', 'variation', 'result',
            'source', 'occurrences', 'first_detected_at', 'last_detected_at',
            'attempted_device_id', 'attempted_device_public_id', 'attempted_gate',
            'attempted_staff_id', 'attempted_staff_name', 'attempted_staff_role',
            'original_device_id', 'original_device_public_id', 'original_gate',
            'original_staff_id', 'original_staff_name', 'original_staff_role', 'resolved_at',
        ];
    }

    public function getCsvSettings(): array
    {
        return ['use_bom' => true];
    }

    private function safe(mixed $value): mixed
    {
        return is_string($value) && preg_match('/^[=+\-@]/u', $value) ? "'".$value : $value;
    }
}
