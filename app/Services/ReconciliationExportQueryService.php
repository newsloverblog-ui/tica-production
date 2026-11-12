<?php

namespace App\Services;

use App\Models\GatewaySettlement;
use App\Models\PaymentReconciliationRun;
use App\Models\PaymentReconciliationEntry;
use App\Models\GatewaySettlementEntry;
use Illuminate\Database\Eloquent\Builder;

class ReconciliationExportQueryService
{
    public function runEntries(array $filters): Builder
    {
        return PaymentReconciliationEntry::query()
            ->select([
                'id', 'run_id', 'payment_intent_id', 'booking_id', 'gateway', 'reference',
                'result', 'expected_amount', 'gateway_amount', 'expected_currency',
                'gateway_currency', 'local_status', 'gateway_status', 'checked_at',
            ])
            ->where('run_id', (int) $filters['run_id'])
            ->when(!empty($filters['max_entry_id']), fn (Builder $query) =>
                $query->where('id', '<=', (int) $filters['max_entry_id']))
            ->when(!empty($filters['result']), fn (Builder $query) =>
                $query->where('result', (string) $filters['result']));
    }

    public function settlementEntries(array $filters): Builder
    {
        return GatewaySettlementEntry::query()
            ->select([
                'id', 'gateway_settlement_id', 'payment_intent_id', 'booking_id', 'reference',
                'result', 'expected_amount', 'gateway_amount', 'gateway_fee',
                'expected_currency', 'gateway_currency', 'local_status', 'gateway_status', 'checked_at',
            ])
            ->where('gateway_settlement_id', (int) $filters['settlement_id'])
            ->when(!empty($filters['max_entry_id']), fn (Builder $query) =>
                $query->where('id', '<=', (int) $filters['max_entry_id']))
            ->when(!empty($filters['result']), fn (Builder $query) =>
                $query->where('result', (string) $filters['result']));
    }

    public function runs(array $filters): Builder
    {
        return PaymentReconciliationRun::query()
            ->select([
                'id', 'public_id', 'gateway', 'reconciliation_date', 'status',
                'gateway_count', 'local_count', 'matched_count', 'mismatch_count',
                'started_at', 'completed_at',
            ])
            ->when(!empty($filters['max_run_id']), fn (Builder $query) =>
                $query->where('id', '<=', (int) $filters['max_run_id']))
            ->when(!empty($filters['gateway']), fn (Builder $query) =>
                $query->where('gateway', (string) $filters['gateway']))
            ->when(!empty($filters['status']), fn (Builder $query) =>
                $query->where('status', (string) $filters['status']))
            ->when(!empty($filters['date']), fn (Builder $query) =>
                $query->whereDate('reconciliation_date', (string) $filters['date']));
    }

    public function settlements(array $filters): Builder
    {
        return GatewaySettlement::query()
            ->select([
                'id', 'gateway', 'gateway_settlement_id', 'status', 'currency',
                'total_processed', 'total_fees', 'total_amount', 'effective_amount',
                'transaction_count', 'matched_count', 'mismatch_count',
                'reconciliation_status', 'settlement_date', 'fetched_at', 'reconciled_at',
            ])
            ->when(!empty($filters['max_settlement_id']), fn (Builder $query) =>
                $query->where('id', '<=', (int) $filters['max_settlement_id']))
            ->when(!empty($filters['status']), fn (Builder $query) =>
                $query->where('reconciliation_status', (string) $filters['status']))
            ->when(!empty($filters['date']), fn (Builder $query) =>
                $query->whereDate('settlement_date', (string) $filters['date']));
    }
}
