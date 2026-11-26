@php
  $payoutAdmin = Auth::guard('admin')->user();
@endphp
@foreach ($collection as $item)
  @php
    $account = $item->payoutAccount;
    $d_feilds = json_decode($item->feilds ?? '[]', true) ?? [];
  @endphp
  <div class="modal fade" id="withdrawModal{{ $item->id }}" tabindex="-1" role="dialog"
    aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h4 class="modal-title" id="exampleModalLongTitle">{{ __('Withdraw Information') }}</h4>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>

        <div class="modal-body">
          <div class="">
            <p>{{ __('Total Payable Amount') }} :
              {{ $currencyInfo->base_currency_symbol_position == 'left' ? $currencyInfo->base_currency_symbol : '' }}
              {{ $item->payable_amount }}
              {{ $currencyInfo->base_currency_symbol_position == 'right' ? $currencyInfo->base_currency_symbol : '' }}
            </p>
            @if ($account)
              <div class="alert {{ $account->status === 'verified' ? 'alert-success' : 'alert-warning' }} py-2">
                <strong>{{ __('Payout destination') }}</strong>
                <div>{{ $account->bank_name }}</div>
                <div>{{ $account->resolved_account_name }}</div>
                <div class="font-weight-bold">{{ $account->maskedNumber() }}</div>
                <small>{{ __(ucfirst(str_replace('_', ' ', (string) $account->status))) }} · {{ ucfirst((string) $account->provider) }}</small>
              </div>
            @else
              <div class="alert alert-warning py-2">{{ __('Legacy withdrawal: no verified payout-account record is attached. Review manually before approval.') }}</div>
            @endif
            @foreach ($d_feilds as $key => $d_feild)
              <p><strong>{{ str_replace('_', ' ', $key) }} : {{ $d_feild }}</strong></p>
            @endforeach
            @if ($item->latestPayoutTransfer)
              <hr>
              <p><strong>{{ __('Latest Paystack transfer') }}</strong></p>
              <p>{{ __('Reference') }}: <code>{{ $item->latestPayoutTransfer->reference }}</code></p>
              <p>{{ __('Status') }}: {{ ucfirst(str_replace('_', ' ', $item->latestPayoutTransfer->status)) }}</p>
              @if ($item->latestPayoutTransfer->last_error)
                <div class="alert alert-warning py-2">{{ $item->latestPayoutTransfer->last_error }}</div>
              @endif
            @endif
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
            {{ __('Close') }}
          </button>
        </div>
      </div>
    </div>
  </div>

  @if (in_array((int) $item->status, [0, 3], true))
      @php
        $releaseBlocker = match (true) {
          !$payoutApprovalAvailable => __('Payout approvals require the database update.'),
          !$account => __('This request has no payout account attached. Finance needs to review the original request.'),
          (int) $account->organizer_id !== (int) $item->organizer_id
            => __('Finance needs to review the organiser and bank account on this request.'),
          $account->status !== 'verified' => __('The attached payout account needs verified status before release.'),
          !$account->is_default => __('The attached account must still be the organiser’s default payout account.'),
          !in_array($account->provider, ['paystack', 'monnify'], true) || strtoupper($account->currency) !== 'NGN'
            => __('The attached account must support NGN payouts through Paystack or Monnify.'),
          $account->hold_until && now()->lt($account->hold_until)
            => __('The payout account is on a security hold until :time.', ['time' => $account->hold_until->format('j M Y, H:i T')]),
          default => null,
        };
        $fingerprint = $releaseBlocker ? null : app(\App\Services\PayoutTransferService::class)->approvalFingerprint($item, $account);
        $canManage = (int) ($payoutAdmin?->status) === 1 && $payoutAdmin->hasPermission('Withdraw Method');
        $canApprove = $canManage && $payoutAdmin->hasPermission('Transaction');
        $approval = $item->release_approval;
        $approved = $fingerprint && (int) data_get($approval, 'expires_at') > now()->getTimestamp()
          && hash_equals($fingerprint, (string) data_get($approval, 'fingerprint'));
        $transfersEnabled = config('payouts.paystack_transfers_enabled');
        $canQueue = $canManage && $approved && (int) data_get($approval, 'admin_id') !== (int) $payoutAdmin->id && $transfersEnabled;
      @endphp
      <div class="modal fade" id="paystackModal{{ $item->id }}" tabindex="-1" role="dialog" aria-labelledby="payoutReviewTitle{{ $item->id }}" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content">
          <form method="post" action="{{ route('admin.withdraw.submit_paystack', ['id' => $item->id]) }}">
            @csrf
            <div class="modal-header"><h4 class="modal-title" id="payoutReviewTitle{{ $item->id }}">{{ __('Review payout release') }}</h4><button type="button" class="close" data-dismiss="modal" aria-label="{{ __('Close') }}"><span aria-hidden="true">&times;</span></button></div>
            <div class="modal-body">
              <div class="alert alert-info">
                @if ($account)
                  <strong>{{ $account->resolved_account_name }}</strong><br>
                  {{ $account->bank_name }} · {{ $account->maskedNumber() }}<br>
                @endif
                {{ __('Payable amount') }}: {{ number_format((float) $item->payable_amount, 2) }} {{ $account?->currency ?? 'NGN' }}
              </div>
              @if ($releaseBlocker)
                <p class="alert alert-warning">{{ $releaseBlocker }}</p>
                <a href="{{ route('admin.payout_accounts.index', ['status' => 'all', 'q' => $account?->account_last4]) }}">{{ __('Open payout accounts') }}</a>
              @else
                @if ($approved)
                  <p>{{ __('Release approved. A different authorized admin can release the payout before approval expires.') }}</p>
                @else
                  <p>{{ __('An authorized admin must approve the amount and destination. A different admin can then release the payout.') }}</p>
                  @unless ($canApprove)
                    <p class="alert alert-warning">{{ __('Approval requires an active admin with Withdraw Method and Transaction permissions.') }}</p>
                  @endunless
                @endif
                @unless ($transfersEnabled)
                  <p class="alert alert-warning">{{ __('Provider transfers are disabled. Finance can record a completed external payment after approval.') }}</p>
                @endunless
              @endif
            </div>
            <div class="modal-footer">
              @if ($fingerprint && $canApprove)
                <input type="hidden" name="review_fingerprint" value="{{ $fingerprint }}">
                <button type="submit" formaction="{{ route('admin.withdraw.approve_release', ['id' => $item->id]) }}" class="btn btn-outline-primary">{{ __('Approve release') }}</button>
              @endif
              @if ($canQueue)
                <button type="submit" class="btn btn-primary">{{ __('Queue approved payout') }}</button>
              @endif
              <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Close') }}</button>
            </div>
          </form>
        </div></div>
      </div>
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
