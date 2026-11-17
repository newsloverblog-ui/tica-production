<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialLedgerAccount extends Model
{
    protected $fillable = [
        'public_id', 'owner_type', 'owner_id', 'code', 'name', 'category',
        'normal_balance', 'currency', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];
}
