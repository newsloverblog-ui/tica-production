<?php

namespace App\Exports;

use App\Models\PaymentReconciliationRun;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PaymentReconciliationRunExport implements FromQuery, WithHeadings, WithMapping, WithCustomCsvSettings
{
    public function __construct(private readonly Builder $runs) {}

    public function query(): Builder
    {
        return $this->runs;
    }

    public function map($run): array
    {
        /** @var PaymentReconciliationRun $run */
        return [
            (int) $run->id,
            $this->safe($run->public_id),
            $this->safe($run->gateway),
            $run->reconciliation_date?->format('Y-m-d'),
            $this->safe($run->status),
            (int) $run->gateway_count,
            (int) $run->local_count,
            (int) $run->matched_count,
            (int) $run->mismatch_count,
            $run->started_at?->toIso8601String(),
            $run->completed_at?->toIso8601String(),
        ];
    }

    public function headings(): array
    {
        return [
            'run_id', 'run_public_id', 'gateway', 'reconciliation_date', 'status',
            'gateway_rows', 'local_rows', 'matched_rows', 'mismatched_rows',
            'started_at', 'completed_at',
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
