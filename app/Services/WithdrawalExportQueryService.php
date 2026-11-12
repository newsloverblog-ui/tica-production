<?php

namespace App\Services;

use App\Models\Withdraw;
use Illuminate\Database\Eloquent\Builder;

class WithdrawalExportQueryService
{
    public function query(array $filters, ?int $organizerId = null): Builder
    {
        return Withdraw::query()
            ->when($organizerId, fn (Builder $query, int $id) => $query->where('organizer_id', $id))
            ->when(!empty($filters['max_withdrawal_id']), fn (Builder $query) =>
                $query->where('id', '<=', (int) $filters['max_withdrawal_id']))
            ->when(!empty($filters['withdrawal_reference']), fn (Builder $query) =>
                $query->where('withdraw_id', 'like', '%'.(string) $filters['withdrawal_reference'].'%'))
            ->when(($filters['status'] ?? '') !== '', fn (Builder $query) =>
                $query->where('status', (int) $filters['status']))
            ->when(!empty($filters['from_date']), fn (Builder $query) =>
                $query->whereDate('created_at', '>=', (string) $filters['from_date']))
            ->when(!empty($filters['to_date']), fn (Builder $query) =>
                $query->whereDate('created_at', '<=', (string) $filters['to_date']))
            ->with([
                'method:id,name',
                'organizer:id,username',
                'payoutAccount:id,public_id,bank_name,account_last4,currency',
            ]);
    }
}
