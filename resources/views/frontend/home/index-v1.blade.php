@extends('frontend.layout')
@section('pageHeading')
  {{ __('Home') }}
@endsection

@php
  $metaKeywords = !empty($seo->meta_keyword_home) ? $seo->meta_keyword_home : '';
  $metaDescription = !empty($seo->meta_description_home) ? $seo->meta_description_home : '';
@endphp
@section('meta-keywords', "{{ $metaKeywords }}")
@section('meta-description', "$metaDescription")
@section('body-class', 'tica-editorial-home')

@section('custom-style')
  <link rel="stylesheet" href="{{ asset('assets/front/css/home-city-pulse.css') }}?v={{ file_exists(public_path('assets/front/css/home-city-pulse.css')) ? filemtime(public_path('assets/front/css/home-city-pulse.css')) : '1' }}">
@endsection

@section('hero-section')
  @php
    $heroBlueprints = collect([
      ['match' => 'Concerts', 'name' => __('Concerts & Shows'), 'slug' => 'concerts-shows', 'image' => 'concerts-shows.webp', 'kicker' => __('Live music & performances'), 'title' => __('Feel the sound.'), 'accent' => __('Live the moment.'), 'description' => __('From intimate live sets to the biggest stages, find a show worth leaving home for.'), 'icon' => 'fa-music'],
      ['match' => 'Parties', 'name' => __('Parties & Nightlife'), 'slug' => 'parties-nightlife', 'image' => 'parties-nightlife.webp', 'kicker' => __('Parties & nightlife'), 'title' => __('Own the night.'), 'accent' => __('Find your people.'), 'description' => __('Rooftop sessions, club nights and unforgettable parties—your next night out starts here.'), 'icon' => 'fa-glass-cheers'],
      ['match' => 'Arts', 'name' => __('Arts & Culture'), 'slug' => 'arts-culture', 'image' => 'arts-culture.webp', 'kicker' => __('Arts, culture & expression'), 'title' => __('Culture lives here.'), 'accent' => __('Come feel it.'), 'description' => __('Discover exhibitions, festivals and performances shaped by the people moving culture forward.'), 'icon' => 'fa-palette'],
      ['match' => 'Sports', 'name' => __('Sports & Fitness'), 'slug' => 'sports-fitness', 'image' => 'sports-fitness.webp', 'kicker' => __('Sports & fitness'), 'title' => __('Feel every heartbeat.'), 'accent' => __('Be part of it.'), 'description' => __('From match day energy to community fitness, experience the moments that move everyone.'), 'icon' => 'fa-futbol'],
      ['match' => 'Conferences', 'name' => __('Conferences & Seminars'), 'slug' => 'conferences-seminars', 'image' => 'conferences-seminars.webp', 'kicker' => __('Ideas & opportunity'), 'title' => __('Ideas meet people.'), 'accent' => __('Possibility follows.'), 'description' => __('Meet the thinkers, builders and communities creating what comes next.'), 'icon' => 'fa-users'],
      ['match' => 'Campus', 'name' => __('Campus & Youth Events'), 'slug' => 'campus-youth-events', 'image' => 'campus-youth.webp', 'kicker' => __('Campus & youth culture'), 'title' => __('Campus energy.'), 'accent' => __('Made by you.'), 'description' => __('Find the festivals, showcases and student experiences everyone will talk about tomorrow.'), 'icon' => 'fa-graduation-cap'],
      ['match' => 'Faith', 'name' => __('Faith-Based Events'), 'slug' => 'faith-based-events', 'image' => 'faith-based.webp', 'kicker' => __('Faith & community'), 'title' => __('Gather together.'), 'accent' => __('Leave inspired.'), 'description' => __('Discover uplifting worship, music and community experiences happening near you.'), 'icon' => 'fa-heart'],
    ])->map(function ($blueprint) use ($eventCategories) {
      $matchedCategory = $eventCategories->first(function ($category) use ($blueprint) {
        return stripos($category->name, $blueprint['match']) !== false;
      });
      $blueprint['name'] = $matchedCategory->name ?? $blueprint['name'];
      $blueprint['slug'] = $matchedCategory->slug ?? $blueprint['slug'];
      return $blueprint;
    });
  @endphp

  <section class="hero-section city-pulse-hero" data-category-hero>
    <div class="city-pulse-slides" aria-hidden="true">
      @foreach ($heroBlueprints as $heroCategory)
        <div class="city-pulse-slide {{ $loop->first ? 'is-active' : '' }}"
          data-hero-slide="{{ $loop->index }}"
          style="--hero-image: url('{{ asset('assets/front/images/home/categories/' . $heroCategory['image']) }}?v={{ file_exists(public_path('assets/front/images/home/categories/' . $heroCategory['image'])) ? filemtime(public_path('assets/front/images/home/categories/' . $heroCategory['image'])) : '1' }}')"></div>
      @endforeach
    </div>
    <div class="container city-pulse-container">
      <div class="city-pulse-copy">
        <span class="hero-kicker" data-hero-kicker>{{ $heroBlueprints->first()['kicker'] }}</span>
        <h1><span data-hero-title>{{ $heroBlueprints->first()['title'] }}</span> <strong data-hero-accent>{{ $heroBlueprints->first()['accent'] }}</strong></h1>
        <p data-hero-description>{{ $heroBlueprints->first()['description'] }}</p>
        <a class="hero-category-link" data-hero-link href="{{ route('events', ['category' => $heroBlueprints->first()['slug']]) }}">
          <span data-hero-link-label>{{ __('Explore') }} {{ $heroBlueprints->first()['name'] }}</span>
          <i class="fas fa-arrow-right" aria-hidden="true"></i>
        </a>
      </div>

      <div class="hero-side-controls" aria-label="{{ __('Hero navigation') }}">
        <div class="hero-progress" aria-hidden="true">
          @foreach ($heroBlueprints as $heroCategory)
            <span class="{{ $loop->first ? 'is-active' : '' }}" data-hero-progress="{{ $loop->index }}"></span>
          @endforeach
        </div>
        <div class="hero-arrows">
          <button type="button" data-hero-prev aria-label="{{ __('Previous category') }}"><i class="fas fa-arrow-left"></i></button>
          <button type="button" data-hero-next aria-label="{{ __('Next category') }}"><i class="fas fa-arrow-right"></i></button>
        </div>
      </div>

      <form id="event-search" class="city-pulse-search" name="event-search" action="{{ route('events') }}" method="get">
        <div class="search-fields">
          <div class="search-control search-keyword">
            <i class="fas fa-search" aria-hidden="true"></i>
            <input type="search" id="search" name="search-input"
              aria-label="{{ __('Search events, artists, venues or vibes') }}"
              placeholder="{{ __('Search events, artists or venues') }}">
          </div>
          <div class="search-control search-location">
            <i class="fas fa-map-marker-alt" aria-hidden="true"></i>
            <select name="location" aria-label="{{ __('Event location') }}">
              <option value="">{{ __('All cities') }}</option>
              @foreach (['Lagos', 'Abuja', 'Port Harcourt', 'Owerri'] as $city)
                <option value="{{ $city }}" {{ $loop->first ? 'selected' : '' }}>{{ $city }}</option>
              @endforeach
            </select>
          </div>
          <div class="search-control search-category hero-category-picker" data-hero-category-picker>
            <input type="hidden" name="category" value="" data-category-input>
            <button type="button" class="hero-category-trigger" aria-haspopup="listbox" aria-expanded="false" data-category-trigger>
              <i class="fas fa-th-large" aria-hidden="true"></i>
              <span data-category-label>{{ __('All categories') }}</span>
              <i class="fas fa-chevron-down hero-category-chevron" aria-hidden="true"></i>
            </button>
            <div class="hero-category-menu" role="listbox" aria-label="{{ __('Event category') }}" data-category-menu hidden>
              <button type="button" role="option" aria-selected="true" class="is-selected" data-category-option data-value="">
                <span>{{ __('All categories') }}</span><i class="fas fa-check" aria-hidden="true"></i>
              </button>
              @foreach ($categories as $category)
                <button type="button" role="option" aria-selected="false" data-category-option data-value="{{ $category->slug }}">
                  <span>{{ $category->name }}</span><i class="fas fa-check" aria-hidden="true"></i>
                </button>
              @endforeach
            </div>
          </div>
          <button type="submit" class="city-search-button">
            <span>{{ __('Search') }}</span>
            <i class="fas fa-arrow-right" aria-hidden="true"></i>
          </button>
        </div>
      </form>

      <div class="mood-nav" role="tablist" aria-label="{{ __('Browse event categories') }}" tabindex="0">
        @foreach ($heroBlueprints as $heroCategory)
          <button type="button" role="tab" aria-selected="{{ $loop->first ? 'true' : 'false' }}"
            class="{{ $loop->first ? 'is-active' : '' }}" data-hero-tab="{{ $loop->index }}"
            data-kicker="{{ $heroCategory['kicker'] }}" data-title="{{ $heroCategory['title'] }}"
            data-accent="{{ $heroCategory['accent'] }}" data-description="{{ $heroCategory['description'] }}"
            data-label="{{ __('Explore') }} {{ $heroCategory['name'] }}"
            data-url="{{ route('events', ['category' => $heroCategory['slug']]) }}">
            <i class="fas {{ $heroCategory['icon'] }}" aria-hidden="true"></i>
            <span>{{ $heroCategory['name'] }}</span>
          </button>
        @endforeach
      </div>
    </div>
  </section>
