<?php

namespace App\Services\TicaUi;

use App\Models\BasicSettings\Basic;
use App\Models\Event;
use App\Models\Event\EventCategory;
use App\Models\Event\EventContent;
use App\Models\Event\EventDates;
use App\Models\Event\Ticket;
use App\Models\Event\TicketContent;
use App\Models\Language;
use App\Models\Organizer;
use App\Models\OrganizerInfo;
use App\Services\EventPublicVisibility;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Support\TicaCurrency;
use Mews\Purifier\Facades\Purifier;

/**
 * Presents Tica events in the shape the TICA design scripts render
 * (window.TICA_EVENTS). Ticket tiers keep the exact order checkout2 expects
 * for its quantity[] array: tickets by id, variations expanded in place.
 */
class EventCatalog
{
  private const POSTER_COLOURS = ['#2B4A73', '#6E2F35', '#4E4676', '#2F5F52', '#1D2238', '#C79A4B', '#3B1F7A', '#B85F24'];

  private ?Basic $basic = null;

  public function __construct(private Language $language)
  {
  }

  /** Upcoming public events, soonest first. */
  public function upcoming(int $limit = 200): Collection
  {
    $contents = EventContent::query()
      ->join('events', 'events.id', '=', 'event_contents.event_id')
      ->where('event_contents.language_id', $this->language->id)
      ->where('events.status', 1)
      ->when(
        Schema::hasColumn('events', 'lifecycle_status'),
        fn ($query) => $query->where('events.lifecycle_status', 'scheduled')
      )
      ->where('events.end_date_time', '>=', now())
      ->when(Schema::hasColumn('events', 'visibility'), fn ($query) => $query->where('events.visibility', 'public'))
      ->select('event_contents.*', 'events.*', 'events.id as id', 'event_contents.id as content_id')
      ->limit($limit)
      ->get();

    return $this->present($contents)->sortBy('ts')->values();
  }

  /** One event by id, including past and cancelled events that stay public. */
  public function find(int $eventId, bool $preview = false): ?array
  {
    $content = EventContent::query()
      ->join('events', 'events.id', '=', 'event_contents.event_id')
      ->where('event_contents.language_id', $this->language->id)
      ->where('events.id', $eventId)
      ->when(!$preview, fn ($query) => app(EventPublicVisibility::class)->apply($query))
      ->select('event_contents.*', 'events.*', 'events.id as id', 'event_contents.id as content_id')
      ->first();

    return $content ? $this->present(collect([$content]))->first() : null;
  }

  /** Every event an organiser owns, drafts and past events included. */
  public function forOrganizer(int $organizerId): Collection
  {
    $contents = EventContent::query()
      ->join('events', 'events.id', '=', 'event_contents.event_id')
      ->where('event_contents.language_id', $this->language->id)
      ->where('events.organizer_id', $organizerId)
      ->select('event_contents.*', 'events.*', 'events.id as id', 'event_contents.id as content_id')
      ->orderByDesc('events.id')
      ->limit(300)
      ->get();

    return $this->present($contents)->values();
  }

  /** Categories shown in Discover filters and the home category rail. */
  public function categories(): array
  {
    return EventCategory::query()
      ->where('language_id', $this->language->id)
      ->where('status', 1)
      ->orderBy('serial_number')
      ->get(['id', 'name', 'slug', 'icon', 'is_featured'])
      ->map(fn ($category) => [
        'id' => (int) $category->id,
        'key' => $this->categoryKey($category->slug, $category->id),
        'name' => $category->name,
        'featured' => $category->is_featured === 'yes',
        'icon' => $category->icon,
        'svg' => config('category_icons.' . $category->icon . '.svg'),
      ])
      ->values()
      ->all();
  }

