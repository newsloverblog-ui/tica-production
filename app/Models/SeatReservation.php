<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeatReservation extends Model
{
    protected $fillable = [
        'token', 'event_id', 'ticket_id', 'seat_id', 'status', 'session_hash', 'customer_id', 'buyer_email_hash',
        'booking_id', 'booking_ticket_id', 'expires_at', 'consumed_at', 'released_at',
    ];

    protected $casts = ['expires_at' => 'datetime', 'consumed_at' => 'datetime', 'released_at' => 'datetime'];

    public function seat() { return $this->belongsTo(EventSeat::class, 'seat_id'); }
}
