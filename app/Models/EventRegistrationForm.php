<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventRegistrationForm extends Model
{
    protected $fillable = [
        'public_id', 'event_id', 'organizer_id', 'name', 'status', 'approval_mode',
        'version', 'intro', 'privacy_notice', 'consent_version', 'published_at', 'archived_at',
    ];

    protected $casts = [
        'version' => 'integer',
        'published_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function questions()
    {
        return $this->hasMany(EventRegistrationQuestion::class, 'form_id')->orderBy('position')->orderBy('id');
    }

    public function submissions()
    {
        return $this->hasMany(EventRegistrationSubmission::class, 'form_id');
    }

    public function allocations()
    {
        return $this->hasMany(EventRegistrationAllocation::class, 'form_id');
    }

    public function sessions()
    {
        return $this->hasMany(EventRegistrationSession::class, 'form_id')->orderBy('starts_at')->orderBy('id');
    }

    public function badgeSetting()
    {
        return $this->hasOne(EventRegistrationBadgeSetting::class, 'form_id');
    }

    public function embedSetting()
    {
        return $this->hasOne(EventRegistrationEmbedSetting::class, 'form_id');
    }
}
