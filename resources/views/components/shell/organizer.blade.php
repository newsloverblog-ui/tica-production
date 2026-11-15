@props(['active' => null, 'title' => null, 'spa' => false])
{{--
  The one organiser shell: sidebar + top bar. Used by the TICA app pages
  (Overview, Events, Create/Edit, Orders, Check-in, Promote, Payouts, Team,
  Settings) and by every legacy "More tools" page. Items the signed-in
  team role can't open are not rendered (the server also refuses them).
--}}
@php
  use App\Support\TeamPermissions;
  $organizer = Auth::guard('organizer')->user();
  $can = fn ($permission) => TeamPermissions::can($permission);
  $name = $organizer ? (optional($organizer->organizer_info)->name ?: ($organizer->verified_legal_name ?: $organizer->username)) : 'Organiser';
  $verified = $organizer && $organizer->kyc_status === 'verified';
  // The bell is the account owner's: team members never see the owner's payout or identity news.
  $isOwner = $organizer && !session()->has('tica_team_member');
  $inbox = $isOwner ? \App\Support\OrganizerInbox::latest((int) $organizer->id, 8) : null;
  $icons = [
    'overview' => '<path d="M4 13h6V4H4zM14 20h6v-9h-6zM4 20h6v-4H4zM14 4v4h6V4z"/>',
    'events' => '<rect x="4" y="5" width="16" height="15" rx="2"/><path d="M4 10h16M9 3v4M15 3v4"/>',
    'tickets' => '<path d="M4 8h16v3a2 2 0 0 0 0 3v3H4v-3a2 2 0 0 0 0-3z"/><path d="M9 8v9M15 8v9"/>',
    'orders' => '<circle cx="9" cy="8" r="3.5"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6M16 4.5a3.5 3.5 0 0 1 0 7M18 14c2 .7 3 2.8 3 6"/>',
    'checkin' => '<path d="M4 8V5a1 1 0 0 1 1-1h3M16 4h3a1 1 0 0 1 1 1v3M20 16v3a1 1 0 0 1-1 1h-3M8 20H5a1 1 0 0 1-1-1v-3M8 12h8"/>',
    'promote' => '<path d="M4 10v4a1 1 0 0 0 1 1h3l7 4V5L8 9H5a1 1 0 0 0-1 1zM18 9a4 4 0 0 1 0 6"/>',
    'payouts' => '<path d="M3 10l9-6 9 6M5 10v8M9.5 10v8M14.5 10v8M19 10v8M3 20h18"/>',
    'team' => '<circle cx="12" cy="8" r="3.5"/><path d="M5 20c0-3.9 3.1-7 7-7s7 3.1 7 7"/>',
    'forms' => '<rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 8h6M9 12h6M9 16h3"/>',
    'settings' => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2 12h3M19 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"/>',
    'tools' => '<rect x="4" y="4" width="6" height="6" rx="1.5"/><rect x="14" y="4" width="6" height="6" rx="1.5"/><rect x="4" y="14" width="6" height="6" rx="1.5"/><rect x="14" y="14" width="6" height="6" rx="1.5"/>',
    'logout' => '<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 17l5-5-5-5M15 12H4"/>',
  ];
  $groups = [
    'Main' => [
      ['overview', 'Overview', '/dashboard', 'overview.view'],
      ['events', 'Events', '/dashboard/events', 'events.view'],
      ['tickets', 'Ticket management', '/dashboard/tickets', 'orders.view'],
      ['orders', 'Orders and attendees', '/dashboard/orders', 'orders.view'],
      ['checkin', 'Gate check-in', '/checkin', 'checkin.run'],
    ],
    'Grow' => [['promote', 'Promote', '/dashboard/promote', 'promote.manage']],
    'Money' => [['payouts', 'Payouts', '/dashboard/payouts', 'payouts.view']],
    'Account' => [
      ['team', 'Team', '/dashboard/team', 'team.manage'],
      ['settings', 'Settings', '/dashboard/settings', 'settings.manage'],
    ],
  ];
  $tools = array_values(array_filter([
    ['Registration forms', '/organizer/forms', 'events.manage'],
    ['Booking report', '/organizer/event-booking/report', 'reports.view'],
    ['Guest lists and RSVPs', '/organizer/event/guest-list', 'events.manage'],
    ['Transactions', '/organizer/transaction', 'reports.view'],
    ['Finance statement', '/organizer/finance', 'reports.view'],
    ['Payout exports', '/organizer/withdraw/exports', 'orders.export'],
    ['Data exports', '/organizer/event-booking/exports', 'orders.export'],
    ['Check-in ledger', '/organizer/check-in-ledger/exports', 'orders.export'],
    ['Gate staff and devices', '/organizer/pwa', 'team.manage'],
    ['Identity and bank', '/organizer/kyc', 'bank.manage'],
    ['Support', '/organizer/support-tikcet/tickets', 'support.use'],
  ], fn ($tool) => $can($tool[2])));
  // Legacy pages don't say which item they belong to: work it out from the address, most specific first,
  // so a page under More tools never lights up "Events" (and vice versa).
  if ($active === null) {
    $path = '/' . ltrim(request()->path(), '/');
    $toolHit = collect($tools)->first(fn ($tool) => str_starts_with($path, $tool[1]));
    $active = match (true) {
      str_starts_with($path, '/organizer/forms'), str_starts_with($path, '/organizer/event/registrations') => 'forms',
      $toolHit !== null => 'tools',
      request()->routeIs('organizer.withdraw*', 'organizer.payout*') => 'payouts',
      request()->routeIs('organizer.event_booking.index', 'organizer.event_booking.details') => 'orders',
      request()->routeIs('organizer.event_management.*', 'organizer.event.*', 'organizer.add.event.*', 'choose-event-type', 'organizer.ticket_management.*') => 'events',
      default => 'overview',
    };
  }
  $toolsActive = in_array($active, ['tools', 'forms'], true);
  $payoutAction = $organizer && $can('payouts.view') && !\App\Models\OrganizerPayoutAccount::where('organizer_id', $organizer->id)->where('is_default', true)->where('status', 'verified')->exists();
  $titles = ['overview' => 'Overview', 'events' => 'Events', 'tickets' => 'Ticket management', 'forms' => 'Registration forms', 'orders' => 'Orders and attendees', 'checkin' => 'Gate check-in', 'promote' => 'Promote', 'payouts' => 'Payouts', 'team' => 'Team', 'settings' => 'Settings', 'tools' => 'More tools'];
