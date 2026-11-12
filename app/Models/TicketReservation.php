<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketReservation extends Model
{
    protected $fillable = [
        'token', 'event_id', 'ticket_id', 'variation_name', 'quantity',
        'status', 'session_hash', 'customer_id', 'expires_at',
        'buyer_email_hash',
        'buyer_email_hash_version',
        'consumed_at', 'released_at',
    ];

    protected $hidden = ['session_hash', 'buyer_email_hash', 'buyer_email_hash_version'];

    protected $casts = [
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
        'released_at' => 'datetime',
    ];
}

