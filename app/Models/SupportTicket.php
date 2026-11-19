<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupportTicket extends Model
{
  use HasFactory;
  protected $fillable = [
    'user_id',
    'user_type',
    'recipient_type',
    'recipient_organizer_id',
    'event_id',
    'booking_id',
    'email',
    'subject',
    'description',
    'attachment',
    'status',
    'last_message',
  ];
  public function customer()
  {
    return $this->belongsTo(Customer::class, 'user_id', 'id');
  }
  public function organizer()
  {
    return $this->belongsTo(Organizer::class, 'user_id', 'id');
  }
  public function messages()
  {
    return $this->hasMany(Conversation::class);
  }

  public function recipientOrganizer()
  {
    return $this->belongsTo(Organizer::class, 'recipient_organizer_id');
  }

  public function event()
  {
    return $this->belongsTo(\App\Models\Event\EventContent::class, 'event_id', 'event_id');
  }

  public function booking()
  {
    return $this->belongsTo(\App\Models\Event\Booking::class, 'booking_id');
  }
}
