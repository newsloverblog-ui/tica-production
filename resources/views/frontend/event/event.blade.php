@extends('frontend.layout')
@section('pageHeading', __('Discover events'))
@section('react-only', 'true')

@php
  $eventsPage = $information['events'];
  $eventStart = function ($event) {
    if ($event->date_type === 'multiple') {
      $latest = eventLatestDates($event->id);
      return !empty($latest) ? $latest->start_date : $event->start_date;
    }
    return $event->start_date;
  };
  $eventPrice = function ($event) {
    $ticket = App\Models\Event\Ticket::where('event_id', $event->id)->orderBy('f_price')->orderBy('price')->first();
    if (!$ticket || $ticket->pricing_type === 'free') return __('Free');
    $price = $ticket->f_price ?? $ticket->price;
    if ($ticket->pricing_type === 'variation') {
      $prices = collect(json_decode($ticket->variations, true) ?: [])->pluck('price')->filter(fn ($value) => is_numeric($value));
      if ($prices->isNotEmpty()) $price = $prices->min();
    }
    return is_numeric($price) ? symbolPrice($price) : __('Free');
  };
  $eventImage = function ($event) {
    $path = 'assets/admin/img/event/thumbnail/' . $event->thumbnail;
    return !empty($event->thumbnail) ? \App\Support\PublicImage::flyer($event->thumbnail) : asset('assets/front/images/home/organizer-live-event-unsplash.jpg');
  };
  $discoverEvents = collect($eventsPage->items())->map(function ($event) use ($eventStart, $eventPrice, $eventImage) {
    $start = \Carbon\Carbon::parse($eventStart($event));
    return [
      'id' => (int) $event->id,
      'title' => $event->title,
      'url' => route('event.details', [$event->slug, $event->id]),
      'image' => $eventImage($event),
      'day' => $start->translatedFormat('D'),
      'dateNumber' => $start->format('d'),
      'month' => $start->translatedFormat('M'),
      'dateLabel' => $start->translatedFormat('D, d M'),
      'time' => \Carbon\Carbon::parse($event->start_time)->translatedFormat('g:i A'),
      'location' => $event->event_type === 'online' ? __('Online event') : trim(($event->city ?: __('Venue')) . ($event->country ? ', ' . $event->country : '')),
      'price' => $eventPrice($event),
      'category' => data_get($event, 'category', __('Event')) ?: __('Event'),
    ];
  })->values();
  $discoverProps = [
    'action' => route('events'),
    'events' => $discoverEvents,
    'categories' => $information['categories']->map(fn ($category) => ['slug' => $category->slug, 'name' => $category->name])->values(),
    'filters' => [
      'search' => (string) request('search-input', ''),
      'location' => (string) request('location', ''),
      'category' => (string) request('category', ''),
      'event' => (string) request('event', ''),
      'dates' => (string) request('dates', ''),
    ],
    'activeCategory' => optional($information['categories']->firstWhere('slug', request('category')))->name,
    'totalLabel' => trans_choice(':count event|:count events', $eventsPage->total(), ['count' => $eventsPage->total()]),
    'paginationHtml' => $eventsPage->appends(request()->query())->links()->render(),
    'clearUrl' => route('events'),
  ];
@endphp

@section('meta-keywords', !empty($seo->meta_keyword_event) ? $seo->meta_keyword_event : '')
@section('meta-description', !empty($seo->meta_description_event) ? $seo->meta_description_event : '')

@section('content')
  <div data-tica-react="discover" data-props='@json($discoverProps, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)'>
    <main class="tica-discover"><header class="wrap pagehead"><p class="tica-eyebrow">{{ __('Find your next moment') }}</p><h1 class="display">{{ __('Discover events') }}</h1><p class="lead">{{ __('Loading events…') }}</p></header></main>
  </div>
@endsection
