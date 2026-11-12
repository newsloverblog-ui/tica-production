<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountLoginChallenge extends Model
{
    protected $fillable = [
        'public_id', 'actor_type', 'actor_id', 'purpose', 'email_hash',
        'code_hash', 'session_hash', 'redirect_path', 'attempts',
        'expires_at', 'consumed_at',
    ];

    protected $hidden = ['email_hash', 'code_hash', 'session_hash'];

    protected $casts = [
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];
}
