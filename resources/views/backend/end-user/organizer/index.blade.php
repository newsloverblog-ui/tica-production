@extends('backend.layout')

@section('style')
  <link rel="stylesheet" href="{{ asset('assets/tica-ui/css/admin-directory.css') }}?v={{ @filemtime(public_path('assets/tica-ui/css/admin-directory.css')) }}">
@endsection

@section('content')
  <div class="tica-directory-page">
    <header class="directory-page-header">
      <div>
        <span class="directory-eyebrow">{{ __('People') }}</span>
        <h1>{{ __('Organizers') }}</h1>
        <p>{{ __('Review account access, verification and event ownership from one calm workspace.') }}</p>
      </div>
      <div class="directory-header-actions">
        <a class="directory-btn directory-btn--quiet" href="{{ route('admin.organizer_management.settings') }}"><i class="fas fa-sliders-h" aria-hidden="true"></i>{{ __('Organizer settings') }}</a>
        <a class="directory-btn directory-btn--quiet" href="{{ route('admin.organizer_kyc.index') }}"><i class="fas fa-user-check" aria-hidden="true"></i>{{ __('KYC reviews') }}</a>
        <a class="directory-btn directory-btn--quiet" href="{{ route('admin.directory_exports.index', ['directory' => 'organizers']) }}"><i class="fas fa-clock" aria-hidden="true"></i>{{ __('Export history') }}</a>
        <a class="directory-btn directory-btn--primary" href="{{ route('admin.organizer_management.add_organizer') }}"><i class="fas fa-plus" aria-hidden="true"></i>{{ __('Add organizer') }}</a>
      </div>
    </header>

    <div class="directory-stats" aria-label="{{ __('Organizer summary') }}">
      <article><span>{{ __('All organizers') }}</span><strong>{{ number_format($summary['total']) }}</strong></article>
      <article><span>{{ __('Active') }}</span><strong>{{ number_format($summary['active']) }}</strong></article>
      <article><span>{{ __('Email verified') }}</span><strong>{{ number_format($summary['verified']) }}</strong></article>
      <article><span>{{ __('KYC needs review') }}</span><strong>{{ number_format($summary['kyc_pending']) }}</strong></article>
    </div>

    <section class="directory-surface">
      <div class="directory-toolbar">
        <form class="directory-filter" action="{{ route('admin.organizer_management.registered_organizer') }}" method="GET">
          <label class="directory-search"><i class="fas fa-search" aria-hidden="true"></i><input name="info" type="search" value="{{ request('info') }}" placeholder="{{ __('Search name or email') }}"></label>
          <select name="status" aria-label="{{ __('Account status') }}"><option value="">{{ __('All accounts') }}</option><option value="1" @selected(request('status') === '1')>{{ __('Active') }}</option><option value="0" @selected(request('status') === '0')>{{ __('Inactive') }}</option></select>
          <select name="verified" aria-label="{{ __('Email verification') }}"><option value="">{{ __('Any verification') }}</option><option value="1" @selected(request('verified') === '1')>{{ __('Email verified') }}</option><option value="0" @selected(request('verified') === '0')>{{ __('Not verified') }}</option></select>
          <button class="directory-btn directory-btn--primary" type="submit">{{ __('Apply') }}</button>
          @if(request()->hasAny(['info','status','verified']))<a class="directory-clear" href="{{ route('admin.organizer_management.registered_organizer') }}">{{ __('Clear') }}</a>@endif
        </form>
        <form class="directory-export" method="POST" action="{{ route('admin.organizer_management.export') }}">
          @csrf
          <input type="hidden" name="search" value="{{ request('info') }}"><input type="hidden" name="status" value="{{ request('status') }}"><input type="hidden" name="verified" value="{{ request('verified') }}">
          <select name="format" aria-label="{{ __('Export format') }}"><option value="csv">CSV</option><option value="xlsx">Excel</option></select>
          <button class="directory-btn directory-btn--quiet" type="submit"><i class="fas fa-file-export" aria-hidden="true"></i>{{ __('Export organizers') }}</button>
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
              <td data-label="{{ __('Account') }}"><form action="{{ route('admin.organizer_management.organizer.update_account_status', ['id'=>$organizer->id]) }}" method="post">@csrf<select class="directory-state-select" name="account_status" onchange="this.form.submit()"><option value="1" @selected((int)$organizer->status === 1)>{{ __('Active') }}</option><option value="0" @selected((int)$organizer->status === 0)>{{ __('Inactive') }}</option></select></form></td>
              <td data-label="{{ __('Verification') }}"><form action="{{ route('admin.organizer_management.organizer.update_email_status', ['id'=>$organizer->id]) }}" method="post">@csrf<select class="directory-state-select" name="email_status" onchange="this.form.submit()"><option value="1" @selected($organizer->email_verified_at)>{{ __('Verified') }}</option><option value="0" @selected(!$organizer->email_verified_at)>{{ __('Not verified') }}</option></select></form></td>
              <td data-label="{{ __('KYC') }}"><span class="directory-status directory-status--{{ $organizer->kyc_status === 'verified' ? 'ready' : ($organizer->kyc_status === 'rejected' ? 'failed' : 'queued') }}">{{ __(ucfirst($organizer->kyc_status ?: 'unverified')) }}</span></td>
              <td class="directory-table-action"><div class="dropdown"><button class="directory-more" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" aria-label="{{ __('Actions for :name', ['name'=>$organizer->username]) }}"><i class="fas fa-ellipsis-h" aria-hidden="true"></i></button><div class="dropdown-menu dropdown-menu-right">
                <a class="dropdown-item" href="{{ route('admin.organizer_management.organizer_details', ['id'=>$organizer->id,'language'=>$defaultLang->code]) }}">{{ __('View details') }}</a>
                <a class="dropdown-item" href="{{ route('admin.edit_management.organizer_edit', ['id'=>$organizer->id]) }}">{{ __('Edit organizer') }}</a>
                <form method="post" action="{{ route('admin.organizer_management.organizer.secret_login', ['id'=>$organizer->id]) }}">@csrf<button class="dropdown-item" type="submit"><i class="fas fa-user-shield mr-2" aria-hidden="true"></i>{{ __('Sign in as organizer') }}</button></form>
                <div class="dropdown-divider"></div>
                <form class="deleteForm" action="{{ route('admin.organizer_management.organizer.delete', ['id'=>$organizer->id]) }}" method="post">@csrf<button type="submit" class="dropdown-item text-danger deleteBtn" data-action="archive">{{ __('Archive organizer') }}</button></form>
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
@endsection
