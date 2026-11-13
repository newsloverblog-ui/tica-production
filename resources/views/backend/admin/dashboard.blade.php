@extends('backend.layout')

@php
  $o = $overview;
  $perms = is_null($roleInfo) ? null : (array) json_decode($roleInfo->permissions ?? '[]', true);
  $can = fn (string ...$any) => is_null($perms) || array_intersect($any, $perms) !== [];
  $right = ($settings->base_currency_symbol_position ?? 'left') === 'right';
  $money = function ($amount, int $decimals = 2) use ($settings, $right) {
    $symbol = $settings->base_currency_symbol ?? '';
    $value = number_format((float) $amount, $decimals);
    return $right ? $value . ' ' . $symbol : $symbol . $value;
  };
  $delta = function (float $now, float $before) {
    if ($before <= 0) {
      return $now > 0 ? ['up', __('New')] : ['flat', '0%'];
    }
    $pct = round(($now - $before) / $before * 100, 1);
    return [$pct > 0 ? 'up' : ($pct < 0 ? 'down' : 'flat'), abs($pct) . '%'];
  };
  $m = $o['month'];
  $mo = $o['money'];
  $a = $o['attention'];
  // [label, value, [direction, change], hint, icon, link]
  $kpis = array_values(array_filter([
    $can('Lifetime Earning') ? [__('Total revenue'), $money($total_earning->total_revenue ?? 0), $delta($m['this']['sales'], $m['last']['sales']), __('Everything buyers have paid since TICA started. The change compares this month with last month.'), 'fa-sack-dollar', route('admin.monthly_earning')] : null,
    $can('Total Profit') ? [__('TICA earnings'), $money($total_earning->total_earning ?? 0), $delta($m['this']['fees'], $m['last']['fees']), __('Booking fees TICA kept. The change compares this month with last month.'), 'fa-chart-line', route('admin.monthly_profit')] : null,
    $can('Event Bookings') ? [__('Tickets sold'), number_format($mo['tickets']), $delta($m['this']['tickets'], $m['last']['tickets']), __('Paid and free tickets issued since TICA started.'), 'fa-ticket-alt', route('admin.event.booking')] : null,
    $can('Withdraw Method') ? [__('Payouts issued'), $money($mo['payouts']), $delta($mo['this']['payouts'], $mo['last']['payouts']), __('Money paid out to organisers.'), 'fa-wallet', route('admin.withdraw.withdraw_request', ['language' => $defaultLang->code])] : null,
  ]));
  // [label, count, link, icon]
  $q = collect(\App\Support\AdminQueues::all())->keyBy('key');
  $waiting = array_values(array_filter([
    $can('Withdraw Method') ? [__('Payout requests'), $a['payouts']['count'], route('admin.withdraw.withdraw_request', ['language' => $defaultLang->code]), 'fa-wallet'] : null,
    $can('Withdraw Method') ? [__('Bank accounts to review'), $q['payout_accounts']['count'], route('admin.payout_accounts.index', ['status' => 'needs_review']), 'fa-university'] : null,
    $can('Event Bookings') ? [__('Refunds to approve'), $q['refunds']['count'], route('admin.event.booking'), 'fa-undo'] : null,
    $can('Advertise') ? [__('Promotions to review'), $q['promotions']['count'] + $q['pixels']['count'], route('admin.advertise.tica_promotions'), 'fa-bullhorn'] : null,
    $can('Organizer Mangement') ? [__('Identity checks'), $a['kyc'], route('admin.organizer_kyc.index', ['status' => 'pending']), 'fa-id-card'] : null,
    $can('Organizer Mangement') && $a['approvals'] ? [__('New organisers'), $a['approvals'], route('admin.organizer_management.registered_organizer'), 'fa-user-check'] : null,
    $can('Event Bookings') ? [__('Bank transfers'), $a['transfers'], route('admin.event.booking', ['status' => 'pending']), 'fa-university'] : null,
    $can('Support Ticket') ? [__('Help desk'), $a['support'], route('admin.support_tickets', ['status' => 1]), 'fa-comments'] : null,
    $can('Transaction') ? [__('Failed payments today'), $a['failed'], route('admin.funnel_analytics.index'), 'fa-credit-card'] : null,
    $can('Transaction') ? [__('Open incidents'), $a['incidents'], route('admin.operational_incidents.index'), 'fa-shield-check'] : null,
  ]));
  // [label, value, link]
  $glance = array_values(array_filter([
    $can('Event Management') ? [__('Events'), $totalEvents, route('admin.event_management.event', ['language' => $defaultLang->code])] : null,
    $can('Event Management') ? [__('Categories'), $totalEventCategories, route('admin.event_management.categories', ['language' => $defaultLang->code])] : null,
    $can('Event Bookings') ? [__('Bookings'), $totalEventBookings, route('admin.event.booking')] : null,
    $can('Transaction') ? [__('Transactions'), $transcation_count, route('admin.transcation')] : null,
    $can('Organizer Mangement') ? [__('Organisers'), $totalOrganizers, route('admin.organizer_management.registered_organizer')] : null,
    $can('Customer Management') ? [__('Customers'), $totalRegisteredUsers, route('admin.organizer_management.registered_customer')] : null,
    $can('Blog Management') ? [__('Blog posts'), $totalBlog, route('admin.blog_management.blogs', ['language' => $defaultLang->code])] : null,
  ]));
  $users = $o['users'];
  $userTotal = max(1, array_sum($users));
  $hour = (int) now()->format('G');
  $greeting = $hour < 12 ? __('Good morning') : ($hour < 17 ? __('Good afternoon') : __('Good evening'));
