<?php

namespace App\Exports;

use App\Models\ShopManagement\ProductOrder;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ProductOrderQueryExport implements FromQuery, WithHeadings, WithMapping, WithCustomCsvSettings
{
    public function __construct(private readonly Builder $orders)
    {
    }

    public function query(): Builder
    {
        return $this->orders;
    }

    public function map($order): array
    {
        /** @var ProductOrder $order */
        return [
            (int) $order->id,
            $this->safe($order->order_number),
            $order->user_id ? (int) $order->user_id : null,
            $this->safe(trim((string) $order->billing_fname.' '.(string) $order->billing_lname)),
            $this->safe($order->billing_email),
            $this->safe($order->billing_phone),
            $this->safe($order->method),
            $this->safe($order->payment_status),
            $this->safe($order->order_status),
            (string) ((float) ($order->cart_total ?? 0)),
            (string) ((float) ($order->discount ?? 0)),
            (string) ((float) ($order->tax ?? 0)),
            (string) ((float) ($order->shipping_charge ?? 0)),
            (string) ((float) ($order->total ?? 0)),
            (string) ((float) ($order->refunded_amount ?? 0)),
            $this->safe($order->currency_text ?: $order->currency_symbol),
            (int) ($order->order_items_count ?? 0),
            (int) ($order->item_quantity ?? 0),
            $order->created_at?->toIso8601String(),
            $order->updated_at?->toIso8601String(),
        ];
    }

    public function headings(): array
    {
        return [
            'order_id', 'order_number', 'customer_id', 'customer_name', 'customer_email',
            'customer_phone', 'payment_method', 'payment_status', 'order_status',
            'cart_amount', 'discount_amount', 'tax_amount', 'shipping_amount', 'total_amount',
            'refunded_amount', 'currency', 'line_item_count', 'item_quantity', 'created_at', 'updated_at',
        ];
    }

    public function getCsvSettings(): array
    {
        return ['use_bom' => true];
    }

    private function safe(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        return preg_match('/^[=+\-@]/u', $value) ? "'".$value : $value;
    }
}
