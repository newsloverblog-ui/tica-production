<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventRegistrationFile extends Model
{
    protected $fillable = [
        'public_id', 'answer_id', 'submission_id', 'attendee_id', 'question_id',
        'disk', 'path', 'original_name', 'mime_type', 'size', 'sha256',
    ];

    protected $hidden = ['path', 'original_name', 'sha256'];

    protected $casts = [
        'original_name' => 'encrypted',
        'size' => 'integer',
    ];

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function answer()
    {
        return $this->belongsTo(EventRegistrationAnswer::class, 'answer_id');
    }

    public function submission()
    {
        return $this->belongsTo(EventRegistrationSubmission::class, 'submission_id');
    }

    public function attendee()
    {
        return $this->belongsTo(EventRegistrationAttendee::class, 'attendee_id');
    }
}