@endphp

@section('content')
  <div class="cd">
    <header class="cd-top">
      <div>
        <nav class="cd-crumb" aria-label="{{ __('Breadcrumb') }}"><span>{{ __('Home') }}</span><i aria-hidden="true">/</i><b>{{ __('Dashboard') }}</b></nav>
        <h1>{{ $greeting }}, {{ Auth::guard('admin')->user()->first_name }}</h1>
        <p>{{ __('Here is what is happening on TICA today, :date.', ['date' => now()->format('l j F')]) }}</p>
      </div>
      <div class="cd-actions">
        <a class="cd-btn" href="{{ route('account-security.show', ['guard' => 'admin']) }}"><i class="fal fa-shield-check" aria-hidden="true"></i>{{ __('Account security') }}</a>
        @if ($can('Event Bookings'))<a class="cd-btn" href="{{ route('admin.data_exports.index') }}"><i class="fal fa-file-chart-line" aria-hidden="true"></i>{{ __('Generate reports') }}</a>@endif
        @if ($can('Event Management'))<a class="cd-btn primary" href="{{ route('admin.choose-event-type', ['language' => $defaultLang->code]) }}"><i class="fal fa-plus" aria-hidden="true"></i>{{ __('Create event') }}</a>@endif
      </div>
    </header>

    @if ($kpis)
      <section class="cd-kpis" aria-label="{{ __('Key numbers') }}">
        @foreach ($kpis as [$label, $value, [$dir, $change], $hint, $icon, $href])
          <a class="cd-card cd-kpi" href="{{ $href }}">
            <span class="cd-kpi-top"><span class="cd-ic"><i class="fal {{ $icon }}" aria-hidden="true"></i></span><span class="cd-info" title="{{ $hint }}"><i class="fal fa-info-circle" aria-hidden="true"></i><span class="sr-only">{{ $hint }}</span></span></span>
            <span class="cd-kpi-l">{{ $label }}</span>
            <span class="cd-kpi-row"><b>{{ $value }}</b><em class="cd-chg {{ $dir }}" title="{{ __('This month against the same days last month') }}">@if ($dir === 'up')<i class="fal fa-arrow-up" aria-hidden="true"></i>@elseif ($dir === 'down')<i class="fal fa-arrow-down" aria-hidden="true"></i>@endif{{ $change }}</em></span>
          </a>
        @endforeach
      </section>
    @endif

    @if ($waiting)
      @php
        $open = array_values(array_filter($waiting, fn ($item) => $item[1] > 0));
      @endphp
      <nav class="cd-card cd-wait" aria-label="{{ __('Work waiting for an admin') }}">
        <span class="cd-wait-h">{{ __('Waiting for you') }}</span>
        @forelse ($open as [$label, $count, $href, $icon])
          <a href="{{ $href }}" class="has"><i class="fal {{ $icon }}" aria-hidden="true"></i>{{ $label }}<b>{{ number_format($count) }}</b></a>
        @empty
          <span class="cd-clear"><i class="fal fa-check-circle" aria-hidden="true"></i>{{ __('All clear. Nothing needs you right now.') }}</span>
        @endforelse
        <span class="cd-wait-note">{{ __('Payouts, identity checks, bank transfers, help desk and payment health are checked here.') }}</span>
      </nav>
    @endif

    <div class="cd-row">
      @if ($can('Event Bookings', 'Transaction'))
        <section class="cd-card cd-chart" aria-labelledby="cd-chart-h">
          <div class="cd-card-h">
            <h2 id="cd-chart-h">{{ __('Ticket sales analytics') }}</h2>
            <span class="cd-pill">{{ __('Last 12 months') }}</span>
          </div>
          @php
            $last = end($o['series']);
            [$tdir, $tchg] = $delta($m['this']['tickets'], $m['last']['tickets']);
          @endphp
          <div class="cd-chart-sum">
            <div><b>{{ number_format($last['tickets']) }}</b><em class="cd-chg {{ $tdir }} plain">@if ($tdir === 'up')<i class="fal fa-arrow-up" aria-hidden="true"></i>@elseif ($tdir === 'down')<i class="fal fa-arrow-down" aria-hidden="true"></i>@endif{{ $tchg }}</em><span>{{ __('tickets in :month', ['month' => $last['title']]) }}</span></div>
            <ul class="cd-legend"><li><i class="blue"></i>{{ __('Tickets') }}</li><li><i class="grey"></i>{{ __('Orders') }}</li></ul>
          </div>
          <div class="cd-canvas"><canvas id="cd-sales" role="img" aria-label="{{ __('Tickets and orders in each of the last 12 months') }}: {{ collect($o['series'])->map(fn ($s) => $s['title'] . ' ' . $s['tickets'] . ' / ' . $s['orders'])->implode(', ') }}"></canvas></div>
        </section>
      @endif

      <section class="cd-card cd-users" aria-labelledby="cd-users-h">
        <div class="cd-card-h"><h2 id="cd-users-h">{{ __('People on TICA') }}</h2></div>
        <div class="cd-donut">
          <canvas id="cd-donut" width="190" height="190" role="img" aria-label="{{ __('Organisers :o, buyers :b, admins :a', ['o' => $users['organisers'], 'b' => $users['buyers'], 'a' => $users['admins']]) }}"></canvas>
          <div class="cd-donut-c"><b>{{ number_format(array_sum($users)) }}</b><span>{{ __('accounts') }}</span></div>
        </div>
        <ul class="cd-split">
          <li><i style="background:#1F5FCC"></i>{{ __('Buyers') }}<b>{{ round($users['buyers'] / $userTotal * 100) }}%</b></li>
          <li><i style="background:#7FA6E8"></i>{{ __('Organisers') }}<b>{{ round($users['organisers'] / $userTotal * 100) }}%</b></li>
          <li><i style="background:#C9D7F2"></i>{{ __('Admins') }}<b>{{ round($users['admins'] / $userTotal * 100) }}%</b></li>
        </ul>
      </section>
    </div>

    @if ($can('Withdraw Method'))
      <section class="cd-card cd-table" aria-labelledby="cd-pay-h">
        <div class="cd-card-h">
          <h2 id="cd-pay-h">{{ __('Recent payout requests') }}</h2>
          <a class="cd-btn sm" href="{{ route('admin.withdraw.withdraw_request', ['language' => $defaultLang->code]) }}">{{ __('See all') }}<i class="fal fa-arrow-right" aria-hidden="true"></i></a>
        </div>
        @if ($o['payouts'])
          <div class="cd-scroll">
            <table>
              <thead><tr><th>{{ __('Request') }}</th><th>{{ __('Organiser') }}</th><th>{{ __('Amount') }}</th><th>{{ __('Requested on') }}</th><th>{{ __('Status') }}</th><th>{{ __('Processed on') }}</th><th><span class="sr-only">{{ __('Actions') }}</span></th></tr></thead>
              <tbody>
                @foreach ($o['payouts'] as $row)
                  <tr>
                    <td class="cd-mono">{{ $row['ref'] }}</td>
                    <td><span class="cd-who"><i aria-hidden="true">{{ mb_strtoupper(mb_substr($row['organiser'], 0, 1)) }}</i>{{ $row['organiser'] }}</span></td>
                    <td class="cd-num">{{ $money($row['amount']) }}</td>
                    <td>{{ $row['requested'] }}</td>
                    <td><span class="cd-st {{ $row['status'][1] }}">{{ __($row['status'][0]) }}</span></td>
                    <td>{{ $row['processed'] ?? '—' }}</td>
                    <td class="cd-act"><a href="{{ route('admin.withdraw.withdraw_request', ['language' => $defaultLang->code]) }}" aria-label="{{ __('Open payout requests') }}"><i class="fal fa-eye" aria-hidden="true"></i></a></td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @else
          <p class="cd-empty">{{ __('No payout requests yet.') }}</p>
        @endif
      </section>
    @endif

    @if ($glance)
      <section class="cd-card cd-glance" aria-labelledby="cd-glance-h">
        <div class="cd-card-h"><h2 id="cd-glance-h">{{ __('Platform at a glance') }}</h2></div>
        <div class="cd-glance-row">
          @foreach ($glance as [$label, $value, $href])
            <a href="{{ $href }}"><span>{{ $label }}</span><b>{{ number_format($value) }}</b></a>
          @endforeach
        </div>
      </section>
    @endif
  </div>
