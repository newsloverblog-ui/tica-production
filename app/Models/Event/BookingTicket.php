<?php

namespace App\Models\Event;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingTicket extends Model
{
    use HasFactory;

    protected $fillable = [
        'public_id', 'booking_id', 'event_id', 'organizer_id', 'ticket_id', 'sequence',
        'variation_name', 'holder_name', 'holder_email', 'holder_customer_id', 'status', 'checked_in_at',
        'checked_in_by_organizer_id', 'checked_in_scanner_device_id',
        'checked_in_scanner_staff_id', 'checked_in_source', 'revoked_at', 'revoked_reason',
    ];

    protected $casts = [
        'checked_in_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function events()
    {
        return $this->hasMany(BookingTicketEvent::class)->orderBy('id');
    }

    public function transfers()
    {
        return $this->hasMany(BookingTicketTransfer::class)->orderByDesc('id');
    }

    public function pendingTransfer()
    {
        return $this->hasOne(BookingTicketTransfer::class)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->latestOfMany();
    }

    public function checkedInScannerDevice()
    {
        return $this->belongsTo(\App\Models\ScannerDevice::class, 'checked_in_scanner_device_id');
    }

    public function checkedInScannerStaff()
    {
        return $this->belongsTo(\App\Models\ScannerStaff::class, 'checked_in_scanner_staff_id');
    }
}
