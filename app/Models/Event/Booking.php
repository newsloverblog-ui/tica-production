<?php

namespace App\Models\Event;

use App\Models\Customer;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\BookingTicketDeliveryRequest;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
  use HasFactory;
  protected $fillable = [
    'customer_id',
    'booking_id',
    'checkout_submission_hash',
    'buyer_email_hash',
    'buyer_email_hash_version',
    'event_id',
    'organizer_id',
    'ticket_id',
    'fname',
    'lname',
    'email',
    'phone',
    'country',
    'state',
    'city',
    'zip_code',
    'address',
    'variation',
    'price',
    'tax_percentage',
    'commission_percentage',
    'tax',
    'commission',
    'quantity',
    'discount',
    'early_bird_discount',
    'currencyText',
    'currencyTextPosition',
    'currencySymbol',
    'currencySymbolPosition',
    'paymentMethod',
    'gatewayType',
    'paymentStatus',
    'refunded_amount',
    'refund_status',
    'invoice',
    'attachmentFile',
    'event_date',
    'scan_status',
    'conversation_id'
  ];

  protected $hidden = ['checkout_submission_hash', 'buyer_email_hash', 'buyer_email_hash_version'];

  /** The short order number buyers see on screen and in email, e.g. TICA-000123. */
  public function getOrderReferenceAttribute(): string
  {
    return 'TICA-' . str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
  }

  public function event()
  {
    return $this->hasOne(EventContent::class, 'event_id', 'event_id');
  }
  public function evnt()
  {
    return $this->belongsTo(Event::class, 'event_id', 'id');
  }
  //userInfo
  public function customerInfo()
  {
    return $this->hasOne(Customer::class, 'id', 'customer_id');
  }
  public function organizer()
  {
    return $this->belongsTo(Organizer::class);
  }

  public function ticketUnits()
  {
    return $this->hasMany(BookingTicket::class)->orderBy('sequence');
  }

  public function ticketDeliveryRequests()
  {
    return $this->hasMany(BookingTicketDeliveryRequest::class)->latest('id');
  }
}
