<?php

namespace App\Models\Event;

use App\Casts\PrefixedEncryptedString;
use Illuminate\Database\Eloquent\Model;

class BookingTicketTransfer extends Model
{
    protected $fillable = [
        'booking_ticket_id', 'from_customer_id', 'recipient_name', 'recipient_email',
        'token_hash', 'dispatch_token', 'dispatch_attempts', 'dispatch_last_attempt_at',
        'dispatch_last_error', 'status', 'expires_at', 'accepted_by_customer_id',
        'accepted_at', 'cancelled_at', 'delivery_status', 'delivery_attempts',
        'last_error', 'processing_at', 'sent_at', 'alerted_at',
    ];

    protected $hidden = ['token_hash', 'dispatch_token', 'dispatch_last_error'];

    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'processing_at' => 'datetime',
        'sent_at' => 'datetime',
        'alerted_at' => 'datetime',
        'delivery_attempts' => 'integer',
        'dispatch_token' => 'encrypted',
        'dispatch_attempts' => 'integer',
        'dispatch_last_attempt_at' => 'datetime',
        'dispatch_last_error' => 'encrypted',
        'last_error' => PrefixedEncryptedString::class,
    ];

    public function ticket()
    {
        return $this->belongsTo(BookingTicket::class, 'booking_ticket_id');
    }
}
