<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketNumberSequence extends Model
{
    protected $fillable = ['event_id', 'ticket_id', 'scope_key', 'next_number'];
}
