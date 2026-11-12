<?php

namespace App\Models;

use App\Casts\PrefixedEncryptedString;
use Illuminate\Database\Eloquent\Model;

class PaymentReconciliationRun extends Model
{
    protected $fillable = [
        'public_id', 'gateway', 'reconciliation_date', 'status',
        'gateway_count', 'local_count', 'matched_count', 'mismatch_count',
        'last_error', 'started_at', 'completed_at',
    ];

    protected $casts = [
        'reconciliation_date' => 'date',
        'last_error' => PrefixedEncryptedString::class,
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function entries()
    {
        return $this->hasMany(PaymentReconciliationEntry::class, 'run_id');
    }
}
