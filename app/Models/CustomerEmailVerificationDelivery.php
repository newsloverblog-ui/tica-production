<?php

namespace App\Models;

use App\Casts\PrefixedEncryptedString;
use Illuminate\Database\Eloquent\Model;

class CustomerEmailVerificationDelivery extends Model
{
    protected $fillable = [
        'public_id', 'customer_id', 'email_hash', 'status', 'attempts',
        'dispatch_attempts', 'dispatch_last_attempt_at', 'dispatch_last_error',
        'last_error', 'processing_at', 'sent_at', 'verified_at', 'expires_at', 'alerted_at',
    ];

    protected $hidden = ['email_hash', 'last_error', 'dispatch_last_error'];

    protected $casts = [
        'attempts' => 'integer',
        'dispatch_attempts' => 'integer',
        'dispatch_last_attempt_at' => 'datetime',
        'dispatch_last_error' => 'encrypted',
        'last_error' => PrefixedEncryptedString::class,
        'processing_at' => 'datetime',
        'sent_at' => 'datetime',
        'verified_at' => 'datetime',
        'expires_at' => 'datetime',
        'alerted_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
