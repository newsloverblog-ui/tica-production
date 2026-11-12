<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventRegistrationBadgeSetting extends Model
{
    protected $fillable = [
        'form_id', 'event_id', 'organizer_id', 'badge_title', 'company_question_key',
        'role_question_key', 'accent_color', 'paper_size',
    ];

    public function form()
    {
        return $this->belongsTo(EventRegistrationForm::class, 'form_id');
    }
}
