<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventAgendaItem extends Model
{
    protected $fillable = [
        'event_id', 'programme_person_id', 'title', 'location', 'description', 'starts_at', 'ends_at', 'sort_order', 'is_visible',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_visible' => 'boolean',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function person()
    {
        return $this->belongsTo(EventProgrammePerson::class, 'programme_person_id');
    }
}
