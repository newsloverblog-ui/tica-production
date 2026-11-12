<?php

namespace App\Models\ShopManagement;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductOrder extends Model
{
  use HasFactory;
  protected $fillable = [
    'user_id',
    'billing_fname',
    'billing_lname',
    'billing_email',
    'billing_phone',
    'billing_country',
    'billing_state',
    'billing_city',
    'billing_zip_code',
    'billing_address',
    'shipping_fname',
    'shipping_lname',
    'shipping_email',
    'shipping_phone',
    'shipping_country',
    'shipping_state',
    'shipping_city',
    'shipping_zip_code',
    'shipping_address',
    'cart_total',
    'discount',
    'tax',
    'tax_percentage',
    'total',
    'method',
    'gateway_type',
    'currency_text',
    'currency_text_position',
    'currency_symbol',
    'currency_symbol_position',
    'order_number',
    'shipping_method',
    'shipping_charge',
    'payment_status',
    'refunded_amount',
    'refund_status',
    'refunded_at',
    'order_status',
    'tnxid',
    'charge_id',
    'invoice_number',
    'receipt',
    'conversation_id',
    'inventory_reserved_at',
    'inventory_released_at',
    'archived_at',
    'archived_by'
  ];

  protected $casts = [
    'inventory_reserved_at' => 'datetime',
    'inventory_released_at' => 'datetime',
    'archived_at' => 'datetime',
    'refunded_at' => 'datetime',
  ];

  //order_items
  public function order_items()
  {
    return $this->hasMany(OrderItem::class, 'product_order_id', 'id');
  }

  public function payment_intent()
  {
    return $this->hasOne(\App\Models\ProductPaymentIntent::class, 'product_order_id', 'id');
  }

  public function gateway_refund()
  {
    return $this->hasOne(\App\Models\ProductGatewayRefund::class, 'product_order_id', 'id');
  }
}
