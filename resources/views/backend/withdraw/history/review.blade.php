@extends('backend.layout')

@section('content')
  @php
    $account = $item->payoutAccount;
    $currency = $account?->currency ?? $currencyInfo->base_currency_text ?? 'NGN';
    $money = fn ($amount) => number_format((float) $amount, 2) . ' ' . $currency;
    $transfer = $item->latestPayoutTransfer;
    $returnFilters = request()->only(['search', 'status', 'from_date', 'to_date', 'language', 'page']);
  @endphp

  <section class="withdraw-queue withdraw-review" aria-labelledby="withdraw-review-title">
    <a class="withdraw-review__back" href="{{ route('admin.withdraw.withdraw_request', $returnFilters) }}">{{ __('Back to withdrawal requests') }}</a>
    <header class="page-header withdraw-review__header">
      <div>
        <h1 class="page-title" id="withdraw-review-title">{{ $review ? __('Review withdrawal') : __('Withdrawal details') }}</h1>
        <p class="withdraw-queue__meta mb-0"><bdi>{{ $item->withdraw_id }}</bdi></p>
      </div>
      <div>@include('backend.withdraw.history.status')</div>
    </header>

    @include('backend.withdraw.history.notices')

    <div class="withdraw-review__layout">
      <div class="card">
        <div class="card-body withdraw-review__summary">
          <h2>{{ __('Request and destination') }}</h2>
          <dl class="withdraw-review__amounts">
            <div><dt>{{ __('Requested') }}</dt><dd class="t-num">{{ $money($item->amount) }}</dd></div>
            <div><dt>{{ __('Withdrawal fee') }}</dt><dd class="t-num">{{ $money($item->total_charge) }}</dd></div>
            <div><dt>{{ __('Payable amount') }}</dt><dd class="t-num">{{ $money($item->payable_amount) }}</dd></div>
          </dl>

          <div class="withdraw-review__destination">
            <h3>{{ __('Payout destination') }}</h3>
            @if ($account)
              <strong>{{ $account->resolved_account_name ?? '—' }}</strong>
              <p>{{ $account->bank_name }} · <bdi>{{ $account->maskedNumber() }}</bdi></p>
              <p class="withdraw-queue__meta">{{ __(ucfirst(str_replace('_', ' ', (string) $account->status))) }} · {{ ucfirst((string) $account->provider) }}</p>
            @else
              <p>{{ __('No verified payout account is linked to this request.') }}</p>
            @endif
          </div>

          <details class="withdraw-review__option">
            <summary>{{ __('Request details') }}</summary>
            <dl class="withdraw-review__facts">
              <div><dt>{{ __('Organizer') }}</dt><dd>{{ $item->organizer?->username ?? __('Organizer unavailable') }}</dd></div>
              <div><dt>{{ __('Requested on') }}</dt><dd>{{ $item->created_at?->translatedFormat('j M Y, H:i') ?? '—' }}</dd></div>
              @if ($item->method)
                <div><dt>{{ __('Payment method') }}</dt><dd>{{ $item->method->name }}</dd></div>
              @endif
            </dl>
          </details>
        </div>
      </div>

      <div class="withdraw-review__side">
        <section class="card" aria-labelledby="withdraw-next-step">
          <div class="card-body withdraw-review__decision">
            <h2 id="withdraw-next-step">{{ __('Next step') }}</h2>
            @include('backend.withdraw.history.view')
          </div>
        </section>

        @if ($transfer || $item->decision_reason || $item->paid_at || $item->payout_reference)
          <section class="card" aria-labelledby="withdraw-activity">
            <div class="card-body withdraw-review__summary">
              <h2 id="withdraw-activity">{{ __('Latest activity') }}</h2>
              <dl class="withdraw-review__facts">
                @if ($transfer)
                  <div><dt>{{ __('Transfer status') }}</dt><dd>{{ __(ucfirst(str_replace('_', ' ', $transfer->status))) }}</dd></div>
                  <div><dt>{{ __('Transfer reference') }}</dt><dd><code><bdi>{{ $transfer->reference }}</bdi></code></dd></div>
                  @if ($transfer->release_source)
                    <div><dt>{{ __('Release method') }}</dt><dd>{{ $transfer->release_source === 'automatic' ? __('Automatic') : __('Manual') }}</dd></div>
                  @endif
                  @if ($transfer->last_error)
                    <div><dt>{{ __('Provider message') }}</dt><dd>{{ $transfer->last_error }}</dd></div>
                  @endif
                @endif
                @if ($item->payout_reference && $item->payout_reference !== $transfer?->reference)
                  <div><dt>{{ __('Recorded payment reference') }}</dt><dd><code><bdi>{{ $item->payout_reference }}</bdi></code></dd></div>
                @endif
                @if ($item->decision_reason)
                  <div><dt>{{ __('Review note') }}</dt><dd>{{ $item->decision_reason }}</dd></div>
                @endif
                @if ($item->paid_at)
                  <div><dt>{{ __('Paid on') }}</dt><dd>{{ $item->paid_at->translatedFormat('j M Y, H:i') }}</dd></div>
                @endif
              </dl>
            </div>
          </section>
        @endif
      </div>
    </div>
  </section>
@endsection
