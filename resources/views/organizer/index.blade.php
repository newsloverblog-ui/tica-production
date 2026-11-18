@extends('organizer.layout')

@section('page-title', __('Overview'))

@section('content')
  @php
    $currencyBefore = $settings->base_currency_symbol_position == 'left' ? $settings->base_currency_symbol : '';
    $currencyAfter = $settings->base_currency_symbol_position == 'right' ? ' '.$settings->base_currency_symbol : '';
    $metrics = [
      ['label' => __('Sales today'), 'value' => $currencyBefore.number_format($today_sales, 2).$currencyAfter, 'icon' => 'fa-chart-line', 'url' => route('organizer.monthly_income')],
      ['label' => __('Published events'), 'value' => number_format($total_events), 'icon' => 'fa-calendar-alt', 'url' => route('organizer.event_management.event', ['language' => $defaultLang->code])],
      ['label' => __('Bookings'), 'value' => number_format($total_event_bookings), 'icon' => 'fa-ticket-alt', 'url' => route('organizer.event.booking')],
      ['label' => __('Pending payouts'), 'value' => number_format($pending_payouts), 'icon' => 'fa-wallet', 'url' => route('organizer.withdraw')],
    ];
  @endphp

  <header class="org-overview-head">
    <div>
      <h1 id="organizer-dashboard-title">{{ __('Welcome back, :name', ['name' => Auth::guard('organizer')->user()->username]) }}</h1>
      <p>{{ __('Here is how your events are performing today.') }}</p>
    </div>
    <div class="org-overview-actions" aria-label="{{ __('Quick actions') }}">
      <a class="btn btn-primary" href="{{ route('choose-event-type', ['language' => $defaultLang->code]) }}"><i class="fal fa-plus mr-2" aria-hidden="true"></i>{{ __('Create event') }}</a>
      <a class="btn btn-outline-primary" href="{{ route('organizer.event.booking') }}">{{ __('Bookings') }}</a>
      <a class="btn btn-outline-primary" href="{{ route('organizer.finance.index') }}">{{ __('Finance') }}</a>
    </div>
  </header>

  @if (Session::get('secret_login') != true && Auth::guard('organizer')->user()->status == 0 && $admin_setting->organizer_admin_approval == 1)
    <div class="alert alert-danger text-dark mb-4" role="alert">
      {{ $admin_setting->admin_approval_notice ?: __('Your account is inactive.') }}
    </div>
  @endif

  <section class="org-metric-grid" aria-label="{{ __('Account overview') }}">
    @foreach ($metrics as $metric)
      <a class="org-metric" href="{{ $metric['url'] }}">
        <span class="org-metric__icon"><i class="fal {{ $metric['icon'] }}" aria-hidden="true"></i></span>
        <span><small>{{ $metric['label'] }}</small><strong>{{ $metric['value'] }}</strong></span>
      </a>
    @endforeach
  </section>

  <section class="org-analytics-grid">
    <article class="card org-chart-card">
      <header class="org-card-head"><h2>{{ __('Revenue by month') }}</h2><a href="{{ route('organizer.monthly_income') }}">{{ __('View report') }}</a></header>
      <div class="card-body">
        <div class="chart-container">
          <canvas id="incomeChart" role="img" aria-describedby="organizer-income-chart-summary"></canvas>
          <p class="sr-only" id="organizer-income-chart-summary">{{ __('Monthly event income for :year:', ['year' => date('Y')]) }} {{ implode(', ', array_map(fn ($month, $value) => $month . ' ' . number_format((float) $value, 2), $eventMonths, $eventIncomes)) }}</p>
        </div>
      </div>
    </article>

    <article class="card org-chart-card">
      <header class="org-card-head"><h2>{{ __('Bookings by month') }}</h2><a href="{{ route('organizer.event.booking') }}">{{ __('View bookings') }}</a></header>
      <div class="card-body">
        <div class="chart-container">
          <canvas id="TotalEventBookingChart" role="img" aria-describedby="organizer-bookings-chart-summary"></canvas>
          <p class="sr-only" id="organizer-bookings-chart-summary">{{ __('Monthly completed bookings for :year:', ['year' => date('Y')]) }} {{ implode(', ', array_map(fn ($month, $value) => $month . ' ' . $value, $eventMonths, $totalBookings)) }}</p>
        </div>
      </div>
    </article>
  </section>
@endsection

@section('script')
  <script src="{{ asset('assets/admin/js/chart.min.js') }}"></script>
  <script>
    "use strict";
    const monthArr = @json($eventMonths);
    const incomeArr = @json($eventIncomes);
    const totalBookings = @json($totalBookings);
  </script>
  <script src="{{ asset('assets/admin/js/chart-init.js') }}"></script>
@endsection
