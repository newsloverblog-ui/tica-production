<?php

namespace App\Models;

use App\Casts\PrefixedEncryptedString;
use Illuminate\Database\Eloquent\Model;

class BookingDelivery extends Model
{
    protected $fillable = [
        'booking_id', 'kind', 'channel', 'recipient_hash', 'provider_reference',
        'status', 'attempts', 'dispatch_attempts', 'dispatch_last_attempt_at',
        'dispatch_last_error', 'last_error',
        'processing_at', 'sent_at', 'alerted_at',
    ];

    protected $hidden = ['recipient_hash', 'dispatch_last_error'];

    protected $casts = [
        'dispatch_attempts' => 'integer',
        'dispatch_last_attempt_at' => 'datetime',
        'dispatch_last_error' => 'encrypted',
        'last_error' => PrefixedEncryptedString::class,
        'processing_at' => 'datetime',
        'sent_at' => 'datetime',
        'alerted_at' => 'datetime',
    ];
}
