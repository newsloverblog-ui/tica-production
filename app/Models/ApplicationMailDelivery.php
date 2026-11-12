<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicationMailDelivery extends Model
{
    protected $fillable = [
        'public_id', 'kind', 'source_type', 'source_id', 'created_by_type', 'created_by_id',
        'recipient_hash', 'dedupe_key', 'recipient_email', 'subject', 'body', 'attachments', 'reply_to',
        'status', 'attempts', 'dispatch_attempts', 'dispatch_last_attempt_at', 'dispatch_last_error',
        'last_error', 'processing_at', 'sent_at', 'failed_at', 'alerted_at',
    ];

    protected $hidden = [
        'recipient_hash', 'recipient_email', 'subject', 'body', 'attachments', 'reply_to',
        'last_error', 'dispatch_last_error',
    ];

    protected $casts = [
        'recipient_email' => 'encrypted',
        'subject' => 'encrypted',
        'body' => 'encrypted',
        'attachments' => 'encrypted:array',
        'reply_to' => 'encrypted',
        'last_error' => 'encrypted',
        'source_id' => 'integer',
        'created_by_id' => 'integer',
        'attempts' => 'integer',
        'dispatch_attempts' => 'integer',
        'dispatch_last_attempt_at' => 'datetime',
        'dispatch_last_error' => 'encrypted',
        'processing_at' => 'datetime',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
        'alerted_at' => 'datetime',
    ];
}