@endsection
@section('content')

  @if ($secInfo->featured_section_status == 1)
    @php
      $layeredEvents = DB::table('event_contents')
          ->join('events', 'events.id', '=', 'event_contents.event_id')
          ->where('event_contents.language_id', $currentLanguageInfo->id)
          ->where('events.status', 1)
          ->where('events.end_date_time', '>=', \Carbon\Carbon::now())
          ->orderByRaw("CASE WHEN events.is_featured = 'yes' THEN 0 ELSE 1 END")
          ->orderBy('events.start_date')
          ->limit(10)
          ->get();

      $trendingEvents = $layeredEvents->where('is_featured', 'yes')->take(5);
      if ($trendingEvents->isEmpty()) {
          $trendingEvents = $layeredEvents->take(5);
      }

      // The light rail is the complete upcoming edit, ordered by event date.
      $happeningEvents = $layeredEvents->sortBy(function ($event) {
          return $event->start_date . ' ' . ($event->start_time ?: '00:00:00');
      })->values();

      $layeredEventTickets = \App\Models\Event\Ticket::whereIn('event_id', $layeredEvents->pluck('id'))
          ->get()
          ->groupBy('event_id');

      $editorialEventData = function ($event) use ($layeredEventTickets, $websiteInfo, $publicContentPolicy) {
          $latestOccurrence = $event->date_type === 'multiple' ? eventLatestDates($event->id) : null;
          $eventDateValue = $event->date_type === 'multiple'
              ? optional($latestOccurrence)->start_date_time
              : trim($event->start_date . ' ' . $event->start_time);
          $eventDate = $eventDateValue
              ? \Carbon\Carbon::parse($eventDateValue)->timezone($websiteInfo->timezone)
              : null;
          $eventTime = $eventDateValue
              ? \Carbon\Carbon::parse($eventDateValue)->timezone($websiteInfo->timezone)
              : null;
          $prices = collect();
          $remaining = 0;
          $limitedOnly = true;

          foreach ($layeredEventTickets->get($event->id, collect()) as $ticket) {
              if ($ticket->pricing_type === 'variation') {
                  foreach ((json_decode($ticket->variations, true) ?: []) as $variation) {
                      if (isset($variation['price']) && is_numeric($variation['price'])) {
                          $prices->push((float) $variation['price']);
                      }
                      if (($variation['ticket_available_type'] ?? null) === 'limited') {
                          $remaining += max(0, (int) ($variation['ticket_available'] ?? 0));
                      } else {
                          $limitedOnly = false;
                      }
                  }
              } else {
                  $price = $ticket->price ?? $ticket->f_price;
                  if ($price !== null && is_numeric($price)) $prices->push((float) $price);
                  if ($ticket->ticket_available_type === 'limited') {
                      $remaining += max(0, (int) $ticket->ticket_available);
                  } else {
                      $limitedOnly = false;
                  }
              }
          }

          $thumbnailPath = 'assets/admin/img/event/thumbnail/' . $event->thumbnail;
          $description = html_entity_decode(trim(strip_tags($event->description ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
          if ($publicContentPolicy->containsPlaceholder($description)) {
              $description = __('View event details, venue information and ticket options.');
          }

          return [
              'date' => $eventDate,
              'time' => $eventTime,
              'price' => $prices->isNotEmpty() ? __('From') . ' ' . symbolPrice($prices->min()) : __('Free'),
              'remaining' => $limitedOnly && $remaining > 0 ? $remaining : null,
              'description' => \Illuminate\Support\Str::limit($description, 92),
              'thumbnail' => !empty($event->thumbnail)
                  ? \App\Support\PublicImage::flyer($event->thumbnail)
                  : asset('assets/front/images/home/city-pulse-hero.png'),
          ];
      };
    @endphp

    <div class="tica-editorial-content">
      <section class="editorial-trending" aria-labelledby="editorial-trending-title">
        <div class="container">
          <header class="editorial-section-heading" data-reveal>
            <div>
              <span>{{ __('Popular right now') }}</span>
              <h2 id="editorial-trending-title">{{ __('Trending near you') }}</h2>
              <p>{{ __('The events people are checking out right now.') }}</p>
            </div>
            <a href="{{ route('events') }}">{{ __('See all events') }} <i class="fas fa-arrow-right"></i></a>
          </header>

          @if ($trendingEvents->isEmpty())
            <p class="editorial-empty">{{ __('New events are coming soon.') }}</p>
          @else
            <div class="editorial-trending-grid">
              @foreach ($trendingEvents->take(3) as $event)
                <div class="editorial-trending-card {{ $loop->first ? 'editorial-trending-card--lead' : '' }}" data-reveal style="--reveal-delay: {{ $loop->index * 85 }}ms">
                  @include('frontend.partials.event-layer-card', [
                    'event' => $event,
                    'variant' => 'light',
                    'ticketInventory' => $layeredEventTickets->get($event->id, collect()),
                  ])
                </div>
              @endforeach
            </div>
          @endif
        </div>
      </section>

      @if ($happeningEvents->isNotEmpty())
        <section class="editorial-agenda" aria-labelledby="editorial-agenda-title">
          <div class="container">
            <header class="editorial-section-heading" data-reveal>
              <div>
                <span>{{ __('Coming up') }}</span>
                <h2 id="editorial-agenda-title">{{ __('Happening next') }}</h2>
                <p>{{ __('A simple view of what’s happening around you.') }}</p>
              </div>
              <a href="{{ route('events') }}">{{ __('See the full event guide') }} <i class="fas fa-arrow-right"></i></a>
            </header>

            <div class="editorial-agenda-list">
              @foreach ($happeningEvents->take(4) as $event)
                @php
                  $agenda = $editorialEventData($event);
                @endphp
                <article class="editorial-agenda-row" data-reveal style="--reveal-delay: {{ $loop->index * 75 }}ms">
                  <time class="editorial-agenda-date" datetime="{{ $agenda['date'] ? $agenda['date']->format('Y-m-d') : '' }}">
                    <span>{{ $agenda['date'] ? $agenda['date']->translatedFormat('D') : __('Soon') }}</span>
                    <strong>{{ $agenda['date'] ? $agenda['date']->translatedFormat('d M Y') : '—' }}</strong>
                  </time>
                  <a class="editorial-agenda-image" href="{{ route('event.details', [$event->slug, $event->id]) }}">
                    <img src="{{ $agenda['thumbnail'] }}" alt="{{ $event->title }}" loading="lazy" decoding="async">
                  </a>
                  <div class="editorial-agenda-copy">
                    <h3><a href="{{ route('event.details', [$event->slug, $event->id]) }}">{{ $event->title }}</a></h3>
                    <p class="editorial-agenda-meta">
                      <span><i class="fas fa-map-marker-alt" aria-hidden="true"></i>{{ $event->event_type === 'online' ? __('Online') : ($event->city ?: __('Venue announced soon')) }}</span>
                      @if ($agenda['time'])<span><i class="far fa-clock" aria-hidden="true"></i>{{ $agenda['time']->translatedFormat('h:i A') }}</span>@endif
                    </p>
                    @if ($agenda['description'])<p class="editorial-agenda-description">{{ $agenda['description'] }}</p>@endif
                  </div>
                  <div class="editorial-agenda-action">
                    <span>{{ $agenda['price'] }}</span>
                    @if ($agenda['remaining'])<small>{{ __(':count tickets left', ['count' => $agenda['remaining']]) }}</small>@endif
                    <a href="{{ route('event.details', [$event->slug, $event->id]) }}">{{ __('Get tickets') }} <i class="fas fa-arrow-right"></i></a>
                  </div>
                </article>
              @endforeach
            </div>
          </div>
        </section>
      @endif
    </div>
  @endif

  <!-- Events Section Start -->
  @if (false)
    {{-- Legacy event grid retained in source history; replaced by the editorial event system. --}}
    <section class="events-section pt-110 rpt-90 pb-90 rpb-70 bg-lighter">
      <div class="container">

        <div class="city-section-heading mb-45">
          <div>
            <span>{{ __('What’s popular') }}</span>
            <h2>{{ __('This Weekend in Nigeria') }}</h2>
          </div>
          <a href="{{ route('events') }}">{{ __('See all events') }} <i class="fas fa-arrow-right"></i></a>
        </div>
        @if (count($eventCategories) < 1)
          <p class="text-center">{{ __('No Events Found') }}</p>
        @else
          <ul class="events-filter mb-40">
            <li data-filter="*" class="current">{{ __('All') }}</li>
            @foreach ($eventCategories as $item)
              <li data-filter=".{{ $item->id }}">{{ $item->name }}</li>
            @endforeach
          </ul>
          <div class="row events-active">
            @foreach ($eventCategories as $item)
              @php
                $now_time = \Carbon\Carbon::now();
                $events = DB::table('event_contents')
                    ->join('events', 'events.id', '=', 'event_contents.event_id')
                    ->where([['event_contents.event_category_id', '=', $item->id], ['event_contents.language_id', '=', $currentLanguageInfo->id], ['events.status', 1], ['events.end_date_time', '>=', $now_time], ['events.is_featured', '=', 'yes']])
                    ->orderBy('events.created_at', 'desc')
                    ->get();
              @endphp
              @foreach ($events as $event)
                @php
                  $eventThumbnailPath = 'assets/admin/img/event/thumbnail/' . $event->thumbnail;
                  $eventThumbnail = !empty($event->thumbnail)
                      ? \App\Support\PublicImage::flyer($event->thumbnail)
                      : asset('assets/front/images/home/city-pulse-hero.png');
                @endphp
                <div class="col-lg-4 col-md-6 item {{ $item->id }} motivational">
                  <div class="event-item">
                    <div class="event-image">
                      <a href="{{ route('event.details', [$event->slug, $event->id]) }}">
                        <img src="{{ $eventThumbnail }}" alt="{{ $event->title }}">
                      </a>
                    </div>
                    <div class="event-content">
                      <ul class="time-info">
                        @php
                          if ($event->date_type == 'multiple') {
                              $event_date = eventLatestDates($event->id);
                              $date = strtotime(@$event_date->start_date);
                          } else {
                              $date = strtotime($event->start_date);
                          }
                        @endphp
                        <li>
                          <i class="far fa-calendar-alt"></i>
                          <span>
                            {{ \Carbon\Carbon::parse($date)->timezone($websiteInfo->timezone)->translatedFormat('d M Y') }}
                          </span>
                        </li>
                        <li>
                          <i class="far fa-hourglass"></i>
                          <span
                            title="{{ __('Event Duration') }}">{{ $event->date_type == 'multiple' ? @$event_date->duration : $event->duration }}</span>
                        </li>
                        <li>
                          <i class="far fa-clock"></i>
                          <span>
                            @php
                              $start_time = strtotime($event->start_time);
                            @endphp
                            {{ \Carbon\Carbon::parse($start_time)->timezone($websiteInfo->timezone)->translatedFormat('h:i A') }}
                          </span>
                        </li>
                      </ul>
                      @if ($event->organizer_id != null)
                        @php
                          $organizer = App\Models\Organizer::where('id', $event->organizer_id)->first();
                        @endphp
                        @if ($organizer)
                          <a href="{{ route('frontend.organizer.details', [$organizer->id, str_replace(' ', '-', $organizer->username)]) }}"
                            class="organizer">{{ __('By') }}&nbsp;&nbsp;{{ $organizer->username }}</a>
                        @endif
                      @else
                        @php
                          $admin = App\Models\Admin::first();
                        @endphp
                        <a href="{{ route('frontend.organizer.details', [$admin->id, str_replace(' ', '-', $admin->username), 'admin' => 'true']) }}"
                          class="organizer">{{ $admin->username }}</a>
                      @endif
                      <h5>
                        <a href="{{ route('event.details', [$event->slug, $event->id]) }}">
                          @if (strlen($event->title) > 30)
                            {{ mb_substr($event->title, 0, 30) . '...' }}
                          @else
                            {{ $event->title }}
                          @endif
                        </a>
                      </h5>
                      @php
                        $desc = strip_tags($event->description);
                      @endphp

                      @if (strlen($desc) > 100)
                        <p class="event-description">{{ mb_substr($desc, 0, 100) . '....' }}</p>
                      @else
                        <p class="event-description">{{ $desc }}</p>
                      @endif
                      @php
                        if ($event->event_type == 'online') {
                            $ticket = App\Models\Event\Ticket::where('event_id', $event->id)
                                ->orderBy('price', 'asc')
                                ->first();
                        } else {
                            $ticket = App\Models\Event\Ticket::where([['event_id', $event->id], ['price', '!=', null]])
                                ->orderBy('price', 'asc')
                                ->first();
                            if (empty($ticket)) {
                                $ticket = App\Models\Event\Ticket::where([['event_id', $event->id], ['f_price', '!=', null]])
                                    ->orderBy('price', 'asc')
                                    ->first();
                            }
                        }
                        $event_count = DB::table('tickets')
                            ->where('event_id', $event->id)
                            ->get()
                            ->count();
                      @endphp
                      <div class="price-remain">
                        <div class="location">
                          @if ($event->event_type == 'venue')
                            <i class="fas fa-map-marker-alt"></i>
                            <span>
                              @if ($event->city != null)
                                {{ $event->city }}
                              @endif
                              @if ($event->country)
                                , {{ $event->country }}
                              @endif
                            </span>
                          @else
                            <i class="fas fa-map-marker-alt"></i>
                            <span>{{ __('Online') }}</span>
                          @endif
                        </div>
                        <span>
                          @if ($ticket)
                            @if ($ticket->event_type == 'online')
                              @if ($ticket->price != null)
                                <span class="price" dir="ltr">
                                  @if ($ticket->early_bird_discount == 'enable')
                                    @php
                                      $discount_date = Carbon\Carbon::parse($ticket->early_bird_discount_date . $ticket->early_bird_discount_time);
                                    @endphp

                                    @if ($ticket->early_bird_discount_type == 'fixed' && !$discount_date->isPast())
                                      @php
                                        $calculate_price = $ticket->price - $ticket->early_bird_discount_amount;
                                      @endphp
                                      {{ symbolPrice($calculate_price) }}
                                      <span>
                                        <del>
                                          {{ symbolPrice($ticket->price) }}
                                        </del>
                                      </span>
                                    @elseif ($ticket->early_bird_discount_type == 'percentage' && !$discount_date->isPast())
                                      @php
                                        $p_price = ($ticket->price * $ticket->early_bird_discount_amount) / 100;
                                        $calculate_price = $ticket->price - $p_price;
                                      @endphp

                                      {{ symbolPrice($calculate_price) }}
                                      <span>
                                        <del>
                                          {{ symbolPrice($ticket->price) }}
                                        </del>
                                      </span>
                                    @else
                                      @php
                                        $calculate_price = $ticket->price;
                                      @endphp
                                      {{ symbolPrice($calculate_price) }}
                                    @endif
                                  @else
                                    @php
                                      $calculate_price = $ticket->price;
                                    @endphp
                                    {{ symbolPrice($calculate_price) }}
                                  @endif

                                </span>
                              @else
                                <span class="price">{{ __('Free') }}</span>
                              @endif
                            @endif
                            @if ($ticket->event_type == 'venue')
                              @if ($ticket->pricing_type == 'variation')
                                <span class="price" dir="ltr">
                                  @php
                                    $variation = json_decode($ticket->variations, true);
                                    $v_min_price = array_reduce(
                                        $variation,
                                        function ($a, $b) {
                                            return $a['price'] < $b['price'] ? $a : $b;
                                        },
                                        array_shift($variation),
                                    );
                                    $price = $v_min_price['price'];
                                  @endphp
                                  <span class="price">
                                    @if ($currentLanguageInfo->direction == 1)
                                      @if ($event_count > 1)<small>{{ __('from') }}</small>@endif
                                    @endif
                                    @if ($ticket->early_bird_discount == 'enable')
                                      @php
                                        $discount_date = Carbon\Carbon::parse($ticket->early_bird_discount_date . $ticket->early_bird_discount_time);
                                      @endphp
                                      @if ($ticket->early_bird_discount_type == 'fixed' && !$discount_date->isPast())
                                        @php
                                          $calculate_price = $price - $ticket->early_bird_discount_amount;
                                        @endphp
                                        {{ symbolPrice($calculate_price) }}
                                        <span><del>
                                            {{ symbolPrice($price) }}
                                          </del></span>
                                      @elseif ($ticket->early_bird_discount_type == 'percentage' && !$discount_date->isPast())
                                        @php
                                          $p_price = ($price * $ticket->early_bird_discount_amount) / 100;
                                          $calculate_price = $p_price - $price;
                                        @endphp
                                        {{ symbolPrice($calculate_price) }}

                                        <span>
                                          <del>
                                            {{ symbolPrice($price) }}
                                          </del>
                                        </span>
                                      @else
                                        @php
                                          $calculate_price = $price;
                                        @endphp
                                        {{ symbolPrice($calculate_price) }}
                                      @endif
                                    @else
                                      @php
                                        $calculate_price = $price;
                                      @endphp
                                      {{ symbolPrice($calculate_price) }}
                                    @endif
                                    @if ($currentLanguageInfo->direction != 1)
                                      @if ($event_count > 1)<small>{{ __('from') }}</small>@endif
                                    @endif
                                  </span>
                                </span>
                              @elseif($ticket->pricing_type == 'normal')
                                <span class="price" dir="ltr">
                                  @if ($currentLanguageInfo->direction == 1)
                                    @if ($event_count > 1)<small>{{ __('from') }}</small>@endif
                                  @endif
                                  @if ($ticket->early_bird_discount == 'enable')
                                    {{-- check discount date over or not --}}
                                    @php
                                      $discount_date = Carbon\Carbon::parse($ticket->early_bird_discount_date . $ticket->early_bird_discount_time);
                                    @endphp

                                    @if ($ticket->early_bird_discount_type == 'fixed' && !$discount_date->isPast())
                                      @php
                                        $calculate_price = $ticket->price - $ticket->early_bird_discount_amount;
                                      @endphp

                                      {{ symbolPrice($calculate_price) }}
                                      <span>
                                        <del>
                                          {{ symbolPrice($ticket->price) }}
                                        </del>
                                      </span>
                                    @elseif ($ticket->early_bird_discount_type == 'percentage' && !$discount_date->isPast())
                                      @php
                                        $p_price = ($ticket->price * $ticket->early_bird_discount_amount) / 100;
                                        $calculate_price = $ticket->price - $p_price;
                                      @endphp
                                      {{ symbolPrice($calculate_price) }}

                                      <span>
                                        <del>
                                          {{ symbolPrice($ticket->price) }}
                                        </del>
                                      </span>
                                    @else
                                      @php
                                        $calculate_price = $ticket->price;
                                      @endphp
                                      {{ symbolPrice($calculate_price) }}
                                    @endif
                                  @else
                                    @php
                                      $calculate_price = $ticket->price;
                                    @endphp
                                    {{ symbolPrice($calculate_price) }}
                                  @endif

                                  @if ($currentLanguageInfo->direction != 1)
                                    @if ($event_count > 1)<small>{{ __('from') }}</small>@endif
                                  @endif
                                </span>
                              @else
                                <span class="price">{{ __('Free') }}
                                  @if ($event_count > 1)<small>{{ __('from') }}</small>@endif
                              @endif
                            @endif
                          @endif
                        </span>
                      </div>

                    </div>
                    @if (Auth::guard('customer')->check())
                      @php
                        $customer_id = Auth::guard('customer')->user()->id;
                        $event_id = $event->id;
                        $checkWishList = checkWishList($event_id, $customer_id);
                      @endphp
                    @else
                      @php
                        $checkWishList = false;
                      @endphp
                    @endif
                    <form method="POST" class="wishlist-action-form"
                      action="{{ $checkWishList == false ? route('addto.wishlist', $event->id) : route('remove.wishlist', $event->id) }}">
                      @csrf
                      <button type="submit" class="wishlist-btn {{ $checkWishList == true ? 'bg-success' : '' }}">
                        <i class="far fa-bookmark"></i>
                      </button>
                    </form>
                  </div>
                </div>
              @endforeach
            @endforeach

          </div>
        @endif
      </div>
      @if (!empty(showAd(3)))
        <div class="text-center mt-4">
          {!! showAd(3) !!}
        </div>
      @endif
    </section>
  @endif
  <!-- Events Section End -->

  <!-- Category Section Start -->
    <section class="category-section editorial-category-section">
      <div class="container">
        <div class="editorial-section-heading" data-reveal>
          <div>
            <span>{{ __('Explore by category') }}</span>
            <h2>{{ __('Find your kind of experience') }}</h2>
            <p>{{ __('Discover events that match your mood.') }}</p>
          </div>
          <a href="{{ route('events') }}">{{ __('See all categories') }} <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="category-wrap text-white">
          @if (count($eventCategories) > 0)
            @foreach ($eventCategories as $item)
              @php
                $categoryImagePath = 'assets/admin/img/event-category/' . $item->image;
                $categoryImage = !empty($item->image)
                    ? asset($categoryImagePath)
                    : asset('assets/front/images/home/city-pulse-hero.png');
              @endphp
              <a href="{{ route('events', ['category' => $item->slug]) }}" class="category-item" data-reveal style="--reveal-delay: {{ min($loop->index * 70, 350) }}ms">
                <img src="{{ $categoryImage }}" alt="{{ $item->name }}">
                <div class="category-content">
                  <h5>{{ $item->name }}</h5>
                </div>
              </a>
            @endforeach
          @else
            <h3 class="text-dark">{{ __('No Category Found') }}</h3>
          @endif


        </div>
      </div>
    </section>
  <!-- Category Section End -->

  <section class="pulse-cities-section">
    <div class="container">
      <div class="editorial-section-heading" data-reveal>
        <div>
          <span>{{ __('Featured cities') }}</span>
          <h2>{{ __('Events in your city') }}</h2>
          <p>{{ __('Find what’s happening wherever you are.') }}</p>
        </div>
        <a href="{{ route('events') }}">{{ __('See all cities') }} <i class="fas fa-arrow-right"></i></a>
      </div>
      <div class="pulse-city-grid">
        @foreach ([
          ['name' => 'Lagos', 'image' => '1752166657.jpg'],
          ['name' => 'Abuja', 'image' => '1752165606.jpg'],
          ['name' => 'Port Harcourt', 'image' => '1756198131.png'],
          ['name' => 'Owerri', 'image' => '1792574250.jpg']
        ] as $city)
          <a class="pulse-city-card" href="{{ route('events', ['location' => $city['name']]) }}" data-reveal style="--reveal-delay: {{ $loop->index * 75 }}ms">
            <img src="{{ \App\Support\PublicImage::flyer($city['image']) }}" alt="{{ $city['name'] }} events">
            <div><h3>{{ $city['name'] }}</h3><span>{{ __('Explore events') }} <i class="fas fa-arrow-right"></i></span></div>
          </a>
        @endforeach
      </div>
    </div>
  </section>

  <section class="organizer-pulse-section">
    <div class="container">
      <div class="organizer-pulse-card" data-reveal>
        <img class="organizer-pulse-image" src="{{ asset('assets/front/images/home/organizer-live-event-unsplash.jpg') }}"
          alt="" aria-hidden="true" loading="lazy" decoding="async">
        <div class="organizer-pulse-content">
          <span>{{ __('For organizers') }}</span>
          <h2>{{ __('Bigger events. Real people.') }}</h2>
          <p>{{ __('From intimate experiences to major activations, Tica helps you reach the right audience and bring your vision to life.') }}</p>
          <a href="{{ route('organizer.signup') }}">{{ __('Create an event') }} <i class="fas fa-arrow-right"></i></a>
        </div>
      </div>
    </div>
  </section>

  <!-- About Section Start -->
  @php
    $showLegacyAbout = $secInfo->about_section_status == 1
        && $aboutUsSection
        && !$publicContentPolicy->containsPlaceholder(implode(' ', [$aboutUsSection->title, $aboutUsSection->subtitle, $aboutUsSection->text]));
    $showLegacyFeatures = $secInfo->features_section_status == 1
        && $featureEventSection
        && !$publicContentPolicy->containsPlaceholder(implode(' ', [$featureEventSection->title, $featureEventSection->text, $featureEventItems->pluck('text')->implode(' ')]));
    $showLegacyHowWork = $secInfo->how_work_section_status == 1
        && $howWork
        && !$publicContentPolicy->containsPlaceholder(implode(' ', [$howWork->title, $howWork->text, $howWorkItems->pluck('text')->implode(' ')]));
    $showLegacyTestimonials = $secInfo->testimonials_section_status == 1
        && isset($testimonialData, $testimonials)
        && $testimonialData
        && !$publicContentPolicy->containsPlaceholder(implode(' ', [$testimonialData->title, $testimonialData->text, $testimonials->pluck('comment')->implode(' ')]));
    $showLegacyPartners = $secInfo->partner_section_status == 1
        && $partnerInfo
        && !$publicContentPolicy->containsPlaceholder(implode(' ', [$partnerInfo->title, $partnerInfo->text]));
  @endphp

  @if ($showLegacyAbout)
    <section class="about-section pb-120 rpb-95">
      <div class="container">
        @if (is_null($aboutUsSection))
          <h2 class="text-center">{{ __('No data found for about section') }}</h2>
        @endif
        <div class="row align-items-center">
          <div class="col-lg-6">
            <div class="about-image-part pt-10 rmb-55">
              @if (!is_null($aboutUsSection))
                <img class="lazy" data-src="{{ asset('assets/admin/img/about-us-section/' . $aboutUsSection->image) }}"
                  alt="About">
              @endif
            </div>
          </div>
          <div class="col-lg-6">
            <div class="about-content">
              <div class="section-title mb-30">
                <h2>{{ $aboutUsSection ? $aboutUsSection->title : '' }}</h2>
              </div>
              <p>{{ $aboutUsSection ? $aboutUsSection->subtitle : '' }}</p>
              <div>
                {!! $aboutUsSection ? $aboutUsSection->text : '' !!}
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  @endif
  <!-- About Section End -->


  <!-- Feature Section Start -->
  @if ($showLegacyFeatures || $showLegacyHowWork)
  <section class="feature-section pt-110 rpt-90 bg-lighter">
    @if ($showLegacyFeatures)
      <div class="container pb-90 rpb-70">
        <div class="section-title text-center mb-55">
          <h2>{{ $featureEventSection ? $featureEventSection->title : '' }}</h2>
          <p>{{ $featureEventSection ? $featureEventSection->text : '' }}</p>
          @if (count($featureEventItems) < 1)
            <h2>{{ __('No data found for features section') }}</h2>
          @endif
        </div>
        <div class="row justify-content-center">
          @foreach ($featureEventItems as $item)
            <div class="col-xl-4 col-md-6">
              <div class="feature-item">
                <i class="{{ $item->icon }}"></i>
                <div class="feature-content">
                  <h5>{{ $item->title }}</h5>
                  <p>{{ $item->text }}</p>
                </div>
              </div>
            </div>
          @endforeach
        </div>

      </div>
    @endif
    @if ($showLegacyHowWork)
      @if ($howWork)
        <div class="work-process text-center">
          <div class="container">
            <div class="work-process-inner pt-110 rpt-90 pb-80 rpb-60">

              <div class="section-title mb-60">
                <h2>{{ $howWork->title }}</h2>
                <p>{{ $howWork->text }}</p>
              </div>
              <div class="row justify-content-center">
                @foreach ($howWorkItems as $item)
                  <div class="col-xl-3 col-md-6">
                    <div class="work-process-item">
                      <div class="icon">
                        <span class="number">{{ $item->serial_number }}</span>
                        <i class="{{ $item->icon }}"></i>
                      </div>
                      <div class="content">
                        <h4>{{ $item->title }}</h4>
                        <p>{{ $item->text }}</p>
                      </div>
                    </div>
                  </div>
                @endforeach
              </div>
            </div>
          </div>
        </div>
      @else
        <div class="work-process text-center">
          <div class="container">
            <h2>{{ __('No Data Found for how work section') }}</h2>
          </div>
        </div>
      @endif
    @endif
  </section>
  @endif
  <!-- Feature Section End -->


  <!-- Testimonial Section Start -->
  @if ($showLegacyTestimonials)
    <section class="testimonial-section pt-120 rpt-80">
      <div class="container">
        <div class="row pb-75 rpb-55">
          <div class="col-lg-4">
            <div class="testimonial-content pt-10 rmb-55">
              <div class="section-title mb-30">
                <h2>{{ $testimonialData ? $testimonialData->title : __('What say our client about us') }}</h2>
              </div>
              <p>{{ $testimonialData ? $testimonialData->text : '' }}</p>
              <div class="total-client-reviews mt-40 bg-lighter">
                <div class="review-images mb-30">
                  @if (!is_null($testimonialData))
                    <img class="lazy"
                      data-src="{{ asset('assets/admin/img/testimonial/' . $testimonialData->image) }}" alt="Reviewer">
                  @else
                    <img class="lazy" data-src="{{ asset('assets/admin/img/testimonial/clients.png') }}"
                      alt="Reviewer">
                  @endif
                  <span class="pluse"><i class="fas fa-plus"></i></span>
                </div>
                <h6>{{ $testimonialData ? $testimonialData->review_text : __('0 Clients Reviews') }}</h6>
              </div>
            </div>
          </div>
          <div class="col-lg-8">
            <div class="testimonial-wrap">
              @if (count($testimonials) > 0)
                <div class="row">
                  @foreach ($testimonials as $item)
                    <div class="col-md-6">
                      <div class="testimonial-item">
                        <div class="author">
                          <img class="lazy" data-src="{{ asset('assets/admin/img/clients/' . $item->image) }}"
                            alt="Author">
                          <div class="content">
                            <h5>{{ $item->name }}</h5>
                            <span>{{ $item->occupation }}</span>
                            <div class="ratting">
                              @for ($i = 1; $i <= $item->rating; $i++)
                                <i class="fas fa-star"></i>
                              @endfor
                            </div>
                          </div>
                        </div>
                        <p>{{ $item->comment }}</p>
                      </div>
                    </div>
                  @endforeach
                </div>
              @else
                <h4 class="text-center">{{ __('No Review Found') }}</h4>
              @endif
            </div>
          </div>
        </div>
        <hr>
      </div>

    </section>
  @endif
  <!-- Testimonial Section End -->

  <!-- Client Logo Start -->
  @if ($showLegacyPartners)
    @php
      $visiblePartners = $partners->filter(function ($partner) {
          return trim((string) $partner->image) !== '';
      });
    @endphp
    @if ($visiblePartners->isNotEmpty())
    <section class="client-logo-area text-center pt-95 rpt-80 pb-90 rpb-70">
      <div class="container">
        <div class="section-title mb-55">
          <h2>{{ $partnerInfo ? $partnerInfo->title : __('Our Partner') }}</h2>
          <p>{{ $partnerInfo ? $partnerInfo->text : '' }}</p>
        </div>
        <div class="client-logo-wrap">
          @if (count($visiblePartners) > 0)
            @foreach ($visiblePartners as $item)
              <div class="client-logo-item">
                <a href="{{ $item->url }}" target="_blank"><img class="lazy"
                    data-src="{{ asset('assets/admin/img/partner/' . $item->image) }}" alt="Client Logo"></a>
              </div>
            @endforeach
          @else
            <h5>{{ __('No Partner Found') }}</h5>
          @endif
        </div>
      </div>
    </section>
    @endif
  @endif
  <!-- Client Logo End -->
@endsection

@section('custom-script')
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      var revealItems = Array.prototype.slice.call(document.querySelectorAll('[data-reveal]'));

      document.documentElement.classList.add('motion-ready');

      if (reduceMotion || !('IntersectionObserver' in window)) {
        revealItems.forEach(function (item) { item.classList.add('is-visible'); });
      } else {
        var revealObserver = new IntersectionObserver(function (entries, observer) {
          entries.forEach(function (entry) {
            if (!entry.isIntersecting) return;
            entry.target.classList.add('is-visible');
            observer.unobserve(entry.target);
          });
        }, { threshold: 0.14, rootMargin: '0px 0px -44px 0px' });

        revealItems.forEach(function (item) { revealObserver.observe(item); });
      }

      var categoryHero = document.querySelector('[data-category-hero]');

      if (categoryHero) {
        var heroTabs = Array.prototype.slice.call(categoryHero.querySelectorAll('[data-hero-tab]'));
        var heroSlides = Array.prototype.slice.call(categoryHero.querySelectorAll('[data-hero-slide]'));
        var heroProgress = Array.prototype.slice.call(categoryHero.querySelectorAll('[data-hero-progress]'));
        var heroKicker = categoryHero.querySelector('[data-hero-kicker]');
        var heroTitle = categoryHero.querySelector('[data-hero-title]');
        var heroAccent = categoryHero.querySelector('[data-hero-accent]');
        var heroDescription = categoryHero.querySelector('[data-hero-description]');
        var heroLink = categoryHero.querySelector('[data-hero-link]');
        var heroLinkLabel = categoryHero.querySelector('[data-hero-link-label]');
        var heroCopy = categoryHero.querySelector('.city-pulse-copy');
        var activeHeroIndex = 0;
        var heroTimer = null;

        function showHero(index, shouldFocus) {
          if (!heroTabs.length) return;
          activeHeroIndex = (index + heroTabs.length) % heroTabs.length;
          var activeTab = heroTabs[activeHeroIndex];

          heroTabs.forEach(function (tab, tabIndex) {
            var isActive = tabIndex === activeHeroIndex;
            tab.classList.toggle('is-active', isActive);
            tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
            tab.setAttribute('tabindex', isActive ? '0' : '-1');
          });
          heroSlides.forEach(function (slide, slideIndex) {
            slide.classList.toggle('is-active', slideIndex === activeHeroIndex);
          });
          heroProgress.forEach(function (progress, progressIndex) {
            progress.classList.toggle('is-active', progressIndex === activeHeroIndex);
          });

          heroKicker.textContent = activeTab.dataset.kicker;
          heroTitle.textContent = activeTab.dataset.title;
          heroAccent.textContent = activeTab.dataset.accent;
          heroDescription.textContent = activeTab.dataset.description;
          heroLink.href = activeTab.dataset.url;
          heroLinkLabel.textContent = activeTab.dataset.label;

          if (!reduceMotion && heroCopy) {
            heroCopy.classList.remove('is-changing');
            window.requestAnimationFrame(function () {
              heroCopy.classList.add('is-changing');
            });
          }

          if (shouldFocus) activeTab.focus({ preventScroll: true });
        }

        function startHeroTimer() {
          if (reduceMotion) return;
          window.clearInterval(heroTimer);
          heroTimer = window.setInterval(function () {
            showHero(activeHeroIndex + 1, false);
          }, 6500);
        }

        heroTabs.forEach(function (tab, index) {
          tab.addEventListener('click', function () {
            showHero(index, false);
            startHeroTimer();
          });
          tab.addEventListener('keydown', function (event) {
            if (event.key === 'ArrowRight' || event.key === 'ArrowLeft') {
              event.preventDefault();
              showHero(index + (event.key === 'ArrowRight' ? 1 : -1), true);
              startHeroTimer();
            }
          });
        });

        categoryHero.querySelector('[data-hero-prev]').addEventListener('click', function () {
          showHero(activeHeroIndex - 1, false);
          startHeroTimer();
        });
        categoryHero.querySelector('[data-hero-next]').addEventListener('click', function () {
          showHero(activeHeroIndex + 1, false);
          startHeroTimer();
        });
        categoryHero.addEventListener('mouseenter', function () { window.clearInterval(heroTimer); });
        categoryHero.addEventListener('mouseleave', startHeroTimer);
        categoryHero.addEventListener('focusin', function () { window.clearInterval(heroTimer); });
        categoryHero.addEventListener('focusout', startHeroTimer);
        showHero(0, false);
        startHeroTimer();
      }

      document.querySelectorAll('[data-hero-category-picker]').forEach(function (picker) {
        var trigger = picker.querySelector('[data-category-trigger]');
        var menu = picker.querySelector('[data-category-menu]');
        var input = picker.querySelector('[data-category-input]');
        var label = picker.querySelector('[data-category-label]');
        var options = Array.prototype.slice.call(picker.querySelectorAll('[data-category-option]'));

        function setPickerOpen(isOpen, focusFirst) {
          picker.classList.toggle('is-open', isOpen);
          trigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
          menu.hidden = !isOpen;
          if (isOpen && focusFirst && options.length) options[0].focus();
        }

        trigger.addEventListener('click', function () {
          setPickerOpen(!picker.classList.contains('is-open'), false);
        });

        trigger.addEventListener('keydown', function (event) {
          if (event.key === 'ArrowDown') {
            event.preventDefault();
            setPickerOpen(true, true);
          }
        });

        options.forEach(function (option) {
          option.addEventListener('click', function () {
            input.value = option.dataset.value;
            label.textContent = option.querySelector('span').textContent;
            options.forEach(function (item) {
              var selected = item === option;
              item.classList.toggle('is-selected', selected);
              item.setAttribute('aria-selected', selected ? 'true' : 'false');
            });
            setPickerOpen(false, false);
            trigger.focus({ preventScroll: true });
          });

          option.addEventListener('keydown', function (event) {
            var optionIndex = options.indexOf(option);
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
              event.preventDefault();
              options[(optionIndex + (event.key === 'ArrowDown' ? 1 : -1) + options.length) % options.length].focus();
            }
            if (event.key === 'Escape') {
              event.preventDefault();
              setPickerOpen(false, false);
              trigger.focus({ preventScroll: true });
            }
          });
        });

        document.addEventListener('click', function (event) {
          if (!picker.contains(event.target)) setPickerOpen(false, false);
        });
      });

      function syncRailControls(railName) {
        var rail = document.querySelector('[data-event-rail="' + railName + '"]');
        var controls = document.querySelectorAll('[data-rail-prev="' + railName + '"], [data-rail-next="' + railName + '"]');

        if (!rail) return;

        var canScroll = rail.scrollWidth > rail.clientWidth + 2;
        var railWrap = rail.closest('.week-rail-wrap');
        if (railWrap) railWrap.classList.toggle('is-scrollable', canScroll);

        controls.forEach(function (button) {
          button.hidden = !canScroll;
        });
      }

      document.querySelectorAll('[data-rail-prev], [data-rail-next]').forEach(function (button) {
        button.addEventListener('click', function () {
          var direction = button.hasAttribute('data-rail-next') ? 1 : -1;
          var railName = button.getAttribute(direction === 1 ? 'data-rail-next' : 'data-rail-prev');
          var rail = document.querySelector('[data-event-rail="' + railName + '"]');

          if (rail) {
            rail.scrollBy({ left: direction * Math.min(rail.clientWidth * 0.8, 680), behavior: 'smooth' });
          }
        });
      });

      document.querySelectorAll('[data-event-rail]').forEach(function (rail) {
        var cards = rail.querySelectorAll('.layer-event-card');
        var middleCard = cards[Math.floor(cards.length / 2)];

        if (middleCard && window.innerWidth >= 768) {
          rail.scrollLeft = middleCard.offsetLeft - ((rail.clientWidth - middleCard.offsetWidth) / 2);
        }

        syncRailControls(rail.getAttribute('data-event-rail'));
      });

      function updateRailDepth(rail) {
        var cards = Array.prototype.slice.call(rail.querySelectorAll('.layer-event-card'));
        if (!cards.length) return;

        if (window.innerWidth < 768) {
          cards.forEach(function (card) {
            card.classList.remove('is-spotlight', 'is-near', 'is-edge');
          });
          return;
        }

        var railBox = rail.getBoundingClientRect();
        var railCenter = railBox.left + railBox.width / 2;
        var ranked = cards.map(function (card) {
          var box = card.getBoundingClientRect();
          return { card: card, distance: Math.abs((box.left + box.width / 2) - railCenter) };
        }).sort(function (a, b) { return a.distance - b.distance; });

        cards.forEach(function (card) {
          card.classList.remove('is-spotlight', 'is-near', 'is-edge');
          card.classList.add('is-edge');
        });

        ranked.forEach(function (entry, index) {
          entry.card.classList.remove('is-edge');
          var depthClass = index === 0
            ? 'is-spotlight'
            : (cards.length > 3 && index < 3 ? 'is-near' : 'is-edge');
          entry.card.classList.add(depthClass);
        });
      }

      document.querySelectorAll('[data-event-rail]').forEach(function (rail) {
        var depthFrame = null;
        updateRailDepth(rail);
        rail.addEventListener('scroll', function () {
          window.cancelAnimationFrame(depthFrame);
          depthFrame = window.requestAnimationFrame(function () { updateRailDepth(rail); });
        }, { passive: true });
      });

      var countdowns = Array.prototype.slice.call(document.querySelectorAll('[data-event-countdown]'));

      function updateEventCountdowns() {
        var now = Date.now();

        countdowns.forEach(function (countdown) {
          var start = Date.parse(countdown.dataset.eventStart);
          var remaining = start - now;

          if (!Number.isFinite(start) || remaining <= 0) {
            countdown.textContent = '{{ __('Starting now') }}';
            return;
          }

          var days = Math.floor(remaining / 86400000);
          var hours = Math.floor((remaining % 86400000) / 3600000);
          var minutes = Math.floor((remaining % 3600000) / 60000);

          if (days > 0) {
            countdown.textContent = '{{ __('Starts in') }} ' + days + 'd ' + hours + 'h';
          } else if (hours > 0) {
            countdown.textContent = '{{ __('Starts in') }} ' + hours + 'h ' + minutes + 'm';
          } else {
            countdown.textContent = '{{ __('Starts in') }} ' + Math.max(1, minutes) + 'm';
          }
        });
      }

      updateEventCountdowns();
      if (countdowns.length) window.setInterval(updateEventCountdowns, 60000);

      window.addEventListener('resize', function () {
        document.querySelectorAll('[data-event-rail]').forEach(function (rail) {
          syncRailControls(rail.getAttribute('data-event-rail'));
          updateRailDepth(rail);
        });
      });
    });
  </script>
@endsection
