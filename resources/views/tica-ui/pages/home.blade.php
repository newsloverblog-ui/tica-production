@verbatim
@endverbatim
@php
  $tsHome = \App\Support\TicaSite::get('home');
  $tsOn = fn ($key) => $tsHome['sections'][$key] ?? true;
  $tsOff = fn ($key) => ($tsHome['sections'][$key] ?? true) ? '' : ' style="display:none" data-off';
  $tsImg = fn ($path) => \App\Support\TicaSite::imageUrl($path);
@endphp
@verbatim
<section class="page" data-page="home">
  <div class="h-hero">
    <div class="h-frame">
      @endverbatim
      <div class="h-slides" aria-hidden="true">@foreach ($tsHome['hero_images'] as $tsI => $tsPath)<div class="h-slide {{ $tsI % 2 ? 'm-blue' : 'm-ember' }}{{ $tsI === 0 ? ' on' : '' }}"><img src="{{ $tsImg($tsPath) }}" alt="" decoding="async" @if ($tsI) loading="lazy" @endif></div>@endforeach</div>
@verbatim

      <div class="h-shade"></div><div class="h-light" aria-hidden="true"><i></i><i></i><i></i></div>
      <div class="h-in">
@endverbatim
        <h1>{!! nl2br(e($tsHome['hero_title']), false) !!}</h1>
        @if ($tsHome['hero_text'])<p>{{ $tsHome['hero_text'] }}</p>@endif
@verbatim

        <form class="search ms" id="home-search" role="search" aria-label="Find events">
          <div class="ms-f ms-q"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6.500"/><path d="M16 16l4 4"/></svg><label for="h-q" class="sr">Search events</label><input id="h-q" type="search" placeholder="Search events or artists" autocomplete="off"></div>
          <div class="ms-f ms-s"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/><circle cx="12" cy="12" r="9"/></svg><label for="h-country" class="sr">Country</label><select id="h-country" data-country><option value="Nigeria">Nigeria</option><option value="Ghana">Ghana</option><option value="Kenya">Kenya</option><option value="South Africa">South Africa</option><option value="United Kingdom">United Kingdom</option><option value="United States">United States</option><option value="Online">Online</option></select></div><div class="ms-f ms-s"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s7-6.5 7-11.5a7 7 0 0 0-14 0C5 14.5 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/></svg><label for="h-where" class="sr">City</label><select id="h-where" data-city><option value="all">All cities</option></select></div>
          <div class="ms-f ms-s ms-date"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3v3M17 3v3M4 9h16"/><rect x="4" y="5" width="16" height="16" rx="2.5"/></svg><label for="h-date" class="sr">Date</label><input id="h-date" type="date" aria-label="Choose event date"><input id="h-month" type="hidden" value="all"><input id="h-day" type="hidden" value="all"></div>
          <select id="h-cat" class="catselect" hidden aria-hidden="true" tabindex="-1"></select>
          <button class="ms-go" type="submit" aria-label="Find events"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6.500"/><path d="M16 16l4 4"/></svg><span class="sb-t">Search</span></button>
        </form>
        <div class="h-chips" role="group" aria-label="Popular searches"><button type="button" data-hk="h-month" data-hv="nov">This month</button><button type="button" data-hk="h-where" data-hv="Owerri">In Owerri</button><button type="button" data-hk="h-cat" data-hv="shows">Concerts</button><button type="button" data-hk="h-cat" data-hv="party">Nightlife</button><button type="button" data-hk="h-cat" data-hv="campus">Campus</button></div>
        <p class="h-org">Hosting an event? <a href="/create">Start selling tickets</a></p>
      </div>
    </div>
  </div>

  <!-- Spotlight overlaps the hero -->
@endverbatim
  <div class="wrap spot-wrap"{!! $tsOff('spotlight') !!}>
@verbatim

    <div class="spot" aria-roledescription="carousel" aria-label="Featured events">
      <div class="spot-flyer" id="spot-flyer" aria-hidden="true"></div>
      <div class="spot-body" aria-live="polite">
        <span class="spot-kicker">Featured event</span>
        <h2 id="spot-name"></h2>
        <p id="spot-meta"></p><div class="spot-live"><div id="spot-cd"></div><div class="stk" id="spot-stock"></div></div>
        <div class="spot-buy">
          <b id="spot-price"></b>
          <a class="btn btn-hot" id="spot-link" href="/discover">Get tickets</a>
        </div>
      </div>
      <div class="spot-nav">
        <button type="button" id="spot-prev" aria-label="Previous featured event"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg></button>
        <span id="spot-count"></span>
        <button type="button" id="spot-next" aria-label="Next featured event"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg></button>
      </div>
    </div>
  </div>

  <!-- Browse by category -->
@endverbatim
  <div class="wrap h-sec"{!! $tsOff('categories') !!}>
@verbatim

    <h2 class="display">What are you in the mood for?</h2>
    <div class="moods" id="home-cats"></div>
  </div>

  <!-- Popular events -->
