@verbatim
<dialog class="sh" id="share" aria-labelledby="sh-title">
  <div class="sh-head"><div><h2 id="sh-title">Share event</h2><p id="sh-sub"></p></div><button class="sh-x" type="button" id="sh-x" aria-label="Close"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button></div>
  <div class="sh-tabs" role="tablist"><button type="button" role="tab" data-t="links" aria-selected="true">Share link</button><button type="button" role="tab" data-t="qr" aria-selected="false">QR code</button></div>
  <div id="sh-p-links" role="tabpanel">
    <div class="sh-nets"><a data-net="wa" target="_blank" rel="noopener"><i style="background:#25D366"><svg viewBox="0 0 24 24" aria-hidden="true"><path fill="#fff" d="M12 3.5a8.4 8.4 0 0 0-7.2 12.700L3.600 20.500l4.400-1.150A8.400 8.400 0 1 0 12 3.500zm4.700 11.800c-.2.600-1.200 1.100-1.700 1.150-.450.050-1 .1-3.200-.8-2.700-1.100-4.400-3.900-4.550-4.100-.15-.2-1.100-1.450-1.100-2.800s.7-2 .95-2.250a1 1 0 0 1 .7-.35h.5c.15 0 .4-.05.6.450l.85 2.050c.07.15.1.35 0 .55-.4.800-.85.750-.6 1.200.9 1.550 1.800 2.100 3.200 2.800.25.100.4.100.55-.05l.8-.950c.2-.25.4-.2.65-.1l1.950.900c.25.150.45.200.5.300.1.150.1.750-.1 1.350z"/></svg></i><span>WhatsApp</span></a><a data-net="x" target="_blank" rel="noopener"><i style="background:#000"><svg viewBox="0 0 24 24" aria-hidden="true"><path fill="#fff" d="M17.700 4h2.900l-6.300 7.200L21.700 20h-5.800l-4.500-5.900L6.200 20H3.300l6.700-7.700L3 4h5.900l4.100 5.400zm-1 14.300h1.600L7.400 5.600H5.700z"/></svg></i><span>X</span></a><a data-net="fb" target="_blank" rel="noopener"><i style="background:#1877F2"><svg viewBox="0 0 24 24" aria-hidden="true"><path fill="#fff" d="M13.500 21v-7.500h2.500l.4-3h-2.900V8.700c0-.850.250-1.450 1.500-1.450h1.500V4.600c-.3-.05-1.150-.1-2.150-.1-2.150 0-3.600 1.300-3.600 3.700v2.300H8.300v3h2.450V21z"/></svg></i><span>Facebook</span></a><a data-net="tg" target="_blank" rel="noopener"><i style="background:#26A5E4"><svg viewBox="0 0 24 24" aria-hidden="true"><path fill="#fff" d="M20.500 4.400 3.600 10.900c-1.150.45-1.150 1.100-.2 1.400l4.300 1.350 1.650 5.100c.2.550.1.750.7.750.45 0 .65-.2.900-.45l2.100-2.050 4.400 3.250c.8.450 1.400.2 1.600-.750L21.900 5.700c.3-1.250-.5-1.800-1.400-1.300zM9.300 14.400l8.300-5.250c.4-.25.750-.1.450.15l-7.100 6.400-.3 3.050z"/></svg></i><span>Telegram</span></a><a data-net="li" target="_blank" rel="noopener"><i style="background:#0A66C2"><svg viewBox="0 0 24 24" aria-hidden="true"><path fill="#fff" d="M6.900 8.900H4.200V20h2.700zM5.550 4a1.600 1.600 0 1 0 0 3.200 1.600 1.600 0 0 0 0-3.200zM20 13.600c0-3-1.600-4.900-4.200-4.900-1.400 0-2.400.8-2.800 1.500V8.900h-2.600V20H13v-5.900c0-1.500.6-2.600 2-2.600s1.900 1.100 1.900 2.700V20H20z"/></svg></i><span>LinkedIn</span></a><a data-net="em" target="_blank" rel="noopener"><i style="background:#526079"><svg viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="#fff" stroke-width="1.800" stroke-linecap="round" stroke-linejoin="round" d="M4 6h16v12H4zM4 7l8 6 8-6"/></svg></i><span>Email</span></a><button type="button" id="sh-more"><i style="background:var(--tint)"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="6" cy="12" r="1.600" fill="#0B2447"/><circle cx="12" cy="12" r="1.600" fill="#0B2447"/><circle cx="18" cy="12" r="1.600" fill="#0B2447"/></svg></i><span>More</span></button></div>
    <label class="sh-l" for="sh-url">Event link</label>
    <div class="sh-copy"><input id="sh-url" type="text" readonly><button class="btn btn-hot" type="button" id="sh-cp">Copy</button></div>
  </div>
  <div id="sh-p-qr" role="tabpanel" hidden>
    <div class="sh-qrbox"><canvas id="sh-qr" aria-label="QR code that opens this event page" role="img"></canvas></div>
    <p class="sh-qrt">Scan with a phone camera to open the event and buy tickets. Print it on flyers, posters and banners.</p>
    <button class="btn btn-hot sh-dlb" type="button" id="sh-dl"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4v11M8 11l4 4 4-4M5 19h14"/></svg>Download QR code</button>
  </div>
  <p class="sh-note" id="sh-filenote" hidden>You opened this design from a file, so the link and QR code point to this device. On the live site they will open the event's web address.</p>
