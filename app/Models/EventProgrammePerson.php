<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventProgrammePerson extends Model
{
    protected $fillable = [
        'event_id', 'kind', 'name', 'role', 'topic', 'bio', 'image', 'sort_order', 'is_visible',
    ];

    protected $casts = ['is_visible' => 'boolean'];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function agendaItems()
    {
        return $this->hasMany(EventAgendaItem::class, 'programme_person_id');
    }
}
