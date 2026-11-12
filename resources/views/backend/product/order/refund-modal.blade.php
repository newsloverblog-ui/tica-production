@if ($order->payment_status === 'completed' && $order->refund_status !== 'refunded')
  @php($providerRefund = optional($order->payment_intent)->gateway === 'paystack' && optional($order->payment_intent)->status === 'completed')
  <div class="modal fade" id="productRefundModal-{{ $order->id }}" tabindex="-1" role="dialog"
    aria-labelledby="productRefundModalLabel-{{ $order->id }}" aria-hidden="true">
    <div class="modal-dialog" role="document">
      <form method="POST" action="{{ route('admin.product_order.refunds.store') }}">
        @csrf
        <input type="hidden" name="product_order_id" value="{{ $order->id }}">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="productRefundModalLabel-{{ $order->id }}">{{ $providerRefund ? __('Request full Paystack refund') : __('Record full product refund') }}</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('Close') }}">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <div class="alert alert-warning">
              {{ $providerRefund
                ? __('This submits a full refund to Paystack. The order, earnings and inventory will change only after Paystack confirms the refund as processed.')
                : __('This records an externally completed full refund, reverses platform earnings, creates a permanent reversal ledger entry and cancels the order. It does not send money through a gateway.') }}
            </div>
            <p><strong>{{ __('Refund amount:') }}</strong>
              {{ $order->currency_symbol_position === 'left' ? $order->currency_symbol : '' }}{{ number_format((float) $order->total, 2) }}{{ $order->currency_symbol_position === 'right' ? $order->currency_symbol : '' }}
            </p>
            @unless ($providerRefund)
            <div class="form-group">
              <label for="external-reference-{{ $order->id }}">{{ __('External refund reference') }}</label>
              <input id="external-reference-{{ $order->id }}" class="form-control" name="external_reference"
                maxlength="191" required autocomplete="off">
              <small class="form-text text-muted">{{ __('Use the unique bank, cash-desk, or provider refund receipt reference.') }}</small>
            </div>
            @endunless
            <div class="form-group">
              <label for="refund-reason-{{ $order->id }}">{{ __('Reason and evidence note') }}</label>
              <textarea id="refund-reason-{{ $order->id }}" class="form-control" name="reason" rows="4"
                maxlength="2000" required></textarea>
            </div>
            @if ($order->order_status !== 'completed')
              <input type="hidden" name="restore_inventory" value="1">
              <div class="form-group form-check">
                <input class="form-check-input" type="checkbox" checked disabled>
                <label class="form-check-label">{{ __('Restore reserved physical inventory (required for an unfulfilled order)') }}</label>
              </div>
            @else
              <div class="form-group form-check">
                <input class="form-check-input" type="checkbox" name="restore_inventory" value="1"
                  id="restore-inventory-{{ $order->id }}">
                <label class="form-check-label" for="restore-inventory-{{ $order->id }}">
                  {{ __('Returned physical items were verified and may be restored to stock') }}
                </label>
              </div>
            @endif
            <div class="form-group form-check">
              <input class="form-check-input" type="checkbox" name="confirm_full_refund" value="1"
                id="confirm-refund-{{ $order->id }}" required>
              <label class="form-check-label" for="confirm-refund-{{ $order->id }}">
                {{ $providerRefund
                  ? __('I authorize this full Paystack refund and understand that it will be submitted asynchronously.')
                  : __('I verified the external full refund and understand this action cannot be reversed from the interface.') }}
              </label>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Cancel') }}</button>
            <button type="submit" class="btn btn-warning">{{ $providerRefund ? __('Request Paystack refund') : __('Record and reconcile refund') }}</button>
          </div>
        </div>
      </form>
    </div>
  </div>
@endif
