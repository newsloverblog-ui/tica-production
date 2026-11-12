<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicaPromotionCampaign extends Model
{
    protected $fillable = ['organizer_id', 'event_id', 'plan_id', 'payment_transaction_id', 'approved_by', 'status', 'headline', 'body', 'destination_url', 'creative_path', 'price', 'currency', 'paid_at', 'starts_at', 'ends_at', 'approved_at', 'admin_notes'];

    protected $casts = ['price' => 'decimal:2', 'paid_at' => 'datetime', 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'approved_at' => 'datetime'];

    public function organizer() { return $this->belongsTo(Organizer::class); }
    public function event() { return $this->belongsTo(Event::class); }
    public function plan() { return $this->belongsTo(TicaPromotionPlan::class, 'plan_id'); }
    public function paymentTransaction() { return $this->belongsTo(Transaction::class, 'payment_transaction_id'); }
}
