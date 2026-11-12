@extends('backend.layout')

@section('content')
  <div class="page-header">
    <h4 class="page-title">{{ __('Export history') }}</h4>
    <ul class="breadcrumbs">
      <li class="nav-home"><a href="{{ route('admin.dashboard') }}"><i class="flaticon-home"></i></a></li>
      <li class="separator"><i class="flaticon-right-arrow"></i></li>
      <li class="nav-item"><a href="{{ route('admin.event.booking') }}">{{ __('Event bookings') }}</a></li>
      <li class="separator"><i class="flaticon-right-arrow"></i></li>
      <li class="nav-item"><a href="#">{{ __('Export history') }}</a></li>
    </ul>
  </div>

  <div class="card mb-4">
    <div class="card-header"><div class="card-title">{{ __('Recurring booking exports') }}</div></div>
    <div class="card-body">
      <p class="text-muted">{{ __('Generate private, owner-only exports on a rolling date window. Files follow the same expiry and download controls as one-time exports.') }}</p>
      <form method="POST" action="{{ route('admin.data_export_schedules.store') }}" class="row align-items-end">
        @csrf
        <div class="col-md-2 form-group"><label>{{ __('Frequency') }}</label><select name="frequency" class="form-control" required><option value="daily">{{ __('Daily') }}</option><option value="weekly">{{ __('Weekly') }}</option><option value="monthly">{{ __('Monthly') }}</option></select></div>
        <div class="col-md-2 form-group"><label>{{ __('Format') }}</label><select name="format" class="form-control" required><option value="csv">CSV</option><option value="xlsx">XLSX</option><option value="pdf">PDF</option></select></div>
        <div class="col-md-2 form-group"><label>{{ __('Rolling days') }}</label><input type="number" name="date_window_days" min="1" max="365" value="7" class="form-control" required></div>
        <div class="col-md-3 form-group"><label>{{ __('First run') }}</label><input type="datetime-local" name="starts_at" value="{{ now()->addHour()->format('Y-m-d\TH:i') }}" class="form-control" required><input type="hidden" name="timezone" value="{{ config('app.timezone', 'UTC') }}"></div>
        <div class="col-md-3 form-group"><button class="btn btn-primary btn-block" type="submit">{{ __('Create schedule') }}</button></div>
      </form>
      <div class="table-responsive mt-3"><table class="table table-sm"><thead><tr><th>{{ __('Cadence') }}</th><th>{{ __('Window') }}</th><th>{{ __('Next run') }}</th><th>{{ __('Last result') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead><tbody>
      @forelse ($schedules as $schedule)
        <tr><td>{{ ucfirst($schedule->frequency) }} · {{ strtoupper($schedule->format) }}</td><td>{{ trans_choice(':count day|:count days', $schedule->date_window_days, ['count' => $schedule->date_window_days]) }}</td><td>{{ optional($schedule->next_run_at)->timezone($schedule->timezone)->format('Y-m-d H:i T') }}</td><td>@if($schedule->last_error)<span class="text-danger">{{ Str::limit($schedule->last_error, 80) }}</span>@elseif($schedule->last_run_at){{ __('Queued successfully') }}@else—@endif</td><td><span class="badge badge-{{ $schedule->active ? 'success' : 'secondary' }}">{{ $schedule->active ? __('Active') : __('Paused') }}</span></td><td><form method="POST" action="{{ route('admin.data_export_schedules.toggle', $schedule) }}">@csrf<button class="btn btn-sm btn-outline-secondary">{{ $schedule->active ? __('Pause') : __('Resume') }}</button></form></td></tr>
      @empty<tr><td colspan="6" class="text-center text-muted">{{ __('No recurring exports configured.') }}</td></tr>@endforelse
      </tbody></table></div>
    </div>
  </div>

  <div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
      <div><div class="card-title">{{ __('Private booking exports') }}</div><small class="text-muted">{{ __('Files are generated in the background and permanently removed after expiry.') }}</small></div>
      <a href="{{ route('admin.event.booking') }}" class="btn btn-primary btn-sm">{{ __('Create export') }}</a>
    </div>
    <div class="card-body">
      <div class="alert alert-info">{{ __('Refresh this page while an export is queued or processing. Download links are signed, short-lived and restricted to the administrator who requested the file.') }}</div>
      <div class="table-responsive">
        <table class="table table-striped">
          <thead><tr><th>{{ __('Requested') }}</th><th>{{ __('Type') }}</th><th>{{ __('Rows') }}</th><th>{{ __('Status') }}</th><th>{{ __('Expires') }}</th><th>{{ __('Attempts') }}</th><th></th></tr></thead>
          <tbody>
          @forelse ($exports as $export)
            @php
              $badge = ['ready' => 'success', 'failed' => 'danger', 'expired' => 'secondary', 'processing' => 'warning', 'queued' => 'info'][$export->status] ?? 'secondary';
            @endphp
            <tr>
              <td>{{ $export->created_at->format('Y-m-d H:i') }}<br><small class="text-muted">{{ $export->filename }}</small></td>
              <td>{{ $export->export_type === 'product_orders' ? __('Product orders') : __(ucwords(str_replace('_', ' ', $export->export_type))) }} · {{ strtoupper($export->format) }}</td>
              <td>{{ number_format($export->row_count) }}</td>
              <td><span class="badge badge-{{ $badge }}">{{ __(ucfirst($export->status)) }}</span>@if ($export->last_error)<br><small class="text-danger">{{ Str::limit($export->last_error, 100) }}</small>@endif</td>
              <td>{{ optional($export->expires_at)->format('Y-m-d H:i') ?: '—' }}</td>
              <td>{{ $export->attempts }}</td>
              <td>
                @if ($export->isDownloadable())
                  <a class="btn btn-sm btn-primary" href="{{ URL::temporarySignedRoute('admin.data_exports.download', now()->addMinutes(config('exports.download_link_minutes', 15)), ['export' => $export]) }}">{{ __('Download') }}</a>
                @else
                  <span class="text-muted">—</span>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="text-center py-4">{{ __('No exports requested yet.') }}</td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
      {{ $exports->links() }}
    </div>
  </div>
@endsection
