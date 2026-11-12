<?php

namespace App\Models;

use App\Models\Event\BookingTicket;
use Illuminate\Database\Eloquent\Model;

class ScannerCheckInConflict extends Model
{
    protected $fillable = [
        'organizer_id', 'event_id', 'booking_ticket_id', 'original_scanner_device_id',
        'attempted_scanner_device_id', 'original_scanner_staff_id',
        'attempted_scanner_staff_id', 'source', 'occurrences', 'first_detected_at',
        'last_detected_at', 'resolved_at', 'resolved_by_organizer_id', 'resolution_note',
    ];

    protected $casts = [
        'first_detected_at' => 'datetime',
        'last_detected_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function ticket()
    {
        return $this->belongsTo(BookingTicket::class, 'booking_ticket_id');
    }

    public function originalDevice()
    {
        return $this->belongsTo(ScannerDevice::class, 'original_scanner_device_id');
    }

    public function attemptedDevice()
    {
        return $this->belongsTo(ScannerDevice::class, 'attempted_scanner_device_id');
    }

    public function originalStaff()
    {
        return $this->belongsTo(ScannerStaff::class, 'original_scanner_staff_id');
    }

    public function attemptedStaff()
    {
        return $this->belongsTo(ScannerStaff::class, 'attempted_scanner_staff_id');
    }
}
