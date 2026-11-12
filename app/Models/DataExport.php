<?php

namespace App\Models;

use App\Casts\PrefixedEncryptedString;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DataExport extends Model
{
    protected $hidden = [
        'filters', 'last_error', 'dispatch_last_error', 'ready_notification_last_error',
    ];

    protected $fillable = [
        'public_id', 'requested_by_type', 'requested_by_id', 'organizer_id', 'schedule_id',
        'export_type', 'format', 'status', 'filters', 'disk', 'path', 'filename',
        'row_count', 'attempts', 'last_error', 'started_at', 'completed_at',
        'expires_at', 'downloaded_at',
        'dispatch_attempts', 'dispatch_last_attempt_at', 'dispatch_last_error', 'dispatch_alerted_at',
        'ready_notification_delivery_id', 'ready_notification_attempts',
        'ready_notification_last_attempt_at', 'ready_notification_last_error',
        'ready_notification_alerted_at', 'ready_notified_at',
    ];

    protected $casts = [
        'filters' => 'encrypted:array',
        'row_count' => 'integer',
        'attempts' => 'integer',
        'last_error' => PrefixedEncryptedString::class,
        'dispatch_attempts' => 'integer',
        'dispatch_last_attempt_at' => 'datetime',
        'dispatch_last_error' => 'encrypted',
        'dispatch_alerted_at' => 'datetime',
        'ready_notification_delivery_id' => 'integer',
        'ready_notification_attempts' => 'integer',
        'ready_notification_last_attempt_at' => 'datetime',
        'ready_notification_last_error' => 'encrypted',
        'ready_notification_alerted_at' => 'datetime',
        'ready_notified_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
        'downloaded_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $export): void {
            $export->public_id = $export->public_id ?: (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function isDownloadable(): bool
    {
        return $this->status === 'ready'
            && $this->path
            && $this->expires_at
            && $this->expires_at->isFuture();
    }

    public function readyNotificationDelivery()
    {
        return $this->belongsTo(ApplicationMailDelivery::class, 'ready_notification_delivery_id');
    }
}
