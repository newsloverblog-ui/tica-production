@foreach ($collection as $item)
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
          @php
            $d_feilds = json_decode($item->feilds, true);
          @endphp
          <div class="">
            <p>{{ __('Total Payable Amount') }} :
              {{ $currencyInfo->base_currency_symbol_position == 'left' ? $currencyInfo->base_currency_symbol : '' }}
              {{ $item->payable_amount }}
              {{ $currencyInfo->base_currency_symbol_position == 'right' ? $currencyInfo->base_currency_symbol : '' }}
            </p>
            @if ($item->payoutAccount)
              <div class="alert alert-success py-2">
                <strong>{{ __('Verified payout destination') }}</strong>
                <div>{{ $item->payoutAccount->bank_name }}</div>
                <div>{{ $item->payoutAccount->resolved_account_name }}</div>
                <div class="font-weight-bold">{{ $item->payoutAccount->maskedNumber() }}</div>
                <small>{{ __('Verified') }} {{ optional($item->payoutAccount->verified_at)->format('Y-m-d H:i') }}</small>
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
    @if (config('payouts.paystack_transfers_enabled') && optional($item->payoutAccount)->provider === 'paystack' && optional($item->payoutAccount)->status === 'verified')
      <div class="modal fade" id="paystackModal{{ $item->id }}" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content">
          <form method="post" action="{{ route('admin.withdraw.submit_paystack', ['id' => $item->id]) }}">
            @csrf
            <div class="modal-header"><h4 class="modal-title">{{ __('Submit Paystack payout') }}</h4><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
            <div class="modal-body">
              <p>{{ __('This queues a real transfer to the verified destination below. The withdrawal stays reserved until a signed Paystack webhook or server verification confirms the final outcome.') }}</p>
              <div class="alert alert-info">
                <strong>{{ $item->payoutAccount->resolved_account_name }}</strong><br>
                {{ $item->payoutAccount->bank_name }} · {{ $item->payoutAccount->maskedNumber() }}<br>
                {{ __('Payable amount') }}: {{ number_format((float) $item->payable_amount, 2) }} {{ $item->payoutAccount->currency }}
              </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Cancel') }}</button><button type="submit" class="btn btn-primary">{{ __('Queue verified payout') }}</button></div>
          </form>
        </div></div>
      </div>
    @endif

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
