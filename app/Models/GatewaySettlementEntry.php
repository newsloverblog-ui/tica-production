<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GatewaySettlementEntry extends Model
{
    protected $fillable = [
        'gateway_settlement_id', 'payment_intent_id', 'booking_id', 'reference',
        'result', 'expected_amount', 'gateway_amount', 'gateway_fee',
        'expected_currency', 'gateway_currency', 'local_status', 'gateway_status',
        'details', 'checked_at',
    ];

    protected $casts = [
        'expected_amount' => 'decimal:2',
        'gateway_amount' => 'decimal:2',
        'gateway_fee' => 'decimal:2',
        'details' => 'encrypted:array',
        'checked_at' => 'datetime',
    ];
}
