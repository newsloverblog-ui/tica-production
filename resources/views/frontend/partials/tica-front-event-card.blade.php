@php
  $tickets = $frontEventTickets->get($event->id, collect());
  $prices = $tickets->flatMap(function ($ticket) { if (($ticket->pricing_type ?? null) === 'variation') return collect(json_decode($ticket->variations ?? '[]', true))->pluck('price'); return collect([$ticket->price ?? $ticket->f_price ?? null]); })->filter(fn($price) => is_numeric($price));
  $dateValue = trim((string) ($event->start_date_time ?? '')) ?: trim((string) ($event->start_date ?? '') . ' ' . (string) ($event->start_time ?? ''));
  try { $moment = \Carbon\Carbon::parse($dateValue); } catch (\Throwable $e) { $moment = now(); }
  $thumbPath = 'assets/admin/img/event/thumbnail/' . $event->thumbnail;
  $thumb = !empty($event->thumbnail) ? asset($thumbPath) : asset('assets/front/images/home/city-pulse-hero.png');
  $eventUrl = route('event.details', [$event->slug, $event->id]);
@endphp
<article class="card"><a class="cardlink" href="{{ $eventUrl }}" aria-label="{{ $event->title }}"><div class="flyer"><img src="{{ $thumb }}" alt="{{ $event->title }}" loading="lazy" decoding="async"></div></a><div class="cardbody"><p class="cdate">{{ $moment->format('D, d M · g:i A') }}</p><h3><a href="{{ $eventUrl }}">{{ $event->title }}</a></h3><p class="meta"><i class="fas fa-map-marker-alt" aria-hidden="true"></i>{{ $event->event_type === 'online' ? __('Online event') : ($event->city ?: $event->address) }}</p><div class="buy"><div class="price">{{ $prices->isNotEmpty() ? __('From') . ' ' . symbolPrice($prices->min()) : __('Free') }}</div><a href="{{ $eventUrl }}">{{ __('Get tickets') }} <i class="fas fa-arrow-right" aria-hidden="true"></i></a></div></div></article>
