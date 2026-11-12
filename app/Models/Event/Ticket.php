<?php

namespace App\Models\Event;

use App\Models\EventWaitlistEntry;
use App\Models\Scopes\SchemaAwareSoftDeletingScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ticket extends Model
{
  use HasFactory, SoftDeletes;

  public static function bootSoftDeletes(): void
  {
    static::addGlobalScope(new SchemaAwareSoftDeletingScope());
  }

  protected $fillable = [
    'event_id',
    'event_type',
    'title',
    'ticket_available_type',
    'ticket_available',
    'max_ticket_buy_type',
    'max_buy_ticket',
    'description',
    'pricing_type',
    'price',
    'f_price',
    'early_bird_discount_type',
    'early_bird_discount',
    'early_bird_discount_amount',

    'early_bird_discount_date',
    'early_bird_discount_time',
    'variations',
    'trans_vars'
  ];

  public function waitlistEntries()
  {
    return $this->hasMany(EventWaitlistEntry::class);
  }
}
