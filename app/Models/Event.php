<?php

namespace App\Models;

use App\Models\Event\Booking;
use App\Models\Event\EventContent;
use App\Models\Event\EventDates;
use App\Models\Event\EventImage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Event\Ticket;
use App\Models\Event\Wishlist;

class Event extends Model
{
  use HasFactory;
  protected $fillable = [
    'organizer_id',
    'thumbnail',
    'status',
    'lifecycle_status',
    'sales_closed_at',
    'cancelled_at',
    'cancellation_reason',
    'countdown_status',
    'date_type',
    'start_date',
    'start_time',
    'duration',
    'end_date',
    'end_time',
    'end_date_time',
    'is_featured',
    'event_type',
    'online_join_url',
    'registration_mode',
    'latitude',
    'longitude',
    'fee_payer',
    'currency',
    'age_restriction',
    'visibility',
    'show_remaining',
    'waitlist_enabled',
    'is_hybrid',
    'join_reveal_minutes',
    'attendee_question'
  ];

  protected $casts = [
    'sales_closed_at' => 'datetime',
    'cancelled_at' => 'datetime',
    'online_join_url' => 'encrypted',
  ];

  public function isBookable(): bool
  {
    return (int) $this->status === 1
      && ($this->lifecycle_status ?: 'scheduled') === 'scheduled'
      && ($this->registration_mode ?: 'ticketing') === 'ticketing'
      && $this->sales_closed_at === null;
  }

  public function cancellationRequests()
  {
    return $this->hasMany(EventCancellationRequest::class);
  }
  public function waitlistEntries()
  {
    return $this->hasMany(EventWaitlistEntry::class);
  }
  public function guestInvitations()
  {
    return $this->hasMany(EventGuestInvitation::class);
  }
  public function registrationForm()
  {
    return $this->hasOne(EventRegistrationForm::class);
  }

  public function registrationAllocations()
  {
    return $this->hasMany(EventRegistrationAllocation::class);
  }
  public function ticket()
  {
    return $this->hasOne(Ticket::class);
  }
  public function tickets()
  {
    return $this->hasMany(Ticket::class);
  }
  //information
  public function information()
  {
    return $this->hasOne(EventContent::class);
  }
  //bookings
  public function booking()
  {
    return $this->hasMany(Booking::class);
  }

  //wishtlist
  public function wishlists()
  {
    return $this->hasMany(Wishlist::class, 'event_id', 'id');
  }

  public function organizer()
  {
    return $this->belongsTo(Organizer::class);
  }

  public function galleries()
  {
    return $this->hasMany(EventImage::class);
  }

  public function dates()
  {
    return $this->hasMany(EventDates::class);
  }
}
