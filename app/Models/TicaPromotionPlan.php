<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicaPromotionPlan extends Model
{
    protected $fillable = ['name', 'placement', 'description', 'price', 'currency', 'duration_days', 'is_active', 'settings'];

    protected $casts = ['price' => 'decimal:2', 'duration_days' => 'integer', 'is_active' => 'boolean', 'settings' => 'array'];
}
