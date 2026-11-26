@if (!$review)
  <p class="mb-0">
    {{ match ((int) $item->status) {
      \App\Models\Withdraw::STATUS_PROCESSING => __('The transfer is processing. Check the latest activity for its provider reference and status.'),
      \App\Models\Withdraw::STATUS_PAID => __('The payout has been recorded as paid.'),
      \App\Models\Withdraw::STATUS_REJECTED => __('This request was rejected and the reserved amount was returned to the organizer’s balance.'),
      default => __('Review the request status and latest activity.'),
    } }}
  </p>
@else
  @php
    $failedAction = data_get(session('payout_review_notice'), 'action');
  @endphp

  @if ($review['blocker'])
    <p class="alert alert-warning">{{ $review['blocker'] }}</p>
  @elseif ($review['can_approve'])
    <p>{{ __('Confirm the amount and masked bank details. Manual release requires a second authorized admin after approval.') }}</p>
    <form method="post" action="{{ route('admin.withdraw.approve_release', $item->id) }}">
      @csrf
      <input type="hidden" name="review_fingerprint" value="{{ $review['fingerprint'] }}">
      <button type="submit" class="btn btn-primary">{{ __('Approve withdrawal') }}</button>
    </form>
  @elseif ($review['approved'])
    <p>{{ match (true) {
      !$review['can_release'] => __('Withdrawal approved. A different authorized admin can release it before the approval expires.'),
      $review['can_queue'] => __('Approval is on file. Confirm the amount and destination, then queue the payout through the provider.'),
      $review['can_record_external'] => __('Provider transfers are unavailable. Record the payment reference after an external transfer has cleared.'),
      default => __('Provider transfers are unavailable. Reconcile the existing transfer before taking another action.'),
    } }}</p>
    <p class="withdraw-queue__meta mb-0">{{ __('Approval expires :time', ['time' => \Illuminate\Support\Carbon::createFromTimestamp($review['expires_at'], config('app.timezone'))->translatedFormat('j M Y, H:i T')]) }}</p>
    @if ($review['can_queue'])
      <form method="post" action="{{ route('admin.withdraw.submit_paystack', $item->id) }}">
        @csrf
        <button type="submit" class="btn btn-primary">{{ __('Queue approved payout') }}</button>
      </form>
    @endif
  @endif

  @if ($review['can_record_external'])
    <details class="withdraw-review__option" @if (!$review['can_queue'] || $failedAction === 'external' || $errors->external->isNotEmpty()) open @endif>
      <summary>{{ __('Record external payment') }}</summary>
      <form method="post" action="{{ route('admin.witdraw.approve_withdraw', $item->id) }}">
        @csrf
        <p>{{ __('Use this after a transfer outside TICA has cleared.') }}</p>
        <label for="withdraw-external-reference">{{ __('Bank or gateway reference') }}</label>
        <input class="form-control @error('payout_reference', 'external') is-invalid @enderror" id="withdraw-external-reference"
          name="payout_reference" maxlength="191" value="{{ old('payout_reference') }}" required>
        @error('payout_reference', 'external')
          <span class="invalid-feedback" role="alert">{{ $message }}</span>
        @enderror
        <button type="submit" class="btn btn-{{ $review['can_queue'] ? 'outline-primary' : 'primary' }}">{{ __('Record completed payment') }}</button>
      </form>
    </details>
  @endif

  <div class="withdraw-review__options">
    @if ((int) $item->status === \App\Models\Withdraw::STATUS_PENDING)
      <details class="withdraw-review__option" @if ($failedAction === 'hold' || $errors->hold->isNotEmpty()) open @endif>
        <summary>{{ __('Place on hold') }}</summary>
        <form method="post" action="{{ route('admin.witdraw.hold_withdraw', $item->id) }}">
          @csrf
          <p>{{ __('Hold the request for finance review. A fresh approval is required to release it.') }}</p>
          <label for="withdraw-hold-reason">{{ __('Hold reason') }}</label>
          <textarea class="form-control @error('decision_reason', 'hold') is-invalid @enderror" id="withdraw-hold-reason"
            name="decision_reason" rows="3" maxlength="2000" required>{{ $failedAction === 'hold' || $errors->hold->isNotEmpty() ? old('decision_reason') : '' }}</textarea>
          @error('decision_reason', 'hold')
            <span class="invalid-feedback" role="alert">{{ $message }}</span>
          @enderror
          <button type="submit" class="btn btn-outline-secondary">{{ __('Confirm hold') }}</button>
        </form>
      </details>
    @endif

    <details class="withdraw-review__option withdraw-review__option--reject" @if ($failedAction === 'reject' || $errors->reject->isNotEmpty()) open @endif>
      <summary>{{ __('Reject withdrawal') }}</summary>
      <form method="post" action="{{ route('admin.witdraw.decline_withdraw', $item->id) }}">
        @csrf
        <p>{{ __('Rejecting this request returns the reserved amount to the organizer’s balance.') }}</p>
        <label for="withdraw-reject-reason">{{ __('Rejection reason') }}</label>
        <textarea class="form-control @error('decision_reason', 'reject') is-invalid @enderror" id="withdraw-reject-reason"
          name="decision_reason" rows="3" maxlength="2000" required>{{ $failedAction === 'reject' || $errors->reject->isNotEmpty() ? old('decision_reason') : '' }}</textarea>
        @error('decision_reason', 'reject')
          <span class="invalid-feedback" role="alert">{{ $message }}</span>
        @enderror
        <button type="submit" class="btn btn-danger">{{ __('Reject and restore balance') }}</button>
      </form>
    </details>
  </div>
@endif
