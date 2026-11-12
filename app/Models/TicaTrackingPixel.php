<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicaTrackingPixel extends Model
{
    protected $fillable = ['organizer_id', 'approved_by', 'provider', 'pixel_identifier', 'label', 'status', 'admin_notes', 'approved_at'];

    protected $casts = ['approved_at' => 'datetime'];
}
