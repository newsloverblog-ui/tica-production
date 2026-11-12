<?php

namespace App\Models;

use App\Casts\PrefixedEncryptedString;
use Illuminate\Database\Eloquent\Model;

class GatewaySettlement extends Model
{
    protected $fillable = [
        'gateway', 'gateway_settlement_id', 'status', 'currency',
        'total_processed', 'total_fees', 'total_amount', 'effective_amount',
        'transaction_count', 'matched_count', 'mismatch_count',
        'reconciliation_status', 'gateway_payload', 'last_error',
        'settlement_date', 'fetched_at', 'reconciled_at',
    ];

    protected $casts = [
        'total_processed' => 'decimal:2',
        'total_fees' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'effective_amount' => 'decimal:2',
        'gateway_payload' => 'encrypted:array',
        'last_error' => PrefixedEncryptedString::class,
        'settlement_date' => 'datetime',
        'fetched_at' => 'datetime',
        'reconciled_at' => 'datetime',
    ];

    public function entries()
    {
        return $this->hasMany(GatewaySettlementEntry::class, 'gateway_settlement_id');
    }
}
