<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventGuestInvitation extends Model
{
    protected $fillable = [
        'public_id', 'event_id', 'organizer_id', 'name', 'email', 'email_hash',
        'party_size', 'status', 'delivery_status', 'token_hash', 'dispatch_token',
        'dispatch_attempts', 'dispatch_last_attempt_at', 'dispatch_last_error',
        'source_attestation_version', 'source_attested_at', 'source_attested_ip_hash',
        'source_import_id', 'source_import_row',
        'invited_at', 'expires_at', 'responded_at', 'rsvp_consent_at',
        'marketing_consent_at', 'cancelled_at', 'checked_in_at',
        'checked_in_by_organizer_id', 'delivery_attempts', 'processing_at',
        'alerted_at', 'last_error', 'anonymized_at', 'anonymization_reason',
    ];

    protected $hidden = [
        'name', 'email', 'email_hash', 'token_hash', 'dispatch_token',
        'last_error', 'dispatch_last_error',
    ];

    protected $casts = [
        'name' => 'encrypted',
        'email' => 'encrypted',
        'last_error' => 'encrypted',
        'dispatch_token' => 'encrypted',
        'dispatch_last_error' => 'encrypted',
        'party_size' => 'integer',
        'dispatch_attempts' => 'integer',
        'dispatch_last_attempt_at' => 'datetime',
        'source_attested_at' => 'datetime',
        'invited_at' => 'datetime',
        'expires_at' => 'datetime',
        'responded_at' => 'datetime',
        'rsvp_consent_at' => 'datetime',
        'marketing_consent_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'checked_in_at' => 'datetime',
        'processing_at' => 'datetime',
        'alerted_at' => 'datetime',
        'anonymized_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function organizer()
    {
        return $this->belongsTo(Organizer::class);
    }
}
