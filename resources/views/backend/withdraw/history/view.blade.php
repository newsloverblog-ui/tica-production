@foreach ($collection as $item)
  @php
    $account = $item->payoutAccount;
    $reviewable = in_array((int) $item->status, [\App\Models\Withdraw::STATUS_PENDING, \App\Models\Withdraw::STATUS_HELD], true);
    $fields = (array) json_decode($item->feilds ?? '[]', true);
    $blocker = $reviewable ? match (true) {
      !$payoutApprovalAvailable => __('Payout approvals require the database update.'),
      !$canManagePayout => __('Reviewing a payout requires an active admin with Withdraw Method permission.'),
      !$account => __('This request has no payout account attached. Finance needs to review the original request.'),
      (int) $account->organizer_id !== (int) $item->organizer_id
        => __('Finance needs to review the organiser and bank account on this request.'),
      $item->organizer?->kyc_status !== 'verified' => __('The organiser needs to complete identity verification before approval.'),
      $account->status !== 'verified' => __('The attached payout account needs verified status before approval.'),
      !$account->is_default => __('The attached account must still be the organiser’s default payout account.'),
      !in_array($account->provider, ['paystack', 'monnify'], true) || strtoupper((string) $account->currency) !== 'NGN'
        => __('The attached account must support NGN payouts through Paystack or Monnify.'),
      $account->hold_until && now()->lt($account->hold_until)
        => __('The payout account is on a security hold until :time.', ['time' => $account->hold_until->format('j M Y, H:i T')]),
      default => null,
    } : null;
    $fingerprint = $reviewable && !$blocker ? app(\App\Services\PayoutTransferService::class)->approvalFingerprint($item, $account) : null;
    $approval = $item->release_approval;
    $approved = $fingerprint && (int) data_get($approval, 'expires_at') > now()->getTimestamp()
      && hash_equals($fingerprint, (string) data_get($approval, 'fingerprint'));
    $canRelease = $approved && (int) data_get($approval, 'admin_id') !== (int) $payoutAdmin->id;
    $transfersEnabled = (bool) config('payouts.paystack_transfers_enabled');
    $currency = $account?->currency ?? $currencyInfo->base_currency_text ?? 'NGN';
  @endphp

  <div class="modal fade" id="withdrawModal{{ $item->id }}" tabindex="-1" role="dialog"
    aria-labelledby="withdrawTitle{{ $item->id }}" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h4 class="modal-title" id="withdrawTitle{{ $item->id }}">{{ $reviewable ? __('Review withdrawal') : __('Withdrawal details') }}</h4>
          <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('Close') }}">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <p class="mb-2">{{ __('Withdrawal reference') }} <strong class="text-break">{{ $item->withdraw_id }}</strong></p>
          <p>{{ __('Payable amount') }} <strong>{{ number_format((float) $item->payable_amount, 2) }} {{ $currency }}</strong></p>
          @if ($account)
            <div class="alert {{ $account->status === 'verified' ? 'alert-success' : 'alert-warning' }} py-2">
              <strong>{{ __('Payout destination') }}</strong>
              <div>{{ $account->bank_name }}</div>
              <div>{{ $account->resolved_account_name }}</div>
              <div class="font-weight-bold">{{ $account->maskedNumber() }}</div>
              <small>{{ __(ucfirst(str_replace('_', ' ', (string) $account->status))) }} · {{ ucfirst((string) $account->provider) }}</small>
            </div>
          @else
            @foreach ($fields as $key => $field)
              <p><strong>{{ str_replace('_', ' ', $key) }}</strong> {{ $field }}</p>
            @endforeach
          @endif

          @if ($item->latestPayoutTransfer)
            <p class="mb-1"><strong>{{ __('Latest payout transfer') }}</strong></p>
            <p>{{ __('Reference') }} <code>{{ $item->latestPayoutTransfer->reference }}</code></p>
            <p>{{ __('Status') }} {{ ucfirst(str_replace('_', ' ', $item->latestPayoutTransfer->status)) }}</p>
            @if ($item->latestPayoutTransfer->last_error)
              <div class="alert alert-warning py-2">{{ $item->latestPayoutTransfer->last_error }}</div>
            @endif
          @endif

          @if ($reviewable)
            @if ($blocker)
              <p class="alert alert-warning mb-0">{{ $blocker }}</p>
            @elseif ($approved)
              <p>{{ $canRelease
                ? __('Withdrawal approved. You can queue the payout for the amount and destination shown.')
                : __('Withdrawal approved. A different authorized admin can release it before the approval expires.') }}</p>
            @elseif ($canApprovePayout)
              <p>{{ __('Review the amount and destination before approving. A second authorized admin releases the payout.') }}</p>
            @else
              <p class="alert alert-warning">{{ __('Approval requires an active admin with Withdraw Method and Transaction permissions.') }}</p>
            @endif
            @if ($fingerprint && !$transfersEnabled)
              <p class="mb-0">{{ __('Provider transfers are disabled. After approval, a second authorized admin can record a completed external payment.') }}</p>
            @endif
          @endif
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Close') }}</button>
          @if ($fingerprint && $canApprovePayout && !$approved)
            <form method="post" action="{{ route('admin.withdraw.approve_release', ['id' => $item->id]) }}">
              @csrf
              <input type="hidden" name="review_fingerprint" value="{{ $fingerprint }}">
              <button type="submit" class="btn btn-primary">{{ __('Approve withdrawal') }}</button>
            </form>
          @elseif ($canRelease && $transfersEnabled)
            <form method="post" action="{{ route('admin.withdraw.submit_paystack', ['id' => $item->id]) }}">
              @csrf
              <button type="submit" class="btn btn-primary">{{ __('Queue approved payout') }}</button>
            </form>
          @endif
        </div>
      </div>
    </div>
  </div>

  @if ($reviewable)
    <div class="modal fade" id="paidModal{{ $item->id }}" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content">
        <form method="post" action="{{ route('admin.witdraw.approve_withdraw', ['id' => $item->id]) }}">
          @csrf
          <div class="modal-header"><h4 class="modal-title">{{ __('Record completed payout') }}</h4><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
          <div class="modal-body">
            <p>{{ __('Only mark this request paid after the transfer has completed outside Tica.') }}</p>
            <label>{{ __('Bank or gateway payout reference') }} *</label>
            <input name="payout_reference" class="form-control" maxlength="191" required>
          </div>
          <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Cancel') }}</button><button type="submit" class="btn btn-success">{{ __('Mark paid') }}</button></div>
        </form>
      </div></div>
    </div>

    <div class="modal fade" id="rejectModal{{ $item->id }}" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content">
        <form method="post" action="{{ route('admin.witdraw.decline_withdraw', ['id' => $item->id]) }}">
          @csrf
          <div class="modal-header"><h4 class="modal-title">{{ __('Reject payout request') }}</h4><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
          <div class="modal-body">
            <p>{{ __('The reserved amount will be restored to the organizer balance.') }}</p>
            <label>{{ __('Reason') }} *</label><textarea name="decision_reason" class="form-control" rows="4" maxlength="2000" required></textarea>
          </div>
          <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Cancel') }}</button><button type="submit" class="btn btn-danger">{{ __('Reject and restore balance') }}</button></div>
        </form>
      </div></div>
    </div>
  @endif

  @if ((int) $item->status === 0)
    <div class="modal fade" id="holdModal{{ $item->id }}" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content">
        <form method="post" action="{{ route('admin.witdraw.hold_withdraw', ['id' => $item->id]) }}">
          @csrf
          <div class="modal-header"><h4 class="modal-title">{{ __('Place payout on hold') }}</h4><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
          <div class="modal-body"><label>{{ __('Review reason') }} *</label><textarea name="decision_reason" class="form-control" rows="4" maxlength="2000" required></textarea></div>
          <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Cancel') }}</button><button type="submit" class="btn btn-warning">{{ __('Place on hold') }}</button></div>
        </form>
      </div></div>
    </div>
  @endif
@endforeach
