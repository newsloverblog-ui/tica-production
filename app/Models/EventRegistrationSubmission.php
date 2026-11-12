<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventRegistrationSubmission extends Model
{
    protected $fillable = [
        'public_id', 'form_id', 'event_id', 'organizer_id', 'customer_id', 'booking_id',
        'form_version', 'status', 'primary_name', 'primary_email', 'primary_email_hash',
        'attendee_count', 'consent_version', 'consented_at', 'source_ip_hash',
        'source_import_id', 'source_import_key', 'source_allocation_claim_id',
        'submitted_at', 'reviewed_by_organizer_id', 'reviewed_at', 'review_note',
        'anonymized_at', 'anonymization_reason',
    ];

    protected $hidden = ['primary_name', 'primary_email', 'primary_email_hash', 'source_ip_hash', 'review_note'];

    protected $casts = [
        'primary_name' => 'encrypted',
        'primary_email' => 'encrypted',
        'review_note' => 'encrypted',
        'form_version' => 'integer',
        'attendee_count' => 'integer',
        'consented_at' => 'datetime',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'anonymized_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function form()
    {
        return $this->belongsTo(EventRegistrationForm::class, 'form_id');
    }

    public function attendees()
    {
        return $this->hasMany(EventRegistrationAttendee::class, 'submission_id')->orderBy('sequence');
    }

    public function answers()
    {
        return $this->hasMany(EventRegistrationAnswer::class, 'submission_id');
    }

    public function files()
    {
        return $this->hasMany(EventRegistrationFile::class, 'submission_id');
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function notifications()
    {
        return $this->hasMany(EventRegistrationNotification::class, 'submission_id');
    }

    public function sourceImport()
    {
        return $this->belongsTo(EventRegistrationImport::class, 'source_import_id');
    }

    public function sourceAllocationClaim()
    {
        return $this->belongsTo(EventRegistrationAllocationClaim::class, 'source_allocation_claim_id');
    }

    public function sessionSelections()
    {
        return $this->hasMany(EventRegistrationSessionSelection::class, 'submission_id');
    }

    public function invoice()
    {
        return $this->hasOne(EventRegistrationInvoice::class, 'submission_id');
    }
}
