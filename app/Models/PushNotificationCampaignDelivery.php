<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PushNotificationCampaignDelivery extends Model
{
    protected $fillable = [
        'campaign_id', 'guest_id', 'status', 'attempts', 'last_error',
        'processing_at', 'sent_at', 'failed_at', 'alerted_at',
    ];

    protected $hidden = ['last_error'];

    protected $casts = [
        'last_error' => 'encrypted',
        'attempts' => 'integer',
        'processing_at' => 'datetime',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
        'alerted_at' => 'datetime',
    ];

    public function campaign()
    {
        return $this->belongsTo(PushNotificationCampaign::class, 'campaign_id');
    }
}
