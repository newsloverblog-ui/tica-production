<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicaPromotionCampaign extends Model
{
    protected $fillable = [
        'reference', 'organizer_id', 'event_id', 'plan_id', 'payment_transaction_id', 'approved_by', 'status',
        'payment_status', 'payment_reference', 'placement', 'headline', 'body', 'destination_url', 'creative_path',
        'priority', 'impressions', 'clicks', 'attributed_orders', 'attributed_tickets', 'attributed_revenue',
        'price', 'currency', 'paid_at', 'starts_at', 'ends_at', 'approved_at', 'paused_at', 'cancelled_at',
        'refunded_at', 'admin_notes', 'change_request', 'metadata',
    ];

    protected $casts = [
        'price' => 'decimal:2', 'attributed_revenue' => 'decimal:2', 'priority' => 'integer',
        'impressions' => 'integer', 'clicks' => 'integer', 'attributed_orders' => 'integer',
        'attributed_tickets' => 'integer', 'paid_at' => 'datetime', 'starts_at' => 'datetime',
        'ends_at' => 'datetime', 'approved_at' => 'datetime', 'paused_at' => 'datetime',
        'cancelled_at' => 'datetime', 'refunded_at' => 'datetime', 'metadata' => 'array',
    ];

    public function organizer() { return $this->belongsTo(Organizer::class); }
    public function event() { return $this->belongsTo(Event::class); }
    public function plan() { return $this->belongsTo(TicaPromotionPlan::class, 'plan_id'); }
    public function paymentTransaction() { return $this->belongsTo(Transaction::class, 'payment_transaction_id'); }
    public function analyticsEvents() { return $this->hasMany(TicaPromotionEvent::class, 'campaign_id'); }

    public function getClickThroughRateAttribute(): float
    {
        return $this->impressions > 0 ? round(($this->clicks / $this->impressions) * 100, 2) : 0.0;
    }

    public function getConversionRateAttribute(): float
    {
        return $this->clicks > 0 ? round(($this->attributed_orders / $this->clicks) * 100, 2) : 0.0;
    }
}