  public function currency(): array
  {
    $basic = $this->basic();

    return [
      'symbol' => (string) ($basic->base_currency_symbol ?? '₦'),
      'position' => (string) ($basic->base_currency_symbol_position ?? 'left'),
      'text' => (string) ($basic->base_currency_text ?? 'NGN'),
      'feePct' => round((float) ($basic->booking_fee_percentage ?? 6), 2),
      'guestCheckout' => (int) ($basic->event_guest_checkout_status ?? 0) === 1,
      'code' => TicaCurrency::base(),
      'options' => TicaCurrency::options(),
      'countries' => array_filter(TicaCurrency::COUNTRIES, fn ($code) => in_array($code, \App\Support\TicaPlatform::currencies(), true)),
      'platform' => \App\Support\TicaPlatform::forOrganizers(),
      'paystack' => TicaCurrency::PAYSTACK,
      'gateways' => \App\Support\TicaPayments::forCheckout(),
    ];
  }

  private function present(Collection $contents): Collection
  {
    if ($contents->isEmpty()) {
      return collect();
    }

    $eventIds = $contents->pluck('id')->map(fn ($id) => (int) $id)->all();
    $tickets = Ticket::query()->whereIn('event_id', $eventIds)->orderBy('id')->get()->groupBy('event_id');
    $ticketContents = TicketContent::query()
      ->whereIn('ticket_id', $tickets->flatten()->pluck('id'))
      ->get()
      ->groupBy('ticket_id');
    $dates = EventDates::query()->whereIn('event_id', $eventIds)->orderBy('start_date_time')->get()->groupBy('event_id');
    $sold = $this->soldCounts($eventIds);
    $categories = EventCategory::query()->whereIn('id', $contents->pluck('event_category_id'))->get()->keyBy('id');
    $organizers = Organizer::query()->whereIn('id', $contents->pluck('organizer_id')->filter())->get()->keyBy('id');
    $organizerNames = OrganizerInfo::query()
      ->whereIn('organizer_id', $organizers->keys())
      ->where('language_id', $this->language->id)
      ->pluck('name', 'organizer_id');
    $sponsored = Schema::hasTable('tica_promotion_campaigns')
      ? DB::table('tica_promotion_campaigns')->join('tica_promotion_plans', 'tica_promotion_plans.id', '=', 'tica_promotion_campaigns.plan_id')
        ->whereIn('tica_promotion_campaigns.event_id', $eventIds)->where('tica_promotion_campaigns.status', 'active')
        ->where('tica_promotion_plans.placement', 'sponsored_event')->pluck('tica_promotion_campaigns.id', 'tica_promotion_campaigns.event_id')
      : collect();

    return $contents->map(function ($content) use ($tickets, $ticketContents, $dates, $sold, $categories, $organizers, $organizerNames, $sponsored) {
      $eventId = (int) $content->id;
      $category = $categories->get($content->event_category_id);
      $organizer = $organizers->get($content->organizer_id);
      [$start, $dateOptions] = $this->startOf($content, $dates->get($eventId, collect()));
      $tiers = $this->tiers($tickets->get($eventId, collect()), $ticketContents, $sold[$eventId] ?? []);
      $online = $content->event_type === 'online';
      $timeKnown = $content->date_type === 'multiple' || !empty($content->start_time);
      $minimum = collect($tiers)->pluck(1)->min();
      $left = collect($tiers)->sum(2);
      $bookable = (int) $content->status === 1
        && (($content->lifecycle_status ?? 'scheduled') ?: 'scheduled') === 'scheduled'
        && empty($content->sales_closed_at)
        && Carbon::parse($content->end_date_time)->isFuture();
      $label = $category?->name ?: __('Event');
      $name = (string) $content->title;
      $venue = $online ? 'Online' : (trim((string) $content->address) ?: __('Venue to be confirmed'));
      $city = $online ? 'Online' : (trim((string) $content->city) ?: trim((string) $content->state) ?: (string) $content->country);
      $byName = $organizer ? ($organizerNames->get($organizer->id) ?: $organizer->username) : config('app.name', 'TICA');
      $time = $timeKnown ? $start->format('g:i A') : 'time to be confirmed';

      return [
        'id' => $eventId,
        'organizerId' => $organizer ? (int) $organizer->id : null,
        'slug' => $content->slug . '/' . $eventId,
        'day' => $start->format('j'),
        'mon' => $start->format('M'),
        'm' => $this->monthKey($start),
        'y' => (int) $start->format('Y'),
        'ts' => $start->getTimestampMs(),
        'name' => $name,
        'cat' => $category ? $this->categoryKey($category->slug, $category->id) : 'other',
        'label' => $label,
        'city' => $city ?: 'Online',
        'country' => $online ? 'Online' : (string) $content->country,
        'venue' => $venue,
        'time' => $time,
        'by' => $byName,
        'v' => $organizer && $organizer->kyc_status === 'verified' ? 1 : 0,
        'price' => $tiers === [] ? __('Free') : $this->money((float) $minimum, $content->currency ?? null),
        'cur' => [
          'code' => TicaCurrency::normalize($content->currency ?? null),
          'symbol' => TicaCurrency::symbol($content->currency ?? null),
          'position' => TicaCurrency::isBase($content->currency ?? null) ? $this->currency()['position'] : 'left',
        ],
        'n' => (float) ($minimum ?? 0),
        'art' => $this->art($content, $label, $name, $start, $time),
        'image' => $this->image($content->thumbnail),
        'desc' => $this->description($content->description),
        'refund' => $this->description($content->refund_policy),
        'tiers' => array_map(fn ($tier) => array_slice($tier, 0, 4), $tiers),
        'tierInfo' => array_map(fn ($tier) => $tier[4], $tiers),
        'mode' => $online ? 'online' : (!empty($content->is_hybrid) ? 'hybrid' : 'venue'),
        'platform' => $online || !empty($content->is_hybrid) ? $this->platform($content->online_join_url) : '',
        'age' => (string) ($content->age_restriction ?? ''),
        'hideStock' => isset($content->show_remaining) && !$content->show_remaining,
        'waitlist' => !isset($content->waitlist_enabled) || (bool) $content->waitlist_enabled,
        'question' => (string) ($content->attendee_question ?? ''),
        'visibility' => (string) ($content->visibility ?? 'public'),
        'sold' => $tiers !== [] && $left === 0 ? 1 : 0,
        'unlimited' => $tiers !== [] && collect($tiers)->every(fn ($tier) => $tier[4]['unlimited']),
        'tag' => isset($sponsored[$eventId]) ? __('Sponsored') : ($content->is_featured === 'yes' ? __('Featured') : ''),
        'sponsored' => isset($sponsored[$eventId]),
        'featured' => $content->is_featured === 'yes',
        'bookable' => $bookable,
        'regMode' => $content->registration_mode ?: 'ticketing',
        'regUrl' => ($content->registration_mode ?: 'ticketing') === 'corporate' ? route('event.registration.show', $eventId) : null,
        'lifecycle' => ($content->lifecycle_status ?? 'scheduled') ?: 'scheduled',
        'dates' => $dateOptions,
        'countdown' => (int) ($content->countdown_status ?? 1) === 1,
        'lat' => $content->latitude,
        'lng' => $content->longitude,
        'organizerUrl' => $organizer ? route('frontend.organizer.details', [$organizer->id, Str::slug($byName) ?: 'organiser']) : null,
        'url' => route('event.details', [$content->slug, $eventId]),
        'absorb' => ($content->fee_payer ?? 'buyer') === 'organizer' ? 1 : 0,
        'status' => (int) $content->status,
        'endTs' => Carbon::parse($content->end_date_time)->getTimestampMs(),
        'salesClosed' => !empty($content->sales_closed_at),
        'transfers' => !isset($content->allow_transfers) || (bool) $content->allow_transfers,
        'refundRule' => $content->refund_policy ? (str_contains((string) $content->refund_policy, '7 days') ? '7' : (str_contains((string) $content->refund_policy, '48 hours') ? '48' : 'custom')) : 'standard',
        'revealMinutes' => (int) ($content->join_reveal_minutes ?? 15),
      ];
    });
  }

