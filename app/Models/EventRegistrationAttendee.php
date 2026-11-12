<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventRegistrationAttendee extends Model
{
    protected $fillable = [
        'public_id', 'submission_id', 'ticket_id', 'variation_name', 'booking_ticket_id', 'sequence',
        'name', 'email', 'email_hash', 'status',
    ];

    protected $hidden = ['name', 'email', 'email_hash'];

    protected $casts = [
        'name' => 'encrypted',
        'email' => 'encrypted',
        'sequence' => 'integer',
    ];

    public function submission()
    {
        return $this->belongsTo(EventRegistrationSubmission::class, 'submission_id');
    }

    public function answers()
    {
        return $this->hasMany(EventRegistrationAnswer::class, 'attendee_id');
    }

    public function ticket()
    {
        return $this->belongsTo(\App\Models\Event\Ticket::class, 'ticket_id');
    }

    public function sessionSelections()
    {
        return $this->hasMany(EventRegistrationSessionSelection::class, 'attendee_id');
    }

    public function bookingTicket()
    {
        return $this->belongsTo(\App\Models\Event\BookingTicket::class, 'booking_ticket_id');
    }
}
