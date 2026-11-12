<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScannerOfflineSyncItem extends Model
{
    protected $fillable = [
        'client_scan_id', 'scanner_offline_manifest_id', 'organizer_id',
        'scanner_device_id', 'scanner_staff_id', 'booking_ticket_id', 'payload_hash',
        'result', 'http_status', 'response', 'client_scanned_at', 'received_at',
    ];

    protected $casts = [
        'response' => 'array',
        'client_scanned_at' => 'datetime',
        'received_at' => 'datetime',
    ];
}
