<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicaPromotionEvent extends Model
{
    protected $fillable = ['campaign_id', 'booking_id', 'event_type', 'visitor_hash', 'amount', 'currency', 'occurred_at'];

    protected $casts = ['amount' => 'decimal:2', 'occurred_at' => 'datetime'];

    public function campaign()
    {
        return $this->belongsTo(TicaPromotionCampaign::class, 'campaign_id');
    }
}
