<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeatingRow extends Model
{
    protected $fillable = ['event_id', 'section_id', 'label', 'sort_order'];

    public function section() { return $this->belongsTo(SeatingSection::class, 'section_id'); }
    public function seats() { return $this->hasMany(EventSeat::class, 'row_id')->orderBy('sort_order'); }
}
