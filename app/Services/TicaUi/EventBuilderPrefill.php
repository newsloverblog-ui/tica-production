<?php

namespace App\Services\TicaUi;

use App\Models\Event;
use App\Models\Event\Booking;
use App\Models\Event\BookingTicket;
use App\Models\Event\EventContent;
use App\Models\Event\EventDates;
use App\Models\Event\Ticket;
use App\Models\Event\TicketContent;
use Carbon\Carbon;

/**
 * Turns a saved event back into the values the shared event builder fills in,
 * so editing uses the same six steps as creating.
 */
class EventBuilderPrefill
{
  public const REFUND_POLICIES = [
    'Up to 7 days before' => 'Refunds are available up to 7 days before the event starts.',
    'Up to 48 hours before' => 'Refunds are available up to 48 hours before the event starts.',
  ];

  /** True once anyone holds a ticket or a booking for the event. */
  public static function hasSales(Event $event): bool
  {
    return BookingTicket::query()->where('event_id', $event->id)->whereNull('revoked_at')->exists()
      || Booking::query()->where('event_id', $event->id)->exists();
  }

  public static function soldFor(Ticket $ticket): int
  {
    return BookingTicket::query()->where('ticket_id', $ticket->id)->whereNull('revoked_at')->count();
  }

  public function build(Event $event, int $languageId): array
  {
    $content = EventContent::query()->where('event_id', $event->id)->where('language_id', $languageId)->first()
      ?? EventContent::query()->where('event_id', $event->id)->first();
    $mode = $event->event_type === 'online' ? 'Online' : ($event->is_hybrid ? 'Hybrid' : 'In person');
    $sales = self::hasSales($event);

    $dates = EventDates::query()->where('event_id', $event->id)->orderBy('start_date_time')->get()
      ->map(fn ($date) => [
        'date' => (string) $date->start_date,
        'start' => substr((string) $date->start_time, 0, 5),
        'end' => substr((string) $date->end_time, 0, 5),
      ])->values()->all();

    $tiers = Ticket::query()->where('event_id', $event->id)->orderBy('id')->get()->map(function (Ticket $ticket) use ($languageId) {
      $sold = self::soldFor($ticket);
      $price = (float) $ticket->price;
      $early = null;
      if ($ticket->early_bird_discount === 'enable' && $ticket->early_bird_discount_amount !== null) {
        $early = $ticket->early_bird_discount_type === 'percentage'
          ? round($price * (1 - (float) $ticket->early_bird_discount_amount / 100), 2)
          : round($price - (float) $ticket->early_bird_discount_amount, 2);
      }
      $description = TicketContent::query()->where('ticket_id', $ticket->id)->where('language_id', $languageId)->value('description');

      return [
        'id' => (int) $ticket->id,
        'name' => (string) $ticket->title,
        'price' => $price,
        'quantity' => $ticket->ticket_available_type === 'limited' ? (int) $ticket->ticket_available + $sold : null,
        'max_per_order' => $ticket->max_ticket_buy_type === 'limited' ? (int) $ticket->max_buy_ticket : null,
        'description' => (string) ($description ?? ''),
        'early_price' => $early,
        'early_ends' => $early !== null && $ticket->early_bird_discount_date
          ? $ticket->early_bird_discount_date . 'T' . substr((string) ($ticket->early_bird_discount_time ?: '23:59'), 0, 5)
          : null,
        'sold' => $sold,
        // Tickets with options (variations) were made in the old editor; the builder leaves them as they are.
        'locked' => $ticket->pricing_type === 'variation',
      ];
    })->values()->all();

    $refund = array_search((string) optional($content)->refund_policy, self::REFUND_POLICIES, true);

    return [
      'id' => (int) $event->id,
      'status' => (int) $event->status === 1 ? 'live' : 'draft',
      'name' => (string) optional($content)->title,
      'category_id' => optional($content)->event_category_id ? (int) $content->event_category_id : null,
      'age_restriction' => (string) ($event->age_restriction ?? ''),
      'description' => self::plainText((string) optional($content)->description),
      'multiple' => $event->date_type === 'multiple',
      'start_date' => (string) ($event->start_date ?? ''),
      'start_time' => substr((string) $event->start_time, 0, 5),
      'end_date' => (string) ($event->end_date ?? ''),
      'end_time' => substr((string) $event->end_time, 0, 5),
      'dates' => $dates,
      'mode' => $mode,
      'country' => (string) (optional($content)->country ?: ''),
      'currency' => (string) ($event->currency ?? ''),
      'address' => (string) (optional($content)->address ?? ''),
      'city' => (string) (optional($content)->city ?? ''),
      'state' => (string) (optional($content)->state ?? ''),
      'online_join_url' => (string) ($event->online_join_url ?? ''),
      'join_reveal_minutes' => (string) ((int) ($event->join_reveal_minutes ?? 15)),
      'entry' => $event->registration_mode === 'corporate' ? 'corporate' : 'ticketing',
      'tiers' => $tiers,
      'fee_payer' => $event->fee_payer === 'organizer' ? 'organizer' : 'buyer',
      'visibility' => (string) ($event->visibility ?: 'public'),
      'refund_policy' => $refund === false ? '' : $refund,
      'show_remaining' => (bool) $event->show_remaining,
      'waitlist_enabled' => (bool) $event->waitlist_enabled,
      'allow_transfers' => (bool) $event->allow_transfers,
      'countdown' => (int) $event->countdown_status === 1,
      'attendee_question' => (string) ($event->attendee_question ?? ''),
      'meta_description' => (string) (optional($content)->meta_description ?? ''),
      'meta_keywords' => (string) (optional($content)->meta_keywords ?? ''),
      'flyer' => $event->thumbnail ? asset('assets/admin/img/event/thumbnail/' . $event->thumbnail) : null,
      'organizer_id' => $event->organizer_id ? (int) $event->organizer_id : null,
      'is_featured' => $event->is_featured === 'yes',
      'locks' => [
        'sales' => $sales,
        'dates' => $sales,
        'currency' => $sales,
        'entry' => true,
      ],
    ];
  }

  /** The builder stores paragraphs as simple HTML; the form edits plain text. */
  public static function plainText(string $html): string
  {
    if (trim($html) === '') {
      return '';
    }
    $text = preg_replace(['/<br\s*\/?>/i', '/<\/p>\s*/i'], ["\n", "\n\n"], $html);

    return trim(html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
  }
}
