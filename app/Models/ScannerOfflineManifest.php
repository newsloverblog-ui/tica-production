<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScannerOfflineManifest extends Model
{
    protected $fillable = [
        'public_id', 'organizer_id', 'event_id', 'scanner_device_id', 'scanner_staff_id',
        'status', 'snapshot_hash', 'ticket_count', 'generated_at', 'expires_at',
        'last_synced_at', 'revoked_at',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
        'expires_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'revoked_at' => 'datetime',
        'ticket_count' => 'integer',
    ];

    public function items()
    {
        return $this->hasMany(ScannerOfflineManifestItem::class);
    }
}
