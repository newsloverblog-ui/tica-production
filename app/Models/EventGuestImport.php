<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventGuestImport extends Model
{
    protected $fillable = [
        'public_id', 'event_id', 'organizer_id', 'status', 'disk', 'path',
        'total_rows', 'accepted_rows', 'rejected_rows', 'attempts',
        'dispatch_attempts', 'dispatch_last_attempt_at', 'dispatch_last_error', 'dispatch_alerted_at',
        'attestation_version', 'attested_at', 'started_at', 'completed_at',
        'source_deleted_at', 'expires_at', 'last_error',
    ];

    protected $hidden = ['path', 'last_error'];

    protected $casts = [
        'total_rows' => 'integer', 'accepted_rows' => 'integer', 'rejected_rows' => 'integer',
        'attempts' => 'integer', 'attested_at' => 'datetime', 'started_at' => 'datetime',
        'dispatch_attempts' => 'integer', 'dispatch_last_attempt_at' => 'datetime',
        'dispatch_last_error' => 'encrypted', 'dispatch_alerted_at' => 'datetime',
        'completed_at' => 'datetime', 'source_deleted_at' => 'datetime', 'expires_at' => 'datetime',
        'last_error' => 'encrypted',
    ];

    public function getRouteKeyName(): string { return 'public_id'; }
    public function event() { return $this->belongsTo(Event::class); }
    public function rows() { return $this->hasMany(EventGuestImportRow::class, 'import_id')->orderBy('row_number'); }
}