  /** Each tier: [name, price, left, total, info] in checkout2 quantity order. */
  private function tiers(Collection $tickets, Collection $ticketContents, array $sold): array
  {
    $tiers = [];
    foreach ($tickets as $ticket) {
      $content = $ticketContents->get($ticket->id, collect())->firstWhere('language_id', $this->language->id)
        ?: $ticketContents->get($ticket->id, collect())->first();
      $title = $content?->title ?: ($ticket->title ?: __('General admission'));
      $description = trim(strip_tags((string) ($content?->description ?? '')));
      $earlyBird = $this->earlyBird($ticket);

      if ($ticket->pricing_type === 'variation') {
        foreach (json_decode((string) $ticket->variations, true) ?: [] as $variation) {
          $name = (string) ($variation['name'] ?? $title);
          $price = (float) ($variation['price'] ?? 0);
          $limited = ($variation['ticket_available_type'] ?? 'unlimited') === 'limited';
          $left = $limited ? max(0, (int) ($variation['ticket_available'] ?? 0)) : null;
          $sales = $sold[$ticket->id . '|' . $name] ?? 0;
          $maxBuy = ($variation['max_ticket_buy_type'] ?? 'unlimited') === 'limited' ? max(1, (int) ($variation['v_max_ticket_buy'] ?? 1)) : null;
          $tiers[] = $this->tier($name, $price, $left, $sales, [
            'ticketId' => (int) $ticket->id,
            'variation' => $name,
            'description' => $description,
            'maxBuy' => $maxBuy,
            'earlyBird' => $earlyBird ? $this->earlyBirdAmount($earlyBird, $price) : 0,
            'earlyBirdEnds' => $earlyBird['ends'] ?? null,
          ]);
        }
        continue;
      }

      $price = $ticket->pricing_type === 'free' ? 0.0 : (float) ($ticket->price ?? $ticket->f_price ?? 0);
      $limited = $ticket->ticket_available_type === 'limited';
      $left = $limited ? max(0, (int) $ticket->ticket_available) : null;
      $maxBuy = $ticket->max_ticket_buy_type === 'limited' ? max(1, (int) $ticket->max_buy_ticket) : null;
      $tiers[] = $this->tier($title, $price, $left, $sold[$ticket->id . '|*'] ?? 0, [
        'ticketId' => (int) $ticket->id,
        'variation' => null,
        'description' => $description,
        'maxBuy' => $maxBuy,
        'earlyBird' => $earlyBird ? $this->earlyBirdAmount($earlyBird, $price) : 0,
        'earlyBirdEnds' => $earlyBird['ends'] ?? null,
      ]);
    }

    return $tiers;
  }

