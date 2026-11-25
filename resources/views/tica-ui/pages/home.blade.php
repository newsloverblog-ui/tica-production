@verbatim
@endverbatim
@php
  $tsHome = \App\Support\TicaSite::get('home');
  $tsOn = fn ($key) => $tsHome['sections'][$key] ?? true;
  $tsOrder = array_flip($tsHome['section_order']);
  $tsOff = fn ($key) => ' data-home-section="' . $key . '" style="order:' . ($tsOrder[$key] ?? 99) . ';' . (($tsHome['sections'][$key] ?? true) ? '' : 'display:none;') . '"' . (($tsHome['sections'][$key] ?? true) ? '' : ' data-off');
  $tsImg = fn ($path) => \App\Support\TicaSite::imageUrl($path);
  $tsHeroPhotos = array_values(array_filter($tsHome['hero_images'], fn ($path) => filled($path)));
  $tsOrganizerTitle = trim((string) ($tsHome['organisers_title'] ?? ''));
  $tsOrganizerTitleLines = preg_split('/\R+|(?<=\.)\h*(?=TICA\b)/u', $tsOrganizerTitle, 2) ?: [$tsOrganizerTitle];
  $tsEticketTitle = trim((string) ($tsHome['eticket_title'] ?? ''));
  $tsEticketTitleLines = preg_split('/\s+(?=on your phone\b)/iu', $tsEticketTitle, 2) ?: [$tsEticketTitle];
  $tsOrganizerImages = array_values($tsHome['organisers_images']);
@endphp
@verbatim
<section class="page" data-page="home">
  <div class="h-hero">
    <div class="h-frame">
      @endverbatim
      @if ($tsHeroPhotos)
        <div class="h-slides" aria-hidden="true">
          @foreach ($tsHeroPhotos as $tsHeroPhoto)
            <img class="h-photo{{ $loop->first ? ' on' : '' }}"
              @if ($loop->first && $initialPage === 'home') src="{{ $tsImg($tsHeroPhoto) }}" fetchpriority="high"
              @elseif ($loop->first) data-src="{{ $tsImg($tsHeroPhoto) }}"
              @else data-hero-src="{{ $tsImg($tsHeroPhoto) }}"
              @endif
              alt="" decoding="async">
          @endforeach
        </div>
      @endif
@verbatim

      <div class="h-shade"></div><div class="h-light" aria-hidden="true"><i></i><i></i><i></i></div>
      <div class="h-in">
@endverbatim
        <h1>{!! nl2br(e($tsHome['hero_title']), false) !!}</h1>
        @if ($tsHome['hero_text'])<p>{{ $tsHome['hero_text'] }}</p>@endif
@verbatim

        <form class="search ms" id="home-search" action="/discover" role="search" aria-label="Find events">
          <div class="ms-f ms-q"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6.500"/><path d="M16 16l4 4"/></svg><input id="h-q" name="q" type="search" placeholder="Events or artists" aria-label="Search events or artists" autocomplete="off"></div>
          <div class="ms-f ms-s"><label for="h-country" class="sr">Country</label><select id="h-country" name="country" data-country><option value="Nigeria">Nigeria</option><option value="Ghana">Ghana</option><option value="Kenya">Kenya</option><option value="South Africa">South Africa</option><option value="United Kingdom">United Kingdom</option><option value="United States">United States</option></select></div><div class="ms-f ms-s"><label for="h-where" class="sr">City</label><select id="h-where" name="city" data-city><option value="all">All cities</option></select></div>
          <div class="ms-f ms-s ms-date"><label for="h-date" class="sr">Date</label><input id="h-date" name="date" type="date" aria-label="Choose event date"></div>
          <button class="ms-go" type="submit" aria-label="Search events"><span class="sb-t">Search</span></button>
        </form>
        <div class="h-actions">
          <p class="h-org">Hosting an event? <a href="/create">Start selling tickets</a></p>
          <button class="h-motion" id="home-motion" type="button" aria-pressed="false" hidden>Pause animations</button>
        </div>
      </div>
    </div>
  </div>

  <div class="home-sections">

  <!-- Spotlight overlaps the hero -->
@endverbatim
  <div class="wrap spot-wrap"{!! $tsOff('spotlight') !!}>
@verbatim

    <div class="spot" role="group" aria-roledescription="carousel" aria-label="Featured events">
      <div class="spot-flyer" id="spot-flyer" aria-hidden="true"></div>
      <div class="spot-body">
        <div class="spot-copy"><span class="spot-kicker">Featured event</span>
        <h2 id="spot-name"></h2>
        <p id="spot-meta"></p></div><div class="spot-live"><div id="spot-cd"></div><div class="stk" id="spot-stock"></div></div>
        <div class="spot-buy">
          <b id="spot-price"></b>
          <a class="btn btn-hot" id="spot-link" href="/discover">Get tickets</a>
        </div>
      </div>
      <div class="spot-nav">
        <button type="button" id="spot-prev" aria-label="Previous featured event"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg></button>
        <span id="spot-count" aria-hidden="true"></span>
        <button type="button" id="spot-next" aria-label="Next featured event"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg></button>
      </div>
      <p class="sr" id="spot-status" role="status"></p>
    </div>
  </div>

  <!-- Browse by category -->
