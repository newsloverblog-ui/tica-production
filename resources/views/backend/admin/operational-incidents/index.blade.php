@extends('backend.layout')

@section('content')
  <div class="page-header">
    <h4 class="page-title">{{ __('Operational incidents') }}</h4>
    <ul class="breadcrumbs">
      <li class="nav-home"><a href="{{ route('admin.dashboard') }}"><i class="flaticon-home"></i></a></li>
      <li class="separator"><i class="flaticon-right-arrow"></i></li>
      <li class="nav-item"><a href="{{ route('admin.transcation') }}">{{ __('Transactions') }}</a></li>
      <li class="separator"><i class="flaticon-right-arrow"></i></li>
      <li class="nav-item"><a href="#">{{ __('Operational incidents') }}</a></li>
    </ul>
  </div>

  <div class="card mb-4">
    <div class="card-header d-flex align-items-center justify-content-between flex-wrap">
      <div>
        <div class="card-title">{{ __('Incident history') }}</div>
        <small class="text-muted">{{ __('Repeated failures are grouped. Resolving an incident does not suppress a future occurrence.') }}</small>
        <div class="mt-2"><a href="{{ route('admin.webhook_operations.index') }}" class="btn btn-sm btn-secondary">{{ __('Webhook ledger') }}</a><a href="{{ route('admin.operations_exports.index') }}" class="btn btn-sm btn-outline-secondary ml-2">{{ __('Export history') }}</a></div>
      </div>
      <form method="get" class="form-inline mt-2 mt-lg-0">
        <select name="status" class="form-control mr-2">
          <option value="">{{ __('All statuses') }}</option>
          <option value="open" @selected(request('status') === 'open')>{{ __('Open') }}</option>
          <option value="resolved" @selected(request('status') === 'resolved')>{{ __('Resolved') }}</option>
        </select>
        <select name="severity" class="form-control mr-2">
          <option value="">{{ __('All severities') }}</option>
          @foreach (['critical', 'error', 'warning'] as $severity)
            <option value="{{ $severity }}" @selected(request('severity') === $severity)>{{ __(ucfirst($severity)) }}</option>
          @endforeach
        </select>
        <button class="btn btn-primary" type="submit">{{ __('Filter') }}</button>
      </form>
    </div>
    <div class="card-body">
      <form method="post" action="{{ route('admin.operational_incidents.export') }}" class="form-inline justify-content-end mb-3">
        @csrf<input type="hidden" name="status" value="{{ request('status') }}"><input type="hidden" name="severity" value="{{ request('severity') }}">
        <label class="mr-2" for="incident-export-format">{{ __('Export current results') }}</label><select id="incident-export-format" name="format" class="form-control mr-2" required><option value="csv">CSV</option><option value="xlsx">XLSX</option></select><button class="btn btn-primary" type="submit">{{ __('Queue private export') }}</button>
      </form>
      <div class="table-responsive">
        <table class="table table-striped mb-0">
          <thead><tr><th>{{ __('Incident') }}</th><th>{{ __('Severity') }}</th><th>{{ __('Occurrences') }}</th><th>{{ __('Last seen') }}</th><th>{{ __('Delivery') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
          <tbody>
          @forelse ($incidents as $incident)
            <tr>
              <td><strong>{{ $incident->title }}</strong><br><small class="text-muted">{{ $incident->incident_key }} · {{ $incident->public_id }}</small></td>
              <td><span class="badge badge-{{ $incident->severity === 'critical' ? 'danger' : ($incident->severity === 'error' ? 'warning' : 'info') }}">{{ strtoupper($incident->severity) }}</span></td>
              <td>{{ number_format($incident->occurrence_count) }}</td>
              <td>{{ optional($incident->last_seen_at)->format('Y-m-d H:i:s') }}</td>
              <td>
                @forelse (($incident->delivery_status ?: []) as $destination => $status)
                  <span class="badge badge-{{ $status === 'sent' ? 'success' : 'danger' }}">{{ $destination }}: {{ $status }}</span>
                @empty
                  <span class="text-muted">{{ __('Log only') }}</span>
                @endforelse
              </td>
              <td>
                <span class="badge badge-{{ $incident->status === 'open' ? 'danger' : 'success' }}">{{ __(ucfirst($incident->status)) }}</span>
                @if ($incident->resolved_at)<br><small>{{ $incident->resolved_at->format('Y-m-d H:i') }}</small>@endif
              </td>
              <td style="min-width: 230px">
                @if ($incident->status === 'open')
                  <form method="post" action="{{ route('admin.operational_incidents.resolve', $incident) }}" class="d-flex">
                    @csrf
                    <input class="form-control form-control-sm mr-1" name="note" required minlength="3" maxlength="1000" placeholder="{{ __('Resolution note') }}">
                    <button class="btn btn-success btn-sm" type="submit">{{ __('Resolve') }}</button>
                  </form>
                @else
                  <small class="text-muted">{{ $incident->resolution_note }}</small>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="text-center py-4">{{ __('No operational incidents recorded.') }}</td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </div>
    @if ($incidents->hasPages())<div class="card-footer">{{ $incidents->links() }}</div>@endif
  </div>
@endsection