@endphp

<div id="tica-shell" class="tsh">
<aside class="tsh-side" id="os-side" aria-label="Organiser menu">
  <a class="tsh-logo" href="/" aria-label="TICA home"><img src="{{ asset('assets/tica-ui/img/tica-logo.png') }}" alt="TICA" width="84" height="28"></a>

  <a class="tsh-org" href="{{ $can('settings.manage') ? '/dashboard/settings' : '/dashboard' }}" aria-label="Profile and settings for {{ $name }}">
    <x-organizer-avatar :organizer="$organizer" size="40" class="tsh-avatar" />
    <span class="tsh-org__text">
      <span class="tsh-org__name"><span class="tsh-org__nm">{{ $name }}</span>@if ($verified)<svg class="tsh-verified" viewBox="0 0 24 24" role="img" aria-label="Verified organiser"><title>Verified organiser</title><path class="tsh-verified__seal" d="M12 1.8l2.4 1.8 3-.2.9 2.9 2.5 1.7-.9 2.9.9 2.9-2.5 1.7-.9 2.9-3-.2L12 22.2l-2.4-1.8-3 .2-.9-2.9-2.5-1.7.9-2.9-.9-2.9 2.5-1.7.9-2.9 3 .2z"/><path class="tsh-verified__tick" d="M8.2 12.3l2.5 2.5 5.1-5.3"/></svg>@endif</span>
      @unless ($verified)<span class="tsh-org__status is-warn">Not verified yet</span>@endunless
    </span>
  </a>

  @if ($can('events.manage'))
    <a class="tsh-create" href="/create"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>Create an event</a>
  @endif

  <nav class="tsh-nav" aria-label="Organiser">
    @foreach ($groups as $group => $items)
      @php($visible = array_filter($items, fn ($item) => $can($item[3])))
      @if ($visible)
        <p class="tsh-group">{{ $group }}</p>
        @foreach ($visible as [$key, $label, $href, $permission])
          <a class="tsh-link os-link {{ $active === $key ? 'is-active' : '' }}" href="{{ $href }}" @if ($spa && !in_array($key, ['checkin', 'forms'], true)) data-panel="{{ $key }}" @endif @if ($active === $key) aria-current="page" @endif>
            <svg viewBox="0 0 24 24" aria-hidden="true">{!! $icons[$key] !!}</svg><span class="tsh-link__label">{{ $label }}</span>
            @if ($key === 'payouts' && $payoutAction)<span class="tsh-dot" id="od-paydot" role="img" aria-label="Action needed"></span>@endif
          </a>
        @endforeach
      @endif
    @endforeach

    @if ($tools)
      <details class="tsh-more" id="tsh-more" @if ($toolsActive) open @endif>
        <summary class="tsh-link {{ $toolsActive ? 'is-active' : '' }}"><svg viewBox="0 0 24 24" aria-hidden="true">{!! $icons['tools'] !!}</svg><span class="tsh-link__label">More tools</span><svg class="tsh-chev" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg></summary>
        <div class="tsh-sub">
          @foreach ($tools as [$label, $href])
            @php($on = $toolsActive && ($label === 'Registration forms' ? $active === 'forms' : request()->is(ltrim($href, '/') . '*')))
            <a class="tsh-sublink os-link {{ $on ? 'is-active' : '' }}" href="{{ $href }}" @if ($on) aria-current="page" @endif>{{ $label }}</a>
          @endforeach
        </div>
      </details>
      <script>
        // Keep "More tools" the way the organiser left it, before the page paints.
        (function () { try { var d = document.getElementById('tsh-more'); if (!d.open && localStorage.getItem('tsh-more') === '1') d.open = true;
          d.addEventListener('toggle', function () { localStorage.setItem('tsh-more', d.open ? '1' : '0'); }); } catch (e) {} })();
      </script>
    @endif
  </nav>

  <form class="tsh-foot" method="POST" action="{{ route('organizer.logout') }}">
    @csrf
    <button class="tsh-link" type="submit"><svg viewBox="0 0 24 24" aria-hidden="true">{!! $icons['logout'] !!}</svg><span class="tsh-link__label">Log out</span></button>
  </form>