@endverbatim
  <div class="wrap h-sec"{!! $tsOff('categories') !!}>
@verbatim

    <h2 class="display">Browse by category</h2>
    <div class="moods" id="home-cats"></div>
  </div>

  <!-- Popular events -->
@endverbatim
  <div class="band"{!! $tsOff('popular') !!}>
@verbatim
<div class="wrap h-sec">
    <div class="head">
      <h2 class="display">Popular events</h2>
      <a class="seeall" href="/discover">Browse events</a>
    </div>
    <div class="tabs" role="group" aria-label="Filter popular events">
      <button type="button" class="tab" data-tab="all" aria-pressed="true">All</button>
      <button type="button" class="tab" data-tab="this-month" aria-pressed="false">This month</button>
      <button type="button" class="tab" data-tab="next-month" aria-pressed="false">Next month</button>
      <button type="button" class="tab" data-tab="free" aria-pressed="false">Free</button>
    </div>
    <div class="grid4" id="home-wall"></div>
    <p class="sr" id="home-wall-status" role="status"></p>
  </div></div>

  <!-- Cities -->
@endverbatim
  <div class="wrap h-sec"{!! $tsOff('cities') !!}>
@verbatim

    <div class="head city-head"><h2 class="display">Events by city</h2></div>
    <div class="cities" id="home-cities"></div>
  </div>

  <!-- Organisers -->
@endverbatim
  <div class="org"{!! $tsOff('organisers') !!}><div class="wrap org-in">
    <div class="org-copy">
      <h2 class="display{{ count($tsOrganizerTitleLines) === 2 ? ' org-title--split' : '' }}">
        @foreach ($tsOrganizerTitleLines as $tsOrganizerTitleLine)
          <span>{{ trim($tsOrganizerTitleLine) }}</span>
        @endforeach
      </h2>
      @if ($tsHome['organisers_text'])<p>{{ $tsHome['organisers_text'] }}</p>@endif
      <div class="btns"><a class="btn btn-hot" href="/create">Create an event</a><a class="btn btn-ghost" href="/pricing">See pricing</a></div>
    </div>
    @if ($tsOrganizerImages)
    <div class="org-art duo-art event-reel" aria-hidden="true">
      <div class="event-reel-stage">
        <figure class="event-reel-slide is-active">
          <div class="da-main da-concert"><img data-src="{{ $tsImg($tsOrganizerImages[0]) }}" width="1200" height="900" loading="lazy" decoding="async" alt=""></div>
          @if (isset($tsOrganizerImages[1]))
            <div class="da-sub da-crowd"><img data-src="{{ $tsImg($tsOrganizerImages[1]) }}" width="560" height="700" loading="lazy" decoding="async" alt=""></div>
          @endif
        </figure>
      </div>
    </div>
    @endif
    @if ($tsHome['organisers_points'])<ol class="org-list org-benefits">@foreach ($tsHome['organisers_points'] as $tsP)
      <li><b>{{ $tsP['title'] }}</b>@if ($tsP['text'])<span>{{ $tsP['text'] }}</span>@endif</li>@endforeach
    </ol>@endif
  </div></div>
@verbatim

  <!-- E-ticket -->
@endverbatim
  <div class="wrap h-sec tk"{!! $tsOff('eticket') !!}>
    <div class="tk-art duo-art" aria-hidden="true">
      <div class="da-main tk-entry-main"><img loading="lazy" decoding="async" src="/assets/tica-ui/img/event-phone-concert-pexels-v3.webp" width="1200" height="1800" alt=""></div>
      <div class="da-sub tk-entry-detail"><img loading="lazy" decoding="async" src="/assets/tica-ui/img/event-ticket-check-pexels-v1.webp" width="900" height="1350" alt=""></div>
      <span class="da-badge"><svg viewBox="0 0 24 24"><rect x="4" y="4" width="6" height="6"/><rect x="14" y="4" width="6" height="6"/><rect x="4" y="14" width="6" height="6"/><path d="M14 14h2v2h-2zM18 18h2v2h-2z"/></svg>E-ticket sent to your email</span>
    </div>
    <div class="tk-copy">
      <h2 class="display">@foreach ($tsEticketTitleLines as $tsEticketTitleLine)<span>{{ $tsEticketTitleLine }}</span>@endforeach</h2>
      @if ($tsHome['eticket_steps'])<ol class="tk-steps">@foreach ($tsHome['eticket_steps'] as $tsP)
        <li><b>{{ $tsP['title'] }}</b>@if ($tsP['text'])<span>{{ $tsP['text'] }}</span>@endif</li>@endforeach
      </ol>@endif
      <a class="btn btn-line" href="/how">How TICA works</a>
    </div>
  </div>

  <div class="partners"{!! $tsOff('partners') !!}><div class="wrap"><p>Our partners</p>@include('tica-ui.components.partner-list', ['partners' => $tsHome['partners']])</div></div>
  </div>
</section>
@verbatim

<!-- DISCOVER -->
@endverbatim
