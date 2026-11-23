@extends('backend.layout')

@section('style')
  <link rel="stylesheet" href="{{ asset('assets/tica-ui/css/admin-directory.css') }}?v={{ tica_asset_version('assets/tica-ui/css/admin-directory.css') }}">
@endsection

@section('content')
  <div class="tica-directory-page audience-page">
    <header class="directory-page-header">
      <div>
        <h1>{{ __('Subscribers') }}</h1>
        <p>{{ __('People who explicitly joined the TICA newsletter from the website footer.') }}</p>
      </div>
      <div class="directory-header-actions">
        <a class="directory-btn directory-btn--quiet" href="{{ route('admin.user_management.push_notification.notification_for_visitors') }}"><i class="far fa-bell" aria-hidden="true"></i>{{ __('Push notification') }}</a>
        <a class="directory-btn directory-btn--primary" href="{{ route('admin.user_management.mail_for_subscribers', ['mode' => 'all', 'q' => request('q')]) }}"><i class="far fa-paper-plane" aria-hidden="true"></i>{{ __('Email subscribers') }}</a>
      </div>
    </header>

    <div class="directory-stats audience-stats" aria-label="{{ __('Subscriber summary') }}">
      <a class="is-active" href="{{ route('admin.audience.index') }}"><span>{{ __('Newsletter subscribers') }}</span><strong>{{ number_format($subscriberCount) }}</strong><small>{{ __('Active marketing opt-ins') }}</small></a>
    </div>

    <section class="directory-surface">
      <div class="directory-toolbar audience-toolbar">
        <form class="directory-filter" action="{{ route('admin.audience.index') }}" method="GET">
          <label class="directory-search"><i class="fas fa-search" aria-hidden="true"></i><input name="q" type="search" value="{{ request('q', request('email')) }}" placeholder="{{ __('Search subscriber email') }}"></label>
          <button class="directory-btn directory-btn--primary" type="submit">{{ __('Apply') }}</button>
          @if(request()->hasAny(['q', 'email']))<a class="directory-clear" href="{{ route('admin.audience.index') }}">{{ __('Clear') }}</a>@endif
        </form>
      </div>

      <div class="audience-guidance">
        <i class="fas fa-check-circle" aria-hidden="true"></i>
        <span><strong>{{ __('Consent-based newsletter') }}</strong>{{ __('Only addresses submitted through the newsletter form appear here. Customer accounts and ticket buyers are kept separate.') }}</span>
      </div>

      <form id="audience-selection" action="{{ route('admin.user_management.mail_for_subscribers') }}" method="GET">
        <input type="hidden" name="mode" value="selected">
        <input type="hidden" name="ids" value="" data-selected-ids>
        <input type="hidden" name="q" value="{{ request('q', request('email')) }}">
        <div class="directory-table-wrap">
          <table class="directory-table audience-table">
            <thead><tr><th><input type="checkbox" data-audience-all aria-label="{{ __('Select this page') }}"></th><th>{{ __('Subscriber') }}</th><th>{{ __('Joined') }}</th><th>{{ __('Source') }}</th><th><span class="sr-only">{{ __('Actions') }}</span></th></tr></thead>
            <tbody>
              @forelse($records as $record)
                @php($initial = mb_strtoupper(mb_substr($record->email_id ?: 'S', 0, 1)))
                <tr>
                  <td data-label=""><input type="checkbox" value="{{ $record->id }}" data-audience-row aria-label="{{ __('Select :email', ['email' => $record->email_id]) }}"></td>
                  <td data-label="{{ __('Subscriber') }}"><div class="directory-person"><span class="directory-avatar"><span>{{ $initial }}</span></span><span><strong>{{ $record->email_id }}</strong><small>{{ __('Newsletter subscriber') }}</small></span></div></td>
                  <td data-label="{{ __('Joined') }}"><strong>{{ optional($record->subscribed_at ?: $record->created_at)->format('d M Y') ?: '—' }}</strong><small>{{ optional($record->subscribed_at ?: $record->created_at)->format('H:i') ?: '' }}</small></td>
                  <td data-label="{{ __('Source') }}"><span class="directory-status directory-status--ready">{{ ($record->source ?? null) === 'website_footer' ? __('Website footer') : __('Newsletter') }}</span></td>
                  <td class="directory-table-action"><a class="directory-row-action" href="{{ route('admin.user_management.mail_for_subscribers', ['mode' => 'selected', 'ids' => $record->id]) }}">{{ __('Email') }}</a></td>
                </tr>
              @empty
                <tr><td colspan="5"><div class="directory-empty"><i class="far fa-envelope-open" aria-hidden="true"></i><strong>{{ __('No newsletter subscribers yet') }}</strong><span>{{ __('New signups from the website footer will appear here.') }}</span></div></td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
        <div class="audience-selection-bar" data-selection-bar hidden><strong><span data-selection-count>0</span> {{ __('selected') }}</strong><button class="directory-btn directory-btn--primary" type="submit"><i class="far fa-envelope" aria-hidden="true"></i>{{ __('Email selected') }}</button><button type="button" class="directory-clear" data-selection-clear>{{ __('Clear') }}</button></div>
      </form>
      <div class="directory-footer"><span>{{ __('Showing :first–:last of :total', ['first' => $records->firstItem() ?: 0, 'last' => $records->lastItem() ?: 0, 'total' => $records->total()]) }}</span><div class="directory-pagination">{{ $records->links() }}</div></div>
    </section>
  </div>
@endsection

@section('script')
<script>document.addEventListener('DOMContentLoaded',function(){var rows=Array.from(document.querySelectorAll('[data-audience-row]')),all=document.querySelector('[data-audience-all]'),bar=document.querySelector('[data-selection-bar]'),count=document.querySelector('[data-selection-count]'),ids=document.querySelector('[data-selected-ids]');function sync(){var selected=rows.filter(function(row){return row.checked}).map(function(row){return row.value});ids.value=selected.join(',');count.textContent=selected.length;bar.hidden=selected.length===0;if(all){all.checked=rows.length>0&&selected.length===rows.length;all.indeterminate=selected.length>0&&selected.length<rows.length}}rows.forEach(function(row){row.addEventListener('change',sync)});if(all)all.addEventListener('change',function(){rows.forEach(function(row){row.checked=all.checked});sync()});document.querySelector('[data-selection-clear]')?.addEventListener('click',function(){rows.forEach(function(row){row.checked=false});sync()});document.getElementById('audience-selection')?.addEventListener('submit',function(event){if(!ids.value)event.preventDefault()})});</script>
@endsection
