@extends('organizer.layout')

@section('content')
  <div class="page-header">
    <h4 class="page-title">{{ __('Check-in ledger') }}</h4>
    <ul class="breadcrumbs">
      <li class="nav-home"><a href="{{ route('organizer.dashboard') }}"><i class="flaticon-home"></i></a></li>
      <li class="separator"><i class="flaticon-right-arrow"></i></li>
      <li class="nav-item"><a href="#">{{ __('Gate operations') }}</a></li>
    </ul>
  </div>

  <div class="card mb-4">
    <div class="card-header"><div class="card-title">{{ __('Create event ledger') }}</div></div>
    <div class="card-body">
      <p class="text-muted">{{ __('Export accepted admissions and duplicate scan attempts with their timestamps, gates, devices and responsible staff. Attendee contact data is excluded.') }}</p>
      <form method="POST" action="" id="check-in-ledger-form" class="row align-items-end">
        @csrf
        <div class="col-md-7 form-group">
          <label for="check-in-event">{{ __('Event') }}</label>
          <select id="check-in-event" class="form-control" required>
            <option value="">{{ __('Choose an event') }}</option>
            @foreach($events as $event)
              <option value="{{ $event->id }}">{{ optional($event->information)->title ?: __('Event #:id', ['id' => $event->id]) }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2 form-group"><label for="check-in-format">{{ __('Format') }}</label><select id="check-in-format" name="format" class="form-control"><option value="csv">CSV</option><option value="xlsx">XLSX</option></select></div>
        <div class="col-md-3 form-group"><button class="btn btn-primary btn-block" type="submit">{{ __('Queue private export') }}</button></div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><div class="card-title">{{ __('Private check-in exports') }}</div><small class="text-muted">{{ __('Files expire automatically and downloads are actor-owned, signed and non-cacheable.') }}</small></div>
    <div class="card-body">
      <div class="table-responsive"><table class="table table-striped"><thead><tr><th>{{ __('Requested') }}</th><th>{{ __('Event') }}</th><th>{{ __('Format') }}</th><th>{{ __('Rows') }}</th><th>{{ __('Status') }}</th><th>{{ __('Expires') }}</th><th></th></tr></thead><tbody>
      @forelse($exports as $export)
        @php($badge = ['ready'=>'success','failed'=>'danger','expired'=>'secondary','processing'=>'warning','queued'=>'info'][$export->status] ?? 'secondary')
        <tr>
          <td>{{ $export->created_at->format('Y-m-d H:i') }}<br><small class="text-muted">{{ $export->filename }}</small></td>
          <td>#{{ $export->filters['event_id'] ?? '—' }}</td><td>{{ strtoupper($export->format) }}</td><td>{{ number_format($export->row_count) }}</td>
          <td><span class="badge badge-{{ $badge }}">{{ __(ucfirst($export->status)) }}</span>@if($export->last_error)<br><small class="text-danger">{{ Str::limit($export->last_error,100) }}</small>@endif</td>
          <td>{{ optional($export->expires_at)->format('Y-m-d H:i') ?: '—' }}</td>
          <td>@if($export->isDownloadable())<a class="btn btn-sm btn-primary" href="{{ URL::temporarySignedRoute('organizer.check_in_ledger_exports.download', now()->addMinutes(config('exports.download_link_minutes',15)), ['export'=>$export]) }}">{{ __('Download') }}</a>@else<span class="text-muted">—</span>@endif</td>
        </tr>
      @empty<tr><td colspan="7" class="text-center py-4">{{ __('No check-in ledgers requested yet.') }}</td></tr>@endforelse
      </tbody></table></div>{{ $exports->links() }}
    </div>
  </div>
@endsection

@push('script')
<script>
  document.getElementById('check-in-ledger-form').addEventListener('submit', function (event) {
    const eventId = document.getElementById('check-in-event').value;
    if (!eventId) { event.preventDefault(); return; }
    this.action = @json(url('/organizer/events')) + '/' + encodeURIComponent(eventId) + '/check-in-ledger/export';
  });
</script>
@endpush
