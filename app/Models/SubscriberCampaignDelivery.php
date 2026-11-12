<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriberCampaignDelivery extends Model
{
    protected $fillable = [
        'campaign_id', 'subscriber_id', 'recipient_hash', 'recipient_email', 'status',
        'attempts', 'last_error', 'processing_at', 'sent_at', 'failed_at', 'alerted_at',
    ];

    protected $hidden = ['recipient_hash', 'recipient_email', 'last_error'];

    protected $casts = [
        'recipient_email' => 'encrypted',
        'last_error' => 'encrypted',
        'attempts' => 'integer',
        'processing_at' => 'datetime',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
        'alerted_at' => 'datetime',
    ];

    public function campaign()
    {
        return $this->belongsTo(SubscriberCampaign::class, 'campaign_id');
    }
}
