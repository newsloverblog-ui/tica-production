@extends('backend.layout')

@section('content')
  <div class="page-header">
    <h4 class="page-title">{{ __('Conversion analytics') }}</h4>
    <ul class="breadcrumbs">
      <li class="nav-home"><a href="{{ route('admin.dashboard') }}"><i class="flaticon-home"></i></a></li>
      <li class="separator"><i class="flaticon-right-arrow"></i></li>
      <li class="nav-item"><a href="{{ route('admin.transcation') }}">{{ __('Transactions') }}</a></li>
      <li class="separator"><i class="flaticon-right-arrow"></i></li>
      <li class="nav-item"><a href="#">{{ __('Conversion analytics') }}</a></li>
    </ul>
  </div>

  <div class="card mb-4">
    <div class="card-header">
      <div class="row align-items-center">
        <div class="col-lg-5">
          <div class="card-title">{{ __('Measured customer funnel') }}</div>
          <small class="text-muted">{{ __('Operational events are first-party, data-minimised and exclude raw search text and customer contact details.') }}</small>
        </div>
        <div class="col-lg-7">
          <form method="get" class="form-inline justify-content-lg-end">
            <input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control mr-2 mb-2" aria-label="{{ __('From date') }}">
            <input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control mr-2 mb-2" aria-label="{{ __('To date') }}">
            <input type="number" min="1" name="event_id" value="{{ request('event_id') }}" placeholder="{{ __('Event ID') }}" class="form-control mr-2 mb-2">
            <button class="btn btn-primary mb-2" type="submit">{{ __('Apply') }}</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    @foreach ($steps as $step)
      <div class="col-6 col-lg-3 mb-4">
        <div class="card h-100">
          <div class="card-body">
            <small class="text-muted text-uppercase">{{ __($step['label']) }}</small>
            <h3 class="mb-1">{{ number_format($step['count']) }}</h3>
            @if ($step['step_rate'] !== null)
              <span class="badge badge-{{ $step['step_rate'] >= 50 ? 'success' : ($step['step_rate'] >= 20 ? 'warning' : 'danger') }}">
                {{ $step['step_rate'] }}% {{ __('from prior step') }}
              </span>
            @else
              <small class="text-muted">{{ __('Entry step') }}</small>
            @endif
          </div>
        </div>
      </div>
    @endforeach
  </div>

  <div class="row">
    <div class="col-lg-8 mb-4">
      <div class="card h-100">
        <div class="card-header"><div class="card-title">{{ __('Event performance') }}</div></div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-striped mb-0">
              <thead><tr><th>{{ __('Event') }}</th><th>{{ __('Views') }}</th><th>{{ __('Checkouts') }}</th><th>{{ __('Paid') }}</th><th>{{ __('View → paid') }}</th></tr></thead>
              <tbody>
                @forelse ($topEvents as $event)
                  <tr>
                    <td><strong>{{ $titles[$event->event_id] ?? __('Event #:id', ['id' => $event->event_id]) }}</strong><br><small class="text-muted">#{{ $event->event_id }}</small></td>
                    <td>{{ number_format($event->views) }}</td>
                    <td>{{ number_format($event->checkouts) }}</td>
                    <td>{{ number_format($event->purchases) }}</td>
                    <td>{{ $event->views > 0 ? round(($event->purchases / $event->views) * 100, 1) . '%' : '—' }}</td>
                  </tr>
                @empty
                  <tr><td colspan="5" class="text-center py-4">{{ __('No analytics events were recorded in this period.') }}</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
    <div class="col-lg-4 mb-4">
      <div class="card h-100">
        <div class="card-header"><div class="card-title">{{ __('Quality and supporting events') }}</div></div>
        <div class="card-body">
          @foreach (['search', 'payment_failed', 'ticket_delivered', 'ticket_scanned'] as $name)
            <div class="d-flex justify-content-between align-items-center py-3 border-bottom">
              <div>
                <strong>{{ __(str_replace('_', ' ', ucfirst($name))) }}</strong>
                @if (($uniqueSessions[$name] ?? 0) > 0)
                  <br><small class="text-muted">{{ number_format($uniqueSessions[$name]) }} {{ __('unique sessions') }}</small>
                @endif
              </div>
              <h5 class="mb-0">{{ number_format($counts[$name] ?? 0) }}</h5>
            </div>
          @endforeach
        </div>
      </div>
    </div>
  </div>
@endsection
