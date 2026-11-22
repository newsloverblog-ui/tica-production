<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class OrganizerFinanceExport implements FromCollection, WithHeadings, WithMapping
{
    private Collection $rows;

    public function __construct(Collection $rows)
    {
        $this->rows = $rows;
    }

    public function collection()
    {
        return $this->rows;
    }

    public function map($row): array
    {
        return [
            $row['id'], $row['reference'], $row['occurred_at'], $row['type'],
            $row['payment_method'], $row['status'], $row['gross_amount'],
            $row['ticket_sales'], $row['checkout_fee'], $row['organizer_fee'],
            $row['platform_fee'], $row['net_amount'], $row['pre_balance'],
            $row['after_balance'], $row['currency'],
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
}
