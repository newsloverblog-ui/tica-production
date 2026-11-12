<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventRegistrationSession extends Model
{
    protected $fillable = [
        'public_id', 'form_id', 'event_id', 'organizer_id', 'ticket_id', 'title', 'description',
        'location', 'starts_at', 'ends_at', 'capacity', 'is_required', 'status', 'cancelled_at',
    ];

    protected $casts = [
        'starts_at' => 'datetime', 'ends_at' => 'datetime', 'capacity' => 'integer',
        'is_required' => 'boolean', 'cancelled_at' => 'datetime',
    ];

    public function getRouteKeyName(): string { return 'public_id'; }
    public function form() { return $this->belongsTo(EventRegistrationForm::class, 'form_id'); }
    public function event() { return $this->belongsTo(Event::class); }
    public function ticket() { return $this->belongsTo(\App\Models\Event\Ticket::class, 'ticket_id'); }
    public function selections() { return $this->hasMany(EventRegistrationSessionSelection::class, 'session_id'); }
}
