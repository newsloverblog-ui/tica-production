<?php

namespace App\Models;

use App\Casts\PrefixedEncryptedString;
use App\Models\Event\Ticket;
use Illuminate\Database\Eloquent\Model;

class EventWaitlistEntry extends Model
{
    protected $fillable = [
        'public_id', 'event_id', 'ticket_id', 'variation_name', 'selection_key',
        'customer_id', 'name', 'email', 'email_hash', 'status', 'consent_version',
        'consented_at', 'joined_at', 'notification_pending_at', 'notified_at',
        'notification_expires_at', 'unsubscribed_at', 'converted_at',
        'processing_at', 'alerted_at', 'attempts', 'last_error',
        'dispatch_attempts', 'dispatch_last_attempt_at', 'dispatch_last_error',
    ];

    protected $hidden = [
        'name', 'email', 'email_hash', 'selection_key', 'dispatch_last_error', 'last_error',
    ];

    protected $casts = [
        'name' => 'encrypted',
        'email' => 'encrypted',
        'consented_at' => 'datetime',
        'joined_at' => 'datetime',
        'notification_pending_at' => 'datetime',
        'notified_at' => 'datetime',
        'notification_expires_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
        'converted_at' => 'datetime',
        'processing_at' => 'datetime',
        'alerted_at' => 'datetime',
        'attempts' => 'integer',
        'dispatch_attempts' => 'integer',
        'dispatch_last_attempt_at' => 'datetime',
        'dispatch_last_error' => 'encrypted',
        'last_error' => PrefixedEncryptedString::class,
    ];

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }
}