</aside>
<div class="tsh-scrim" id="os-scrim" aria-hidden="true"></div>

<header class="tsh-top" aria-label="Page">
  <button class="tsh-iconbtn tsh-burger" id="os-burger" type="button" aria-label="Open menu" aria-controls="os-side" aria-expanded="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button>
  <h1 class="tsh-title" id="os-title" @if ($title) data-fixed="1" @endif>{{ $title ?? ($titles[$active] ?? 'Dashboard') }}</h1>
  @if ($inbox)
    <details class="tsh-pop tsh-bell os-bell" id="os-bell" data-unread="{{ $inbox['unread'] }}">
      <summary class="tsh-iconbtn" aria-label="{{ $inbox['unread'] ? trans_choice(':count new notification|:count new notifications', $inbox['unread']) : __('Notifications') }}">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 16V11a6 6 0 1 1 12 0v5l1.5 2h-15z"/><path d="M10 20a2 2 0 0 0 4 0"/></svg>
        @if ($inbox['unread'])<span class="tsh-bell__n os-bell__n" aria-hidden="true">{{ $inbox['unread'] > 9 ? '9+' : $inbox['unread'] }}</span>@endif
      </summary>
      <div class="tsh-pop__list tsh-bell__list">
        <p class="tsh-pop__head">{{ __('Notifications') }}</p>
        @forelse ($inbox['items'] as $item)
          <a class="tsh-bell__item{{ $item['read'] ? '' : ' is-new' }}" href="{{ $item['url'] ?: '#' }}">
            <b>{{ $item['title'] }}</b>
            @if ($item['body'])<span>{{ $item['body'] }}</span>@endif
            <small>{{ $item['when'] }}</small>
          </a>
        @empty
          <p class="tsh-pop__empty">{{ __('Nothing new. We\'ll tell you here when TICA reviews your account, payouts or promotions.') }}</p>
        @endforelse
      </div>
    </details>
  @endif
  <details class="tsh-pop tsh-me os-menu">
    <summary class="tsh-me__btn" aria-label="Account menu"><x-organizer-avatar :organizer="$organizer" size="36" class="tsh-avatar" /></summary>
    <div class="tsh-pop__list">
      <div class="tsh-me__who"><b>{{ $name }}</b><small>{{ $organizer?->email }}</small></div>
      @if ($can('settings.manage'))
        <a href="/dashboard/settings">Profile</a>
        <a href="/dashboard/settings#security">Settings</a>
      @endif
      @if ($organizer)
        <a href="{{ route('frontend.organizer.details', [$organizer->id, \Illuminate\Support\Str::slug((string) $organizer->username)]) }}" target="_blank" rel="noopener">View public page</a>
      @endif
      <form method="POST" action="{{ route('organizer.logout') }}">@csrf<button type="submit">Log out</button></form>
    </div>
  </details>
