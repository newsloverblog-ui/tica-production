<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventRegistrationEmbedSetting extends Model
{
    protected $fillable = [
        'form_id', 'event_id', 'organizer_id', 'enabled', 'allowed_origins',
        'accent_color', 'background_color',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'allowed_origins' => 'array',
    ];

    public function form()
    {
        return $this->belongsTo(EventRegistrationForm::class, 'form_id');
    }
}
