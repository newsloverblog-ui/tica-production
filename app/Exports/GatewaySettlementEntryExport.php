<?php

namespace App\Exports;

use App\Models\GatewaySettlementEntry;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class GatewaySettlementEntryExport implements FromQuery, WithHeadings, WithMapping, WithCustomCsvSettings
{
    public function __construct(private readonly Builder $entries) {}
    public function query(): Builder { return $this->entries; }

    public function map($entry): array
    {
        /** @var GatewaySettlementEntry $entry */
        return [
            (int) $entry->id, (int) $entry->gateway_settlement_id,
            $entry->payment_intent_id ? (int) $entry->payment_intent_id : null,
            $entry->booking_id ? (int) $entry->booking_id : null,
            $this->safe($entry->reference), $this->safe($entry->result),
            $entry->expected_amount !== null ? (string) ((float) $entry->expected_amount) : null,
            (string) ((float) $entry->gateway_amount), (string) ((float) $entry->gateway_fee),
            $this->safe($entry->expected_currency), $this->safe($entry->gateway_currency),
            $this->safe($entry->local_status), $this->safe($entry->gateway_status),
            $entry->checked_at?->toIso8601String(),
        ];
    }

    public function headings(): array
    {
        return ['entry_id','settlement_id','payment_intent_id','booking_id','reference','result','expected_amount','settled_amount','gateway_fee','expected_currency','gateway_currency','local_status','gateway_status','checked_at'];
    }

    public function getCsvSettings(): array { return ['use_bom'=>true]; }
    private function safe(mixed $value): mixed
    {
        return is_string($value) && preg_match('/^[=+\-@]/u', $value) ? "'".$value : $value;
    }
}
