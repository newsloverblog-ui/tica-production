<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeatingSection extends Model
{
    protected $fillable = [
        'event_id', 'name', 'kind', 'ticket_id', 'price_override', 'capacity', 'sort_order', 'layout',
    ];

    protected $casts = ['layout' => 'array', 'price_override' => 'decimal:2'];

    public function rows() { return $this->hasMany(SeatingRow::class, 'section_id')->orderBy('sort_order'); }
    public function seats() { return $this->hasMany(EventSeat::class, 'section_id')->orderBy('sort_order'); }
}
