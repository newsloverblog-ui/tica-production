<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialLedgerEntry extends Model
{
    protected $fillable = [
        'financial_ledger_transaction_id', 'financial_ledger_account_id',
        'direction', 'amount_minor', 'currency',
    ];

    protected $casts = ['amount_minor' => 'integer'];
}