  /**
   * Unlimited tickets have no stock to show, so they report a generous
   * remaining figure that never trips the design's "selling fast" states.
   */
  private function tier(string $name, float $price, ?int $left, int $sold, array $info): array
  {
    $info['unlimited'] = $left === null;
    if ($left === null) {
      $left = 9999;
    }

    return [$name, $price, $left, $left + $sold, $info];
  }

  private function earlyBird(Ticket $ticket): ?array
  {
    if ($ticket->early_bird_discount !== 'enable' || empty($ticket->early_bird_discount_date)) {
      return null;
    }
    try {
      $ends = Carbon::parse(trim($ticket->early_bird_discount_date . ' ' . $ticket->early_bird_discount_time));
    } catch (\Throwable) {
      return null;
    }
    if ($ends->isPast()) {
      return null;
    }

    return ['type' => $ticket->early_bird_discount_type, 'amount' => (float) $ticket->early_bird_discount_amount, 'ends' => $ends->getTimestampMs()];
  }

  private function earlyBirdAmount(array $earlyBird, float $price): float
  {
    return $earlyBird['type'] === 'fixed'
      ? (float) $earlyBird['amount']
      : round($price * $earlyBird['amount'] / 100, 2);
  }

  /** Tickets issued and not revoked, keyed by "ticketId|variation". */
  private function soldCounts(array $eventIds): array
  {
    if (!Schema::hasTable('booking_tickets')) {
      return [];
    }

    $rows = DB::table('booking_tickets')
      ->whereIn('event_id', $eventIds)
      ->whereNull('revoked_at')
      ->groupBy('event_id', 'ticket_id', 'variation_name')
      ->select('event_id', 'ticket_id', 'variation_name', DB::raw('count(*) as total'))
      ->get();

    // Tica stores the ticket's name in variation_name for normal tickets too,
    // so normal tickets are counted across every name they were sold under.
    $sold = [];
    foreach ($rows as $row) {
      $event = (int) $row->event_id;
      $sold[$event][$row->ticket_id . '|' . ($row->variation_name ?? '')] = (int) $row->total;
      $sold[$event][$row->ticket_id . '|*'] = ($sold[$event][$row->ticket_id . '|*'] ?? 0) + (int) $row->total;
    }

    return $sold;
  }

