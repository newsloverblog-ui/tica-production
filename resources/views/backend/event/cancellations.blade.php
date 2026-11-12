@extends('backend.layout')

@section('content')
  @php
    $canManageCancellationFinance = Auth::guard('admin')->user()?->hasPermission('Transaction') ?? false;
  @endphp

  <div class="page-header">
    <h4 class="page-title">{{ __('Event cancellation reviews') }}</h4>
    <ul class="breadcrumbs">
      <li class="nav-home"><a href="{{ route('admin.dashboard') }}"><i class="flaticon-home"></i></a></li>
      <li class="separator"><i class="flaticon-right-arrow"></i></li>
      <li class="nav-item"><a href="#">{{ __('Event cancellation reviews') }}</a></li>
    </ul>
  </div>

  <div class="row">
    <div class="col-12">
      <div class="alert alert-info">
        <strong>{{ __('Controlled cancellation workflow') }}</strong><br>
        {{ __('Approval permanently cancels the event, keeps sales closed, queues full refunds only for verified Paystack/Flutterwave payments that have not checked in, and sends attendee notices. Any legacy, unsupported, conflicting, or checked-in payment remains in the manual finance-review total.') }}
      </div>

      @unless ($canManageCancellationFinance)
        <div class="alert alert-warning">
          <strong>{{ __('Finance permission required') }}</strong><br>
          {{ __('You can review cancellation requests and booking-level evidence, but approving, rejecting, disputing, or resolving a case requires Transaction permission.') }}
        </div>
      @endunless

      @forelse ($requests as $cancellation)
        <div class="card mb-4">
          <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
            <div>
              <div class="card-title mb-1">
                {{ $cancellation->event?->information?->title ?: __('Event #') . $cancellation->event_id }}
              </div>
              <small class="text-muted">
                {{ __('Requested') }} {{ optional($cancellation->requested_at)->diffForHumans() }}
                @if ($cancellation->organizer)
                  · {{ __('Organizer:') }} {{ $cancellation->organizer->username }}
                @endif
              </small>
            </div>
            @php
              $badge = match ($cancellation->status) {
                'approved' => 'success',
                'rejected' => 'danger',
                default => 'warning',
              };
            @endphp
            <span class="badge badge-{{ $badge }} p-2">{{ str_replace('_', ' ', ucfirst($cancellation->status)) }}</span>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-lg-7">
                <h6>{{ __('Internal reason') }}</h6>
                <p>{{ $cancellation->reason }}</p>
                @if ($cancellation->customer_message)
                  <h6>{{ __('Attendee message') }}</h6>
                  <p>{{ $cancellation->customer_message }}</p>
                @endif
                @if ($cancellation->rejection_reason)
                  <div class="alert alert-danger mb-0"><strong>{{ __('Rejected:') }}</strong> {{ $cancellation->rejection_reason }}</div>
                @endif
              </div>
              <div class="col-lg-5">
                <div class="row text-center">
                  <div class="col-6 mb-3">
                    <div class="border rounded p-3 h-100">
                      <strong class="d-block h5 mb-1">{{ $cancellation->queued_refund_count ?: $cancellation->eligible_refund_count }}</strong>
                      <span>{{ __('Gateway refunds') }}</span><br>
                      <small>{{ number_format((float) ($cancellation->queued_refund_amount ?: $cancellation->eligible_refund_amount), 2) }}</small>
                    </div>
                  </div>
                  <div class="col-6 mb-3">
                    <div class="border rounded p-3 h-100">
                      <strong class="d-block h5 mb-1">{{ $cancellation->manual_review_count }}</strong>
                      <span>{{ __('Manual reviews') }}</span><br>
                      <small>{{ number_format((float) $cancellation->manual_review_amount, 2) }}</small>
                    </div>
                  </div>
                </div>

                @if ($cancellation->status === 'approval_pending' && $canManageCancellationFinance)
                  <form method="post" action="{{ route('admin.event_cancellations.approve', $cancellation) }}" class="mb-3">
                    @csrf
                    <button class="btn btn-danger btn-block" type="submit"
                      onclick="return confirm('{{ __('Cancel this event and queue all currently eligible refunds? This cannot be undone from this screen.') }}')">
                      {{ __('Approve cancellation and queue refunds') }}
                    </button>
                  </form>
                  <form method="post" action="{{ route('admin.event_cancellations.reject', $cancellation) }}">
                    @csrf
                    <div class="form-group">
                      <label>{{ __('Rejection reason') }}</label>
                      <textarea name="rejection_reason" class="form-control" rows="2" required minlength="5" maxlength="2000"></textarea>
                    </div>
                    <button class="btn btn-outline-secondary btn-block" type="submit">{{ __('Reject and reopen sales') }}</button>
                  </form>
                @endif
              </div>
            </div>

            @if ($cancellation->cases->isNotEmpty())
              <hr>
              <div class="d-flex flex-wrap align-items-end justify-content-between mb-3">
                <h6 class="mb-2">{{ __('Booking-level cancellation ledger') }}</h6>
                @if ($canManageCancellationFinance)
                  <form method="post" action="{{ route('admin.event_cancellation_exports.store', $cancellation) }}" class="form-inline mb-2">
                    @csrf
                    <label class="sr-only" for="case-status-{{ $cancellation->id }}">{{ __('Case status') }}</label>
                    <select id="case-status-{{ $cancellation->id }}" name="status" class="form-control form-control-sm mr-2">
                      <option value="">{{ __('All cases') }}</option>
                      <option value="refund_queued">{{ __('Refund queued') }}</option>
                      <option value="manual_review">{{ __('Manual review') }}</option>
                      <option value="dispute_open">{{ __('Dispute open') }}</option>
                      <option value="external_refund_recorded">{{ __('External refund recorded') }}</option>
                      <option value="resolved_no_refund">{{ __('Resolved without refund') }}</option>
                    </select>
                    <select name="format" class="form-control form-control-sm mr-2" aria-label="{{ __('Export format') }}">
                      <option value="csv">CSV</option>
                      <option value="xlsx">XLSX</option>
                    </select>
                    <button type="submit" class="btn btn-primary btn-sm mr-2">{{ __('Export ledger') }}</button>
                    <a href="{{ route('admin.event_cancellation_exports.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('Export history') }}</a>
                  </form>
                @endif
              </div>
              <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0">
                  <thead>
                    <tr>
                      <th>{{ __('Booking') }}</th>
                      <th>{{ __('Customer') }}</th>
                      <th>{{ __('Amount') }}</th>
                      <th>{{ __('Disposition') }}</th>
                      <th>{{ __('Reason') }}</th>
                      <th>{{ __('Resolution / action') }}</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach ($cancellation->cases as $case)
                      <tr>
                        <td>{{ $case->booking?->booking_id ?: '#' . $case->booking_id }}</td>
                        <td>{{ $case->booking?->email ?: __('Unavailable') }}</td>
                        <td>{{ number_format((float) $case->amount, 2) }}</td>
                        <td>
                          <span class="badge badge-{{ in_array($case->status, ['refund_queued', 'external_refund_recorded']) ? 'success' : (in_array($case->status, ['manual_review', 'dispute_open']) ? 'warning' : 'secondary') }}">
                            {{ str_replace('_', ' ', ucfirst($case->status)) }}
                          </span>
                        </td>
                        <td>{{ $case->reason }}</td>
                        <td style="min-width: 320px">
                          @if ($case->resolution)
                            <p class="mb-2">{{ $case->resolution }}</p>
                            @if ($case->external_refund_reference)<code>{{ $case->external_refund_reference }}</code>@endif
                          @endif
                          @if ($case->status === 'manual_review' && $canManageCancellationFinance)
                            @php
                              $isCheckedIn = (int) ($case->booking?->scan_status ?? 0) === 1
                                || ($case->booking?->ticketUnits?->contains(fn ($ticket) => $ticket->status === 'checked_in') ?? false);
                              $isControlled = $case->paymentIntent
                                && $case->paymentIntent->status === 'completed'
                                && in_array($case->paymentIntent->gateway, ['paystack', 'flutterwave']);
                            @endphp
                            @if ($isCheckedIn && $isControlled)
                              <details>
                                <summary class="text-warning" style="cursor:pointer">{{ __('Open checked-in dispute') }}</summary>
                                <form method="post" action="{{ route('admin.event_cancellation_cases.dispute', $case) }}" class="mt-2">
                                  @csrf
                                  <select class="form-control mb-2" name="category" required>
                                    <option value="">{{ __('Select category') }}</option>
                                    @foreach (\App\Models\PaymentRefundDispute::CATEGORIES as $category)
                                      <option value="{{ $category }}">{{ __(ucwords(str_replace('_', ' ', $category))) }}</option>
                                    @endforeach
                                  </select>
                                  <textarea class="form-control mb-2" name="description" rows="3" minlength="20" maxlength="2000" required placeholder="{{ __('Evidence summary') }}"></textarea>
                                  <label class="d-block"><input type="checkbox" name="confirm_dispute" value="1" required> {{ __('Escalate immutable check-in evidence for review') }}</label>
                                  <button class="btn btn-warning btn-sm" type="submit">{{ __('Open dispute') }}</button>
                                </form>
                              </details>
                            @elseif ($isControlled)
                              <form method="post" action="{{ route('admin.event_cancellation_cases.resolve', $case) }}" class="mb-3">
                                @csrf
                                <input type="hidden" name="outcome" value="retry_provider">
                                <input type="hidden" name="resolution" value="Controlled provider identity repaired and refund retry authorized.">
                                <label class="d-block"><input type="checkbox" name="confirm_resolution" value="1" required> {{ __('I repaired and verified the gateway sale identity') }}</label>
                                <button class="btn btn-info btn-sm" type="submit">{{ __('Retry provider refund') }}</button>
                              </form>
                            @else
                              <details class="mb-3">
                                <summary class="text-success" style="cursor:pointer">{{ __('Record verified external refund') }}</summary>
                                <form method="post" action="{{ route('admin.event_cancellation_cases.resolve', $case) }}" class="mt-2">
                                  @csrf
                                  <input type="hidden" name="outcome" value="external_refund">
                                  <input class="form-control mb-2" name="external_refund_reference" minlength="6" maxlength="191" required placeholder="{{ __('Bank/provider refund reference') }}">
                                  <textarea class="form-control mb-2" name="resolution" rows="3" minlength="10" maxlength="2000" required placeholder="{{ __('Evidence checked and outcome') }}"></textarea>
                                  <label class="d-block"><input type="checkbox" name="confirm_resolution" value="1" required> {{ __('I independently verified that money was returned externally') }}</label>
                                  <button class="btn btn-success btn-sm" type="submit">{{ __('Record and reconcile') }}</button>
                                </form>
                              </details>
                            @endif
                            <details>
                              <summary class="text-danger" style="cursor:pointer">{{ __('Close without refund') }}</summary>
                              <form method="post" action="{{ route('admin.event_cancellation_cases.resolve', $case) }}" class="mt-2">
                                @csrf
                                <input type="hidden" name="outcome" value="no_refund">
                                <textarea class="form-control mb-2" name="resolution" rows="3" minlength="10" maxlength="2000" required></textarea>
                                <label class="d-block"><input type="checkbox" name="confirm_resolution" value="1" required> {{ __('I reviewed the case and recorded the final decision') }}</label>
                                <button class="btn btn-danger btn-sm" type="submit">{{ __('Close case') }}</button>
                              </form>
                            </details>
                          @elseif ($case->status === 'manual_review')
                            <span class="text-muted">{{ __('Transaction permission is required to act on this case.') }}</span>
                          @elseif (!$case->resolution)
                            <span class="text-muted">{{ __('No action required') }}</span>
                          @endif
                        </td>
                      </tr>
                    @endforeach
                  </tbody>
                </table>
              </div>
            @endif
          </div>
        </div>
      @empty
        <div class="card"><div class="card-body text-center py-5">{{ __('No cancellation requests have been submitted.') }}</div></div>
      @endforelse

      <div class="d-flex justify-content-center">{{ $requests->links() }}</div>
    </div>
  </div>
@endsection
