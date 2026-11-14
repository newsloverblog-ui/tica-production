@php
  $topbarAdmin = Auth::guard('admin')->user();
  $topbarAdminName = trim(($topbarAdmin->first_name ?? '') . ' ' . ($topbarAdmin->last_name ?? ''));
  $topbarAdminName = $topbarAdminName !== '' ? $topbarAdminName : ($topbarAdmin->username ?? __('Administrator'));
  $topbarAdminParts = preg_split('/\s+/', $topbarAdminName, -1, PREG_SPLIT_NO_EMPTY);
  $topbarAdminInitials = '';
  foreach (array_slice($topbarAdminParts ?: [], 0, 2) as $topbarAdminPart) {
    $topbarAdminInitials .= mb_strtoupper(mb_substr($topbarAdminPart, 0, 1));
  }
  $topbarAdminImage = $topbarAdmin->image ? 'assets/admin/img/admins/' . $topbarAdmin->image : null;
  $topbarAdminHasImage = !empty($topbarAdminImage);
@endphp

<div class="main-header">
  <!-- Logo Header Start -->
  <div class="logo-header" data-background-color="white">
    <a href="{{ route('index') }}" class="logo" target="_blank" aria-label="{{ __('Open the TICA website') }}">
      <img src="{{ asset('assets/tica-ui/img/tica-logo.png') }}" alt="TICA" class="navbar-brand">
    </a>

    <button class="navbar-toggler sidenav-toggler ml-auto" type="button" data-toggle="collapse" data-target="collapse"
      aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon">
        <i class="icon-menu"></i>
      </span>
    </button>
    <button class="topbar-toggler more"><i class="icon-options-vertical"></i></button>

    <div class="nav-toggle">
      <button class="btn btn-toggle toggle-sidebar">
        <i class="icon-menu"></i>
      </button>
    </div>
  </div>
  <!-- Logo Header End -->

  <!-- Navbar Header Start: page title, work waiting for an admin, account menu -->
  @php
    $topbarPerms = is_null($roleInfo ?? null) ? null : (array) json_decode($roleInfo->permissions ?? '[]', true);
    $topbarCan = fn (string $perm) => is_null($topbarPerms) || in_array($perm, $topbarPerms, true);
    $queuePerm = ['kyc' => 'Organizer Mangement', 'approvals' => 'Organizer Mangement', 'payout_accounts' => 'Withdraw Method', 'withdrawals' => 'Withdraw Method', 'refunds' => 'Event Bookings', 'promotions' => 'Advertise', 'pixels' => 'Advertise', 'support' => 'Support Ticket'];
    $topbarQueues = array_values(array_filter(\App\Support\AdminQueues::all(), fn ($q) => $q['count'] > 0 && $q['url'] && $topbarCan($queuePerm[$q['key']] ?? 'Dashboard')));
    $topbarWaiting = array_sum(array_column($topbarQueues, 'count'));
  @endphp
  <nav class="navbar navbar-header navbar-expand-lg ad-top" data-background-color="white2" aria-label="{{ __('Admin top bar') }}">
    <div class="container-fluid">
      <h1 class="os-top__title" id="os-title">{{ __('Admin') }}</h1>
      <div class="ad-top__right">
        <details class="t-menu ad-bell">
          <summary class="t-icon-btn" aria-label="{{ $topbarWaiting ? trans_choice(':count item waiting for you|:count items waiting for you', $topbarWaiting) : __('Nothing waiting for you') }}">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 16V11a6 6 0 1 1 12 0v5l1.5 2h-15z"/><path d="M10 20a2 2 0 0 0 4 0"/></svg>
            @if ($topbarWaiting)<span class="ad-bell__n" aria-hidden="true">{{ $topbarWaiting > 99 ? '99+' : $topbarWaiting }}</span>@endif
          </summary>
          <div class="t-menu__list ad-bell__list">
            <p class="ad-bell__head">{{ __('Waiting for you') }}</p>
            @forelse ($topbarQueues as $queue)
              <a href="{{ $queue['url'] }}"><span>{{ $queue['label'] }}</span><b class="t-badge t-badge--orange">{{ $queue['count'] }}</b></a>
            @empty
              <p class="ad-bell__empty">{{ __('All caught up. Nothing is waiting for an admin.') }}</p>
            @endforelse
          </div>
        </details>
        <details class="t-menu ad-me">
          <summary class="ad-me__btn" aria-label="{{ __('Open admin account menu') }}">
            <span class="t-avatar" style="--av:36px" aria-hidden="true">{{ $topbarAdminInitials ?: 'TA' }}@if ($topbarAdminHasImage)<img src="{{ asset($topbarAdminImage) }}" alt="" onerror="this.remove()">@endif</span>
          </summary>
          <div class="t-menu__list">
            <p class="ad-me__who"><b>{{ $topbarAdminName }}</b><span>{{ Auth::guard('admin')->user()->email }}</span></p>
            <a href="{{ route('admin.edit_profile') }}">{{ __('Profile') }}</a>
            <a href="{{ route('admin.change_password') }}">{{ __('Change password') }}</a>
            <a href="{{ route('index') }}" target="_blank" rel="noopener">{{ __('View website') }}</a>
            <form method="POST" action="{{ route('admin.logout') }}">@csrf<button type="submit">{{ __('Log out') }}</button></form>
          </div>
        </details>
      </div>
    </div>
  </nav>
  <script>
    (function () {
      // The page's own heading becomes the top-bar title, so it isn't shown twice.
      var t = document.getElementById('os-title'), pt = document.querySelector('.page-header .page-title, .page-inner h1:not(#os-title)');
      if (t && pt && pt.textContent.trim()) { t.textContent = pt.textContent.trim(); document.body.classList.add('ad-titled'); }
      document.addEventListener('click', function (e) { document.querySelectorAll('.ad-top details[open]').forEach(function (d) { if (!d.contains(e.target)) d.removeAttribute('open'); }); });
      document.addEventListener('keydown', function (e) { if (e.key === 'Escape') document.querySelectorAll('.ad-top details[open]').forEach(function (d) { d.removeAttribute('open'); }); });
    })();
  </script>
  <!-- Navbar Header End -->
</div>
