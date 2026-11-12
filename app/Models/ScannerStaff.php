<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;

class ScannerStaff extends Model implements AuthenticatableContract
{
    use Authenticatable;

    protected $table = 'scanner_staff';

    protected $fillable = [
        'organizer_id', 'name', 'email', 'password', 'role', 'status',
        'last_login_at', 'last_seen_at', 'revoked_at', 'auth_version',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'last_login_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'revoked_at' => 'datetime',
        'auth_version' => 'integer',
    ];

    public function organizer()
    {
        return $this->belongsTo(Organizer::class);
    }

    public function isSupervisor(): bool
    {
        return $this->role === 'supervisor';
    }
}
