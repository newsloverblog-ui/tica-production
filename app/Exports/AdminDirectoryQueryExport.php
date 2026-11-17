<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AdminDirectoryQueryExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(
        private Builder $records,
        private string $directory
    ) {
    }

    public function query(): Builder
    {
        return $this->records;
    }

    public function headings(): array
    {
        return $this->directory === 'organizers'
            ? ['ID', 'Username', 'Email', 'Phone', 'Account status', 'Email status', 'KYC status', 'Joined']
            : ['ID', 'First name', 'Last name', 'Username', 'Email', 'Phone', 'Account status', 'Email status', 'Joined'];
    }

    public function map($record): array
    {
        $common = [
            (int) $record->id,
        ];

        if ($this->directory === 'organizers') {
            return array_merge($common, [
                $this->safeCell($record->username),
                $this->safeCell($record->email),
                $this->safeCell($record->phone),
                (int) $record->status === 1 ? 'Active' : 'Inactive',
                $record->email_verified_at ? 'Verified' : 'Not verified',
                $this->safeCell($record->kyc_status ?: 'unverified'),
                optional($record->created_at)->toIso8601String(),
            ]);
        }

        return array_merge($common, [
            $this->safeCell($record->fname),
            $this->safeCell($record->lname),
            $this->safeCell($record->username),
            $this->safeCell($record->email),
            $this->safeCell($record->phone),
            (int) $record->status === 1 ? 'Active' : 'Inactive',
            $record->email_verified_at ? 'Verified' : 'Not verified',
            optional($record->created_at)->toIso8601String(),
        ]);
    }

    private function safeCell(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        $value = str_replace(["\r\n", "\r", "\n"], ' ', $value);

        return preg_match('/^[=+\-@]/u', $value) === 1 ? "'".$value : $value;
    }
}
