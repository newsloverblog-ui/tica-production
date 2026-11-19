@extends('backend.layout')

@section('style')
  <link rel="stylesheet" href="{{ asset('assets/tica-ui/css/admin-directory.css') }}?v={{ tica_asset_version('assets/tica-ui/css/admin-directory.css') }}">
@endsection

@section('content')
  <div class="tica-directory-page">
    <header class="directory-page-header">
      <div><span class="directory-eyebrow">{{ __('Audience') }}</span><h1>{{ __('Customers') }}</h1><p>{{ __('Find registered customers, review access status and prepare private operational exports.') }}</p></div>
      <div class="directory-header-actions">
        <a class="directory-btn directory-btn--quiet" href="{{ route('admin.customer_privacy_requests.index') }}"><i class="fas fa-user-shield" aria-hidden="true"></i>{{ __('Privacy requests') }}</a>
        <a class="directory-btn directory-btn--quiet" href="{{ route('admin.directory_exports.index', ['directory'=>'customers']) }}"><i class="fas fa-clock" aria-hidden="true"></i>{{ __('Export history') }}</a>
        <a class="directory-btn directory-btn--primary" href="{{ route('admin.organizer_management.add_customer') }}"><i class="fas fa-plus" aria-hidden="true"></i>{{ __('Add customer') }}</a>
      </div>
    </header>

    <div class="directory-stats" aria-label="{{ __('Customer summary') }}">
      <article><span>{{ __('All customers') }}</span><strong>{{ number_format($summary['total']) }}</strong></article>
      <article><span>{{ __('Active') }}</span><strong>{{ number_format($summary['active']) }}</strong></article>
      <article><span>{{ __('Email verified') }}</span><strong>{{ number_format($summary['verified']) }}</strong></article>
      <article><span>{{ __('With bookings') }}</span><strong>{{ number_format($summary['with_orders']) }}</strong></article>
    </div>

    <section class="directory-surface">
      <div class="directory-toolbar">
        <form class="directory-filter" action="{{ route('admin.organizer_management.registered_customer') }}" method="GET">
          <label class="directory-search"><i class="fas fa-search" aria-hidden="true"></i><input name="info" type="search" value="{{ request('info') }}" placeholder="{{ __('Search name, username or email') }}"></label>
          <select name="status" aria-label="{{ __('Account status') }}"><option value="">{{ __('All accounts') }}</option><option value="1" @selected(request('status') === '1')>{{ __('Active') }}</option><option value="0" @selected(request('status') === '0')>{{ __('Inactive') }}</option></select>
          <select name="verified" aria-label="{{ __('Email verification') }}"><option value="">{{ __('Any verification') }}</option><option value="1" @selected(request('verified') === '1')>{{ __('Email verified') }}</option><option value="0" @selected(request('verified') === '0')>{{ __('Not verified') }}</option></select>
          <button class="directory-btn directory-btn--primary" type="submit">{{ __('Apply') }}</button>
          @if(request()->hasAny(['info','status','verified']))<a class="directory-clear" href="{{ route('admin.organizer_management.registered_customer') }}">{{ __('Clear') }}</a>@endif
        </form>
        <form class="directory-export" method="POST" action="{{ route('admin.customer_management.export') }}">@csrf<input type="hidden" name="search" value="{{ request('info') }}"><input type="hidden" name="status" value="{{ request('status') }}"><input type="hidden" name="verified" value="{{ request('verified') }}"><select name="format" aria-label="{{ __('Export format') }}"><option value="csv">CSV</option><option value="xlsx">Excel</option></select><button class="directory-btn directory-btn--quiet" type="submit"><i class="fas fa-file-export" aria-hidden="true"></i>{{ __('Export customers') }}</button></form>
      </div>

      <div class="directory-table-wrap"><table class="directory-table directory-table--people"><thead><tr><th><input type="checkbox" class="bulk-check" data-val="all" aria-label="{{ __('Select all customers') }}"></th><th>{{ __('Customer') }}</th><th>{{ __('Contact') }}</th><th>{{ __('Account') }}</th><th>{{ __('Verification') }}</th><th><span class="sr-only">{{ __('Actions') }}</span></th></tr></thead><tbody>
      @forelse($customers as $customer)
        @php $customerName = trim($customer->fname.' '.$customer->lname) ?: $customer->username; $initials = mb_strtoupper(mb_substr($customer->fname ?: $customer->username ?: 'C',0,1).mb_substr($customer->lname ?: '',0,1)); @endphp
        <tr>
          <td data-label=""><input type="checkbox" class="bulk-check" data-val="{{ $customer->id }}" aria-label="{{ __('Select :name',['name'=>$customerName]) }}"></td>
          <td data-label="{{ __('Customer') }}"><div class="directory-person"><span class="directory-avatar directory-avatar--customer"><span>{{ $initials }}</span></span><span><strong>{{ $customerName }}</strong><small>{{ '@'.$customer->username }}</small></span></div></td>
          <td data-label="{{ __('Contact') }}"><strong>{{ $customer->email }}</strong><small>{{ $customer->phone ?: __('No phone added') }}</small></td>
          <td data-label="{{ __('Account') }}"><form action="{{ route('admin.customer_management.customer.update_account_status',['id'=>$customer->id]) }}" method="post">@csrf<select class="directory-state-select" name="account_status" onchange="this.form.submit()"><option value="1" @selected((int)$customer->status===1)>{{ __('Active') }}</option><option value="0" @selected((int)$customer->status===0)>{{ __('Inactive') }}</option></select></form></td>
          <td data-label="{{ __('Verification') }}"><form action="{{ route('admin.customer_management.customer.update_email_status',['id'=>$customer->id]) }}" method="post">@csrf<select class="directory-state-select" name="email_status" onchange="this.form.submit()"><option value="1" @selected($customer->email_verified_at)>{{ __('Verified') }}</option><option value="0" @selected(!$customer->email_verified_at)>{{ __('Not verified') }}</option></select></form></td>
          <td class="directory-table-action"><div class="dropdown"><button class="directory-more" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" aria-label="{{ __('Actions for :name',['name'=>$customerName]) }}"><i class="fas fa-ellipsis-h" aria-hidden="true"></i></button><div class="dropdown-menu dropdown-menu-right"><a class="dropdown-item" href="{{ route('admin.customer_management.customer_details',['id'=>$customer->id,'language'=>$defaultLang->code]) }}">{{ __('View details') }}</a><a class="dropdown-item" href="{{ route('admin.customer_management.customer_edit',['id'=>$customer->id]) }}">{{ __('Edit customer') }}</a><div class="dropdown-divider"></div><form class="deleteForm" action="{{ route('admin.customer_management.customer_delete',['id'=>$customer->id]) }}" method="post">@csrf<button type="submit" class="dropdown-item text-danger deleteBtn" data-action="archive">{{ __('Archive customer') }}</button></form></div></div></td>
        </tr>
      @empty
        <tr><td colspan="6"><div class="directory-empty"><i class="fas fa-user-friends" aria-hidden="true"></i><strong>{{ __('No customers match these filters') }}</strong><span>{{ __('Try a broader search or clear the filters.') }}</span></div></td></tr>
      @endforelse
      </tbody></table></div>
      <div class="directory-footer"><button class="directory-btn directory-btn--quiet d-none bulk-delete" data-action="archive" data-href="{{ route('admin.customer_management.bulk_delete_customer') }}"><i class="fas fa-archive" aria-hidden="true"></i>{{ __('Archive selected') }}</button><div class="directory-pagination">{{ $customers->links() }}</div></div>
    </section>
  </div>
@endsection
