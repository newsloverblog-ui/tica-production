<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventRegistrationAnswer extends Model
{
    protected $fillable = [
        'submission_id', 'attendee_id', 'question_id', 'question_key',
        'question_label', 'question_type', 'value',
    ];

    protected $hidden = ['value'];

    protected $casts = ['value' => 'encrypted:array'];

    public function question()
    {
        return $this->belongsTo(EventRegistrationQuestion::class, 'question_id');
    }

    public function submission()
    {
        return $this->belongsTo(EventRegistrationSubmission::class, 'submission_id');
    }

    public function file()
    {
        return $this->hasOne(EventRegistrationFile::class, 'answer_id');
    }
}
