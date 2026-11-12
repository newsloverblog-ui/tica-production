<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventRegistrationSessionSelection extends Model
{
    protected $fillable = [
        'session_id', 'submission_id', 'attendee_id', 'selected_at', 'cancelled_at', 'cancellation_reason',
    ];

    protected $casts = ['selected_at' => 'datetime', 'cancelled_at' => 'datetime'];

    public function session() { return $this->belongsTo(EventRegistrationSession::class, 'session_id'); }
    public function submission() { return $this->belongsTo(EventRegistrationSubmission::class, 'submission_id'); }
    public function attendee() { return $this->belongsTo(EventRegistrationAttendee::class, 'attendee_id'); }
}
