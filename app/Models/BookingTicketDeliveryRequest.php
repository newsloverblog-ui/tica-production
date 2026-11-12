<?php

namespace App\Models;

use App\Casts\PrefixedEncryptedString;
use App\Models\Event\Booking;
use Illuminate\Database\Eloquent\Model;

class BookingTicketDeliveryRequest extends Model
{
    protected $fillable = [
        'public_id', 'booking_id', 'requested_by_type', 'requested_by_id',
        'recipient_email_hash', 'status', 'attempts',
        'dispatch_attempts', 'dispatch_last_attempt_at', 'dispatch_last_error',
        'last_error',
        'processing_at', 'sent_at', 'alerted_at',
    ];

    protected $hidden = ['recipient_email_hash', 'dispatch_last_error', 'last_error'];

    protected $casts = [
        'attempts' => 'integer',
        'dispatch_attempts' => 'integer',
        'dispatch_last_attempt_at' => 'datetime',
        'dispatch_last_error' => 'encrypted',
        'last_error' => PrefixedEncryptedString::class,
        'processing_at' => 'datetime',
        'sent_at' => 'datetime',
        'alerted_at' => 'datetime',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
