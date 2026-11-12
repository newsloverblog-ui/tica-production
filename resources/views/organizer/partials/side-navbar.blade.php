@php
  $ticaRole = (string) (session('tica_team_member.role') ?: 'owner');
  $ticaOrganizer = Auth::guard('organizer')->user();
  $ticaOrgName = \App\Models\OrganizerInfo::query()->where('organizer_id', $ticaOrganizer->id)->value('name') ?: $ticaOrganizer->username;
  $ticaInitials = collect(preg_split('/\s+/u', trim($ticaOrgName)) ?: [])->take(2)->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))->implode('') ?: 'T';
  $ticaSupport = optional(DB::table('support_ticket_statuses')->first())->support_ticket_status === 'active';
  $ticaCan = fn (string $area) => match ($area) {
    'events' => in_array($ticaRole, ['owner', 'manager'], true),
    'money' => in_array($ticaRole, ['owner', 'finance'], true),
    'owner' => $ticaRole === 'owner',
    default => true,
  };
  // [label, url, active routes, area, icon path, section]
  $ticaWorkspace = [
    ['Overview', url('/dashboard'), ['organizer.dashboard'], 'all', '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>', 'Main'],
    ['Events', url('/dashboard/events'), ['organizer.event_management.*', 'organizer.event.ticket', 'organizer.event.add.ticket', 'organizer.event.edit.ticket', 'organizer.add.event.event', 'choose-event-type'], 'events', '<rect x="4" y="5" width="16" height="15" rx="2"/><path d="M4 10h16M9 3v4M15 3v4"/>', 'Main'],
    ['Orders and attendees', url('/dashboard/orders'), ['organizer.event.booking', 'organizer.event_booking.*'], 'all', '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4.5a3.5 3.5 0 0 1 0 7M21.5 20a6.5 6.5 0 0 0-4-6"/>', 'Main'],
    ['Gate check-in', url('/checkin'), [], 'all', '<path d="M4 8V5a1 1 0 0 1 1-1h3M16 4h3a1 1 0 0 1 1 1v3M20 16v3a1 1 0 0 1-1 1h-3M8 20H5a1 1 0 0 1-1-1v-3M8 12h8"/>', 'Main'],
    ['Promote', url('/dashboard/promote'), [], 'events', '<path d="M3 11v2a1 1 0 0 0 1 1h3l5 4V6L7 10H4a1 1 0 0 0-1 1zM16 8a5 5 0 0 1 0 8"/>', 'Grow'],
    ['Payouts', url('/dashboard/payouts'), ['organizer.kyc.*', 'organizer.withdraw*'], 'money', '<rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 10h18M7 15h3"/>', 'Money'],
    ['Team', url('/dashboard/team'), [], 'owner', '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M17 11v6M14 14h6"/>', 'Account'],
    ['Settings', url('/dashboard/settings'), ['organizer.edit.profile'], 'owner', '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-2.7 1.1V21a2 2 0 1 1-4 0v-.1A1.6 1.6 0 0 0 7.4 19.6l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1A1.6 1.6 0 0 0 3 14H3a2 2 0 1 1 0-4h.1A1.6 1.6 0 0 0 4.4 7.4l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1A1.6 1.6 0 0 0 10 3.1V3a2 2 0 1 1 4 0v.1a1.6 1.6 0 0 0 2.6 1.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1A1.6 1.6 0 0 0 20.9 10H21a2 2 0 1 1 0 4h-.1a1.6 1.6 0 0 0-1.5 1z"/>', 'Account'],
  ];
  $ticaTools = [
    ['Booking report', route('organizer.event_booking.report'), ['organizer.event_booking.report'], 'all'],
    ['Guest lists and RSVPs', route('organizer.event.guest-list.index'), ['organizer.event.guest-list.*'], 'events'],
    ['Registration forms', route('organizer.event.registrations.index'), ['organizer.event.registrations.*'], 'events'],
    ['Transactions', route('organizer.transcation'), ['organizer.transcation', 'organizer.transaction_exports.*'], 'money'],
    ['Payout exports', route('organizer.withdraw_exports.index'), ['organizer.withdraw_exports.*'], 'money'],
    ['Finance statement', route('organizer.finance.index'), ['organizer.finance.*'], 'money'],
    ['Data exports', route('organizer.data_exports.index'), ['organizer.data_exports.*'], 'all'],
    ['Check-in ledger', route('organizer.check_in_ledger_exports.index'), ['organizer.check_in_ledger_exports.*'], 'all'],
    ['Gate staff and devices', route('organizer.pwa'), ['organizer.pwa', 'organizer.scanner.*'], 'owner'],
    ['Identity and bank account', route('organizer.kyc.show'), ['organizer.kyc.*'], 'owner'],
    ['Support', route('organizer.support_tickets'), ['organizer.support_tickets', 'organizer.support_tickets.*', 'organizer.support_ticket.*'], $ticaSupport ? 'all' : 'none'],
  ];
