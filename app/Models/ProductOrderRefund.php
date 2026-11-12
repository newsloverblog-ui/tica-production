<?php

namespace App\Models;

use App\Models\ShopManagement\ProductOrder;
use Illuminate\Database\Eloquent\Model;

class ProductOrderRefund extends Model
{
    protected $fillable = [
        'public_id', 'product_order_id', 'sale_transaction_id', 'requested_by_admin_id',
        'external_reference', 'amount', 'currency', 'reason', 'restore_inventory',
        'status', 'financial_applied_at', 'inventory_restored_at', 'processed_at',
    ];

    protected $casts = [
        'restore_inventory' => 'boolean',
        'financial_applied_at' => 'datetime',
        'inventory_restored_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(ProductOrder::class, 'product_order_id');
    }
}
