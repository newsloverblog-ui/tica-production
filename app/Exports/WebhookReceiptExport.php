<?php

namespace App\Exports;

use App\Models\WebhookReceipt;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class WebhookReceiptExport implements FromQuery, WithHeadings, WithMapping, WithCustomCsvSettings
{
    public function __construct(private readonly Builder $receipts) {}

    public function query(): Builder { return $this->receipts; }

    public function map($receipt): array
    {
        /** @var WebhookReceipt $receipt */
        return [
            (int) $receipt->id,
            $this->safe($receipt->public_id),
            $this->safe($receipt->gateway),
            $this->safe($receipt->event_type),
            $this->safe($receipt->body_hash),
            $this->safe($receipt->request_id),
            $receipt->signature_present ? 'yes' : 'no',
            $receipt->signature_valid === null ? 'unknown' : ($receipt->signature_valid ? 'valid' : 'invalid'),
            $this->safe($receipt->outcome),
            $receipt->http_status !== null ? (int) $receipt->http_status : null,
            $receipt->processing_ms !== null ? (int) $receipt->processing_ms : null,
            $this->safe($receipt->error_code),
            $receipt->received_at?->toIso8601String(),
            $receipt->completed_at?->toIso8601String(),
        ];
    }

    public function headings(): array
    {
        return [
            'receipt_id', 'receipt_public_id', 'gateway', 'event_type', 'body_sha256',
            'request_id', 'signature_present', 'signature_result', 'outcome', 'http_status',
            'processing_ms', 'error_code', 'received_at', 'completed_at',
        ];
    }

    public function getCsvSettings(): array { return ['use_bom' => true]; }

    private function safe(mixed $value): mixed
    {
        return is_string($value) && preg_match('/^[=+\-@]/u', $value) ? "'".$value : $value;
    }
}
