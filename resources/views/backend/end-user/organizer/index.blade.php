@extends('backend.layout')

@section('style')
  <link rel="stylesheet" href="{{ asset('assets/tica-ui/css/admin-directory.css') }}?v={{ @filemtime(public_path('assets/tica-ui/css/admin-directory.css')) }}">
@endsection

@section('content')
  <div class="tica-directory-page tica-directory-page--organizers">
    <header class="directory-page-header">
      <div>
        <h1>{{ __('Organizers') }}</h1>
        <p>{{ __('Manage organizer access, verification and event ownership.') }}</p>
      </div>
      <div class="directory-header-actions">
        <a class="directory-btn directory-btn--primary" href="{{ route('admin.organizer_management.add_organizer') }}"><i class="fas fa-plus" aria-hidden="true"></i>{{ __('Add organizer') }}</a>
      </div>
    </header>

    <nav class="directory-shortcuts" aria-label="{{ __('Organizer tools') }}">
      <a href="{{ route('admin.organizer_management.settings') }}"><i class="fas fa-sliders-h" aria-hidden="true"></i>{{ __('Settings') }}</a>
      <a href="{{ route('admin.organizer_kyc.index') }}"><i class="fas fa-user-check" aria-hidden="true"></i>{{ __('KYC reviews') }}@if($summary['kyc_pending'])<span>{{ number_format($summary['kyc_pending']) }}</span>@endif</a>
      <a href="{{ route('admin.directory_exports.index', ['directory' => 'organizers']) }}"><i class="fas fa-clock" aria-hidden="true"></i>{{ __('Export history') }}</a>
    </nav>

    <div class="directory-stats" aria-label="{{ __('Organizer summary') }}">
      <article><span>{{ __('All organizers') }}</span><strong>{{ number_format($summary['total']) }}</strong></article>
      <article><span>{{ __('Active') }}</span><strong>{{ number_format($summary['active']) }}</strong></article>
      <article><span>{{ __('Email verified') }}</span><strong>{{ number_format($summary['verified']) }}</strong></article>
      <article><span>{{ __('KYC needs review') }}</span><strong>{{ number_format($summary['kyc_pending']) }}</strong></article>
    </div>

    <section class="directory-surface">
      <div class="directory-toolbar">
        <form class="directory-filter" action="{{ route('admin.organizer_management.registered_organizer') }}" method="GET">
          <label class="directory-search"><span class="sr-only">{{ __('Search organizers') }}</span><i class="fas fa-search" aria-hidden="true"></i><input name="info" type="search" value="{{ request('info') }}" placeholder="{{ __('Search name or email') }}"></label>
          <select name="status" aria-label="{{ __('Account status') }}"><option value="">{{ __('All accounts') }}</option><option value="1" @selected(request('status') === '1')>{{ __('Active') }}</option><option value="0" @selected(request('status') === '0')>{{ __('Inactive') }}</option></select>
          <select name="verified" aria-label="{{ __('Email verification') }}"><option value="">{{ __('Any verification') }}</option><option value="1" @selected(request('verified') === '1')>{{ __('Email verified') }}</option><option value="0" @selected(request('verified') === '0')>{{ __('Not verified') }}</option></select>
          <button class="directory-btn directory-btn--quiet" type="submit">{{ __('Filter') }}</button>
          @if(request()->hasAny(['info','status','verified']))<a class="directory-clear" href="{{ route('admin.organizer_management.registered_organizer') }}">{{ __('Clear') }}</a>@endif
        </form>
        <form class="directory-export" method="POST" action="{{ route('admin.organizer_management.export') }}">
          @csrf
          <input type="hidden" name="search" value="{{ request('info') }}"><input type="hidden" name="status" value="{{ request('status') }}"><input type="hidden" name="verified" value="{{ request('verified') }}">
          <select name="format" aria-label="{{ __('Export format') }}"><option value="csv">CSV</option><option value="xlsx">Excel</option></select>
          <button class="directory-btn directory-btn--quiet" type="submit"><i class="fas fa-file-export" aria-hidden="true"></i>{{ __('Export') }}</button>
        </form>
      </div>

      <div class="directory-table-wrap">
        <table class="directory-table directory-table--people">
          <thead><tr><th><input type="checkbox" class="bulk-check" data-val="all" aria-label="{{ __('Select all organizers') }}"></th><th>{{ __('Organizer') }}</th><th>{{ __('Contact') }}</th><th>{{ __('Account') }}</th><th>{{ __('Verification') }}</th><th>{{ __('KYC') }}</th><th><span class="sr-only">{{ __('Actions') }}</span></th></tr></thead>
          <tbody>
          @forelse($organizers as $organizer)
            <tr>
              <td data-label=""><input type="checkbox" class="bulk-check" data-val="{{ $organizer->id }}" aria-label="{{ __('Select :name', ['name' => $organizer->username]) }}"></td>
              <td data-label="{{ __('Organizer') }}"><div class="directory-person"><span class="directory-avatar">@if($organizer->avatar_url)<img src="{{ $organizer->avatar_url }}" alt="" onerror="this.remove()">@endif<span>{{ $organizer->avatar_initials }}</span></span><span><strong>{{ $organizer->username }}</strong><small>{{ __('Joined :date', ['date' => optional($organizer->created_at)->format('j M Y') ?: '—']) }}</small></span></div></td>
              <td data-label="{{ __('Contact') }}"><strong>{{ $organizer->email }}</strong><small>{{ $organizer->phone ?: __('No phone added') }}</small></td>
              <td data-label="{{ __('Account') }}"><span class="directory-status directory-status--{{ (int) $organizer->status === 1 ? 'ready' : 'muted' }}">{{ (int) $organizer->status === 1 ? __('Active') : __('Suspended') }}</span></td>
              <td data-label="{{ __('Verification') }}"><form action="{{ route('admin.organizer_management.organizer.update_email_status', ['id'=>$organizer->id]) }}" method="post">@csrf<select class="directory-state-select" name="email_status" onchange="this.form.submit()"><option value="1" @selected($organizer->email_verified_at)>{{ __('Verified') }}</option><option value="0" @selected(!$organizer->email_verified_at)>{{ __('Not verified') }}</option></select></form></td>
              <td data-label="{{ __('KYC') }}"><span class="directory-status directory-status--{{ $organizer->kyc_status === 'verified' ? 'ready' : ($organizer->kyc_status === 'rejected' ? 'failed' : 'queued') }}">{{ __(ucfirst($organizer->kyc_status ?: 'unverified')) }}</span></td>
              <td class="directory-table-action"><div class="dropdown"><button class="directory-more" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" aria-label="{{ __('Actions for :name', ['name'=>$organizer->username]) }}"><i class="fas fa-ellipsis-h" aria-hidden="true"></i></button><div class="dropdown-menu dropdown-menu-right">
                <a class="dropdown-item" href="{{ route('admin.organizer_management.organizer_details', ['id'=>$organizer->id,'language'=>$defaultLang->code]) }}"><i class="fas fa-chart-bar" aria-hidden="true"></i>{{ __('Events and details') }}</a>
                <a class="dropdown-item" href="{{ route('admin.edit_management.organizer_edit', ['id'=>$organizer->id]) }}"><i class="fas fa-pen" aria-hidden="true"></i>{{ __('Edit organizer') }}</a>
                <button class="dropdown-item js-message-organizer" type="button" data-toggle="modal" data-target="#organizerMessageModal" data-organizer-message data-organizer-name="{{ $organizer->username }}" data-message-url="{{ route('admin.organizer_management.organizer.message', ['id'=>$organizer->id]) }}"><i class="fas fa-envelope" aria-hidden="true"></i>{{ __('Message organizer') }}</button>
                <a class="dropdown-item" href="{{ route('admin.organizer_management.organizer.change_password', ['id'=>$organizer->id]) }}"><i class="fas fa-key" aria-hidden="true"></i>{{ __('Reset access') }}</a>
                <form method="post" action="{{ route('admin.organizer_management.organizer.secret_login', ['id'=>$organizer->id]) }}">@csrf<button class="dropdown-item" type="submit"><i class="fas fa-user-shield" aria-hidden="true"></i>{{ __('Sign in as organizer') }}</button></form>
                <div class="dropdown-divider"></div>
                <form class="organizer-status-form" action="{{ route('admin.organizer_management.organizer.update_account_status', ['id'=>$organizer->id]) }}" method="post" data-confirm="{{ (int) $organizer->status === 1 ? __('Suspend this organizer? They will be signed out and their active events will be unpublished.') : __('Reactivate this organizer and restore account access?') }}">@csrf<input type="hidden" name="account_status" value="{{ (int) $organizer->status === 1 ? 0 : 1 }}"><button type="submit" class="dropdown-item"><i class="fas fa-{{ (int) $organizer->status === 1 ? 'pause-circle' : 'check-circle' }}" aria-hidden="true"></i>{{ (int) $organizer->status === 1 ? __('Suspend organizer') : __('Reactivate organizer') }}</button></form>
                <form action="{{ route('admin.organizer_management.organizer.delete', ['id'=>$organizer->id]) }}" method="post" data-confirm="{{ __('Delete this organizer’s access? Active events will be unpublished, while financial and event history stays preserved.') }}">@csrf<button type="submit" class="dropdown-item text-danger"><i class="fas fa-trash-alt" aria-hidden="true"></i>{{ __('Delete organizer') }}</button></form>
              </div></div></td>
            </tr>
          @empty
            <tr><td colspan="7"><div class="directory-empty"><i class="fas fa-users" aria-hidden="true"></i><strong>{{ __('No organizers match these filters') }}</strong><span>{{ __('Try a broader search or create a new organizer.') }}</span></div></td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
      <div class="directory-footer"><button class="directory-btn directory-btn--quiet d-none bulk-delete" data-action="archive" data-href="{{ route('admin.organizer_management.bulk_delete_organizer') }}"><i class="fas fa-archive" aria-hidden="true"></i>{{ __('Archive selected') }}</button><div class="directory-pagination">{{ $organizers->links() }}</div></div>
    </section>
  </div>

  <div class="modal fade organizer-message-modal" id="organizerMessageModal" tabindex="-1" role="dialog" aria-labelledby="organizerMessageTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content">
        <form id="organizerMessageForm" method="POST" action="">
          @csrf
          <div class="modal-header">
            <div><h2 class="modal-title" id="organizerMessageTitle">{{ __('Message organizer') }}</h2><p>{{ __('To') }} <strong id="organizerMessageRecipient">—</strong></p></div>
            <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('Close') }}"><span aria-hidden="true">&times;</span></button>
          </div>
          <div class="modal-body">
            <label for="organizerMessageSubject">{{ __('Subject') }}</label>
            <input id="organizerMessageSubject" name="subject" type="text" maxlength="160" required autocomplete="off">
            <label for="organizerMessageBody">{{ __('Message') }}</label>
            <textarea id="organizerMessageBody" name="message" rows="7" maxlength="5000" required></textarea>
          </div>
          <div class="modal-footer">
            <button type="button" class="directory-btn directory-btn--quiet" data-dismiss="modal">{{ __('Cancel') }}</button>
            <button type="submit" class="directory-btn directory-btn--primary"><i class="fas fa-paper-plane" aria-hidden="true"></i>{{ __('Queue message') }}</button>
          </div>
        </form>
      </div>
    </div>
  </div>
@endsection

@section('script')
  <script>
    document.addEventListener('click', function (event) {
      var trigger = event.target.closest('[data-organizer-message]');
      if (!trigger) return;
      document.getElementById('organizerMessageForm').action = trigger.dataset.messageUrl;
      document.getElementById('organizerMessageRecipient').textContent = trigger.dataset.organizerName;
      document.getElementById('organizerMessageSubject').value = '';
      document.getElementById('organizerMessageBody').value = '';
    });

    document.addEventListener('submit', function (event) {
      var message = event.target.dataset.confirm;
      if (message && !window.confirm(message)) event.preventDefault();
    });
  </script>
@endsection
