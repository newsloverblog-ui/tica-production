@extends('backend.layout')

@php
  $o = $overview;
  $admin = Auth::guard('admin')->user();
  $can = fn (string ...$any) => $admin?->hasPermission(...$any) ?? false;
  $money = function ($amount) use ($o) {
    if ($amount === null) {
      return __('Unavailable');
    }

    $value = number_format((float) $amount, 2);
    $symbol = $o['base']['symbol'];

    return $o['base']['position'] === 'right' ? $value . ' ' . $symbol : $symbol . $value;
  };
  $month = $o['month'];
  $lifetime = $o['lifetime'];
  $counts = $o['counts'];
  $payoutUrl = route('admin.withdraw.withdraw_request', ['language' => $defaultLang->code]);
  $eventUrl = route('admin.event_management.event', ['language' => $defaultLang->code]);
  $today = $month['through'];
  $hour = (int) $today->format('G');
  $name = $admin?->first_name ?: 'TICA';
  $greeting = match (true) {
    $hour < 12 => __('Good morning, :name', ['name' => $name]),
    $hour < 17 => __('Good afternoon, :name', ['name' => $name]),
    default => __('Good evening, :name', ['name' => $name]),
  };
  $change = function ($current, $previous) {
    if ($previous == 0) {
      return $current > 0 ? ['up', __('New')] : ['flat', '0%'];
    }

    $percent = round(($current - $previous) / $previous * 100, 1);
    return [match ($percent <=> 0) {1 => 'up', -1 => 'down', default => 'flat'}, $percent . '%'];
  };
  $ticketChange = $change($month['tickets'], $month['prior_tickets']);
  $payoutChange = $change($month['payouts'], $month['prior_payouts']);
  $comparisonLabel = __('Month to date compared with the same period last month');
  $kpis = array_filter([
    'revenue' => $can('Lifetime Earning') ? [__('Total revenue'), $money($lifetime['revenue']), __('Lifetime revenue in :currency.', ['currency' => $o['base']['code']]), 'fa-sack-dollar', route('admin.monthly_earning'), null] : null,
    'earnings' => $can('Total Profit') ? [__('TICA earnings'), $money($lifetime['earnings']), __('Lifetime platform earnings from the accounting ledger in :currency.', ['currency' => $o['base']['code']]), 'fa-chart-line', route('admin.monthly_profit'), null] : null,
    'tickets' => $can('Event Bookings') ? [__('Tickets sold'), number_format($lifetime['tickets']), __('Paid and free tickets booked since launch.'), 'fa-ticket-alt', route('admin.event.booking'), $ticketChange] : null,
    'payouts' => $can('Withdraw Method') ? [__('Payouts issued'), $money($lifetime['payouts']), __('Total paid out to organisers in :currency.', ['currency' => $o['base']['code']]), 'fa-wallet', $payoutUrl, $payoutChange] : null,
  ]);
  $monthStats = array_filter([
    $can('Lifetime Earning') ? [__('Gross ticket sales'), $money($month['sales'])] : null,
    $can('Event Bookings') ? [__('Tickets booked'), number_format($month['tickets'])] : null,
    $can('Withdraw Method') ? [__('Payouts sent'), $money($month['payouts'])] : null,
  ]);
  $glance = array_filter([
    $can('Event Management') ? [__('Events'), $counts['events'], $eventUrl] : null,
    $can('Event Management') ? [__('Categories'), $counts['categories'], route('admin.event_management.categories', ['language' => $defaultLang->code])] : null,
    $can('Event Bookings') ? [__('Bookings'), $counts['bookings'], route('admin.event.booking')] : null,
    $can('Transaction') ? [__('Transactions'), $counts['transactions'], route('admin.transcation')] : null,
    $can('Blog Management') ? [__('Blog posts'), $counts['blogs'], route('admin.blog_management.blogs', ['language' => $defaultLang->code])] : null,
  ]);
  $people = array_filter([
    $can('Customer Management') ? [__('Buyers'), $counts['customers'], route('admin.organizer_management.registered_customer'), '#2F5BD3'] : null,
    $can('Organizer Mangement') ? [__('Organisers'), $counts['organisers'], route('admin.organizer_management.registered_organizer'), '#7FA6E8'] : null,
    $can('Admin Management') ? [__('Admins'), $counts['admins'], route('admin.admin_management.registered_admins'), '#C9D7F2'] : null,
  ]);
  $peopleTotal = array_sum(array_column($people, 1));
  $peopleChart = array_values(array_map(fn ($item) => [$item[0], $item[1], $item[3]], $people));
  $queues = collect(\App\Support\AdminQueues::all())->keyBy('key');
  $attention = $o['attention'];
  $waiting = array_filter([
    $can('Transaction') ? [__('Open incidents'), $attention['incidents'], route('admin.operational_incidents.index', ['status' => 'open']), 'fa-shield-check'] : null,
    $can('Withdraw Method') ? [__('Payouts to review'), $queues['withdrawals']['count'], $payoutUrl, 'fa-wallet'] : null,
    $can('Withdraw Method') ? [__('Bank accounts to review'), $queues['payout_accounts']['count'], route('admin.payout_accounts.index', ['status' => 'needs_review']), 'fa-university'] : null,
    $can('Event Bookings') && $can('Transaction') ? [__('Refunds to review'), $queues['refunds']['count'], route('admin.event.booking'), 'fa-undo'] : null,
    $can('Event Bookings') ? [__('Pending bank transfers'), $attention['transfers'], route('admin.event.booking', ['status' => 'pending']), 'fa-university'] : null,
    $can('Transaction') ? [__('Failed payments today'), $attention['failed'], route('admin.funnel_analytics.index'), 'fa-credit-card'] : null,
    $can('Organizer Mangement') ? [__('Identity checks'), $queues['kyc']['count'], route('admin.organizer_kyc.index', ['status' => 'pending']), 'fa-id-card'] : null,
    $can('Organizer Mangement') ? [__('Organisers to approve'), $queues['approvals']['count'], route('admin.organizer_management.registered_organizer'), 'fa-user-check'] : null,
    $can('Organizer Mangement') ? [__('Organiser upgrade requests'), $queues['organizer_upgrades']['count'], route('admin.organizer_upgrades.index'), 'fa-user-check'] : null,
    $can('Advertise') ? [__('Promotions and pixels to review'), $queues['promotions']['count'] + $queues['pixels']['count'], route('admin.advertise.tica_promotions'), 'fa-bullhorn'] : null,
    $can('Support Ticket') ? [__('Open support requests'), $queues['support']['count'], route('admin.support_tickets', ['status' => 1]), 'fa-comments'] : null,
  ]);
  $open = array_filter($waiting, fn ($item) => $item[1] > 0);
  $showSales = $can('Event Bookings', 'Transaction');
  $showEvents = $can('Event Management');
  $latestSales = end($o['series']);
  $todayStats = array_filter([
    $can('Event Bookings') ? [__('Confirmed orders'), number_format($o['today']['orders'])] : null,
    $can('Event Bookings') ? [__('Tickets booked'), number_format($o['today']['tickets'])] : null,
    $can('Lifetime Earning') ? [__('Buyer payments (:currency)', ['currency' => $o['base']['code']]), $money($o['today']['sales'])] : null,
  ]);
