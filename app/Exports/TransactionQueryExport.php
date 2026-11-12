<?php

namespace App\Exports;

use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class TransactionQueryExport implements FromQuery, WithHeadings, WithMapping, WithCustomCsvSettings
{
    public function __construct(private readonly Builder $transactions)
    {
    }

    public function query(): Builder
    {
        return $this->transactions;
    }

    public function map($transaction): array
    {
        /** @var Transaction $transaction */
        return [
            (int) $transaction->id,
            $this->safe($transaction->transcation_id),
            $this->safe($transaction->gateway),
            $this->safe($transaction->gateway_reference),
            $transaction->booking_id ? $this->safe($transaction->booking_id) : null,
            $transaction->customer_id ? (int) $transaction->customer_id : null,
            $transaction->organizer_id ? (int) $transaction->organizer_id : null,
            $this->safe($transaction->organizer?->username),
            (int) $transaction->transcation_type,
            $this->typeLabel((int) $transaction->transcation_type),
            (int) $transaction->payment_status,
            $this->statusLabel((int) $transaction->payment_status),
            $this->safe($transaction->transcation_type == 3
                ? ($transaction->method?->name ?: $transaction->payment_method)
                : $transaction->payment_method),
            (string) ((float) $transaction->grand_total),
            (string) ((float) $transaction->commission),
            (string) ((float) $transaction->tax),
            (string) ((float) $transaction->pre_balance),
            (string) ((float) $transaction->after_balance),
            $this->safe($transaction->currency_symbol),
            $this->safe($transaction->currency_symbol_position),
            $transaction->created_at?->toIso8601String(),
            $transaction->updated_at?->toIso8601String(),
        ];
    }

    public function headings(): array
    {
        return [
            'ledger_id', 'transaction_reference', 'gateway', 'gateway_reference',
            'booking_or_order_id', 'customer_id', 'organizer_id', 'organizer_username',
            'transaction_type_code', 'transaction_type', 'payment_status_code', 'payment_status',
            'payment_method', 'gross_amount', 'commission_amount', 'tax_amount',
            'balance_before', 'balance_after', 'currency_symbol', 'currency_symbol_position',
            'created_at', 'updated_at',
        ];
    }

    public function getCsvSettings(): array
    {
        return ['use_bom' => true];
    }

    private function typeLabel(int $type): string
    {
        return match ($type) {
            1 => 'event_booking', 2 => 'product_order', 3 => 'withdrawal',
            4 => 'balance_credit', 5 => 'balance_debit', 6 => 'event_refund',
            7 => 'product_refund', default => 'unknown',
        };
    }

    private function statusLabel(int $status): string
    {
        return match ($status) { 1 => 'paid', 2 => 'declined', default => 'unpaid' };
    }

    private function safe(mixed $value): mixed
    {
        if (!is_string($value)) return $value;
        return preg_match('/^[=+\-@]/u', $value) ? "'".$value : $value;
    }
}
