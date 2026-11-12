<?php

namespace App\Exports;

use App\Models\GatewaySettlement;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class GatewaySettlementExport implements FromQuery, WithHeadings, WithMapping, WithCustomCsvSettings
{
    public function __construct(private readonly Builder $settlements) {}

    public function query(): Builder
    {
        return $this->settlements;
    }

    public function map($settlement): array
    {
        /** @var GatewaySettlement $settlement */
        return [
            (int) $settlement->id,
            $this->safe($settlement->gateway),
            $this->safe($settlement->gateway_settlement_id),
            $this->safe($settlement->status),
            $this->safe($settlement->reconciliation_status),
            $this->safe($settlement->currency),
            (string) ((float) $settlement->total_processed),
            (string) ((float) $settlement->total_fees),
            (string) ((float) $settlement->total_amount),
            (string) ((float) $settlement->effective_amount),
            (int) $settlement->transaction_count,
            (int) $settlement->matched_count,
            (int) $settlement->mismatch_count,
            $settlement->settlement_date?->toIso8601String(),
            $settlement->fetched_at?->toIso8601String(),
            $settlement->reconciled_at?->toIso8601String(),
        ];
    }

    public function headings(): array
    {
        return [
            'settlement_id', 'gateway', 'gateway_settlement_id', 'gateway_status',
            'reconciliation_status', 'currency', 'total_processed', 'total_fees',
            'total_amount', 'effective_amount', 'transaction_count', 'matched_count',
            'mismatch_count', 'settlement_date', 'fetched_at', 'reconciled_at',
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
