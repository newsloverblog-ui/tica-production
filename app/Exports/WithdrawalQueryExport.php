<?php

namespace App\Exports;

use App\Models\Withdraw;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class WithdrawalQueryExport implements FromQuery, WithHeadings, WithMapping, WithCustomCsvSettings
{
    public function __construct(private readonly Builder $withdrawals, private readonly string $fallbackCurrency)
    {
    }

    public function query(): Builder
    {
        return $this->withdrawals;
    }

    public function map($withdrawal): array
    {
        /** @var Withdraw $withdrawal */
        return [
            (int) $withdrawal->id,
            $this->safe($withdrawal->withdraw_id),
            (int) $withdrawal->organizer_id,
            $this->safe($withdrawal->organizer?->username),
            $withdrawal->method_id ? (int) $withdrawal->method_id : null,
            $this->safe($withdrawal->method?->name),
            (string) ((float) $withdrawal->amount),
            (string) ((float) $withdrawal->total_charge),
            (string) ((float) $withdrawal->payable_amount),
            $this->safe($withdrawal->payoutAccount?->currency ?: $this->fallbackCurrency),
            (int) $withdrawal->status,
            $this->statusLabel((int) $withdrawal->status),
            $this->safe($withdrawal->payout_reference),
            $withdrawal->payout_account_id ? (int) $withdrawal->payout_account_id : null,
            $this->safe($withdrawal->payoutAccount?->public_id),
            $this->safe($withdrawal->payoutAccount?->bank_name),
            $withdrawal->payoutAccount?->account_last4
                ? '******'.$this->safe($withdrawal->payoutAccount->account_last4)
                : null,
            $withdrawal->created_at?->toIso8601String(),
            $withdrawal->reviewed_at?->toIso8601String(),
            $withdrawal->paid_at?->toIso8601String(),
            $withdrawal->updated_at?->toIso8601String(),
        ];
    }

    public function headings(): array
    {
        return [
            'withdrawal_id', 'withdrawal_reference', 'organizer_id', 'organizer_username',
            'payment_method_id', 'payment_method', 'requested_amount', 'fee_amount',
            'payable_amount', 'currency', 'status_code', 'status', 'payout_reference',
            'payout_account_id', 'payout_account_public_id', 'bank_name', 'account_number_masked',
            'requested_at', 'reviewed_at', 'paid_at', 'updated_at',
        ];
    }

    public function getCsvSettings(): array
    {
        return ['use_bom' => true];
    }

    private function statusLabel(int $status): string
    {
        return match ($status) {
            Withdraw::STATUS_PENDING => 'pending',
            Withdraw::STATUS_PAID => 'paid',
            Withdraw::STATUS_REJECTED => 'rejected',
            Withdraw::STATUS_HELD => 'held',
            Withdraw::STATUS_PROCESSING => 'processing',
            default => 'unknown',
        };
    }

    private function safe(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        return preg_match('/^[=+\-@]/u', $value) ? "'".$value : $value;
    }
}
