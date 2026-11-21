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
    'admits',
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
    'trans_vars',
    'currency',
    'min_buy_ticket',
    'sales_start_at',
    'sales_end_at',
    'visibility',
    'sales_status',
    'numbering_enabled',
    'numbering_scope',
    'number_prefix',
    'number_start',
    'number_padding',
    'number_format',
    'seating_mode',
    'design_settings',
  ];

  protected $casts = [
    'sales_start_at' => 'datetime',
    'sales_end_at' => 'datetime',
    'numbering_enabled' => 'boolean',
    'design_settings' => 'array',
  ];

  public function waitlistEntries()
  {
    return $this->hasMany(EventWaitlistEntry::class);
  }

  /** How many people one ticket of this type lets in (a couple pass is 2, a table for 10 is 10). */
  public function admitsPerTicket(): int
  {
    return max(1, min(100, (int) ($this->getAttribute('admits') ?: 1)));
  }

  /**
   * People admitted per ticket type id. Online tickets always admit one.
   *
   * @param  iterable<int|string>  $ticketIds
   * @return array<int, int>
   */
  public static function admitsFor(iterable $ticketIds): array
  {
    $ids = collect($ticketIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();
    if ($ids->isEmpty() || !\Illuminate\Support\Facades\Schema::hasColumn('tickets', 'admits')) {
      return [];
    }

    return static::query()->whereIn('id', $ids)->get(['id', 'admits', 'event_type'])
      ->mapWithKeys(fn (self $ticket) => [(int) $ticket->id => $ticket->event_type === 'online' ? 1 : $ticket->admitsPerTicket()])
      ->all();
  }
}