</header>
</div>

<script>
(function () {
  var body = document.body, burger = document.getElementById('os-burger'), side = document.getElementById('os-side'), scrim = document.getElementById('os-scrim');
  if (!burger || burger.dataset.ready) return; burger.dataset.ready = '1';
  function set(open) { body.classList.toggle('os-drawer-open', open); burger.setAttribute('aria-expanded', open ? 'true' : 'false'); if (open) { var f = side.querySelector('a,button'); f && f.focus(); } }
  burger.addEventListener('click', function () { set(!body.classList.contains('os-drawer-open')); });
  scrim.addEventListener('click', function () { set(false); });
  side.addEventListener('click', function (e) { if (e.target.closest('a')) set(false); });
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    if (body.classList.contains('os-drawer-open')) { set(false); burger.focus(); }
    var m = document.querySelector('.os-menu[open]'); if (m) { m.removeAttribute('open'); m.querySelector('summary').focus(); }
  });
  document.addEventListener('click', function (e) { var m = document.querySelector('.os-menu[open]'); if (m && !m.contains(e.target)) m.removeAttribute('open'); var b = document.querySelector('.os-bell[open]'); if (b && !b.contains(e.target)) b.removeAttribute('open'); });
  // Opening the bell marks everything read.
  var bell = document.getElementById('os-bell');
  if (bell) bell.addEventListener('toggle', function () {
    if (!bell.open || bell.dataset.unread === '0') return;
    var token = document.querySelector('meta[name=csrf-token]'); token = token ? token.content : (window.TICA_DATA && TICA_DATA.csrf) || '';
    fetch('/tica-api/organizer/notifications/read', { method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { if (!r.ok) return; bell.dataset.unread = '0'; var n = bell.querySelector('.os-bell__n'); if (n) n.remove(); bell.querySelector('summary').setAttribute('aria-label', 'Notifications'); });
  });
  // Legacy pages already show a title in their page header; reuse it in the top bar.
  document.addEventListener('DOMContentLoaded', function () {
    var t = document.getElementById('os-title'), pt = document.querySelector('.orgshell-legacy .page-header .page-title, .orgshell-legacy .page-inner h1:not(#os-title)');
    if (t && pt && t.dataset.fixed !== '1') t.textContent = pt.textContent.trim();
  });
})();
</script>
