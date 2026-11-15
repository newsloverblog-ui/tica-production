<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketNumberAllocation extends Model
{
    protected $fillable = [
        'event_id', 'ticket_id', 'booking_ticket_id', 'number_value', 'ticket_number',
        'status', 'allocated_at', 'invalidated_at',
    ];

    protected $casts = ['allocated_at' => 'datetime', 'invalidated_at' => 'datetime'];
}
