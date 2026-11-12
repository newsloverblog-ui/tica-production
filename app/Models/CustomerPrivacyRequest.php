<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CustomerPrivacyRequest extends Model
{
    protected $fillable = [
        'public_id', 'customer_id', 'request_type', 'status', 'customer_email', 'customer_name',
        'reason', 'policy_version', 'reviewed_by_admin_id', 'resolution', 'evidence_reference',
        'submitted_at', 'reviewed_at', 'completed_at',
    ];

    protected $casts = [
        'customer_email' => 'encrypted',
        'customer_name' => 'encrypted',
        'reason' => 'encrypted',
        'resolution' => 'encrypted',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $request): void {
            $request->public_id = $request->public_id ?: (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
