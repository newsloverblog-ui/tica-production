<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicaSponsorshipPackage extends Model
{
    protected $fillable = [
        'public_id', 'organizer_id', 'event_id', 'name', 'summary', 'deliverables',
        'price', 'currency', 'available_slots', 'status',
    ];

    protected $casts = [
        'deliverables' => 'array',
        'price' => 'decimal:2',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
