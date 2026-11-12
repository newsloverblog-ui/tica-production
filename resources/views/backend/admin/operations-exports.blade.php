@extends('backend.layout')

@section('content')
  <div class="page-header"><h4 class="page-title">{{ __('Operations export history') }}</h4><ul class="breadcrumbs"><li class="nav-home"><a href="{{ route('admin.dashboard') }}"><i class="flaticon-home"></i></a></li><li class="separator"><i class="flaticon-right-arrow"></i></li><li class="nav-item"><a href="{{ route('admin.operational_incidents.index') }}">{{ __('Operational incidents') }}</a></li><li class="separator"><i class="flaticon-right-arrow"></i></li><li class="nav-item"><a href="#">{{ __('Export history') }}</a></li></ul></div>
  <div class="card">
    <div class="card-header d-flex align-items-center justify-content-between flex-wrap">
      <div><div class="card-title">{{ __('Private operations evidence') }}</div><small class="text-muted">{{ __('Sensitive incident context and provider identifiers are excluded. Files expire and remain restricted to your administrator account.') }}</small></div>
      <div><a href="{{ route('admin.operational_incidents.index') }}" class="btn btn-primary btn-sm">{{ __('Incidents') }}</a><a href="{{ route('admin.webhook_operations.index') }}" class="btn btn-secondary btn-sm ml-2">{{ __('Webhook ledger') }}</a></div>
    </div>
    <div class="card-body">
      <div class="alert alert-info">{{ __('Refresh while an export is queued or processing. Downloads use short-lived signed links and are not browser-cacheable.') }}</div>
      <div class="table-responsive"><table class="table table-striped"><thead><tr><th>{{ __('Requested') }}</th><th>{{ __('Dataset') }}</th><th>{{ __('Format') }}</th><th>{{ __('Rows') }}</th><th>{{ __('Status') }}</th><th>{{ __('Expires') }}</th><th></th></tr></thead><tbody>
      @forelse($exports as $export)
        @php($badge = ['ready'=>'success','failed'=>'danger','expired'=>'secondary','processing'=>'warning','queued'=>'info'][$export->status] ?? 'secondary')
        <tr><td>{{ $export->created_at->format('Y-m-d H:i') }}<br><small class="text-muted">{{ $export->filename }}</small></td><td>{{ $export->export_type === 'webhook_receipts' ? __('Webhook receipts') : __('Operational incidents') }}</td><td>{{ strtoupper($export->format) }}</td><td>{{ number_format($export->row_count) }}</td><td><span class="badge badge-{{ $badge }}">{{ __(ucfirst($export->status)) }}</span>@if($export->last_error)<br><small class="text-danger">{{ Str::limit($export->last_error,100) }}</small>@endif</td><td>{{ optional($export->expires_at)->format('Y-m-d H:i') ?: '—' }}</td><td>@if($export->isDownloadable())<a class="btn btn-sm btn-primary" href="{{ URL::temporarySignedRoute('admin.operations_exports.download', now()->addMinutes(config('exports.download_link_minutes',15)), ['export'=>$export]) }}">{{ __('Download') }}</a>@else<span class="text-muted">—</span>@endif</td></tr>
      @empty<tr><td colspan="7" class="text-center py-4">{{ __('No operations exports requested yet.') }}</td></tr>@endforelse
      </tbody></table></div>{{ $exports->links() }}
    </div>
  </div>
@endsection
