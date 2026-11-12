<?php

namespace App\Models;

use App\Casts\PrefixedEncryptedString;
use App\Models\ShopManagement\ProductOrder;
use Illuminate\Database\Eloquent\Model;

class ProductGatewayRefund extends Model
{
    protected $fillable = [
        'public_id', 'product_payment_intent_id', 'product_order_id', 'sale_transaction_id',
        'requested_by_admin_id', 'gateway', 'gateway_reference', 'gateway_refund_id',
        'gateway_status', 'amount', 'currency', 'reason', 'restore_inventory',
        'status', 'gateway_response', 'last_error', 'requested_at', 'submitted_at',
        'completed_at', 'financial_applied_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'restore_inventory' => 'boolean',
        'gateway_response' => 'encrypted:array',
        'last_error' => PrefixedEncryptedString::class,
        'requested_at' => 'datetime',
        'submitted_at' => 'datetime',
        'completed_at' => 'datetime',
        'financial_applied_at' => 'datetime',
    ];

    public function paymentIntent()
    {
        return $this->belongsTo(ProductPaymentIntent::class, 'product_payment_intent_id');
    }

    public function order()
    {
        return $this->belongsTo(ProductOrder::class, 'product_order_id');
    }
}
