<?php

namespace App\Models\Event;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class BookingTicketEvent extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['before' => 'array', 'after' => 'array', 'created_at' => 'datetime'];

    protected static function booted()
    {
        static::updating(fn () => throw new LogicException('Ticket history is immutable.'));
        static::deleting(fn () => throw new LogicException('Ticket history is immutable.'));
    }

    public function ticket()
    {
        return $this->belongsTo(BookingTicket::class, 'booking_ticket_id');
    }
}