@endphp
<div class="sidebar tsb" data-background-color="white">
  <div class="sidebar-wrapper scrollbar scrollbar-inner">
    <div class="sidebar-content">
      <a class="tsb-org" href="{{ url('/dashboard/settings') }}" aria-label="{{ __('Open organiser profile and settings') }}">
        <i class="tsb-av" aria-hidden="true">{{ $ticaInitials }}</i>
        <span><b>{{ $ticaOrgName }}</b><small class="{{ $ticaOrganizer->kyc_status === 'verified' ? '' : 'no' }}">{{ $ticaOrganizer->kyc_status === 'verified' ? __('Verified organiser') : __('Not verified yet') }}</small></span>
      </a>
      <nav class="tsb-nav" aria-label="{{ __('Organiser menu') }}">
        @php $ticaLastSection = null; @endphp
        @foreach ($ticaWorkspace as [$label, $href, $routes, $area, $icon, $section])
          @if ($ticaCan($area))
            @if ($section !== $ticaLastSection)
              <span class="tsb-h">{{ __($section) }}</span>
              @php $ticaLastSection = $section; @endphp
            @endif
            <a href="{{ $href }}" @class(['on' => $routes && request()->routeIs(...$routes)]) @if ($routes && request()->routeIs(...$routes)) aria-current="page" @endif>
              <svg viewBox="0 0 24 24" aria-hidden="true">{!! $icon !!}</svg>{{ __($label) }}
            </a>
          @endif
        @endforeach
      </nav>
      @php
        $ticaToolOpen = collect($ticaTools)->contains(fn ($tool) => $tool[3] !== 'none' && $ticaCan($tool[3]) && request()->routeIs(...$tool[2]));
      @endphp
      <details class="tsb-more" @if ($ticaToolOpen) open @endif>
        <summary><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="6" height="6" rx="1.5"/><rect x="14" y="4" width="6" height="6" rx="1.5"/><rect x="4" y="14" width="6" height="6" rx="1.5"/><rect x="14" y="14" width="6" height="6" rx="1.5"/></svg><span>{{ __('More tools') }}</span><svg class="more-chev" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg></summary>
        <nav class="tsb-nav tsb-tools" aria-label="{{ __('More organiser tools') }}">
          @foreach ($ticaTools as [$label, $href, $routes, $area])
            @if ($area !== 'none' && $ticaCan($area))
              <a href="{{ $href }}" @class(['on' => request()->routeIs(...$routes)]) @if (request()->routeIs(...$routes)) aria-current="page" @endif>{{ __($label) }}</a>
            @endif
          @endforeach
        </nav>
      </details>
      <form class="tsb-out" method="POST" action="{{ route('organizer.logout') }}">
        @csrf
        <button type="submit"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 17l5-5-5-5M15 12H4"/></svg>{{ __('Log out') }}</button>
      </form>
      @if ($ticaCan('events'))
        <a class="btn btn-primary tsb-new" href="{{ url('/create') }}">{{ __('Create an event') }}</a>
      @endif
    </div>
  </div>
</div>
