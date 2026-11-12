@extends('backend.layout')

@section('content')
  <div class="page-header">
    <h4 class="page-title">{{ __('Reconciliation export history') }}</h4>
    <ul class="breadcrumbs">
      <li class="nav-home"><a href="{{ route('admin.dashboard') }}"><i class="flaticon-home"></i></a></li>
      <li class="separator"><i class="flaticon-right-arrow"></i></li>
      <li class="nav-item"><a href="{{ route('admin.payment_reconciliation.index') }}">{{ __('Payment reconciliation') }}</a></li>
      <li class="separator"><i class="flaticon-right-arrow"></i></li>
      <li class="nav-item"><a href="#">{{ __('Export history') }}</a></li>
    </ul>
  </div>

  <div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
      <div>
        <div class="card-title">{{ __('Private reconciliation exports') }}</div>
        <small class="text-muted">{{ __('Point-in-time finance evidence is restricted to your administrator account and permanently removed after expiry.') }}</small>
      </div>
      <div>
        <a href="{{ route('admin.payment_reconciliation.index') }}" class="btn btn-primary btn-sm">{{ __('Payment runs') }}</a>
        <a href="{{ route('admin.settlement_reconciliation.index') }}" class="btn btn-secondary btn-sm">{{ __('Bank settlements') }}</a>
      </div>
    </div>
    <div class="card-body">
      <div class="alert alert-info">{{ __('Refresh while an export is queued or processing. Downloads use short-lived signed links and are not browser-cacheable.') }}</div>
      <div class="table-responsive">
        <table class="table table-striped">
          <thead><tr><th>{{ __('Requested') }}</th><th>{{ __('Dataset') }}</th><th>{{ __('Format') }}</th><th>{{ __('Rows') }}</th><th>{{ __('Status') }}</th><th>{{ __('Expires') }}</th><th></th></tr></thead>
          <tbody>
          @forelse($exports as $export)
            @php($badge = ['ready'=>'success','failed'=>'danger','expired'=>'secondary','processing'=>'warning','queued'=>'info'][$export->status] ?? 'secondary')
            <tr>
              <td>{{ $export->created_at->format('Y-m-d H:i') }}<br><small class="text-muted">{{ $export->filename }}</small></td>
              <td>{{ match($export->export_type) { 'gateway_settlements'=>__('Bank settlements'), 'payment_reconciliation_entries'=>__('Payment reconciliation details'), 'gateway_settlement_entries'=>__('Settlement details'), default=>__('Payment reconciliation runs') } }}</td>
              <td>{{ strtoupper($export->format) }}</td>
              <td>{{ number_format($export->row_count) }}</td>
              <td><span class="badge badge-{{ $badge }}">{{ __(ucfirst($export->status)) }}</span>@if($export->last_error)<br><small class="text-danger">{{ Str::limit($export->last_error, 100) }}</small>@endif</td>
              <td>{{ optional($export->expires_at)->format('Y-m-d H:i') ?: '—' }}</td>
              <td>@if($export->isDownloadable())<a class="btn btn-sm btn-primary" href="{{ URL::temporarySignedRoute('admin.reconciliation_exports.download', now()->addMinutes(config('exports.download_link_minutes', 15)), ['export'=>$export]) }}">{{ __('Download') }}</a>@else<span class="text-muted">—</span>@endif</td>
            </tr>
          @empty
            <tr><td colspan="7" class="text-center py-4">{{ __('No reconciliation exports requested yet.') }}</td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
      {{ $exports->links() }}
    </div>
  </div>
@endsection
