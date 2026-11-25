@extends('backend.layout')

@php
  $o = $overview;
  $admin = Auth::guard('admin')->user();
  $can = fn (string ...$any) => $admin?->hasPermission(...$any) ?? false;
  $money = function ($amount) use ($o) {
    $value = number_format((float) $amount, 2);
    $symbol = $o['base']['symbol'];

    return $o['base']['position'] === 'right' ? $value . ' ' . $symbol : $symbol . $value;
  };
  $month = $o['month'];
  $current = $month['this'];
  $previous = $month['last'];
  $delta = function ($value, $before) {
    if ($value === $before) {
      return null;
    }
    if ($before <= 0) {
      return $value > 0 ? ['up', __('New activity'), __('No activity at this point last month.')] : null;
    }
    $percent = round(($value - $before) / $before * 100, 1);
    if ($percent == 0) {
      return null;
    }
    $label = ($percent > 0 ? '+' : '−') . abs($percent) . '%';

    return [$percent > 0 ? 'up' : 'down', $label, __('Compared with the same point last month.')];
  };
  $payoutUrl = route('admin.withdraw.withdraw_request', ['language' => $defaultLang->code]);
  $eventUrl = route('admin.event_management.event', ['language' => $defaultLang->code]);
  $kpis = array_filter([
    $can('Lifetime Earning') ? [__('Gross ticket sales'), $money($current['sales']), $delta($current['sales'], $previous['sales']), 'fa-sack-dollar', route('admin.monthly_earning')] : null,
    $can('Total Profit') ? [__('Platform booking fees'), $money($current['fees']), $delta($current['fees'], $previous['fees']), 'fa-chart-line', route('admin.monthly_profit')] : null,
    $can('Event Bookings') ? [__('Tickets booked'), number_format($current['tickets']), $delta($current['tickets'], $previous['tickets']), 'fa-ticket-alt', route('admin.event.booking')] : null,
    $can('Withdraw Method') ? [__('Payouts sent'), $money($current['payouts']), $delta($current['payouts'], $previous['payouts']), 'fa-wallet', $payoutUrl] : null,
  ]);
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
  $showChart = $showSales && collect($o['series'])->contains(fn ($row) => $row['tickets'] > 0 || $row['orders'] > 0);
@endphp