</dialog>
<section class="ck-banner" id="ck-banner" role="region" aria-label="Cookie choices" hidden>
  <span class="ckb-ic" aria-hidden="true"><svg viewBox="0 0 32 32"><path fill="currentColor" d="M16 3a13 13 0 1 0 13 13 4 4 0 0 1-4.6-4.3A4.2 4.2 0 0 1 20 7.2 4.3 4.3 0 0 1 16 3z"/><g fill="#fff"><circle cx="11" cy="12" r="2"/><circle cx="18.5" cy="19" r="2.2"/><circle cx="10.5" cy="20.5" r="1.5"/><circle cx="16" cy="12.5" r="1.1"/><circle cx="23" cy="22" r="1.2"/></g></svg></span>
  <div class="ckb-main">
    <h2>Cookies on TICA</h2>
    <p>We use essential cookies to run the site and keep payments safe. With your permission, we'd also use your approximate city to show events near you, and analytics to improve TICA. Read our <a href="/policies/cookies" data-ck-pol>cookie policy</a> or <button class="ckb-link" type="button" data-ck="manage">manage your choices</button>.</p>
  </div>
  <div class="ckb-acts"><button class="btn btn-line" type="button" data-ck="reject">Reject non-essential</button><button class="btn btn-hot" type="button" data-ck="accept">Accept all</button></div>
  <button class="ckb-x" type="button" data-ck="reject" aria-label="Close and use essential cookies only"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button>
</section>
<dialog class="ck-dlg" id="ck-dlg" aria-labelledby="ckd-h">
  <div class="sh-head"><div><h2 id="ckd-h">Cookie settings</h2><p>Choose what TICA can use. You can change this at any time.</p></div><button class="sh-x" type="button" data-ck="close" aria-label="Close"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button></div>
  <ul class="ck-list">
    <li><div><b>Essential</b><span>Log in, checkout, ticket holds and security. Always on.</span></div><span class="ck-on">Always on</span></li>
    <li><label for="ck-p"><b>Personalisation</b><span>Use your approximate city to suggest events near you.</span></label><input class="ck-sw" type="checkbox" id="ck-p"></li>
    <li><label for="ck-a"><b>Analytics</b><span>Understand how the site is used so we can improve it.</span></label><input class="ck-sw" type="checkbox" id="ck-a"></li>
    <li><label for="ck-m"><b>Marketing</b><span>Show TICA events in ads on other sites.</span></label><input class="ck-sw" type="checkbox" id="ck-m"></li>
  </ul>
  <div class="ck-dacts"><button class="btn btn-line" type="button" data-ck="reject">Reject non-essential</button><button class="btn btn-hot" type="button" data-ck="save">Save choices</button></div>
</dialog>
<aside class="nb" id="nb" aria-live="polite" aria-label="Events near you" hidden>
  <a class="nb-link" id="nb-link" href="/discover"><span class="nb-th flyer" id="nb-th"></span><span class="nb-tx"><em id="nb-k"></em><b id="nb-t"></b><small id="nb-s"></small></span></a>
  <button class="nb-x" type="button" id="nb-x" aria-label="Stop event suggestions"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button>
</aside>
<div class="toast" id="live-msg" role="status"></div>

<footer id="footer" class="ft">
  <div class="wrap">
    <div class="ft-top">
@endverbatim
@php
  $tsFooter = \App\Support\TicaSite::get('footer');
  $tsContact = \App\Support\TicaSite::get('contact');
@endphp
      <div class="ft-lead">
        <p class="ft-line">{!! nl2br(e($tsFooter['tagline'])) !!}</p>
        <div class="ft-cta"><a class="ft-go" href="/discover">Discover events<i aria-hidden="true"></i></a><a class="ft-go alt" href="/create">Create an event<i aria-hidden="true"></i></a></div>
      </div>
      <nav class="ft-nav" aria-label="Footer">
@foreach ($tsFooter['columns'] as $tsColumn)
        <div><h2>{{ $tsColumn['title'] }}</h2>@foreach ($tsColumn['links'] as $tsLink)<a href="{{ $tsLink['url'] }}" @unless (str_starts_with($tsLink['url'], '/')) target="_blank" rel="noopener" @endunless>{{ $tsLink['label'] }}</a>@endforeach</div>
@endforeach
        <div><h2>Talk to us</h2>@if ($tsContact['phone'])<a href="tel:{{ \App\Support\TicaSite::tel($tsContact['phone']) }}">{{ $tsContact['phone'] }}</a>@endif @if (!empty($tsContact['emails'][0]['address']))<a href="mailto:{{ $tsContact['emails'][0]['address'] }}">{{ $tsContact['emails'][0]['address'] }}</a>@endif<a href="/contact">Contact us</a>@if ($tsContact['city'])<span>{{ $tsContact['city'] }}</span>@endif</div>
      </nav>
    </div>
    <div class="ft-base">
      <span>{{ str_replace('{year}', date('Y'), $tsFooter['copyright']) }}</span>
@verbatim
      <span class="ft-legal"><a href="/policies/refunds">Refunds</a><a href="/policies/terms">Terms</a><a href="/policies/privacy">Privacy</a><a href="/policies/cookies" data-cookie-open>Cookie settings</a></span>
    </div>
  </div>
  <div class="wrap ft-mark" aria-hidden="true">
    <svg viewBox="0 52 1000 222" preserveAspectRatio="xMidYMin meet"><defs><linearGradient id="ftg" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fff" stop-opacity=".17"/><stop offset="1" stop-color="#fff" stop-opacity=".015"/></linearGradient></defs><text x="0" y="300" textLength="1000" lengthAdjust="spacingAndGlyphs" fill="url(#ftg)" font-family="Montserrat,sans-serif" font-weight="800" font-size="340">TICA</text></svg>
  </div>
</footer>







<div class="fab" id="fab" aria-hidden="true"><div class="fab-l"><span id="fab-k"></span><b id="fab-v"></b></div><button class="btn btn-hot" type="button" id="fab-b" tabindex="-1"></button></div>
@endverbatim
