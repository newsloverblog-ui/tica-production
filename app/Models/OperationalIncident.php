<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class OperationalIncident extends Model
{
    protected $guarded = [];

    protected $casts = [
        'context' => 'encrypted:array',
        'delivery_status' => 'array',
        'occurrence_count' => 'integer',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'last_alerted_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $incident): void {
            $incident->public_id = $incident->public_id ?: (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
