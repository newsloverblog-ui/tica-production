@extends('organizer.layout')

@section('content')
  <section class="tica-workspace-hero" aria-labelledby="organizer-dashboard-title">
    <div>
      <p class="tica-eyebrow">{{ __('Organizer workspace') }}</p>
      <h1 id="organizer-dashboard-title">{{ __('Welcome back,') }} {{ Auth::guard('organizer')->user()->username }}.</h1>
      <p class="tica-lede">{{ __('Create experiences, track ticket sales and manage guests from one clear workspace.') }}</p>
    </div>
    <div class="tica-quick-actions" aria-label="{{ __('Quick actions') }}">
      <a class="btn btn-primary" href="{{ route('choose-event-type', ['language' => $defaultLang->code]) }}">
        <i class="fal fa-plus mr-2" aria-hidden="true"></i>{{ __('Create event') }}
      </a>
      <a class="btn btn-outline-primary" href="{{ route('organizer.event.booking') }}">
        <i class="fal fa-ticket-alt mr-2" aria-hidden="true"></i>{{ __('View bookings') }}
      </a>
      <a class="btn btn-outline-primary" href="{{ route('organizer.finance.index') }}">
        <i class="fal fa-file-invoice-dollar mr-2" aria-hidden="true"></i>{{ __('Finance') }}
      </a>
    </div>
  </section>

  <section class="tica-priority-grid mb-4" aria-label="{{ __('Today and account priorities') }}">
    <article class="tica-priority-card">
      <span class="tica-priority-icon is-blue"><i class="fal fa-chart-line" aria-hidden="true"></i></span>
      <div><p>{{ __('Sales today') }}</p><strong>{{ $settings->base_currency_symbol_position == 'left' ? $settings->base_currency_symbol : '' }}{{ number_format($today_sales, 2) }}{{ $settings->base_currency_symbol_position == 'right' ? ' '.$settings->base_currency_symbol : '' }}</strong><small>{{ trans_choice(':count completed booking|:count completed bookings', $today_bookings, ['count' => $today_bookings]) }}</small></div>
    </article>
    <a class="tica-priority-card" href="{{ route('organizer.event_management.event', ['language' => $defaultLang->code]) }}">
      <span class="tica-priority-icon is-blue"><i class="fal fa-calendar-alt" aria-hidden="true"></i></span>
      <div><p>{{ __('Published events') }}</p><strong>{{ $total_events }}</strong><small>{{ __('Manage availability and tickets') }}</small></div>
    </a>
    <a class="tica-priority-card" href="{{ route('organizer.event.booking') }}">
      <span class="tica-priority-icon is-success"><i class="fal fa-ticket-alt" aria-hidden="true"></i></span>
      <div><p>{{ __('All bookings') }}</p><strong>{{ $total_event_bookings }}</strong><small>{{ __('Review guest and payment status') }}</small></div>
    </a>
    <a class="tica-priority-card" href="{{ route('organizer.withdraw') }}">
      <span class="tica-priority-icon is-orange"><i class="fal fa-wallet" aria-hidden="true"></i></span>
      <div><p>{{ __('Pending payouts') }}</p><strong>{{ $pending_payouts }}</strong><small>{{ __('Pending or held requests') }}</small></div>
    </a>
  </section>

  @if (Session::get('secret_login') != true)
    @if (Auth::guard('organizer')->user()->status == 0 && $admin_setting->organizer_admin_approval == 1)
      <div class="mt-2 mb-4">
        <div class="alert alert-danger text-dark">
          {{ $admin_setting->admin_approval_notice != null ? $admin_setting->admin_approval_notice : __( 'Your account is deactive') }}
        </div>
      </div>
    @endif
  @endif

  <div class="d-flex flex-wrap align-items-end justify-content-between mb-3">
    <div>
      <p class="tica-eyebrow mb-1">{{ __('Overview') }}</p>
      <h2 class="h4 mb-0">{{ __('Your business at a glance') }}</h2>
    </div>
    <a class="btn btn-link px-0" href="{{ route('organizer.data_exports.index') }}">{{ __('Manage exports') }} <i class="fal fa-arrow-right ml-1" aria-hidden="true"></i></a>
  </div>

  <div class="row dashboard-items">
    <div class="col-xl-3 col-lg-6">
      <a href="{{ route('organizer.monthly_income') }}">
        <div class="card card-stats card-info card-round">
          <div class="card-body">
            <div class="row">
              <div class="col-5">
                <div class="icon-big text-center">
                  <i class="fas fa-sack-dollar"></i>
                </div>
              </div>

              <div class="col-7 col-stats">
                <div class="numbers">
                  <p class="card-category">{{ __('My Balance') }}</p>
                  <h4 class="card-title">
                    {{ $settings->base_currency_symbol_position == 'left' ? $settings->base_currency_symbol : '' }}
                    {{ round(Auth::guard('organizer')->user()->amount, 2) }}
                    {{ $settings->base_currency_symbol_position == 'right' ? $settings->base_currency_symbol : '' }}
                  </h4>
                </div>
              </div>
            </div>
          </div>
        </div>
      </a>
    </div>
    <div class="col-xl-3 col-lg-6">
      <a href="{{ route('organizer.event_management.event', ['language' => $defaultLang->code]) }}">
        <div class="card card-stats card-success card-round">
          <div class="card-body">
            <div class="row">
              <div class="col-5">
                <div class="icon-big text-center">
                  <i class="fas fa-calendar-alt"></i>
                </div>
              </div>

              <div class="col-7 col-stats">
                <div class="numbers">
                  <p class="card-category">{{ __('Events') }}</p>
                  <h4 class="card-title">{{ $total_events }}</h4>
                </div>
              </div>
            </div>
          </div>
        </div>
      </a>
    </div>
    <div class="col-xl-3 col-lg-6">
      <a href="{{ route('organizer.event.booking') }}">
        <div class="card card-stats card-danger card-round">
          <div class="card-body">
            <div class="row">
              <div class="col-5">
                <div class="icon-big text-center">
                  <i class="fas fa-presentation"></i>
                </div>
              </div>
              <div class="col-7 col-stats">
                <div class="numbers">
                  <p class="card-category">{{ __('Total Event Bookings') }}</p>
                  <h4 class="card-title">{{ $total_event_bookings }}</h4>
                </div>
              </div>
            </div>
          </div>
        </div>
      </a>
    </div>
    <div class="col-xl-3 col-lg-6">
      <a href="{{ route('organizer.transcation') }}">
        <div class="card card-stats card-secondary card-round">
          <div class="card-body">
            <div class="row">
              <div class="col-5">
                <div class="icon-big text-center">
                  <i class="fal fa-exchange-alt"></i>
                </div>
              </div>

              <div class="col-7 col-stats">
                <div class="numbers">
                  <p class="card-category">{{ __('Total Transcation') }}</p>
                  <h4 class="card-title">{{ $transcation_count }}
                  </h4>
                </div>
              </div>
            </div>
          </div>
        </div>
      </a>
    </div>
    <div class="col-lg-6">
      <div class="card">
        <div class="card-header">
          <div class="card-title">{{ __('Event Booking Monthly Income') }} ({{ date('Y') }})</div>
        </div>

        <div class="card-body">
          <div class="chart-container">
            <canvas id="incomeChart" role="img" aria-describedby="organizer-income-chart-summary"></canvas>
            <p class="sr-only" id="organizer-income-chart-summary">{{ __('Monthly event income for :year:', ['year' => date('Y')]) }} {{ implode(', ', array_map(fn ($month, $value) => $month . ' ' . number_format((float) $value, 2), $eventMonths, $eventIncomes)) }}</p>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="card">
        <div class="card-header">
          <div class="card-title">{{ __('Monthly Event Bookings') }} ({{ date('Y') }})</div>
        </div>

        <div class="card-body">
          <div class="chart-container">
            <canvas id="TotalEventBookingChart" role="img" aria-describedby="organizer-bookings-chart-summary"></canvas>
            <p class="sr-only" id="organizer-bookings-chart-summary">{{ __('Monthly completed bookings for :year:', ['year' => date('Y')]) }} {{ implode(', ', array_map(fn ($month, $value) => $month . ' ' . $value, $eventMonths, $totalBookings)) }}</p>
          </div>
        </div>
      </div>
    </div>

  </div>
@endsection

@section('script')
  {{-- chart js --}}
  <script type="text/javascript" src="{{ asset('assets/admin/js/chart.min.js') }}"></script>

  <script>
    "use strict";
    const monthArr = @php echo json_encode($eventMonths) @endphp;
    const incomeArr = @php echo json_encode($eventIncomes) @endphp;
    const totalBookings = @php echo json_encode($totalBookings) @endphp;
  </script>

  <script type="text/javascript" src="{{ asset('assets/admin/js/chart-init.js') }}"></script>
@endsection
