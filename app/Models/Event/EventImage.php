<?php

namespace App\Models\Event;

use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventImage extends Model
{
    use HasFactory;

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
