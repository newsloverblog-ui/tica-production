<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;

class TransactionExportQueryService
{
    public function query(array $filters, ?int $organizerId = null): Builder
    {
        return Transaction::query()
            ->when($organizerId, fn (Builder $query, int $id) => $query->where('organizer_id', $id))
            ->when(!empty($filters['max_transaction_id']), fn (Builder $query) =>
                $query->where('id', '<=', (int) $filters['max_transaction_id']))
            ->when(!empty($filters['transaction_reference']), fn (Builder $query) =>
                $query->where('transcation_id', 'like', '%'.(string) $filters['transaction_reference'].'%'))
            ->when(($filters['transaction_type'] ?? '') !== '', fn (Builder $query) =>
                $query->where('transcation_type', (int) $filters['transaction_type']))
            ->when(($filters['payment_status'] ?? '') !== '', fn (Builder $query) =>
                $query->where('payment_status', (int) $filters['payment_status']))
            ->when(!empty($filters['from_date']), fn (Builder $query) =>
                $query->whereDate('created_at', '>=', (string) $filters['from_date']))
            ->when(!empty($filters['to_date']), fn (Builder $query) =>
                $query->whereDate('created_at', '<=', (string) $filters['to_date']))
            ->with(['organizer:id,username', 'method:id,name']);
    }
}
