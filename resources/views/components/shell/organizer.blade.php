@props(['active' => 'overview', 'title' => null, 'spa' => false])
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
  $icons = [
    'overview' => '<path d="M4 13h6V4H4zM14 20h6v-9h-6zM4 20h6v-4H4zM14 4v4h6V4z"/>',
    'events' => '<rect x="4" y="5" width="16" height="15" rx="2"/><path d="M4 10h16M9 3v4M15 3v4"/>',
    'orders' => '<circle cx="9" cy="8" r="3.5"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6M16 4.5a3.5 3.5 0 0 1 0 7M18 14c2 .7 3 2.8 3 6"/>',
    'checkin' => '<path d="M4 8V5a1 1 0 0 1 1-1h3M16 4h3a1 1 0 0 1 1 1v3M20 16v3a1 1 0 0 1-1 1h-3M8 20H5a1 1 0 0 1-1-1v-3M8 12h8"/>',
    'promote' => '<path d="M4 10v4a1 1 0 0 0 1 1h3l7 4V5L8 9H5a1 1 0 0 0-1 1zM18 9a4 4 0 0 1 0 6"/>',
    'payouts' => '<path d="M3 10l9-6 9 6M5 10v8M9.5 10v8M14.5 10v8M19 10v8M3 20h18"/>',
    'team' => '<circle cx="12" cy="8" r="3.5"/><path d="M5 20c0-3.9 3.1-7 7-7s7 3.1 7 7"/>',
    'settings' => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2 12h3M19 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"/>',
    'tools' => '<rect x="4" y="4" width="6" height="6" rx="1.5"/><rect x="14" y="4" width="6" height="6" rx="1.5"/><rect x="4" y="14" width="6" height="6" rx="1.5"/><rect x="14" y="14" width="6" height="6" rx="1.5"/>',
    'logout' => '<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 17l5-5-5-5M15 12H4"/>',
  ];
  $groups = [
    'Main' => [
      ['overview', 'Overview', '/dashboard', 'overview.view'],
      ['events', 'Events', '/dashboard/events', 'events.view'],
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
    ['Booking report', '/organizer/event-booking/report', 'reports.view'],
    ['Guest lists and RSVPs', '/organizer/event/guest-list', 'events.manage'],
    ['Registration forms', '/organizer/event/registrations', 'events.manage'],
    ['Transactions', '/organizer/transaction', 'reports.view'],
    ['Finance statement', '/organizer/finance', 'reports.view'],
    ['Payout exports', '/organizer/withdraw/exports', 'orders.export'],
    ['Data exports', '/organizer/event-booking/exports', 'orders.export'],
    ['Check-in ledger', '/organizer/check-in-ledger/exports', 'orders.export'],
    ['Gate staff and devices', '/organizer/pwa', 'team.manage'],
    ['Identity and bank', '/organizer/kyc', 'bank.manage'],
    ['Support', '/organizer/support-tikcet/tickets', 'support.use'],
  ], fn ($tool) => $can($tool[2])));
  $toolsActive = $active === 'tools';
  $payoutAction = $organizer && $can('payouts.view') && !\App\Models\OrganizerPayoutAccount::where('organizer_id', $organizer->id)->where('is_default', true)->where('status', 'verified')->exists();
  $titles = ['overview' => 'Overview', 'events' => 'Events', 'orders' => 'Orders and attendees', 'checkin' => 'Gate check-in', 'promote' => 'Promote', 'payouts' => 'Payouts', 'team' => 'Team', 'settings' => 'Settings', 'tools' => 'More tools'];
@endphp

<aside class="os-side" id="os-side" aria-label="Organiser menu">
  <a class="os-logo" href="/" aria-label="TICA home"><img src="{{ asset('assets/tica-ui/img/tica-logo.png') }}" alt="TICA" width="84" height="28"></a>

  <a class="os-org" href="/dashboard/settings" aria-label="Profile and settings for {{ $name }}">
    <x-organizer-avatar :organizer="$organizer" size="40" class="t-avatar" />
    <span>
      <span class="os-org__name">{{ $name }}</span>
      @if ($verified)
        <span class="t-badge t-badge--green"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>Verified</span>
      @else
        <span class="t-badge t-badge--orange">Not verified</span>
      @endif
    </span>
  </a>

  @if ($can('events.manage'))
    <a class="t-btn t-btn--primary t-btn--block" href="/create">Create an event</a>
  @endif

  <nav class="os-nav" aria-label="Organiser">
    @foreach ($groups as $group => $items)
      @php($visible = array_filter($items, fn ($item) => $can($item[3])))
      @if ($visible)
        <span class="os-group">{{ $group }}</span>
        @foreach ($visible as [$key, $label, $href, $permission])
          <a class="os-link {{ $active === $key ? 'is-active' : '' }}" href="{{ $href }}" @if ($spa && $key !== 'checkin') data-panel="{{ $key }}" @endif @if ($active === $key) aria-current="page" @endif>
            <svg viewBox="0 0 24 24" aria-hidden="true">{!! $icons[$key] !!}</svg>{{ $label }}
            @if ($key === 'payouts' && $payoutAction)<span class="os-dot" id="od-paydot" role="img" aria-label="Action needed"></span>@endif
          </a>
        @endforeach
      @endif
    @endforeach

    @if ($tools)
      <details class="os-more" @if ($toolsActive) open @endif>
        <summary class="os-link {{ $toolsActive ? 'is-active' : '' }}"><svg viewBox="0 0 24 24" aria-hidden="true">{!! $icons['tools'] !!}</svg>More tools<svg class="os-chev" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg></summary>
        <div class="os-sub">
          @foreach ($tools as [$label, $href])
            <a class="os-link {{ $toolsActive && request()->is(ltrim($href, '/') . '*') ? 'is-active' : '' }}" href="{{ $href }}">{{ $label }}</a>
          @endforeach
        </div>
      </details>
    @endif
  </nav>

  <form class="os-foot" method="POST" action="{{ route('organizer.logout') }}">
    @csrf
    <button class="os-link" type="submit"><svg viewBox="0 0 24 24" aria-hidden="true">{!! $icons['logout'] !!}</svg>Log out</button>
  </form>
</aside>
<div class="os-scrim" id="os-scrim" aria-hidden="true"></div>

<header class="os-top" aria-label="Page">
  <button class="t-icon-btn os-burger" id="os-burger" type="button" aria-label="Open menu" aria-controls="os-side" aria-expanded="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button>
  <h1 class="os-top__title" id="os-title" @if ($title) data-fixed="1" @endif>{{ $title ?? ($titles[$active] ?? 'Dashboard') }}</h1>
  <details class="os-menu">
    <summary aria-label="Account menu"><x-organizer-avatar :organizer="$organizer" size="36" class="t-avatar" /></summary>
    <div class="os-menu__list">
      <div class="os-menu__who"><b>{{ $name }}</b><small>{{ $organizer?->email }}</small></div>
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
  document.addEventListener('click', function (e) { var m = document.querySelector('.os-menu[open]'); if (m && !m.contains(e.target)) m.removeAttribute('open'); });
  // Legacy pages already show a title in their page header; reuse it in the top bar.
  document.addEventListener('DOMContentLoaded', function () {
    var t = document.getElementById('os-title'), pt = document.querySelector('.orgshell-legacy .page-header .page-title, .orgshell-legacy .page-inner h1');
    if (t && pt && t.dataset.fixed !== '1') t.textContent = pt.textContent.trim();
  });
})();
</script>