  /** @return array{0: Carbon, 1: array<int, array{value: string, label: string, ts: int}>} */
  private function startOf($content, Collection $dates): array
  {
    if ($content->date_type === 'multiple') {
      $upcoming = $dates->filter(fn ($date) => Carbon::parse($date->end_date_time)->isFuture())->values();
      $first = $upcoming->first() ?: $dates->first();
      $options = $upcoming->map(fn ($date) => [
        'value' => FullDateTime($date->start_date_time),
        'label' => Carbon::parse($date->start_date_time)->format('D, j M Y, g:i A'),
        'ts' => Carbon::parse($date->start_date_time)->getTimestampMs(),
      ])->values()->all();

      return [$first ? Carbon::parse($first->start_date_time) : Carbon::parse($content->end_date_time), $options];
    }

    $start = Carbon::parse(trim($content->start_date . ' ' . ($content->start_time ?: '00:00')));

    return [$start, []];
  }

  /** The design's month filter keys: nov, dec, otherwise m01..m12. */
  private function monthKey(Carbon $date): string
  {
    return match ((int) $date->format('n')) {
      11 => 'nov',
      12 => 'dec',
      default => 'm' . $date->format('m'),
    };
  }

  private function categoryKey(?string $slug, int $id): string
  {
    return Str::slug((string) $slug) ?: 'category-' . $id;
  }

  private function art($content, string $label, string $name, Carbon $start, string $time): string
  {
    $colour = self::POSTER_COLOURS[(int) $content->id % count(self::POSTER_COLOURS)];
    $image = $this->image($content->thumbnail);
    $escape = fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    $img = $image ? '<img src="' . $escape($image) . '" alt="" loading="lazy" decoding="async">' : '';

    return '<div class="p' . ($image ? ' pp' : '') . '" style="background:' . $colour . ($image ? '' : ';color:#fff') . '">' . $img
      . ($image ? '<b></b>' : '') . '<em>' . $escape($label) . '</em><strong>' . $escape($name) . '</strong>'
      . '<small><span>' . $start->format('j M') . '</span><span>' . $escape($time) . '</span></small></div>';
  }

  private function image(?string $thumbnail): ?string
  {
    $path = 'assets/admin/img/event/thumbnail/' . $thumbnail;

    return !empty($thumbnail) ? asset($path) : null;
  }

  /** Organiser descriptions are rich text; keep basic formatting only. */
  private function description(?string $html): string
  {
    if (trim((string) $html) === '') {
      return '';
    }

    return trim((string) Purifier::clean((string) $html, ['HTML.Allowed' => 'p,br,b,strong,i,em,u,ul,ol,li,h3,h4,a[href]']));
  }

  private function platform(?string $url): string
  {
    // The join link is stored encrypted (Event casts it); only its host is shown.
    $url = rescue(fn () => \Illuminate\Support\Facades\Crypt::decryptString((string) $url), $url, false);
    $host = strtolower((string) parse_url((string) $url, PHP_URL_HOST));

    return match (true) {
      str_contains($host, 'zoom') => 'Zoom',
      str_contains($host, 'meet.google') => 'Google Meet',
      str_contains($host, 'youtube') || str_contains($host, 'youtu.be') => 'YouTube Live',
      str_contains($host, 'instagram') => 'Instagram Live',
      str_contains($host, 'teams') => 'Microsoft Teams',
      default => 'the event link',
    };
  }

  public function money(float $amount, ?string $code = null): string
  {
    if ($amount <= 0) {
      return __('Free');
    }
    $value = preg_replace('/\.00$/', '', number_format($amount, 2, '.', ','));
    if ($code !== null && !TicaCurrency::isBase($code)) {
      return TicaCurrency::symbol($code) . $value;
    }
    $currency = $this->currency();

    return $currency['position'] === 'right' ? $value . $currency['symbol'] : $currency['symbol'] . $value;
  }

  private function basic(): Basic
  {
    return $this->basic ??= Basic::query()->firstOrFail();
  }
}
