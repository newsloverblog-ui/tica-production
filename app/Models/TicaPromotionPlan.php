<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicaPromotionPlan extends Model
{
    protected $fillable = [
        'name', 'slug', 'placement', 'type', 'description', 'price', 'currency', 'duration_days', 'is_active',
        'status', 'settings', 'benefits', 'placements', 'eligibility', 'approval_required', 'priority',
        'capacity', 'max_concurrent', 'available_from', 'available_until', 'archived_at', 'duplicated_from_id',
    ];

    protected $casts = [
        'price' => 'decimal:2', 'duration_days' => 'integer', 'is_active' => 'boolean', 'settings' => 'array',
        'benefits' => 'array', 'placements' => 'array', 'eligibility' => 'array', 'approval_required' => 'boolean',
        'priority' => 'integer', 'capacity' => 'integer', 'max_concurrent' => 'integer',
        'available_from' => 'datetime', 'available_until' => 'datetime', 'archived_at' => 'datetime',
    ];

    public function campaigns()
    {
        return $this->hasMany(TicaPromotionCampaign::class, 'plan_id');
    }

    public function scopePublished($query)
    {
        return $query->where('is_active', true)->where('status', 'published')->whereNull('archived_at')
            ->where(fn ($q) => $q->whereNull('available_from')->orWhere('available_from', '<=', now()))
            ->where(fn ($q) => $q->whereNull('available_until')->orWhere('available_until', '>', now()));
    }
}
