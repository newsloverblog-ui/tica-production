<?php

namespace App\Models\Event;

use App\Models\Organizer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use HasFactory;
    protected $fillable = [
      'name',
      'code',
      'type',
      'value',
      'events',
      'start_date',
      'end_date'
      , 'organizer_id', 'max_uses', 'used_count', 'minimum_purchase', 'is_active', 'paused_at'
    ];

    protected $casts = ['is_active' => 'boolean', 'paused_at' => 'datetime', 'minimum_purchase' => 'decimal:2'];

    public function organizer()
    {
      return $this->belongsTo(Organizer::class);
    }
}
