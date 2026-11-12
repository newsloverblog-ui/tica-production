<?php

namespace App\Exports;

use App\Models\EventCancellationCase;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class EventCancellationCaseExport implements FromQuery, WithHeadings, WithMapping, WithCustomCsvSettings
{
    public function __construct(private readonly Builder $cases) {}

    public function query(): Builder
    {
        return $this->cases;
    }

    public function map($case): array
    {
        /** @var EventCancellationCase $case */
        return [
            (int) $case->id,
            (int) $case->event_cancellation_request_id,
            (int) $case->booking_id,
            $this->safe($case->booking_reference),
            $case->payment_intent_id ? (int) $case->payment_intent_id : null,
            $case->payment_refund_id ? (int) $case->payment_refund_id : null,
            $case->payment_refund_dispute_id ? (int) $case->payment_refund_dispute_id : null,
            $this->safe($case->status),
            number_format((float) $case->amount, 2, '.', ''),
            $this->safe($case->external_refund_reference),
            $case->resolved_by_admin_id ? (int) $case->resolved_by_admin_id : null,
            $case->resolved_at?->toIso8601String(),
            $case->created_at?->toIso8601String(),
            $case->updated_at?->toIso8601String(),
        ];
    }

    public function headings(): array
    {
        return [
            'case_id', 'cancellation_request_id', 'booking_record_id', 'booking_reference',
            'payment_intent_id', 'payment_refund_id', 'payment_refund_dispute_id',
            'status', 'amount', 'external_refund_reference', 'resolved_by_admin_id',
            'resolved_at', 'created_at', 'updated_at',
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
