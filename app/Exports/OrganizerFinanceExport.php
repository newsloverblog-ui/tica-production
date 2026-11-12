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
            $row['platform_fee'], $row['net_amount'], $row['pre_balance'],
            $row['after_balance'], $row['currency'],
        ];
    }

    public function headings(): array
    {
        return [
            'transaction_id', 'reference', 'occurred_at', 'type', 'payment_method',
            'status', 'gross_amount', 'platform_fee', 'net_amount', 'pre_balance',
            'after_balance', 'currency_symbol',
        ];
    }
}
