@extends('organizer.layout')

@section('content')
  @php
    $symbol = optional($currency)->base_currency_symbol ?: '₦';
    $money = fn ($value) => $symbol . number_format((float) $value, 2);
    $tone = [
      'ready' => 'green', 'scheduled' => 'blue', 'processing' => 'blue', 'none' => '',
      'manual_review' => 'orange', 'review_required' => 'red', 'action_required' => 'orange',
    ][$nextPayout['status']] ?? '';
  @endphp

  <div class="t-page-head">
    <div>
      <h1 class="page-title">{{ __('Finance statement') }}</h1>
      <p>{{ __('Sales, fees, refunds and payouts for any date range.') }}</p>
    </div>
    <form method="get" class="lg-range">
      <label class="t-field"><span class="t-label">{{ __('From') }}</span><input type="date" name="from" class="t-input" value="{{ $from->format('Y-m-d') }}" required></label>
      <label class="t-field"><span class="t-label">{{ __('To') }}</span><input type="date" name="to" class="t-input" value="{{ $to->format('Y-m-d') }}" required></label>
      <button class="t-btn t-btn--secondary" type="submit">{{ __('Apply') }}</button>
    </form>
  </div>

  {{-- Layer 1: the four numbers that answer "how did I do?" --}}
  <div class="lg-stats">
    @foreach ([['Gross sales', 'gross_sales'], ['Net sales', 'net_sales'], ['Available balance', 'available_balance'], ['Paid out', 'paid_out']] as [$label, $key])
      <div class="t-card t-stat"><span class="t-stat__label">{{ __($label) }}</span><b class="t-stat__value">{{ $money($statement[$key]) }}</b></div>
    @endforeach
  </div>

  {{-- Layer 2: next payout, then the breakdown --}}
  <div class="lg-two">
    <section class="t-card" aria-labelledby="fin-next-h">
      <div class="t-card__head"><h2 class="t-card__title" id="fin-next-h">{{ __('Next payout') }}</h2>@if ($nextPayout['scheduled_at'])<span class="t-badge{{ $tone ? ' t-badge--' . $tone : '' }}">{{ __($nextPayout['label']) }}</span>@endif</div>
      <p class="lg-big">
        @if ($nextPayout['scheduled_at'])
          {{ \Carbon\Carbon::parse($nextPayout['scheduled_at'])->timezone(config('payouts.eligibility_policy.timezone', 'Africa/Lagos'))->format('D, j M Y \a\t g:i A') }}
        @else
          {{ __($nextPayout['label']) }}
        @endif
      </p>
      <p class="t-help">{{ __($nextPayout['message']) }}</p>
      @if ($nextPayout['reference'])<p class="t-help">{{ __('Request') }}: {{ $nextPayout['reference'] }}</p>@endif
    </section>
    <section class="t-card" aria-labelledby="fin-br-h">
      <div class="t-card__head"><h2 class="t-card__title" id="fin-br-h">{{ __('Breakdown') }}</h2></div>
      <dl class="lg-dl">
        <div><dt>{{ __('Gross sales') }}</dt><dd>{{ $money($statement['gross_sales']) }}</dd></div>
        <div><dt>{{ __('Platform fees') }}</dt><dd>−{{ $money($statement['platform_fees']) }}</dd></div>
        <div><dt>{{ __('Refunds') }}</dt><dd>−{{ $money($statement['refunds']) }}</dd></div>
        <div class="is-total"><dt>{{ __('Net sales') }}</dt><dd>{{ $money($statement['net_sales']) }}</dd></div>
        <div><dt>{{ __('Pending payouts') }}</dt><dd>{{ $money($statement['pending_payouts']) }}</dd></div>
        <div><dt>{{ __('Payout fees') }}</dt><dd>{{ $money($statement['payout_fees']) }}</dd></div>
      </dl>
      <p class="t-help">{{ __('Card processing fees are settled with Paystack and are not included here.') }}</p>
    </section>
  </div>

  {{-- Layer 3: the ledger --}}
  <section class="lg-ledger" aria-labelledby="fin-led-h">
    <div class="t-toolbar lg-ledger__head">
      <div><h2 class="t-card__title" id="fin-led-h">{{ __('Ledger activity') }}</h2><span class="t-help">{{ $statement['transaction_count'] }} {{ __('records in this period') }}</span></div>
      <div class="lg-actions">
        @foreach (['csv' => 'CSV', 'xlsx' => 'Excel', 'pdf' => 'PDF'] as $format => $label)
          <a class="t-btn t-btn--secondary t-btn--sm" href="{{ route('organizer.finance.export', ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d'), 'file_type' => $format]) }}">{{ __('Download') }} {{ $label }}</a>
        @endforeach
        <a class="t-btn t-btn--ghost t-btn--sm" href="{{ route('organizer.data_exports.index') }}">{{ __('Export history') }}</a>
      </div>
    </div>
    @if ($statement['transaction_count'] > config('exports.immediate_finance_rows', 10000))
      <p class="t-help lg-note">{{ __('CSV and Excel statements of this size are generated privately in the background. PDF is available for smaller ranges only.') }}</p>
    @endif
    @if ($transactions->count() === 0)
      <div class="t-card"><div class="t-empty"><h3>{{ __('No finance activity in this period') }}</h3><p>{{ __('Try a wider date range.') }}</p></div></div>
    @else
      <div class="t-table-wrap"><table class="t-table t-table--cards">
        <thead><tr><th scope="col">{{ __('Date') }}</th><th scope="col">{{ __('Reference') }}</th><th scope="col">{{ __('Type') }}</th><th scope="col">{{ __('Status') }}</th><th scope="col" class="t-num">{{ __('Gross') }}</th><th scope="col" class="t-num">{{ __('Fee') }}</th><th scope="col" class="t-num">{{ __('Net') }}</th></tr></thead>
        <tbody>
          @foreach ($transactions as $row)
            <tr>
              <td data-label="{{ __('Date') }}">{{ $row['occurred_at'] ? \Carbon\Carbon::parse($row['occurred_at'])->format('j M Y, g:i a') : '—' }}</td>
              <td data-label="{{ __('Reference') }}">{{ $row['reference'] }}</td>
              <td data-label="{{ __('Type') }}">{{ __(ucfirst(str_replace('_', ' ', $row['type']))) }}</td>
              <td data-label="{{ __('Status') }}"><span class="t-badge t-badge--{{ $row['status'] === 'completed' ? 'green' : ($row['status'] === 'rejected' ? 'red' : 'orange') }}">{{ __(ucfirst($row['status'])) }}</span></td>
              <td data-label="{{ __('Gross') }}" class="t-num">{{ $money($row['gross_amount']) }}</td>
              <td data-label="{{ __('Fee') }}" class="t-num">{{ $money($row['platform_fee']) }}</td>
              <td data-label="{{ __('Net') }}" class="t-num"><b>{{ $money($row['net_amount']) }}</b></td>
            </tr>
          @endforeach
        </tbody>
      </table></div>
      <div class="t-pager lg-pager">{{ $transactions->withQueryString()->links() }}</div>
    @endif
  </section>
@endsection
