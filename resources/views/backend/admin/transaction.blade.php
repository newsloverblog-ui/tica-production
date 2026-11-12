@extends('backend.layout')

@section('content')
  <div class="page-header">
    <h4 class="page-title">{{ __('Transactions') }}</h4>
    <ul class="breadcrumbs">
      <li class="nav-home">
        <a href="{{ route('admin.dashboard') }}">
          <i class="flaticon-home"></i>
        </a>
      </li>
      <li class="separator">
        <i class="flaticon-right-arrow"></i>
      </li>
      <li class="nav-item">
        <a href="#">{{ __('Transactions') }}</a>
      </li>
    </ul>
  </div>

  @if ($refundDisputes->isNotEmpty())
    <div class="row">
      <div class="col-md-12">
        <div class="card border-danger">
          <div class="card-header">
            <div class="card-title">{{ __('Checked-in refund disputes') }}</div>
          </div>
          <div class="card-body table-responsive">
            <p class="text-muted">
              {{ __('These payments include an admission already used at check-in. Approve only after independently reviewing entry evidence and the written exception. Approval queues a provider refund; accounting changes only after provider confirmation and check-in history is never erased.') }}
            </p>
            <table class="table table-bordered">
              <thead>
                <tr>
                  <th>{{ __('Case') }}</th>
                  <th>{{ __('Booking') }}</th>
                  <th>{{ __('Amount') }}</th>
                  <th>{{ __('Evidence summary') }}</th>
                  <th>{{ __('Actions') }}</th>
                </tr>
              </thead>
              <tbody>
                @foreach ($refundDisputes as $dispute)
                  <tr>
                    <td>
                      <code>{{ $dispute->public_id }}</code><br>
                      <span class="badge badge-warning">{{ __(ucwords(str_replace('_', ' ', $dispute->status))) }}</span><br>
                      <small>{{ optional($dispute->opened_at)->translatedFormat('M d, Y h:i a') }}</small>
                    </td>
                    <td>
                      #{{ optional($dispute->booking)->booking_id ?: $dispute->booking_id }}<br>
                      <small>{{ optional($dispute->organizer)->username ?: __('Platform event') }}</small>
                    </td>
                    <td>
                      {{ number_format((float) $dispute->requested_amount, 2) }} {{ $dispute->currency }}<br>
                      <small>{{ trans_choice(':count checked-in ticket|:count checked-in tickets', $dispute->checked_in_ticket_count, ['count' => $dispute->checked_in_ticket_count]) }}</small>
                    </td>
                    <td style="min-width: 260px">
                      <strong>{{ __(ucwords(str_replace('_', ' ', $dispute->category))) }}</strong><br>
                      {{ $dispute->description }}
                      @if ($dispute->resolution)<hr class="my-2"><small>{{ $dispute->resolution }}</small>@endif
                    </td>
                    <td style="min-width: 320px">
                      <form method="post" action="{{ route('admin.refund_disputes.review', $dispute) }}" class="mb-3">
                        @csrf
                        <input type="hidden" name="status" value="under_review">
                        <textarea class="form-control mb-2" name="resolution" rows="2" maxlength="2000" placeholder="{{ __('Optional internal review note') }}"></textarea>
                        <button class="btn btn-info btn-sm" type="submit">{{ __('Mark under review') }}</button>
                      </form>
                      <details>
                        <summary class="text-success mb-2" style="cursor: pointer">{{ __('Approve exception and queue refund') }}</summary>
                        <form method="post" action="{{ route('admin.refund_disputes.approve', $dispute) }}">
                          @csrf
                          <textarea class="form-control mb-2" name="resolution" rows="3" minlength="10" maxlength="2000" placeholder="{{ __('Decision and evidence reviewed') }}" required></textarea>
                          <label class="d-block mb-2">
                            <input type="checkbox" name="confirm_approval" value="1" required>
                            {{ __('I reviewed the check-in evidence and authorize the provider refund.') }}
                          </label>
                          <button class="btn btn-success btn-sm" type="submit">{{ __('Approve and queue') }}</button>
                        </form>
                      </details>
                      <details class="mt-3">
                        <summary class="text-danger" style="cursor: pointer">{{ __('Reject with resolution') }}</summary>
                        <form method="post" action="{{ route('admin.refund_disputes.review', $dispute) }}" class="mt-2">
                          @csrf
                          <input type="hidden" name="status" value="rejected">
                          <textarea class="form-control mb-2" name="resolution" rows="3" minlength="10" maxlength="2000" required></textarea>
                          <button class="btn btn-danger btn-sm" type="submit">{{ __('Reject dispute') }}</button>
                        </form>
                      </details>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  @endif

  @if ($organizerRefundRequests->isNotEmpty())
    <div class="row">
      <div class="col-md-12">
        <div class="card border-warning">
          <div class="card-header">
            <div class="card-title">{{ __('Organizer refund approvals') }}</div>
          </div>
          <div class="card-body table-responsive">
            <p class="text-muted">
              {{ __('Approval queues a controlled provider refund. Financial balances and ticket status remain unchanged until the provider confirms a final successful outcome.') }}
            </p>
            <table class="table table-bordered">
              <thead>
                <tr>
                  <th>{{ __('Reference') }}</th>
                  <th>{{ __('Organizer') }}</th>
                  <th>{{ __('Booking') }}</th>
                  <th>{{ __('Gateway') }}</th>
                  <th>{{ __('Amount') }}</th>
                  <th>{{ __('Reason') }}</th>
                  <th>{{ __('Actions') }}</th>
                </tr>
              </thead>
              <tbody>
                @foreach ($organizerRefundRequests as $refundRequest)
                  <tr>
                    <td><code>{{ $refundRequest->public_id }}</code></td>
                    <td>{{ optional($refundRequest->organizer)->username ?: '#'.$refundRequest->organizer_id }}</td>
                    <td>#{{ optional($refundRequest->booking)->booking_id ?: $refundRequest->booking_id }}</td>
                    <td>{{ ucfirst($refundRequest->gateway) }}</td>
                    <td>{{ number_format((float) $refundRequest->amount, 2) }} {{ $refundRequest->currency }}</td>
                    <td style="min-width: 220px">{{ $refundRequest->reason }}</td>
                    <td style="min-width: 260px">
                      <form method="post" action="{{ route('admin.refunds.approve', $refundRequest) }}" class="d-inline-block mb-2">
                        @csrf
                        <button type="submit" class="btn btn-success btn-sm">{{ __('Approve and queue') }}</button>
                      </form>
                      <details>
                        <summary class="text-danger" style="cursor: pointer">{{ __('Reject with note') }}</summary>
                        <form method="post" action="{{ route('admin.refunds.reject', $refundRequest) }}" class="mt-2">
                          @csrf
                          <textarea class="form-control mb-2" name="rejection_reason" rows="3" minlength="10" maxlength="1000" required></textarea>
                          <button type="submit" class="btn btn-danger btn-sm">{{ __('Reject request') }}</button>
                        </form>
                      </details>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  @endif

  <div class="row">
    <div class="col-md-12">
      <div class="card">
        <div class="card-header">
          <div class="row mb-3">
            <div class="col-lg-12">
              <div class="card-title d-inline-block">{{ __('Transactions') }}</div>
              <a href="{{ route('admin.payment_reconciliation.index') }}" class="btn btn-sm btn-secondary ml-2">{{ __('Reconciliation') }}</a>
              <a href="{{ route('admin.funnel_analytics.index') }}" class="btn btn-sm btn-secondary ml-2">{{ __('Conversion analytics') }}</a>
              <a href="{{ route('admin.audit_logs.index') }}" class="btn btn-sm btn-secondary ml-2">{{ __('Audit history') }}</a>
              <a href="{{ route('admin.operational_incidents.index') }}" class="btn btn-sm btn-secondary ml-2">{{ __('Incidents') }}</a>
              <a href="{{ route('admin.webhook_operations.index') }}" class="btn btn-sm btn-secondary ml-2">{{ __('Webhooks') }}</a>
            </div>
          </div>
          <form action="{{ route('admin.transcation') }}" method="get" class="row align-items-end">
            <div class="col-md-3 form-group"><label>{{ __('Transaction reference') }}</label><input type="search" maxlength="100" value="{{ request('transcation_id') }}" name="transcation_id" placeholder="{{ __('Search reference') }}" class="form-control"></div>
            <div class="col-md-2 form-group"><label>{{ __('Type') }}</label><select name="transaction_type" class="form-control"><option value="">{{ __('All types') }}</option>@foreach([1=>'Event booking',2=>'Product order',3=>'Withdrawal',4=>'Balance credit',5=>'Balance debit',6=>'Event refund',7=>'Product refund'] as $value=>$label)<option value="{{ $value }}" @selected((string)request('transaction_type')===(string)$value)>{{ __($label) }}</option>@endforeach</select></div>
            <div class="col-md-2 form-group"><label>{{ __('Status') }}</label><select name="payment_status" class="form-control"><option value="">{{ __('All statuses') }}</option><option value="0" @selected(request('payment_status')==='0')>{{ __('Unpaid') }}</option><option value="1" @selected(request('payment_status')==='1')>{{ __('Paid') }}</option><option value="2" @selected(request('payment_status')==='2')>{{ __('Declined') }}</option></select></div>
            <div class="col-md-2 form-group"><label>{{ __('From') }}</label><input type="date" name="from_date" class="form-control" value="{{ request('from_date') }}"></div>
            <div class="col-md-2 form-group"><label>{{ __('To') }}</label><input type="date" name="to_date" class="form-control" value="{{ request('to_date') }}"></div>
            <div class="col-md-1 form-group"><button class="btn btn-primary btn-block" type="submit">{{ __('Filter') }}</button></div>
          </form>
          <div class="d-flex flex-wrap align-items-center border-top pt-3">
            <form method="POST" action="{{ route('admin.transaction_exports.store') }}" class="form-inline mr-2 mb-2">@csrf
              <input type="hidden" name="transcation_id" value="{{ request('transcation_id') }}"><input type="hidden" name="transaction_type" value="{{ request('transaction_type') }}"><input type="hidden" name="payment_status" value="{{ request('payment_status') }}"><input type="hidden" name="from_date" value="{{ request('from_date') }}"><input type="hidden" name="to_date" value="{{ request('to_date') }}">
              <select name="format" class="form-control form-control-sm mr-2" required><option value="csv">CSV</option><option value="xlsx">XLSX</option></select><button class="btn btn-success btn-sm" type="submit"><i class="fas fa-file-export"></i> {{ __('Queue private export') }}</button>
            </form><a class="btn btn-outline-primary btn-sm mb-2 mr-2" href="{{ route('admin.transaction_exports.index') }}">{{ __('Export history') }}</a><a class="btn btn-outline-secondary btn-sm mb-2" href="{{ route('admin.transcation') }}">{{ __('Reset filters') }}</a>
          </div>
        </div>

        <div class="card-body">
          <div class="row">
            <div class="col-lg-12">
              @if (count($transcations) == 0)
                <h3 class="text-center mt-3">{{ __('NO TRANSACTION FOUND') . '!' }}</h3>
              @else
                <div class="table-responsive">
                  <table class="table table-striped mt-3">
                    <thead>
                      <tr>
                        <th scope="col">{{ __('Transaction Id') }}</th>
                        <th scope="col">{{ __('Organizer') }}</th>
                        <th scope="col">{{ __('Transaction Type') }}</th>
                        <th scope="col">{{ __('Payment Method') }}</th>
                        <th scope="col">{{ __('Pre Balance') }}</th>
                        <th scope="col">{{ __('Amount') }}</th>
                        <th scope="col">{{ __('After Balance') }}</th>
                        <th scope="col">{{ __('Status') }}</th>
                        <th scope="col">{{ __('Refund') }}</th>
                        <th scope="col">{{ __('Actions') }}</th>
                      </tr>
                    </thead>
                    <tbody>
                      @foreach ($transcations as $transcation)
                        <tr>
                          <td>#{{ $transcation->transcation_id }}</td>
                          <td>
                            @php
                              $organizer = $transcation->organizer()->first();
                            @endphp
                            @if ($organizer)
                              <a target="_blank"
                                href="{{ route('admin.organizer_management.organizer_details', ['id' => $organizer->id, 'language' => $defaultLang->code]) }}">{{ $organizer->username }}</a>
                            @else
                              <span class="badge badge-success">{{ __('Admin') }}</span>
                            @endif
                          </td>
                          <td>
                            @if ($transcation->transcation_type == 1)
                              {{ 'Event Booking' }}
                            @elseif ($transcation->transcation_type == 2)
                              {{ 'Product Order' }}
                            @elseif ($transcation->transcation_type == 3)
                              {{ 'Withdraw' }}
                            @elseif ($transcation->transcation_type == 4)
                              {{ 'Balance Add' }}
                            @elseif ($transcation->transcation_type == 5)
                              {{ 'Balance Subtract' }}
                            @elseif ($transcation->transcation_type == 6)
                              {{ 'Payment Refund' }}
                            @elseif ($transcation->transcation_type == 7)
                              {{ 'Product Refund' }}
                            @endif
                          </td>
                          <td>
                            @if ($transcation->transcation_type == 3)
                              @php
                                $method = $transcation->method()->first();
                              @endphp
                              @if ($method)
                                {{ $method->name }}
                              @else
                                {{ '-' }}
                              @endif
                            @else
                              {{ $transcation->payment_method != null ? $transcation->payment_method : '-' }}
                            @endif
                          </td>
                          <td>
                            {{ $transcation->currency_symbol_position == 'left' ? $transcation->currency_symbol : '' }}
                            {{ $transcation->pre_balance }}
                            {{ $transcation->currency_symbol_position == 'right' ? $transcation->currency_symbol : '' }}
                          </td>
                          <td>
                            @if (in_array($transcation->transcation_type, [3, 5, 6, 7]))
                              <span class="text-danger">{{ '(-)' }}</span>
                            @else
                              <span class="text-success">{{ '(+)' }}</span>
                            @endif

                            {{ $transcation->currency_symbol_position == 'left' ? $transcation->currency_symbol : '' }}
                            {{ $transcation->grand_total - $transcation->commission }}
                            {{ $transcation->currency_symbol_position == 'right' ? $transcation->currency_symbol : '' }}
                          </td>
                          <td>
                            {{ $transcation->currency_symbol_position == 'left' ? $transcation->currency_symbol : '' }}
                            {{ $transcation->after_balance }}
                            {{ $transcation->currency_symbol_position == 'right' ? $transcation->currency_symbol : '' }}
                          </td>
                          <td>
                            @if ($transcation->payment_status == 1)
                              <span class="badge badge-success">{{ __('Paid') }}</span>
                            @elseif ($transcation->payment_status == 2)
                              <span class="badge badge-warning">{{ __('Decline') }}</span>
                            @else
                              <span class="badge badge-danger">{{ __('Unpaid') }}</span>
                            @endif
                          </td>

                          <td>
                            @php
                              $paymentIntent = $paymentIntents->get($transcation->transcation_id)
                                ?? $paymentIntentsByBooking->get((int) $transcation->booking_id);
                              $intentRefunds = $paymentIntent ? $refundsByIntent->get($paymentIntent->id, collect()) : collect();
                              $reservedRefund = $intentRefunds->whereIn('status', ['approval_pending', 'requested', 'submitting', 'verification_required', 'pending', 'processing', 'needs_attention', 'processed'])->sum('amount');
                              $remainingRefund = $paymentIntent ? max(0, (float) $paymentIntent->amount - (float) $reservedRefund) : 0;
                              $latestRefund = $intentRefunds->first();
                              $hasCheckedInAdmission = $paymentIntent && $paymentIntent->booking
                                && ((int) $paymentIntent->booking->scan_status === 1
                                  || $paymentIntent->booking->ticketUnits->contains(fn ($ticket) => $ticket->status === 'checked_in'));
                            @endphp
                            @if ($latestRefund)
                              <span class="badge badge-{{ $latestRefund->status === 'processed' ? 'success' : ($latestRefund->status === 'failed' ? 'danger' : 'warning') }}">
                                {{ __(ucwords(str_replace('_', ' ', $latestRefund->status))) }}
                              </span>
                            @else
                              <span class="text-muted">{{ __('None') }}</span>
                            @endif
                          </td>

                          <td>
                            @if ($transcation->transcation_type == 1)
                              @php
                                $t_invoice = $transcation->event_booking()->first();
                              @endphp
                              @if ($t_invoice)
                                <a target="_blank" class="btn btn-secondary btn-sm mr-1"
                                  href="{{ route('admin.event_booking.invoice', $t_invoice->id) }}">
                                  <i class="fas fa-eye"></i>
                                </a>
                              @else
                                {{ '-' }}
                              @endif
                            @elseif ($transcation->transcation_type == 2)
                              @php
                                $t_invoice = $transcation->product_order()->first();
                              @endphp
                              @if ($t_invoice && $t_invoice->invoice_number)
                                <a target="_blank" class="btn btn-secondary btn-sm mr-1"
                                  href="{{ route('admin.product_order.invoice', ['id' => $t_invoice->id]) }}">
                                  <i class="fas fa-eye"></i>
                                </a>
                              @else
                                {{ '-' }}
                              @endif
                            @else
                              {{ '-' }}
                            @endif
                            @if ($paymentIntent && $paymentIntent->status === 'completed' && $remainingRefund > 0)
                              <button type="button" class="btn btn-warning btn-sm" data-toggle="modal"
                                data-target="#refundModal{{ $transcation->id }}" title="{{ $hasCheckedInAdmission ? __('Open checked-in refund dispute') : __('Create refund') }}">
                                <i class="fas {{ $hasCheckedInAdmission ? 'fa-gavel' : 'fa-undo' }}"></i>
                              </button>
                            @endif
                          </td>
                        </tr>

                        @if ($paymentIntent && $paymentIntent->status === 'completed' && $remainingRefund > 0)
                          <tr><td colspan="10" class="p-0 border-0">
                          <div class="modal fade" id="refundModal{{ $transcation->id }}" tabindex="-1" role="dialog"
                            aria-labelledby="refundModalLabel{{ $transcation->id }}" aria-hidden="true">
                            <div class="modal-dialog" role="document">
                              <div class="modal-content">
                                <form method="POST" action="{{ $hasCheckedInAdmission ? route('admin.refund_disputes.store') : route('admin.refunds.store') }}">
                                  @csrf
                                  <input type="hidden" name="payment_intent_id" value="{{ $paymentIntent->id }}">
                                  <div class="modal-header">
                                    <h5 class="modal-title" id="refundModalLabel{{ $transcation->id }}">
                                      {{ $hasCheckedInAdmission ? __('Open checked-in refund dispute') : __('Create :gateway refund', ['gateway' => ucfirst($paymentIntent->gateway)]) }}
                                    </h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('Close') }}">
                                      <span aria-hidden="true">&times;</span>
                                    </button>
                                  </div>
                                  <div class="modal-body">
                                    <p class="text-muted">
                                      {{ $hasCheckedInAdmission
                                        ? __('This admission has a recorded check-in. Opening a case does not submit a refund; finance must independently review and approve the exception first.')
                                        : __('Refunds are submitted asynchronously and accounting is reversed only after the payment provider confirms a final successful status.') }}
                                    </p>
                                    <div class="form-group">
                                      <label>{{ __('Amount') }} ({{ $paymentIntent->currency }})</label>
                                      <input class="form-control" type="number" name="{{ $hasCheckedInAdmission ? 'requested_amount' : 'amount' }}" min="0.01" step="0.01"
                                        max="{{ number_format($remainingRefund, 2, '.', '') }}"
                                        value="{{ number_format($remainingRefund, 2, '.', '') }}" required>
                                      <small class="form-text text-muted">
                                        {{ __('Remaining refundable amount:') }} {{ number_format($remainingRefund, 2) }} {{ $paymentIntent->currency }}
                                      </small>
                                    </div>
                                    @if ($hasCheckedInAdmission)
                                      <div class="form-group">
                                        <label>{{ __('Exception category') }}</label>
                                        <select class="form-control" name="category" required>
                                          <option value="">{{ __('Select a category') }}</option>
                                          @foreach (\App\Models\PaymentRefundDispute::CATEGORIES as $category)
                                            <option value="{{ $category }}">{{ __(ucwords(str_replace('_', ' ', $category))) }}</option>
                                          @endforeach
                                        </select>
                                      </div>
                                      <div class="form-group">
                                        <label>{{ __('Evidence summary') }}</label>
                                        <textarea class="form-control" name="description" rows="4" minlength="20" maxlength="2000" required></textarea>
                                      </div>
                                      <label class="d-block">
                                        <input type="checkbox" name="confirm_dispute" value="1" required>
                                        {{ __('I confirm the immutable check-in record must be reviewed before any refund is approved.') }}
                                      </label>
                                    @else
                                      <div class="form-group">
                                        <label>{{ __('Reason') }}</label>
                                        <textarea class="form-control" name="reason" rows="4" minlength="10" maxlength="1000" required></textarea>
                                      </div>
                                    @endif
                                  </div>
                                  <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Cancel') }}</button>
                                    <button type="submit" class="btn btn-warning">{{ $hasCheckedInAdmission ? __('Open dispute') : __('Queue refund') }}</button>
                                  </div>
                                </form>
                              </div>
                            </div>
                          </div>
                          </td></tr>
                        @endif
                      @endforeach
                    </tbody>
                  </table>
                </div>
              @endif
            </div>
          </div>
        </div>

        <div class="card-footer text-center">
          <div class="d-inline-block mt-3">
            {{ $transcations->appends([
                    'transcation_id' => request()->input('transcation_id'),
                ])->links() }}
          </div>
        </div>
      </div>
    </div>
  </div>
@endsection
