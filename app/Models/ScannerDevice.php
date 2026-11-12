<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScannerDevice extends Model
{
    protected $fillable = [
        'public_id', 'organizer_id', 'name', 'secret_hash', 'status',
        'last_seen_at', 'last_ip', 'last_user_agent', 'revoked_at',
        'revoked_by_organizer_id',
    ];

    protected $hidden = ['secret_hash'];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function organizer()
    {
        return $this->belongsTo(Organizer::class);
    }
}
