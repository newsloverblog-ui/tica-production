<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventRegistrationAllocation extends Model
{
    protected $fillable = [
        'public_id', 'form_id', 'event_id', 'organizer_id', 'sponsor_name', 'sponsor_email',
        'sponsor_email_hash', 'capacity', 'status', 'access_token_hash', 'attestation_version',
        'dispatch_token', 'dispatch_attempts', 'dispatch_last_attempt_at', 'dispatch_last_error',
        'attested_at', 'attested_ip_hash', 'expires_at', 'invited_at', 'cancelled_at',
        'delivery_status', 'delivery_attempts', 'processing_at', 'alerted_at', 'last_error',
        'anonymized_at', 'anonymization_reason',
    ];

    protected $hidden = ['sponsor_name', 'sponsor_email', 'sponsor_email_hash', 'access_token_hash', 'dispatch_token', 'attested_ip_hash', 'last_error', 'dispatch_last_error'];

    protected $casts = [
        'sponsor_name' => 'encrypted', 'sponsor_email' => 'encrypted', 'last_error' => 'encrypted',
        'dispatch_token' => 'encrypted', 'dispatch_last_error' => 'encrypted',
        'dispatch_attempts' => 'integer', 'dispatch_last_attempt_at' => 'datetime',
        'capacity' => 'integer', 'delivery_attempts' => 'integer', 'attested_at' => 'datetime',
        'expires_at' => 'datetime', 'invited_at' => 'datetime', 'cancelled_at' => 'datetime',
        'processing_at' => 'datetime', 'alerted_at' => 'datetime', 'anonymized_at' => 'datetime',
    ];

    public function getRouteKeyName(): string { return 'public_id'; }
    public function event() { return $this->belongsTo(Event::class); }
    public function form() { return $this->belongsTo(EventRegistrationForm::class, 'form_id'); }
    public function claims() { return $this->hasMany(EventRegistrationAllocationClaim::class, 'allocation_id'); }
}
