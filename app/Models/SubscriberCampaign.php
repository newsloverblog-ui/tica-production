<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriberCampaign extends Model
{
    protected $fillable = [
        'public_id', 'created_by_admin_id', 'subject', 'body', 'status',
        'total_count', 'sent_count', 'failed_count', 'started_at', 'completed_at',
        'dispatch_attempts', 'dispatch_last_attempt_at', 'dispatch_last_error', 'dispatch_alerted_at',
    ];

    protected $hidden = ['body'];

    protected $casts = [
        'body' => 'encrypted',
        'total_count' => 'integer',
        'sent_count' => 'integer',
        'failed_count' => 'integer',
        'dispatch_attempts' => 'integer',
        'dispatch_last_attempt_at' => 'datetime',
        'dispatch_last_error' => 'encrypted',
        'dispatch_alerted_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function deliveries()
    {
        return $this->hasMany(SubscriberCampaignDelivery::class, 'campaign_id');
    }
}
