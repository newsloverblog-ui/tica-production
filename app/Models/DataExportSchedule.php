<?php

namespace App\Models;

use App\Casts\PrefixedEncryptedString;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DataExportSchedule extends Model
{
    protected $fillable = [
        'public_id', 'requested_by_type', 'requested_by_id', 'organizer_id',
        'export_type', 'format', 'filters', 'language_id', 'frequency',
        'timezone', 'date_window_days', 'active', 'next_run_at', 'last_run_at',
        'last_export_id', 'consecutive_failures', 'last_error',
    ];

    protected $casts = [
        'filters' => 'encrypted:array',
        'language_id' => 'integer',
        'date_window_days' => 'integer',
        'active' => 'boolean',
        'next_run_at' => 'datetime',
        'last_run_at' => 'datetime',
        'consecutive_failures' => 'integer',
        'last_error' => PrefixedEncryptedString::class,
    ];

    protected static function booted(): void
    {
        static::creating(function (self $schedule): void {
            $schedule->public_id = $schedule->public_id ?: (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
