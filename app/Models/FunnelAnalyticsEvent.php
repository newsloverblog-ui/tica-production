<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class FunnelAnalyticsEvent extends Model
{
    protected $fillable = [
        'public_id', 'event_name', 'source', 'event_id', 'booking_id',
        'payment_intent_id', 'organizer_id', 'customer_id', 'session_hash',
        'dedupe_key', 'properties', 'occurred_at', 'page_key', 'referrer_host',
    ];

    protected $casts = [
        'properties' => 'encrypted:array',
        'occurred_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $event): void {
            $event->public_id = $event->public_id ?: (string) Str::uuid();
        });
    }

    public function scopePageViews(Builder $query): Builder
    {
        return $query->where('event_name', 'page_view')->where('source', 'browser');
    }
}
