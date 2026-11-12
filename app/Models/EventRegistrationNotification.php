<?php

namespace App\Models;

use App\Casts\PrefixedEncryptedString;
use Illuminate\Database\Eloquent\Model;

class EventRegistrationNotification extends Model
{
    protected $fillable = [
        'submission_id', 'event_id', 'kind', 'status', 'attempts',
        'dispatch_attempts', 'dispatch_last_attempt_at', 'dispatch_last_error', 'processing_at',
        'sent_at', 'alerted_at', 'last_error',
    ];

    protected $hidden = ['dispatch_last_error', 'last_error'];

    protected $casts = [
        'attempts' => 'integer',
        'dispatch_attempts' => 'integer',
        'dispatch_last_attempt_at' => 'datetime',
        'dispatch_last_error' => 'encrypted',
        'last_error' => PrefixedEncryptedString::class,
        'processing_at' => 'datetime',
        'sent_at' => 'datetime',
        'alerted_at' => 'datetime',
    ];

    public function submission()
    {
        return $this->belongsTo(EventRegistrationSubmission::class, 'submission_id');
    }
}