@section('content')
  <div class="cd">
    <header class="cd-top">
      <div>
        <h1>{{ __('Admin dashboard') }}</h1>
        <p><time datetime="{{ $month['through']->toDateString() }}">{{ $month['through']->translatedFormat('l, j F Y') }}</time></p>
      </div>
      <div class="cd-actions">
        <a class="cd-btn" href="{{ route('account-security.show', ['guard' => 'admin']) }}"><i class="fal fa-shield-check" aria-hidden="true"></i>{{ __('Account security') }}</a>
        @if ($can('Event Bookings'))<a class="cd-btn" href="{{ route('admin.data_exports.index') }}"><i class="fal fa-file-chart-line" aria-hidden="true"></i>{{ __('Reports') }}</a>@endif
        @if ($showEvents)<a class="cd-btn primary" href="{{ route('admin.choose-event-type', ['language' => $defaultLang->code]) }}"><i class="fal fa-plus" aria-hidden="true"></i>{{ __('Create event') }}</a>@endif
      </div>
    </header>

    @if ($waiting)
      <section class="cd-card cd-review" aria-labelledby="cd-review-h">
        <div class="cd-review-head">
          <span class="cd-ic" aria-hidden="true"><i class="fal fa-clipboard-list"></i></span>
          <div>
            <h2 id="cd-review-h">{{ __('Needs attention') }}</h2>
            <p>{{ $open ? trans_choice(':count queue has work|:count queues have work', count($open)) : __('Your review queues are clear.') }}</p>
          </div>
        </div>
        @if ($open)
          <ul class="cd-review-list">
            @foreach ($open as [$label, $count, $href, $icon])
              <li>
                <a href="{{ $href }}">
                  <i class="fal {{ $icon }}" aria-hidden="true"></i>
                  <span>{{ $label }}</span>
                  <b>{{ number_format($count) }}</b>
                </a>
              </li>
            @endforeach
          </ul>
        @endif
      </section>
    @endif

    @if ($kpis)
      <section aria-labelledby="cd-period-h">
        <div class="cd-period">
          <h2 id="cd-period-h">{{ __('Month to date') }}</h2>
          <p>
            <time datetime="{{ $month['from']->toDateString() }}">{{ $month['from']->translatedFormat('j M') }}</time>
            <span aria-hidden="true">–</span><span class="sr-only"> {{ __('to') }} </span>
            <time datetime="{{ $month['through']->toDateString() }}">{{ $month['through']->translatedFormat('j M Y') }}</time>
            <span aria-hidden="true"> · </span>{{ $o['base']['code'] }}
          </p>
        </div>
        <div class="cd-kpis">
          @foreach ($kpis as [$label, $value, $change, $icon, $href])
            <a class="cd-card cd-kpi" href="{{ $href }}">
              <span class="cd-ic"><i class="fal {{ $icon }}" aria-hidden="true"></i></span>
              <span class="cd-kpi-l">{{ $label }}</span>
              <b class="cd-kpi-value">{{ $value }}</b>
              @if ($change)
                <span class="cd-chg {{ $change[0] }}"><span>{{ $change[1] }}</span><span class="sr-only">{{ $change[2] }}</span></span>
              @endif
            </a>
          @endforeach
        </div>
      </section>
    @endif

    @if ($can('Withdraw Method'))
      <section class="cd-card cd-table" aria-labelledby="cd-pay-h">
        <div class="cd-card-h">
          <h2 id="cd-pay-h">{{ __('Recent payout requests') }}</h2>
          <a class="cd-btn sm" href="{{ $payoutUrl }}">{{ __('View payouts') }}</a>
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

    @if ($showSales || $showEvents)
      <div class="cd-row {{ $showSales && $showEvents ? 'cd-row--split' : '' }}">
        @if ($showSales)
          <section class="cd-card cd-chart" aria-labelledby="cd-chart-h">
            <div class="cd-card-h">
              <h2 id="cd-chart-h">{{ __('Booking activity') }}</h2>
              <span class="cd-pill">{{ __('Last 12 months') }}</span>
            </div>
            @if ($showChart)
              <ul class="cd-legend">
                <li><i class="blue" aria-hidden="true"></i>{{ __('Tickets') }}</li>
                <li><i class="grey" aria-hidden="true"></i>{{ __('Orders') }}</li>
              </ul>
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
            @else
              <p class="cd-empty">{{ __('No completed or free bookings in the last 12 months.') }}</p>
            @endif
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
    @endif
  </div>
@endsection

@section('script')
  @if ($showChart)
    <script src="{{ asset('assets/admin/js/chart.min.js') }}"></script>
    <script>
      (function () {
        var canvas = document.getElementById('cd-sales');
        if (!window.Chart || !canvas) return;
        var series = @json($o['series']);
        var tickColor = '#526079';
        new Chart(canvas.getContext('2d'), {
          type: 'bar',
          data: {
            labels: series.map(function (month) { return month.label; }),
            datasets: [
              {label: @json(__('Tickets')), data: series.map(function (month) { return month.tickets; }), backgroundColor: '#2452C7'},
              {label: @json(__('Orders')), data: series.map(function (month) { return month.orders; }), backgroundColor: '#677C99'}
            ]
          },
          options: {
            maintainAspectRatio: false,
            animation: {duration: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 260},
            legend: {display: false},
            tooltips: {mode: 'index', intersect: false},
            scales: {
              xAxes: [{gridLines: {display: false}, ticks: {fontColor: tickColor, maxRotation: 0, autoSkipPadding: 8}}],
              yAxes: [{gridLines: {color: '#E4EAF3'}, ticks: {beginAtZero: true, precision: 0, maxTicksLimit: 5, fontColor: tickColor}}]
            }
          }
        });
      })();
    </script>
  @endif
@endsection
