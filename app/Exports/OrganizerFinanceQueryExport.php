<?php

namespace App\Exports;

use App\Models\Transaction;
use App\Services\OrganizerFinanceStatementService;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class OrganizerFinanceQueryExport implements FromQuery, WithHeadings, WithMapping
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
        $row = app(OrganizerFinanceStatementService::class)->line($transaction);

        return [
            $this->safe($row['id']), $this->safe($row['reference']), $row['occurred_at'], $row['type'],
            $this->safe($row['payment_method']), $row['status'], $row['gross_amount'],
            $row['ticket_sales'], $row['checkout_fee'], $row['organizer_fee'],
            $row['platform_fee'], $row['net_amount'], $row['pre_balance'],
            $row['after_balance'], $this->safe($row['currency']),
        ];
    }

    public function headings(): array
    {
        return [
            'transaction_id', 'reference', 'occurred_at', 'type', 'payment_method',
            'status', 'customer_paid_amount', 'ticket_sales_amount', 'checkout_fee',
            'organizer_paid_fee', 'tica_platform_revenue', 'organizer_revenue', 'pre_balance',
            'after_balance', 'currency_symbol',
        ];
    }

    private function safe(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        return preg_match('/^[=+\-@]/u', $value) ? "'".$value : $value;
    }
}