@endphp

@section('content')
  <div class="cd">
    <header class="cd-top">
      <div>
        <nav class="cd-crumb" aria-label="{{ __('Breadcrumb') }}">
          <a href="{{ url('/') }}">{{ __('Home') }}</a>
          <span aria-hidden="true">/</span>
          <span aria-current="page">{{ __('Dashboard') }}</span>
        </nav>
        <h1>{{ $greeting }}</h1>
        <p>{{ __('Here is what is happening on TICA today, :date.', ['date' => $today->translatedFormat('l j F')]) }}</p>
      </div>
      <div class="cd-actions">
        <a class="cd-btn" href="{{ route('account-security.show', ['guard' => 'admin']) }}"><i class="fal fa-shield-check" aria-hidden="true"></i>{{ __('Account security') }}</a>
        @if ($can('Event Bookings', 'Lifetime Earning', 'Total Profit', 'Transaction', 'Organizer Mangement', 'Customer Management'))<a class="cd-btn" href="#cd-reports-h"><i class="fal fa-file-chart-line" aria-hidden="true"></i>{{ __('Generate reports') }}</a>@endif
        @if ($showEvents)<a class="cd-btn primary" href="{{ route('admin.choose-event-type', ['language' => $defaultLang->code]) }}"><i class="fal fa-plus" aria-hidden="true"></i>{{ __('Create event') }}</a>@endif
      </div>
    </header>

    @if ($kpis)
      <section class="cd-kpis" aria-label="{{ __('Lifetime totals') }}">
        @foreach ($kpis as $key => [$label, $value, $hint, $icon, $href, $trend])
          <a class="cd-card cd-kpi cd-kpi--{{ $key }}" href="{{ $href }}" title="{{ $hint }}">
            <span class="cd-kpi-top">
              <span class="cd-ic"><i class="fal {{ $icon }}" aria-hidden="true"></i></span>
              <i class="fal fa-info-circle cd-info" aria-hidden="true"></i>
            </span>
            <span class="cd-kpi-l">{{ $label }}</span>
            <span class="cd-kpi-row">
              <b class="cd-kpi-value">{{ $value }}</b>
              @if ($trend)
                <span class="cd-chg {{ $trend[0] }}" title="{{ $comparisonLabel }}">
                  @if ($trend[0] !== 'flat')<i class="fal fa-arrow-{{ $trend[0] }}" aria-hidden="true"></i>@endif
                  <span class="sr-only">{{ $comparisonLabel }}</span>{{ $trend[1] }}
                </span>
              @endif
            </span>
          </a>
        @endforeach
      </section>
    @endif

    @if ($waiting)
      <section class="cd-card cd-wait" aria-labelledby="cd-wait-h">
        <strong id="cd-wait-h">{{ __('Waiting for you') }}</strong>
        @forelse ($open as [$label, $count, $href, $icon])
          <a href="{{ $href }}">
            <i class="fal {{ $icon }}" aria-hidden="true"></i>
            <span>{{ $label }}</span><b>{{ number_format($count) }}</b>
          </a>
        @empty
          <span class="cd-clear"><i class="fal fa-check-circle" aria-hidden="true"></i>{{ __('All clear. Nothing needs you right now.') }}</span>
        @endforelse
      </section>
    @endif

    @if ($showSales || $people)
      <div class="cd-row {{ $showSales && $people ? 'cd-row--split' : '' }}">
        @if ($showSales)
          <section class="cd-card cd-chart" aria-labelledby="cd-chart-h">
            <div class="cd-card-h">
              <h2 id="cd-chart-h">{{ __('Ticket sales analytics') }}</h2>
              <span class="cd-pill">{{ __('Last 12 months') }}</span>
            </div>
            <div class="cd-chart-sum">
              <div>
                <b>{{ number_format($latestSales['tickets']) }}</b>
                <span class="cd-chg {{ $ticketChange[0] }}" title="{{ $comparisonLabel }}">
                  @if ($ticketChange[0] !== 'flat')<i class="fal fa-arrow-{{ $ticketChange[0] }}" aria-hidden="true"></i>@endif
                  <span class="sr-only">{{ $comparisonLabel }}</span>{{ $ticketChange[1] }}
                </span>
                <span>{{ trans_choice('ticket in :month|tickets in :month', $latestSales['tickets'], ['month' => $latestSales['title']]) }}</span>
              </div>
              <ul class="cd-legend">
                <li><i class="blue" aria-hidden="true"></i>{{ __('Tickets') }}</li>
                <li><i class="grey" aria-hidden="true"></i>{{ __('Orders') }}</li>
              </ul>
            </div>
            <div class="cd-canvas"><canvas id="cd-sales" role="img" aria-label="{{ __('Tickets and orders per month. See the table below for exact totals.') }}"></canvas></div>
            <details class="cd-series">
              <summary>{{ __('Show monthly totals') }}</summary>
              <div class="cd-scroll" tabindex="0" role="region" aria-label="{{ __('Booking activity table') }}">
                <table>
                  <thead><tr><th scope="col">{{ __('Month') }}</th><th scope="col" class="cd-num">{{ __('Tickets') }}</th><th scope="col" class="cd-num">{{ __('Orders') }}</th></tr></thead>
                  <tbody>
                    @foreach ($o['series'] as $row)
                      <tr><th scope="row">{{ $row['title'] }}</th><td class="cd-num">{{ number_format($row['tickets']) }}</td><td class="cd-num">{{ number_format($row['orders']) }}</td></tr>
                    @endforeach
                  </tbody>
                </table>
              </div>
            </details>
          </section>
        @endif

        @if ($people)
          <section class="cd-card cd-users" aria-labelledby="cd-people-h">
            <div class="cd-card-h"><h2 id="cd-people-h">{{ __('People on TICA') }}</h2></div>
            <div class="cd-donut {{ $peopleTotal ? '' : 'cd-donut--empty' }}">
              @if ($peopleTotal)<canvas id="cd-people" aria-hidden="true"></canvas>@endif
              <div class="cd-donut-c"><b>{{ number_format($peopleTotal) }}</b><span>{{ __('accounts') }}</span></div>
            </div>
            <ul class="cd-split">
              @foreach ($people as [$label, $count, $href, $color])
                <li>
                  <a href="{{ $href }}">
                    <i style="background-color: {{ $color }}" aria-hidden="true"></i>
                    <span>{{ $label }}</span>
                    <b title="{{ number_format($count) }}">{{ $peopleTotal ? round($count / $peopleTotal * 100) : 0 }}%<span class="sr-only"> · {{ number_format($count) }} {{ __('accounts') }}</span></b>
                  </a>
                </li>
              @endforeach
            </ul>
          </section>
        @endif
      </div>
    @endif

    @if ($can('Withdraw Method'))
      <section class="cd-card cd-table" aria-labelledby="cd-pay-h">
        <div class="cd-card-h">
          <h2 id="cd-pay-h">{{ __('Recent payout requests') }}</h2>
          <a class="cd-btn sm" href="{{ $payoutUrl }}" aria-label="{{ __('See all payout requests') }}">{{ __('See all') }} <i class="fal fa-arrow-right" aria-hidden="true"></i></a>
        </div>
        @if ($o['payouts'])
          <div class="cd-scroll" tabindex="0" role="region" aria-label="{{ __('Recent payout requests table') }}">
            <table>
              <thead><tr><th scope="col">{{ __('Request') }}</th><th scope="col">{{ __('Status') }}</th><th scope="col" class="cd-num">{{ __('Amount') }}</th><th scope="col">{{ __('Organiser') }}</th><th scope="col">{{ __('Requested') }}</th><th scope="col">{{ __('Reviewed or paid') }}</th></tr></thead>
              <tbody>
                @foreach ($o['payouts'] as $row)
                  <tr>
                    <td class="cd-mono">
                      @if ($row['search'])
                        <a class="cd-record" href="{{ route('admin.withdraw.withdraw_request', ['language' => $defaultLang->code, 'search' => $row['search']]) }}">{{ $row['ref'] }}</a>
                      @else
                        {{ $row['ref'] }}
                      @endif
                    </td>
                    <td><span class="cd-st {{ $row['status'][1] }}">{{ __($row['status'][0]) }}</span></td>
                    <td class="cd-num">{{ $money($row['amount']) }}</td>
                    <td>{{ $row['organiser'] }}</td>
                    <td>{{ $row['requested'] }}</td>
                    <td>{{ $row['processed'] ?? '—' }}</td>
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

    @include('backend.admin.dashboard-activity')

    @if ($glance)
      <section class="cd-card" aria-labelledby="cd-glance-h">
        <div class="cd-card-h"><h2 id="cd-glance-h">{{ __('Platform at a glance') }}</h2></div>
        <ul class="cd-overview-links">
          @foreach ($glance as [$label, $count, $href])
            <li><a href="{{ $href }}"><span>{{ $label }}</span><strong>{{ number_format($count) }}</strong></a></li>
          @endforeach
        </ul>
      </section>
    @endif

    @include('backend.admin.dashboard-reports')

    @if ($monthStats)
      <section class="cd-card" aria-labelledby="cd-period-h">
        <div class="cd-card-h">
          <h2 id="cd-period-h">{{ __('Month to date') }}</h2>
          <span class="cd-pill">
            <time datetime="{{ $month['from']->toDateString() }}">{{ $month['from']->translatedFormat('j M') }}</time>
            <span aria-hidden="true">–</span><span class="sr-only"> {{ __('to') }} </span>
            <time datetime="{{ $month['through']->toDateString() }}">{{ $month['through']->translatedFormat('j M Y') }}</time>
            <span aria-hidden="true"> · </span>{{ $o['base']['code'] }}
          </span>
        </div>
        <dl class="cd-today">
          @foreach ($monthStats as [$label, $value])
            <div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
          @endforeach
        </dl>
      </section>
    @endif

    @if ($todayStats)
      <section class="cd-card" aria-labelledby="cd-today-h">
        <div class="cd-card-h"><h2 id="cd-today-h">{{ __('Today') }}</h2></div>
        <dl class="cd-today">
          @foreach ($todayStats as [$label, $value])
            <div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
          @endforeach
        </dl>
      </section>
    @endif

    @if ($showEvents)
      <section class="cd-card cd-upcoming" aria-labelledby="cd-events-h">
        <div class="cd-card-h">
          <h2 id="cd-events-h">{{ __('Upcoming events') }}</h2>
          <a class="cd-btn sm" href="{{ $eventUrl }}">{{ __('View events') }}</a>
        </div>
        @if ($o['upcoming'])
          <ul class="cd-upcoming-list">
            @foreach ($o['upcoming'] as $event)
              <li>
                <a class="cd-record" href="{{ route('admin.event_builder.edit', ['id' => $event['id']]) }}">{{ $event['title'] }}</a>
                <p>{{ $event['date'] }} <span aria-hidden="true">·</span> {{ trans_choice(':count ticket booked|:count tickets booked', $event['sold']) }}</p>
              </li>
            @endforeach
          </ul>
        @else
          <p class="cd-empty">{{ __('No published events scheduled in the next 60 days.') }}</p>
        @endif
      </section>
    @endif
  </div>
