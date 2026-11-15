<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventSeat extends Model
{
    protected $fillable = [
        'public_id', 'event_id', 'section_id', 'row_id', 'ticket_id', 'label', 'status',
        'is_accessible', 'price_override', 'sort_order',
    ];

    protected $casts = ['is_accessible' => 'boolean', 'price_override' => 'decimal:2'];

    public function section() { return $this->belongsTo(SeatingSection::class, 'section_id'); }
    public function row() { return $this->belongsTo(SeatingRow::class, 'row_id'); }
    public function ticket() { return $this->belongsTo(\App\Models\Event\Ticket::class, 'ticket_id'); }
    public function reservation() { return $this->hasOne(SeatReservation::class, 'seat_id'); }
}