@endverbatim
  <div class="band"{!! $tsOff('popular') !!}>
@verbatim
<div class="wrap h-sec">
    <div class="head">
      <h2 class="display">Popular events</h2>
      <a class="seeall" href="/discover">See all events</a>
    </div>
    <div class="tabs" role="group" aria-label="Filter popular events">
      <button type="button" class="tab" data-tab="all" aria-pressed="true">All</button>
      <button type="button" class="tab" data-tab="nov" aria-pressed="false">November</button>
      <button type="button" class="tab" data-tab="dec" aria-pressed="false">December</button>
      <button type="button" class="tab" data-tab="free" aria-pressed="false">Free</button>
    </div>
    <div class="grid4" id="home-wall"></div>
    <a class="btn btn-line wall-more" href="/discover">See all events</a>
  </div></div>

  <!-- Cities -->
@endverbatim
  <div class="wrap h-sec"{!! $tsOff('cities') !!}>
@verbatim

    <div class="head city-head"><h2 class="display">Going out near you</h2></div>
    <div class="cities" id="home-cities"></div>
  </div>

  <!-- Organisers -->
@endverbatim
  <div class="org"{!! $tsOff('organisers') !!}><div class="wrap org-in">
    <div class="org-art duo-art" aria-hidden="true">
      <div class="da-main"><img loading="lazy" decoding="async" src="{{ $tsImg($tsHome['organisers_images'][0] ?? '') }}" alt=""></div>
      <div class="da-sub"><img loading="lazy" decoding="async" src="{{ $tsImg($tsHome['organisers_images'][1] ?? '') }}" alt=""></div>
      <span class="da-badge"><svg viewBox="0 0 24 24"><path d="M4 8h16v3a2 2 0 0 0 0 3v3H4v-3a2 2 0 0 0 0-3zM14 8v2M14 13v1M14 16v1"/></svg>Your event, live in minutes</span>
    </div>
    <div>
      <h2 class="display">{{ $tsHome['organisers_title'] }}</h2>
      @if ($tsHome['organisers_text'])<p>{{ $tsHome['organisers_text'] }}</p>@endif
      @if ($tsHome['organisers_points'])<ul class="org-list">@foreach ($tsHome['organisers_points'] as $tsP)
        <li><b>{{ $tsP['title'] }}</b>@if ($tsP['text'])<span>{{ $tsP['text'] }}</span>@endif</li>@endforeach
      </ul>@endif
      <div class="btns"><a class="btn btn-hot" href="/create">Create an event</a><a class="btn btn-ghost" href="/pricing">See pricing</a></div>
    </div>
  </div></div>
@verbatim

  <!-- E-ticket -->
@endverbatim
  <div class="wrap h-sec tk"{!! $tsOff('eticket') !!}>
    <div>
      <h2 class="display">{{ $tsHome['eticket_title'] }}</h2>
      @if ($tsHome['eticket_steps'])<ol class="tk-steps">@foreach ($tsHome['eticket_steps'] as $tsP)
        <li><b>{{ $tsP['title'] }}</b>@if ($tsP['text'])<span>{{ $tsP['text'] }}</span>@endif</li>@endforeach
      </ol>@endif
      <a class="btn btn-line" href="/how">How TICA works</a>
    </div>
    <div class="tk-art duo-art" aria-hidden="true">
      <div class="da-main"><img loading="lazy" decoding="async" src="{{ $tsImg($tsHome['eticket_images'][0] ?? '') }}" alt=""></div>
      <div class="da-sub"><img loading="lazy" decoding="async" src="{{ $tsImg($tsHome['eticket_images'][1] ?? '') }}" alt=""></div>
      <span class="da-badge"><svg viewBox="0 0 24 24"><rect x="4" y="4" width="6" height="6"/><rect x="14" y="4" width="6" height="6"/><rect x="4" y="14" width="6" height="6"/><path d="M14 14h2v2h-2zM18 18h2v2h-2z"/></svg>E-ticket sent to your email</span>
    </div>
  </div>

  <div class="partners"{!! $tsOff('partners') !!}><div class="wrap"><p>Our partners</p><div class="partner-marks" aria-label="TICA partners">@foreach ($tsHome['partners'] as $tsI => $tsPartner)@if (!empty($tsPartner['logo']))<span class="partner-logo has-img" aria-label="{{ trim(($tsPartner['short'] ?? '') . ' ' . str_replace("\n", ' ', $tsPartner['name'] ?? '')) }}"><img src="{{ $tsImg($tsPartner['logo']) }}" alt="" loading="lazy"></span>@else<span class="partner-logo {{ ['coh', 'ndfa'][$tsI % 2] }}" aria-label="{{ trim(($tsPartner['short'] ?? '') . ' ' . str_replace("\n", ' ', $tsPartner['name'] ?? '')) }}"><b>{{ $tsPartner['short'] }}</b><small>{!! nl2br(e($tsPartner['name']), false) !!}</small></span>@endif @endforeach</div></div></div>
</section>
@verbatim

<!-- DISCOVER -->
@endverbatim
