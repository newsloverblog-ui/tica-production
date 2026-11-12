<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventRegistrationAllocationClaim extends Model
{
    protected $fillable = [
        'public_id', 'allocation_id', 'event_id', 'form_id', 'name', 'email', 'email_hash',
        'token_hash', 'source_attestation_version', 'source_attested_at', 'source_attested_ip_hash',
        'dispatch_token', 'dispatch_attempts', 'dispatch_last_attempt_at', 'dispatch_last_error',
        'status', 'submission_id', 'expires_at', 'invited_at', 'completed_at',
        'cancelled_at', 'delivery_status', 'delivery_attempts', 'processing_at', 'alerted_at', 'last_error',
        'anonymized_at', 'anonymization_reason',
    ];

    protected $hidden = ['name', 'email', 'email_hash', 'token_hash', 'dispatch_token', 'source_attested_ip_hash', 'last_error', 'dispatch_last_error'];

    protected $casts = [
        'name' => 'encrypted', 'email' => 'encrypted', 'last_error' => 'encrypted',
        'dispatch_token' => 'encrypted', 'dispatch_last_error' => 'encrypted',
        'dispatch_attempts' => 'integer', 'dispatch_last_attempt_at' => 'datetime',
        'source_attested_at' => 'datetime', 'expires_at' => 'datetime', 'invited_at' => 'datetime', 'completed_at' => 'datetime',
        'cancelled_at' => 'datetime', 'processing_at' => 'datetime', 'alerted_at' => 'datetime', 'anonymized_at' => 'datetime',
        'delivery_attempts' => 'integer',
    ];

    public function getRouteKeyName(): string { return 'public_id'; }
    public function allocation() { return $this->belongsTo(EventRegistrationAllocation::class, 'allocation_id'); }
    public function event() { return $this->belongsTo(Event::class); }
    public function form() { return $this->belongsTo(EventRegistrationForm::class, 'form_id'); }
    public function submission() { return $this->belongsTo(EventRegistrationSubmission::class, 'submission_id'); }
}
