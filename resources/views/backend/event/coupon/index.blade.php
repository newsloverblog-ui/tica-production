@extends('backend.layout')

@section('style')
  <link rel="stylesheet" href="{{ asset('assets/tica-ui/css/admin-coupons.css') }}?v={{ tica_asset_version('assets/tica-ui/css/admin-coupons.css') }}">
@endsection

@section('content')
  <div class="tica-coupons-page">
    <header class="coupon-page-header">
      <div><h1>{{ __('Coupons') }}</h1><p>{{ __('Create and manage event discount codes.') }}</p></div>
      <button type="button" class="coupon-btn coupon-btn--primary" data-toggle="modal" data-target="#createModal"><span class="coupon-btn-mark" aria-hidden="true">+</span>{{ __('Create coupon') }}</button>
    </header>

    <div class="coupon-stats" aria-label="{{ __('Coupon summary') }}">
      <article><span>{{ __('All coupons') }}</span><strong>{{ number_format($summary['total']) }}</strong></article>
      <article><span>{{ __('Active now') }}</span><strong>{{ number_format($summary['active']) }}</strong></article>
      <article><span>{{ __('Scheduled') }}</span><strong>{{ number_format($summary['scheduled']) }}</strong></article>
      <article><span>{{ __('Redemptions') }}</span><strong>{{ number_format($summary['uses']) }}</strong></article>
    </div>

    <section class="coupon-surface">
      <form class="coupon-toolbar" method="GET" action="{{ route('admin.event_management.coupons') }}">
        <label class="coupon-search"><span class="sr-only">{{ __('Search coupons') }}</span><input type="search" name="search" value="{{ request('search') }}" placeholder="{{ __('Search name or code') }}"></label>
        <select name="status" aria-label="{{ __('Coupon status') }}"><option value="">{{ __('All statuses') }}</option><option value="active" @selected(request('status')==='active')>{{ __('Active') }}</option><option value="pending" @selected(request('status')==='pending')>{{ __('Scheduled') }}</option><option value="expired" @selected(request('status')==='expired')>{{ __('Expired') }}</option><option value="paused" @selected(request('status')==='paused')>{{ __('Paused') }}</option></select>
        <button class="coupon-btn coupon-btn--quiet" type="submit">{{ __('Filter') }}</button>
        @if(request()->hasAny(['search','status']))<a class="coupon-clear" href="{{ route('admin.event_management.coupons') }}">{{ __('Clear') }}</a>@endif
      </form>

      <div class="coupon-list">
        @if($coupons->isNotEmpty())
          <div class="coupon-list-header" aria-hidden="true">
            <span>{{ __('Code') }}</span><span>{{ __('Coupon') }}</span><span>{{ __('Discount') }}</span><span>{{ __('Valid dates') }}</span><span>{{ __('Used') }}</span><span>{{ __('Status') }}</span><span></span>
          </div>
        @endif
        @forelse($coupons as $coupon)
          @php
            $today = now()->startOfDay(); $start = \Carbon\Carbon::parse($coupon->start_date)->startOfDay(); $end = \Carbon\Carbon::parse($coupon->end_date)->endOfDay();
            $state = !$coupon->is_active ? 'paused' : ($start->gt($today) ? 'scheduled' : ($end->lt($today) ? 'expired' : 'active'));
            $eventIds = array_values(array_filter((array) json_decode((string)$coupon->events, true)));
          @endphp
          <article class="coupon-row">
            <div class="coupon-code-block"><span>{{ __('Code') }}</span><strong>{{ $coupon->code }}</strong><button type="button" class="coupon-copy" data-copy="{{ $coupon->code }}" aria-label="{{ __('Copy :code', ['code'=>$coupon->code]) }}"><i class="far fa-copy" aria-hidden="true"></i></button></div>
            <div class="coupon-primary"><span class="coupon-mobile-label">{{ __('Coupon') }}</span><strong>{{ $coupon->name }}</strong><span>{{ count($eventIds) ? trans_choice(':count selected event|:count selected events', count($eventIds), ['count'=>count($eventIds)]) : __('All events') }}</span></div>
            <div class="coupon-discount"><span>{{ __('Discount') }}</span><strong>@if($coupon->type==='fixed'){{ $currencyInfo->base_currency_symbol_position === 'left' ? $currencyInfo->base_currency_symbol : '' }}{{ number_format((float)$coupon->value, 2) }}{{ $currencyInfo->base_currency_symbol_position === 'right' ? $currencyInfo->base_currency_symbol : '' }}@else{{ rtrim(rtrim(number_format((float)$coupon->value, 2), '0'), '.') }}%@endif</strong></div>
            <div class="coupon-period"><span>{{ __('Valid dates') }}</span><strong>{{ $start->format('j M') }} – {{ $end->format('j M Y') }}</strong></div>
            <div class="coupon-usage"><span>{{ __('Used') }}</span><strong>{{ number_format((int)$coupon->used_count) }}@if($coupon->max_uses)<small>/ {{ number_format((int)$coupon->max_uses) }}</small>@endif</strong></div>
            <span class="coupon-status coupon-status--{{ $state }}">{{ __(ucfirst($state)) }}</span>
            <div class="dropdown coupon-actions"><button type="button" class="coupon-more" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" aria-label="{{ __('Actions for :coupon',['coupon'=>$coupon->name]) }}"><i class="fas fa-ellipsis-h" aria-hidden="true"></i></button><div class="dropdown-menu dropdown-menu-right">
              <button class="dropdown-item editBtn" type="button" data-toggle="modal" data-target="#editModal" data-id="{{ $coupon->id }}" data-name="{{ $coupon->name }}" data-code="{{ $coupon->code }}" data-type="{{ $coupon->type }}" data-events='@json($eventIds)' data-value="{{ $coupon->value }}" data-start_date="{{ $start->format('m/d/Y') }}" data-end_date="{{ $end->format('m/d/Y') }}"><i class="fas fa-pen mr-2" aria-hidden="true"></i>{{ __('Edit coupon') }}</button>
              <div class="dropdown-divider"></div><form class="deleteForm" action="{{ route('admin.event_management.delete_coupon',['id'=>$coupon->id]) }}" method="post">@csrf<button type="submit" class="dropdown-item text-danger deleteBtn"><i class="fas fa-trash mr-2" aria-hidden="true"></i>{{ __('Delete coupon') }}</button></form>
            </div></div>
          </article>
        @empty
          <div class="coupon-empty"><span><i class="fas fa-ticket-alt" aria-hidden="true"></i></span><strong>{{ __('No coupons found') }}</strong><p>{{ __('Try another filter or create a coupon.') }}</p><button type="button" class="coupon-btn coupon-btn--primary" data-toggle="modal" data-target="#createModal">{{ __('Create coupon') }}</button></div>
        @endforelse
      </div>
      <div class="coupon-pagination">{{ $coupons->links() }}</div>
    </section>
  </div>

  @include('backend.event.coupon.create')
  @include('backend.event.coupon.edit')
@endsection

@section('script')
  <script>
    document.querySelectorAll('[data-copy]').forEach(function (button) {
      button.addEventListener('click', function () {
        navigator.clipboard.writeText(button.dataset.copy).then(function () {
          button.classList.add('is-copied');
          window.setTimeout(function () { button.classList.remove('is-copied'); }, 1200);
        });
      });
    });
  </script>
@endsection
