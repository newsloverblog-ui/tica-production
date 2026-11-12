<?php

namespace App\Models;

use App\Casts\PrefixedEncryptedString;
use App\Models\ShopManagement\ProductOrder;
use Illuminate\Database\Eloquent\Model;

class ProductPaymentIntent extends Model
{
    protected $fillable = [
        'gateway', 'reference', 'product_order_id', 'customer_id', 'status',
        'amount', 'currency', 'email', 'authorization_url', 'gateway_transaction_id',
        'verification_attempts', 'last_verified_at', 'expires_at', 'processed_at', 'last_error',
    ];

    protected $casts = [
        'authorization_url' => 'encrypted',
        'email' => PrefixedEncryptedString::class,
        'last_error' => PrefixedEncryptedString::class,
        'amount' => 'decimal:2',
        'verification_attempts' => 'integer',
        'last_verified_at' => 'datetime',
        'expires_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(ProductOrder::class, 'product_order_id');
    }
}
