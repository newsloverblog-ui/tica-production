<?php

namespace App\Services;

use App\Models\ShopManagement\ProductOrder;
use Illuminate\Database\Eloquent\Builder;

class ProductOrderExportQueryService
{
    public function query(array $filters): Builder
    {
        return ProductOrder::query()
            ->whereNull('archived_at')
            ->when(!empty($filters['max_order_id']), fn (Builder $query) =>
                $query->where('id', '<=', (int) $filters['max_order_id']))
            ->when(!empty($filters['from_date']), fn (Builder $query) =>
                $query->whereDate('created_at', '>=', (string) $filters['from_date']))
            ->when(!empty($filters['to_date']), fn (Builder $query) =>
                $query->whereDate('created_at', '<=', (string) $filters['to_date']))
            ->when(!empty($filters['payment_method']), fn (Builder $query) =>
                $query->where('method', (string) $filters['payment_method']))
            ->when(!empty($filters['payment_status']), fn (Builder $query) =>
                $query->where('payment_status', (string) $filters['payment_status']))
            ->when(!empty($filters['order_status']), fn (Builder $query) =>
                $query->where('order_status', (string) $filters['order_status']))
            ->withCount('order_items')
            ->withSum('order_items as item_quantity', 'quantity');
    }
}
