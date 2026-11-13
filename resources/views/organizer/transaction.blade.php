@extends('organizer.layout')

@php
  $types = [1 => __('Ticket sale'), 2 => __('Product order'), 3 => __('Withdrawal'), 4 => __('Balance credit'), 5 => __('Balance debit'), 6 => __('Event refund'), 7 => __('Product refund')];
  $money = function ($t, $amount) {
    $value = number_format((float) $amount, 2);
    return $t->currency_symbol_position === 'right' ? $value . $t->currency_symbol : $t->currency_symbol . $value;
  };
  $filtered = collect(['transcation_id', 'transaction_type', 'payment_status', 'from_date', 'to_date'])->contains(fn ($k) => filled(request($k)));
@endphp

@section('content')
  <div class="t-page-head">
    <div>
      <h1 class="page-title">{{ __('Transactions') }}</h1>
      <p>{{ __('Every movement of money in your TICA balance: sales, refunds and withdrawals.') }}</p>
    </div>
    <div class="lg-actions">
      <a class="t-btn t-btn--secondary" href="{{ route('organizer.transaction_exports.index') }}">{{ __('Export history') }}</a>
      <form method="POST" action="{{ route('organizer.transaction_exports.store') }}" class="lg-export">@csrf
        @foreach (['transcation_id', 'transaction_type', 'payment_status', 'from_date', 'to_date'] as $key)
          <input type="hidden" name="{{ $key }}" value="{{ request($key) }}">
        @endforeach
        <label class="t-sr" for="tx-format">{{ __('Export format') }}</label>
        <select id="tx-format" name="format" class="t-select" required><option value="csv">CSV</option><option value="xlsx">Excel</option></select>
        <button class="t-btn t-btn--primary" type="submit">{{ __('Export') }}</button>
      </form>
    </div>
  </div>

  <form action="{{ route('organizer.transcation') }}" method="get" class="t-toolbar lg-filters">
    <label class="t-search"><span class="t-sr">{{ __('Search by reference') }}</span><input class="t-input" type="search" maxlength="100" value="{{ request('transcation_id') }}" name="transcation_id" placeholder="{{ __('Search by reference') }}"></label>
    <select name="transaction_type" class="t-select" aria-label="{{ __('Type') }}"><option value="">{{ __('All types') }}</option>@foreach ($types as $value => $label)<option value="{{ $value }}" @selected((string) request('transaction_type') === (string) $value)>{{ $label }}</option>@endforeach</select>
    <select name="payment_status" class="t-select" aria-label="{{ __('Status') }}"><option value="">{{ __('Any status') }}</option><option value="1" @selected(request('payment_status') === '1')>{{ __('Paid') }}</option><option value="0" @selected(request('payment_status') === '0')>{{ __('Unpaid') }}</option><option value="2" @selected(request('payment_status') === '2')>{{ __('Declined') }}</option></select>
    <input type="date" name="from_date" class="t-input" value="{{ request('from_date') }}" aria-label="{{ __('From') }}">
    <input type="date" name="to_date" class="t-input" value="{{ request('to_date') }}" aria-label="{{ __('To') }}">
    <button class="t-btn t-btn--secondary" type="submit">{{ __('Apply') }}</button>
    @if ($filtered)<a class="t-btn t-btn--ghost" href="{{ route('organizer.transcation') }}">{{ __('Clear filters') }}</a>@endif
  </form>

  @if (count($transcations) == 0)
    <div class="t-card"><div class="t-empty">
      <h3>{{ $filtered ? __('No transactions match these filters') : __('No transactions yet') }}</h3>
      <p>{{ $filtered ? __('Try a different type, status or date.') : __('Ticket sales, refunds and withdrawals will appear here.') }}</p>
    </div></div>
  @else
    <div class="t-table-wrap">
      <table class="t-table t-table--cards">
        <thead><tr>
          <th scope="col">{{ __('Reference') }}</th><th scope="col">{{ __('Type') }}</th><th scope="col">{{ __('Method') }}</th>
          <th scope="col" class="t-num">{{ __('Amount') }}</th><th scope="col" class="t-num">{{ __('Balance after') }}</th><th scope="col">{{ __('Status') }}</th><th scope="col"><span class="t-sr">{{ __('Actions') }}</span></th>
        </tr></thead>
        <tbody>
          @foreach ($transcations as $transcation)
            @php
              $out = in_array((int) $transcation->transcation_type, [3, 5, 6, 7], true);
              $method = (int) $transcation->transcation_type === 3 ? optional($transcation->method()->first())->name : $transcation->payment_method;
              $booking = (int) $transcation->transcation_type === 1 ? $transcation->event_booking()->first() : null;
            @endphp
            <tr>
              <td data-label="{{ __('Reference') }}">#{{ $transcation->transcation_id }}<span class="t-muted lg-sub">{{ optional($transcation->created_at)->format('j M Y, g:i a') }}</span></td>
              <td data-label="{{ __('Type') }}">{{ $types[(int) $transcation->transcation_type] ?? __('Other') }}</td>
              <td data-label="{{ __('Method') }}">{{ $method ?: '—' }}</td>
              <td data-label="{{ __('Amount') }}" class="t-num lg-amt {{ $out ? 'is-out' : 'is-in' }}">{{ $out ? '−' : '+' }}{{ $money($transcation, $transcation->grand_total - $transcation->commission) }}</td>
              <td data-label="{{ __('Balance after') }}" class="t-num">{{ $money($transcation, $transcation->after_balance) }}</td>
              <td data-label="{{ __('Status') }}">
                @if ((int) $transcation->payment_status === 1)<span class="t-badge t-badge--green">{{ __('Paid') }}</span>
                @elseif ((int) $transcation->payment_status === 2)<span class="t-badge t-badge--orange">{{ __('Declined') }}</span>
                @else<span class="t-badge t-badge--red">{{ __('Unpaid') }}</span>@endif
              </td>
              <td class="ev-actions">
                @if ($booking)<a class="t-btn t-btn--ghost t-btn--sm" target="_blank" rel="noopener" href="{{ route('organizer.event_booking.invoice', $booking->id) }}">{{ __('Invoice') }}</a>@endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    <div class="t-pager lg-pager">{{ $transcations->withQueryString()->links() }}</div>
  @endif
@endsection