@endsection

@section('script')
  <script src="{{ asset('assets/admin/js/chart.min.js') }}"></script>
  <script>
    (function () {
      if (!window.Chart) return;
      var font = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";
      Chart.defaults.global.defaultFontFamily = font;
      Chart.defaults.global.defaultFontColor = '#8A94A6';
      var tips = {backgroundColor: '#fff', titleFontColor: '#111827', bodyFontColor: '#4B5563', borderColor: '#E6EAF0', borderWidth: 1, titleFontFamily: font, bodyFontFamily: font, xPadding: 12, yPadding: 10, cornerRadius: 10, displayColors: true, mode: 'index', intersect: false};
      var series = @json($o['series']);
      var sales = document.getElementById('cd-sales');
      if (sales) {
        new Chart(sales.getContext('2d'), {
          type: 'bar',
          data: {
            labels: series.map(function (s) { return s.label; }),
            datasets: [
              {label: @json(__('Tickets')), data: series.map(function (s) { return s.tickets; }), backgroundColor: '#0B2447', hoverBackgroundColor: '#153B63', borderWidth: 0},
              {label: @json(__('Orders')), data: series.map(function (s) { return s.orders; }), backgroundColor: '#DCE3EE', hoverBackgroundColor: '#C9D3E2', borderWidth: 0}
            ]
          },
          options: {
            maintainAspectRatio: false, legend: {display: false}, tooltips: tips,
            scales: {
              xAxes: [{gridLines: {display: false}, barPercentage: .55, categoryPercentage: .62, ticks: {fontSize: 12}}],
              yAxes: [{gridLines: {color: '#EEF1F5', zeroLineColor: '#E6EAF0', drawBorder: false, borderDash: [4, 4]}, ticks: {beginAtZero: true, maxTicksLimit: 5, fontSize: 12, padding: 8, callback: function (v) { return Number.isInteger(v) ? v : ''; }}}]
            }
          }
        });
      }
      var donut = document.getElementById('cd-donut');
      if (donut) {
        new Chart(donut.getContext('2d'), {
          type: 'doughnut',
          data: {labels: [@json(__('Buyers')), @json(__('Organisers')), @json(__('Admins'))], datasets: [{data: [{{ $users['buyers'] }}, {{ $users['organisers'] }}, {{ $users['admins'] }}], backgroundColor: ['#2F5BD3', '#7FA6E8', '#C9D7F2'], borderColor: '#fff', borderWidth: 4, hoverBorderColor: '#fff'}]},
          options: {responsive: false, cutoutPercentage: 72, legend: {display: false}, tooltips: Object.assign({}, tips, {mode: 'nearest', intersect: true})}
        });
      }
    })();
  </script>
@endsection
