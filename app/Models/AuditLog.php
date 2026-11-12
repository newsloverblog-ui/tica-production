<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'before' => 'array',
        'after' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::updating(function () {
            throw new LogicException('Audit records are immutable.');
        });

        static::deleting(function () {
            throw new LogicException('Audit records are immutable.');
        });
    }
}

