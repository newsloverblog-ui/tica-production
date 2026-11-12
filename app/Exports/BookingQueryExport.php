<?php

namespace App\Exports;

use App\Services\BookingExportFieldPolicyService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class BookingQueryExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(
        private Builder $bookingQuery,
        private string $currencySymbol,
        private string $currencyPosition,
        private array $fieldKeys
    ) {
        $this->fieldKeys = array_values(array_intersect(
            array_keys(BookingExportFieldPolicyService::FIELDS),
            $this->fieldKeys
        ));
    }

    public function query(): Builder
    {
        return $this->bookingQuery;
    }

    public function map($booking): array
    {
        $refundedAmount = (float) ($booking->snapshot_refunded_amount ?? 0);
        $chargedAmount = (float) ($booking->ledger_charged_amount ?? 0);
        $refundStatus = $refundedAmount <= 0
            ? 'Not refunded'
            : ($chargedAmount > 0 && $refundedAmount + 0.005 >= $chargedAmount ? 'Refunded' : 'Partially refunded');

        $values = [
            'booking_id' => $this->safeCell($booking->booking_id),
            'event' => $this->safeCell($booking->title),
            'customer_name' => $this->safeCell(trim($booking->customerfname . ' ' . $booking->customerlname)),
            'discount' => $this->money($booking->discount),
            'early_bird_discount' => $this->money($booking->early_bird_discount ?: 0),
            'quantity' => (int) $booking->quantity,
            'total' => $this->money($booking->price ?: 0),
            'attendee_name' => $this->safeCell(trim($booking->fname . ' ' . $booking->lname)),
            'email' => $this->safeCell($booking->email),
            'phone' => $this->safeCell($booking->phone),
            'city' => $this->safeCell($booking->city),
            'state' => $this->safeCell($booking->state),
            'country' => $this->safeCell($booking->country),
            'zip_code' => $this->safeCell($booking->zip_code),
            'payment_method' => $this->safeCell($booking->paymentMethod),
            'payment_status' => $this->safeCell($booking->paymentStatus),
            'ledger_gateway' => $this->safeCell($booking->ledger_gateway),
            'gateway_reference' => $this->safeCell($booking->ledger_reference),
            'charged_amount' => $this->money($chargedAmount),
            'refunded_amount' => $this->money($refundedAmount),
            'refund_status' => $refundStatus,
            'settlement_id' => $this->safeCell($booking->settlement_id),
            'settlement_status' => $this->safeCell($booking->settlement_status),
            'settlement_date' => $booking->settlement_date ? CarbonImmutable::parse($booking->settlement_date)->toIso8601String() : null,
            'gateway_fee' => $this->money((float) ($booking->settlement_gateway_fee ?? 0)),
            'date' => optional($booking->created_at)->toIso8601String(),
        ];

        return array_map(fn (string $key) => $values[$key], $this->fieldKeys);
    }

    public function headings(): array
    {
        return array_map(
            fn (string $key) => BookingExportFieldPolicyService::FIELDS[$key],
            $this->fieldKeys
        );
    }

    private function money($value): string
    {
        $amount = number_format((float) $value, 2, '.', '');
        return $this->currencyPosition === 'right'
            ? $amount . $this->currencySymbol
            : $this->currencySymbol . $amount;
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