@endsection

@section('script')
  @if ($showSales || $peopleTotal)
    <script src="{{ asset('assets/admin/js/chart.min.js') }}"></script>
    <script>
      (function () {
        if (!window.Chart) return;

        var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var canvas = document.getElementById('cd-sales');
        if (canvas) {
          var series = @json($showSales ? $o['series'] : []);
          var tickColor = '#526079';
          new Chart(canvas, {
            type: 'bar',
            data: {
              labels: series.map(function (month) { return month.label; }),
              datasets: [
                {label: @json(__('Tickets')), data: series.map(function (month) { return month.tickets; }), backgroundColor: '#0B2447'},
                {label: @json(__('Orders')), data: series.map(function (month) { return month.orders; }), backgroundColor: '#D9E0EB'}
              ]
            },
            options: {
              maintainAspectRatio: false,
              animation: {duration: reducedMotion ? 0 : 260},
              legend: {display: false},
              tooltips: {mode: 'index', intersect: false},
              scales: {
                xAxes: [{gridLines: {display: false}, barPercentage: .55, ticks: {fontColor: tickColor, maxRotation: 0, autoSkipPadding: 8}}],
                yAxes: [{
                  gridLines: {color: '#E4EAF3', drawBorder: false, borderDash: [4, 4]},
                  ticks: {
                    beginAtZero: true, suggestedMax: 1, maxTicksLimit: 5, fontColor: tickColor,
                    callback: function (value) { return Number.isInteger(value) ? value : ''; }
                  }
                }]
              }
            }
          });
        }

        var donut = document.getElementById('cd-people');
        var people = @json($peopleChart);
        if (donut) {
          new Chart(donut, {
            type: 'doughnut',
            data: {
              labels: people.map(function (group) { return group[0]; }),
              datasets: [{
                data: people.map(function (group) { return group[1]; }),
                backgroundColor: people.map(function (group) { return group[2]; }),
                borderColor: '#fff',
                borderWidth: 4
              }]
            },
            options: {
              maintainAspectRatio: false,
              cutoutPercentage: 75,
              legend: {display: false},
              animation: {duration: reducedMotion ? 0 : 260}
            }
          });
        }
      })();
    </script>
  @endif
@endsection
