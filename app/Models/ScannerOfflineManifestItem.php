<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScannerOfflineManifestItem extends Model
{
    protected $fillable = [
        'scanner_offline_manifest_id', 'booking_ticket_id', 'payload_hash',
    ];

    public function manifest()
    {
        return $this->belongsTo(ScannerOfflineManifest::class, 'scanner_offline_manifest_id');
    }
}
