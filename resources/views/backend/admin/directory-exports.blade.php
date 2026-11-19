@extends('backend.layout')

@section('style')
  <link rel="stylesheet" href="{{ asset('assets/tica-ui/css/admin-directory.css') }}?v={{ tica_asset_version('assets/tica-ui/css/admin-directory.css') }}">
@endsection

@section('content')
  <div class="tica-directory-page">
    <header class="directory-page-header">
      <div>
        <span class="directory-eyebrow">{{ __('Private data exports') }}</span>
        <h1>{{ __('Export history') }}</h1>
        <p>{{ __('Secure organizer and customer files are available only to the administrator who requested them and expire automatically.') }}</p>
      </div>
      <div class="directory-header-actions">
        @if (!$directory || $directory === 'organizers')
          <a class="directory-btn directory-btn--quiet" href="{{ route('admin.organizer_management.registered_organizer') }}">{{ __('Organizers') }}</a>
        @endif
        @if (!$directory || $directory === 'customers')
          <a class="directory-btn directory-btn--quiet" href="{{ route('admin.organizer_management.registered_customer') }}">{{ __('Customers') }}</a>
        @endif
      </div>
    </header>

    <section class="directory-surface">
      <div class="directory-surface-head">
        <div>
          <h2>{{ $directory ? __(ucfirst($directory).' exports') : __('Directory exports') }}</h2>
          <p>{{ __('Queued files are generated in the background. A private download appears when processing finishes.') }}</p>
        </div>
        <span class="directory-security-note"><i class="fas fa-lock" aria-hidden="true"></i>{{ __('Owner-only downloads') }}</span>
      </div>

      <div class="directory-table-wrap">
        <table class="directory-table">
          <thead><tr><th>{{ __('Requested') }}</th><th>{{ __('Directory') }}</th><th>{{ __('Rows') }}</th><th>{{ __('Status') }}</th><th>{{ __('Expires') }}</th><th><span class="sr-only">{{ __('Action') }}</span></th></tr></thead>
          <tbody>
          @forelse ($exports as $export)
            @php $badge = ['ready'=>'ready','failed'=>'failed','expired'=>'muted','processing'=>'working','queued'=>'queued'][$export->status] ?? 'muted'; @endphp
            <tr>
              <td data-label="{{ __('Requested') }}"><strong>{{ $export->created_at->format('j M Y') }}</strong><small>{{ $export->created_at->format('g:i A') }}</small></td>
              <td data-label="{{ __('Directory') }}"><span class="directory-type">{{ __(ucfirst($export->export_type)) }}</span><small>{{ strtoupper($export->format) }}</small></td>
              <td data-label="{{ __('Rows') }}">{{ number_format($export->row_count) }}</td>
              <td data-label="{{ __('Status') }}"><span class="directory-status directory-status--{{ $badge }}">{{ __(ucfirst($export->status)) }}</span></td>
              <td data-label="{{ __('Expires') }}">{{ optional($export->expires_at)->format('j M Y, g:i A') ?: '—' }}</td>
              <td class="directory-table-action">
                @if ($export->isDownloadable())
                  <a class="directory-btn directory-btn--primary" href="{{ URL::temporarySignedRoute('admin.directory_exports.download', now()->addMinutes(config('exports.download_link_minutes', 15)), ['export' => $export]) }}"><i class="fas fa-download" aria-hidden="true"></i>{{ __('Download') }}</a>
                @else
                  <span class="directory-pending">{{ $export->status === 'failed' ? __('Generation failed') : __('Preparing') }}</span>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="6"><div class="directory-empty"><i class="fas fa-file-export" aria-hidden="true"></i><strong>{{ __('No exports requested yet') }}</strong><span>{{ __('Start from an organizer or customer directory.') }}</span></div></td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
      <div class="directory-pagination">{{ $exports->links() }}</div>
    </section>
  </div>
@endsection
