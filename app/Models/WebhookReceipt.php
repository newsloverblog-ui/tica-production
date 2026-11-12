<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WebhookReceipt extends Model
{
    protected $guarded = [];

    protected $casts = [
        'signature_present' => 'boolean',
        'signature_valid' => 'boolean',
        'received_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $receipt): void {
            $receipt->public_id = $receipt->public_id ?: (string) Str::uuid();
        });
    }
}
