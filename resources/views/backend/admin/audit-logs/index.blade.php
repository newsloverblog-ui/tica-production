@extends('backend.layout')

@section('content')
  <div class="page-header">
    <h4 class="page-title">{{ __('Audit history') }}</h4>
    <ul class="breadcrumbs"><li class="nav-home"><a href="{{ route('admin.dashboard') }}"><i class="flaticon-home"></i></a></li><li class="separator"><i class="flaticon-right-arrow"></i></li><li class="nav-item"><a href="{{ route('admin.transcation') }}">{{ __('Transactions') }}</a></li><li class="separator"><i class="flaticon-right-arrow"></i></li><li class="nav-item"><a href="#">{{ __('Audit history') }}</a></li></ul>
  </div>

  <div class="card mb-4"><div class="card-header"><div class="card-title">{{ __('Filter and export evidence') }}</div></div><div class="card-body">
    <form method="GET" class="row align-items-end">
      <div class="col-md-2 form-group"><label>{{ __('From') }}</label><input type="date" name="from_date" value="{{ $filters['from_date'] ?? '' }}" class="form-control"></div>
      <div class="col-md-2 form-group"><label>{{ __('To') }}</label><input type="date" name="to_date" value="{{ $filters['to_date'] ?? '' }}" class="form-control"></div>
      <div class="col-md-3 form-group"><label>{{ __('Action') }}</label><input type="text" name="action" value="{{ $filters['action'] ?? '' }}" class="form-control" placeholder="configuration.changed"></div>
      <div class="col-md-2 form-group"><label>{{ __('Actor') }}</label><select name="actor_type" class="form-control"><option value="">{{ __('All actors') }}</option>@foreach(['admin','organizer','scanner','customer','web','system'] as $type)<option value="{{ $type }}" @selected(($filters['actor_type'] ?? '') === $type)>{{ ucfirst($type) }}</option>@endforeach</select></div>
      <div class="col-md-3 form-group"><button class="btn btn-primary" type="submit">{{ __('Apply filters') }}</button> <a class="btn btn-link" href="{{ route('admin.audit_logs.index') }}">{{ __('Clear') }}</a></div>
    </form>
    <form method="POST" action="{{ route('admin.audit_logs.export') }}" class="d-flex align-items-center mt-2">
      @csrf
      @foreach(['from_date','to_date','action','actor_type'] as $key)<input type="hidden" name="{{ $key }}" value="{{ $filters[$key] ?? '' }}">@endforeach
      <select name="format" class="form-control form-control-sm mr-2" style="max-width: 120px"><option value="csv">CSV</option><option value="xlsx">XLSX</option></select>
      <button class="btn btn-outline-primary btn-sm" type="submit">{{ __('Generate private export') }}</button>
      <small class="text-muted ml-3">{{ __('Exports are encrypted at rest, actor-owned and expire automatically.') }}</small>
    </form>
  </div></div>

  <div class="card mb-4"><div class="card-header"><div class="card-title">{{ __('Recent audit exports') }}</div></div><div class="card-body p-0"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>{{ __('Requested') }}</th><th>{{ __('Rows') }}</th><th>{{ __('Status') }}</th><th>{{ __('Expires') }}</th><th></th></tr></thead><tbody>
    @forelse($exports as $export)
      <tr><td>{{ $export->created_at->format('Y-m-d H:i') }} · {{ strtoupper($export->format) }}</td><td>{{ number_format($export->row_count) }}</td><td>{{ ucfirst($export->status) }}</td><td>{{ optional($export->expires_at)->format('Y-m-d H:i') ?: '—' }}</td><td>@if($export->isDownloadable())<a class="btn btn-sm btn-primary" href="{{ URL::temporarySignedRoute('admin.audit_logs.download', now()->addMinutes(config('exports.download_link_minutes', 15)), ['export' => $export]) }}">{{ __('Download') }}</a>@else<span class="text-muted">—</span>@endif</td></tr>
    @empty<tr><td colspan="5" class="text-center text-muted py-3">{{ __('No audit exports requested.') }}</td></tr>@endforelse
  </tbody></table></div></div></div>

  <div class="card"><div class="card-header"><div class="card-title">{{ __('Append-only activity') }}</div></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-striped mb-0"><thead><tr><th>{{ __('Time') }}</th><th>{{ __('Actor') }}</th><th>{{ __('Action') }}</th><th>{{ __('Subject') }}</th><th>{{ __('Request') }}</th></tr></thead><tbody>
    @forelse($logs as $log)
      <tr><td>{{ optional($log->created_at)->format('Y-m-d H:i:s') }}</td><td>{{ $log->actor_type }}{{ $log->actor_id ? ' #'.$log->actor_id : '' }}</td><td><code>{{ $log->action }}</code></td><td>{{ $log->subject_type ? class_basename($log->subject_type).' #'.$log->subject_id : '—' }}</td><td><small>{{ $log->request_id ?: '—' }}</small></td></tr>
    @empty<tr><td colspan="5" class="text-center text-muted py-4">{{ __('No audit records match these filters.') }}</td></tr>@endforelse
  </tbody></table></div></div><div class="card-footer">{{ $logs->links() }}</div></div>
@endsection
