<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicaOrganizerSubscription extends Model
{
    protected $fillable = [
        'organizer_id', 'plan', 'status', 'automatic_upgrade', 'requested_plan',
        'change_requested_at', 'started_at', 'renews_at',
    ];

    protected $casts = [
        'automatic_upgrade' => 'boolean',
        'change_requested_at' => 'datetime',
        'started_at' => 'datetime',
        'renews_at' => 'datetime',
    ];
}
