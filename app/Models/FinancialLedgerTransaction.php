<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialLedgerTransaction extends Model
{
    protected $fillable = [
        'public_id', 'reference', 'kind', 'source_type', 'source_id',
        'occurred_at', 'metadata',
    ];

    protected $casts = ['occurred_at' => 'datetime', 'metadata' => 'array'];

    public function entries()
    {
        return $this->hasMany(FinancialLedgerEntry::class);
    }
}
