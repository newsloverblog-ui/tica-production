@extends('organizer.layout')

@section('content')
  <div class="page-header">
    <h4 class="page-title">{{ __('Booking Details') }}</h4>
    <ul class="breadcrumbs">
      <li class="nav-home">
        <a href="{{ route('organizer.dashboard') }}">
          <i class="flaticon-home"></i>
        </a>
      </li>
      <li class="separator">
        <i class="flaticon-right-arrow"></i>
      </li>
      <li class="nav-item">
        <a href="#">{{ __('Event Booking') }}</a>
      </li>
      <li class="separator">
        <i class="flaticon-right-arrow"></i>
      </li>
      @php
        $eventInfos = \App\Models\Event\EventContent::where('language_id', $defaultLang->id)
            ->where('event_id', $booking->event_id)
            ->select('slug', 'event_id', 'title')
            ->first();
        if (empty($eventInfos)) {
            $eventInfos = \App\Models\Event\EventContent::where('event_id', $booking->event_id)
                ->select('slug', 'event_id', 'title')
                ->first();
        }
        
        $slug = $eventInfos ? $eventInfos->slug : '';
      @endphp
      <li class="nav-item">
        <a href="#">{{ $eventInfos->title }}</a>
      </li>
      <li class="separator">
        <i class="flaticon-right-arrow"></i>
      </li>
      <li class="nav-item">
        <a href="#">{{ __('Booking Details') }}</a>
      </li>
    </ul>
  </div>

  <div class="row">
    @php
      $position = $booking->currencyTextPosition;
      $currency = $booking->currencyText;
    @endphp

    <div class="col-md-6">
      <div class="card">
        <div class="card-header">
          <div class="row">
            <div class="col-lg-8">
              <div class="card-title d-inline-block">
                {{ __('Booking ID') . ' ' . '#' . $booking->booking_id }}
              </div>
            </div>
            <div class="col-lg-4">
              <a class="btn btn-info btn-sm float-right d-inline-block mr-2" href="{{ url()->previous() }}">
                <span class="btn-label">
                  <i class="fas fa-backward"></i>
                </span>
                {{ __('Back') }}
              </a>
              @php
                $hasActiveTicket = $booking->ticketUnits->isEmpty() || $booking->ticketUnits->contains('status', 'issued');
                $eventLifecycle = optional($booking->evnt)->lifecycle_status ?: 'scheduled';
                $canResendTickets = in_array((string) $booking->paymentStatus, ['completed', 'free', '1'], true)
                    && $booking->refund_status !== 'refunded'
                    && $hasActiveTicket
                    && !in_array($eventLifecycle, ['cancelled', 'cancellation_pending'], true);
              @endphp
              @if ($canResendTickets)
                <form method="post" action="{{ route('organizer.event_booking.resend-tickets', $booking->id) }}" class="float-right mr-2">
                  @csrf
                  <button class="btn btn-primary btn-sm" type="submit">
                    <i class="fas fa-envelope"></i> {{ __('Resend tickets') }}
                  </button>
                </form>
              @endif
            </div>
          </div>
        </div>

        <div class="card-body">
          @if ($booking->ticketDeliveryRequests->first())
            @php $lastTicketDelivery = $booking->ticketDeliveryRequests->first(); @endphp
            <div class="alert alert-info py-2">
              {{ __('Latest ticket resend') }}:
              <strong>{{ __(ucfirst($lastTicketDelivery->status)) }}</strong>
              · {{ $lastTicketDelivery->created_at->diffForHumans() }}
            </div>
          @endif
          <div class="payment-information">
            <div class="row mb-2">
              <div class="col-lg-4">
                <strong>{{ __('Event') . ' :' }}</strong>
              </div>
              <div class="col-lg-8">
                @if (!empty($eventInfos))
                  <a href="{{ route('event.details', ['slug' => $slug, 'id' => $eventInfos ? $eventInfos->event_id : 0]) }}"
                    target="_blank">
                    {{ strlen($eventInfos->title) > 30 ? mb_substr($eventInfos->title, 0, 30, 'utf-8') . '...' : $eventInfos->title }}
                  </a>
                @endif
              </div>
            </div>

            <div class="row">
              <div class="col-lg-4">
                <strong>{{ __('Booking Date') . ' :' }}</strong>
              </div>
              <div class="col-lg-8">
                {{ FullDateTime($booking->created_at) }}
              </div>
            </div>

            <div class="row">
              <div class="col-lg-4">
                <strong>{{ __('Event Start Date') . ' :' }}</strong>
              </div>
              <div class="col-lg-8">
                {{ $booking->event_date }}
              </div>
            </div>

            @if (@$booking->evnt->date_type == 'single')
              <div class="row">
                <div class="col-lg-4">
                  <strong>{{ __('Event End Date') . ' :' }}</strong>
                </div>
                <div class="col-lg-8">
                  {{ Carbon\Carbon::parse(@$booking->evnt->end_date . @$booking->evnt->end_time)->format('D, M d, Y h:i A') }}
                </div>
              </div>

              <div class="row">
                <div class="col-lg-4">
                  <strong>{{ __('Duration') . ' :' }}</strong>
                </div>
                <div class="col-lg-8">
                  {{ @$booking->evnt->duration }}
                </div>
              </div>
            @else
              @php
                $date = Carbon\Carbon::parse($booking->event_date)->format('Y-m-d');
                $time = Carbon\Carbon::parse($booking->event_date)->format('H:i');
                $evnt = @$booking->evnt
                    ->dates()
                    ->where('start_date', $date)
                    ->where('start_time', $time)
                    ->first();
              @endphp
              <div class="row">
                <div class="col-lg-4">
                  <strong>{{ __('Event End Date') . ' :' }}</strong>
                </div>
                <div class="col-lg-8">
                  @if (!empty($evnt))
                    {{ Carbon\Carbon::parse(@$evnt->end_date . @$evnt->end_time)->translatedFormat('D, M d, Y h:i a') }}
                  @endif
                </div>
              </div>
              <div class="row">
                <div class="col-lg-4">
                  <strong>{{ __('Duration') . ' :' }}</strong>
                </div>
                <div class="col-lg-8">
                  @if (!empty($evnt))
                    {{ $evnt->duration }}
                  @endif
                </div>
              </div>
            @endif

            <div class="row mb-2">
              <div class="col-lg-4">
                <strong>{{ __('Early Bird Discount') }} <span class="text-success">(-)</span>
                  :</strong></strong>
              </div>
              <div class="col-lg-8">
                @if (!is_null($booking->early_bird_discount))
                  {{ $position == 'left' ? $currency . ' ' : '' }}{{ $booking->early_bird_discount }}{{ $position == 'right' ? ' ' . $currency : '' }}
                @else
                  -
                @endif
              </div>
            </div>
            <div class="row mb-2">
              <div class="col-lg-4">
                <strong>{{ __('Coupon Discount') }} <span class="text-success">(-)</span> :</strong>
              </div>
              <div class="col-lg-8">
                @if (!is_null($booking->discount))
                  {{ $position == 'left' ? $currency . ' ' : '' }}{{ $booking->discount }}{{ $position == 'right' ? ' ' . $currency : '' }}
                @else
                  -
                @endif
              </div>
            </div>

            <div class="row mb-2">
              <div class="col-lg-4">
                <strong>{{ __('Tax') }} <span class="text-danger">({{ $booking->tax_percentage }}%+)</span>
                </strong>
              </div>
              <div class="col-lg-8">
                @if (!is_null($booking->tax))
                  {{ $position == 'left' ? $currency . ' ' : '' }}{{ $booking->tax }}{{ $position == 'right' ? ' ' . $currency : '' }}
                  ({{ __('Received by Admin') }})
                @else
                  -
                @endif
              </div>
            </div>

            <div class="row mb-2">
              <div class="col-lg-4">
                <strong>{{ __('Customer Paid') . ' :' }}</strong>
              </div>
              <div class="col-lg-8">
                @if (!is_null($booking->price))
                  {{ $position == 'left' ? $currency . ' ' : '' }}{{ $booking->price + $booking->tax }}{{ $position == 'right' ? ' ' . $currency : '' }}
                @else
                  -
                @endif
              </div>
            </div>

            <div class="row mb-2">
              <div class="col-lg-4">
                <strong>{{ __('Commission') }} ({{ $booking->commission_percentage }}%)</strong>
              </div>
              <div class="col-lg-8">
                @if (!is_null($booking->commission))
                  {{ $position == 'left' ? $currency . ' ' : '' }}{{ $booking->commission }}{{ $position == 'right' ? ' ' . $currency : '' }}
                  ({{ __('Received by Admin') }})
                @else
                  -
                @endif
              </div>
            </div>
            <div class="row mb-2">
              <div class="col-lg-4">
                <strong>{{ __('Received by Organization') }} </strong>
              </div>
              <div class="col-lg-8">
                @if (!is_null($booking->price - $booking->commission))
                  {{ $position == 'left' ? $currency . ' ' : '' }}{{ $booking->price - $booking->commission }}{{ $position == 'right' ? ' ' . $currency : '' }}
                @else
                  -
                @endif
              </div>
            </div>

            <div class="row mb-2">
              <div class="col-lg-4">
                <strong>{{ __('Paid via') . ' :' }}</strong>
              </div>
              <div class="col-lg-8">
                @if (!is_null($booking->paymentMethod))
                  {{ $booking->paymentMethod }}
                @else
                  -
                @endif
              </div>
            </div>
            <div class="row mb-2">
              <div class="col-lg-4">
                <strong>{{ __('Quantity') . ' :' }}</strong>
              </div>
              <div class="col-lg-8">
                @if (!is_null($booking->quantity))
                  {{ $booking->quantity }}
                @else
                  -
                @endif
              </div>
            </div>

            <div class="row mb-2">
              <div class="col-lg-4">
                <strong>{{ __('Payment Status') . ' :' }}</strong>
              </div>
              <div class="col-lg-8">
                @if ($booking->paymentStatus == 'completed')
                  <span class="badge badge-success">{{ __('Completed') }}</span>
                @elseif ($booking->paymentStatus == 'pending')
                  <span class="badge badge-warning">{{ __('Pending') }}</span>
                @elseif ($booking->paymentStatus == 'rejected')
                  <span class="badge badge-danger">{{ __('Rejected') }}</span>
                @else
                  -
                @endif
              </div>
            </div>

            <div class="row mb-2">
              <div class="col-lg-4">
                <strong>{{ __('Ticket Scan Status') }}</strong>
              </div>
              <div class="col-lg-8">
                @php
                  $ticketUnitCount = $booking->ticketUnits()->count();
                  $checkedInUnitCount = $booking->ticketUnits()->where('status', 'checked_in')->count();
                @endphp
                @if ($ticketUnitCount > 0)
                  <span class="badge {{ $checkedInUnitCount === $ticketUnitCount ? 'badge-success' : ($checkedInUnitCount > 0 ? 'badge-warning' : 'badge-secondary') }}">
                    {{ $checkedInUnitCount }} / {{ $ticketUnitCount }} {{ __('checked in') }}
                  </span>
                @elseif ($booking->scan_status == 1)
                  <span class="badge badge-success">{{ __('Already Scanned') }}</span>
                @else
                  <span class="badge badge-secondary">{{ __('Not Scanned') }}</span>
                @endif
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-md-6">
      <div class="card">
        <div class="card-header">
          <div class="card-title d-inline-block">
            {{ __('Billing Details') }}
          </div>
        </div>

        <div class="card-body">
          <div class="payment-information">
            <div class="row mb-2">
              <div class="col-lg-4">
                <strong>{{ __('Name') . ' :' }}</strong>
              </div>
              <div class="col-lg-8">
                {{ $booking->fname . ' ' . $booking->lname }}
              </div>
            </div>

            <div class="row mb-2">
              <div class="col-lg-4">
                <strong>{{ __('Email') . ' :' }}</strong>
              </div>
              <div class="col-lg-8">
                {{ $booking->email }}
              </div>
            </div>

            <div class="row mb-2">
              <div class="col-lg-4">
                <strong>{{ __('Phone') . ' :' }}</strong>
              </div>
              <div class="col-lg-8">
                {{ $booking->phone }}
              </div>
            </div>

            <div class="row mb-2">
              <div class="col-lg-4">
                <strong>{{ __('Address') . ' :' }}</strong>
              </div>
              <div class="col-lg-8">
                {{ $booking->address }}
              </div>
            </div>

            <div class="row mb-2">
              <div class="col-lg-4">
                <strong>{{ __('City') . ' :' }}</strong>
              </div>
              <div class="col-lg-8">
                {{ $booking->city }}
              </div>
            </div>

            <div class="row mb-2">
              <div class="col-lg-4">
                <strong>{{ __('State') . ' :' }}</strong>
              </div>
              <div class="col-lg-8">
                @if (!is_null($booking->state))
                  {{ $booking->state }}
                @else
                  -
                @endif
              </div>
            </div>

            <div class="row">
              <div class="col-lg-4">
                <strong>{{ __('Country') . ' :' }}</strong>
              </div>
              <div class="col-lg-8">
                {{ $booking->country }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    @if ($booking->variation != null)
      <div class="col-md-6">
        <div class="card">
          <div class="card-header">
            <div class="card-title d-inline-block">
              {{ __('Tickets Info') }}
            </div>
          </div>

          <div class="card-body">
            <div class="payment-information">
              <table class="table">
                <tr>
                  <th>{{ __('Ticket') }}</th>
                  <th>{{ __('Quantity') }}</th>
                  <th>{{ __('Price') }}</th>
                </tr>
                @if ($booking->variation != null)
                  @php
                    $variations = json_decode($booking->variation, true);
                  @endphp
                  @foreach ($variations as $variation)
                    <tr>
                      <td>
                        @php
                          $ticket_content = App\Models\Event\TicketContent::where([['ticket_id', $variation['ticket_id']], ['language_id', $defaultLang->id]])->first();
                          
                          $ticket = App\Models\Event\Ticket::where('id', $variation['ticket_id'])
                              ->select('pricing_type')
                              ->first();
                        @endphp
                        @if ($ticket_content && $ticket->pricing_type == 'variation')
                          {{ $ticket_content->title }} -
                        @endif
                        <small>{{ $variation['name'] }}</small>
                      </td>
                      <td>
                        {{ $variation['qty'] }}
                      </td>
                      <td>
                        @php
                          $evd = $variation['early_bird_dicount'] / $variation['qty'];
                        @endphp
                        {{ symbolPrice($variation['price'] - $evd) }}
                        @if ($variation['early_bird_dicount'] != null)
                          <del>{{ symbolPrice($variation['price']) }}</del>
                        @endif
                      </td>
                    </tr>
                  @endforeach
                @endif
              </table>
            </div>
          </div>
        </div>
      </div>
    @endif

    @if ($paymentIntent || $refundHistory->isNotEmpty())
      <div class="col-md-12">
        <div class="card">
          <div class="card-header">
            <div class="card-title">{{ __('Refund request') }}</div>
          </div>
          <div class="card-body">
            <p class="text-muted">
              {{ __('Request a full or partial refund for finance review. Approval only queues a controlled provider request; booking balances and tickets change only after the provider confirms success.') }}
            </p>

            @if ($refundDisputes->isNotEmpty())
              <div class="table-responsive mb-4">
                <table class="table table-bordered">
                  <thead>
                    <tr>
                      <th>{{ __('Dispute case') }}</th>
                      <th>{{ __('Amount') }}</th>
                      <th>{{ __('Status') }}</th>
                      <th>{{ __('Finance resolution') }}</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach ($refundDisputes as $dispute)
                      <tr>
                        <td><code>{{ $dispute->public_id }}</code><br><small>{{ __(ucwords(str_replace('_', ' ', $dispute->category))) }}</small></td>
                        <td>{{ number_format((float) $dispute->requested_amount, 2) }} {{ $dispute->currency }}</td>
                        <td><span class="badge badge-{{ $dispute->status === 'refund_queued' ? 'success' : ($dispute->status === 'rejected' ? 'danger' : 'warning') }}">{{ __(ucwords(str_replace('_', ' ', $dispute->status))) }}</span></td>
                        <td>{{ $dispute->resolution ?: __('Awaiting finance review') }}</td>
                      </tr>
                    @endforeach
                  </tbody>
                </table>
              </div>
            @endif

            @if ($refundHistory->isNotEmpty())
              <div class="table-responsive mb-4">
                <table class="table table-bordered">
                  <thead>
                    <tr>
                      <th>{{ __('Reference') }}</th>
                      <th>{{ __('Amount') }}</th>
                      <th>{{ __('Status') }}</th>
                      <th>{{ __('Requested') }}</th>
                      <th>{{ __('Review note') }}</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach ($refundHistory as $refund)
                      <tr>
                        <td><code>{{ $refund->public_id }}</code></td>
                        <td>{{ number_format((float) $refund->amount, 2) }} {{ $refund->currency }}</td>
                        <td>
                          <span class="badge badge-{{ $refund->status === 'processed' ? 'success' : (in_array($refund->status, ['failed', 'rejected']) ? 'danger' : 'warning') }}">
                            {{ __(ucwords(str_replace('_', ' ', $refund->status))) }}
                          </span>
                        </td>
                        <td>{{ optional($refund->requested_at)->translatedFormat('M d, Y h:i a') }}</td>
                        <td>{{ $refund->rejection_reason ?: '-' }}</td>
                      </tr>
                    @endforeach
                  </tbody>
                </table>
              </div>
            @endif

            @php
              $hasActiveRefund = $refundHistory->contains(fn ($refund) => in_array($refund->status, ['approval_pending', 'requested', 'submitting', 'verification_required', 'pending', 'processing', 'needs_attention']));
              $hasActiveDispute = $refundDisputes->contains(fn ($dispute) => in_array($dispute->status, ['open', 'under_review']));
            @endphp
            @if ($paymentIntent && $remainingRefund > 0 && $hasCheckedInTickets && !$hasActiveRefund && !$hasActiveDispute)
              <div class="alert alert-warning">
                {{ __('One or more admissions have already checked in. A normal refund is blocked; submit a documented exception for finance review instead.') }}
              </div>
              <form method="post" action="{{ route('organizer.refund_disputes.store') }}" class="row">
                @csrf
                <input type="hidden" name="payment_intent_id" value="{{ $paymentIntent->id }}">
                <div class="form-group col-lg-4">
                  <label>{{ __('Requested amount') }} ({{ $paymentIntent->currency }})</label>
                  <input class="form-control" type="number" name="requested_amount" min="0.01" step="0.01"
                    max="{{ number_format($remainingRefund, 2, '.', '') }}"
                    value="{{ number_format($remainingRefund, 2, '.', '') }}" required>
                </div>
                <div class="form-group col-lg-4">
                  <label>{{ __('Exception category') }}</label>
                  <select class="form-control" name="category" required>
                    <option value="">{{ __('Select a category') }}</option>
                    @foreach (\App\Models\PaymentRefundDispute::CATEGORIES as $category)
                      <option value="{{ $category }}" @selected(old('category') === $category)>{{ __(ucwords(str_replace('_', ' ', $category))) }}</option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group col-lg-12">
                  <label>{{ __('Evidence summary') }}</label>
                  <textarea class="form-control" name="description" rows="4" minlength="20" maxlength="2000" required>{{ old('description') }}</textarea>
                  <small class="form-text text-muted">{{ __('Describe why a refund is justified despite recorded admission. Do not include card details, passwords, identity documents, or other unnecessary sensitive data.') }}</small>
                </div>
                <div class="col-lg-12">
                  <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="confirm_dispute" value="1" id="confirm-refund-dispute" required>
                    <label class="form-check-label" for="confirm-refund-dispute">{{ __('I confirm this exception is accurate and understand finance will review the immutable check-in record.') }}</label>
                  </div>
                  <button type="submit" class="btn btn-warning">{{ __('Open refund dispute') }}</button>
                </div>
              </form>
            @elseif ($paymentIntent && $remainingRefund > 0 && !$hasCheckedInTickets && !$hasActiveRefund)
              <form method="post" action="{{ route('organizer.refunds.store') }}" class="row">
                @csrf
                <input type="hidden" name="payment_intent_id" value="{{ $paymentIntent->id }}">
                <div class="form-group col-lg-4">
                  <label>{{ __('Refund amount') }} ({{ $paymentIntent->currency }})</label>
                  <input class="form-control" type="number" name="amount" min="0.01" step="0.01"
                    max="{{ number_format($remainingRefund, 2, '.', '') }}"
                    value="{{ number_format($remainingRefund, 2, '.', '') }}" required>
                  <small class="form-text text-muted">
                    {{ __('Maximum available:') }} {{ number_format($remainingRefund, 2) }} {{ $paymentIntent->currency }}
                  </small>
                </div>
                <div class="form-group col-lg-8">
                  <label>{{ __('Reason and supporting note') }}</label>
                  <textarea class="form-control" name="reason" rows="3" minlength="10" maxlength="1000" required>{{ old('reason') }}</textarea>
                </div>
                <div class="col-lg-12">
                  <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="confirm_refund_request" value="1"
                      id="confirm-refund-request" required>
                    <label class="form-check-label" for="confirm-refund-request">
                      {{ __('I confirm this request is for the correct booking and understand finance must approve it before submission.') }}
                    </label>
                  </div>
                  <button type="submit" class="btn btn-warning">{{ __('Submit refund request') }}</button>
                </div>
              </form>
            @elseif ($paymentIntent && $remainingRefund <= 0)
              <div class="alert alert-info mb-0">{{ __('No refundable balance remains for this payment.') }}</div>
            @endif
          </div>
        </div>
      </div>
    @endif

    @if ($booking->ticketUnits->isNotEmpty())
      <div class="col-md-12">
        <div class="card">
          <div class="card-header">
            <div class="card-title">{{ __('Individual Admission Tickets') }}</div>
          </div>
          <div class="card-body table-responsive">
            <table class="table table-bordered">
              <thead>
                <tr>
                  <th>#</th>
                  <th>{{ __('Holder') }}</th>
                  <th>{{ __('Status') }}</th>
                  <th>{{ __('Last activity') }}</th>
                  <th>{{ __('Actions') }}</th>
                </tr>
              </thead>
              <tbody>
                @foreach ($booking->ticketUnits as $ticketUnit)
                  <tr>
                    <td>{{ $ticketUnit->sequence }}</td>
                    <td>
                      {{ $ticketUnit->holder_name ?: $booking->fname . ' ' . $booking->lname }}<br>
                      <small>{{ $ticketUnit->holder_email ?: $booking->email }}</small>
                    </td>
                    <td><span class="badge {{ $ticketUnit->status === 'issued' ? 'badge-success' : 'badge-secondary' }}">{{ __(ucfirst(str_replace('_', ' ', $ticketUnit->status))) }}</span></td>
                    <td>
                      @if ($ticketUnit->events->last())
                        {{ __(ucfirst($ticketUnit->events->last()->action)) }}<br>
                        <small>{{ $ticketUnit->events->last()->created_at->translatedFormat('M d, Y h:i a') }}</small>
                      @elseif ($ticketUnit->checked_in_at)
                        {{ __('Checked in') }}<br><small>{{ $ticketUnit->checked_in_at->translatedFormat('M d, Y h:i a') }}</small>
                      @else
                        {{ __('Issued') }}
                      @endif
                    </td>
                    <td>
                      @if ($ticketUnit->status === 'issued')
                        <details class="mb-2">
                          <summary>{{ __('Replace QR') }}</summary>
                          <form method="post" action="{{ route('organizer.event_booking.ticket.reissue', $ticketUnit->id) }}" class="mt-2">
                            @csrf
                            <input class="form-control mb-2" name="reason" required maxlength="500" placeholder="{{ __('Reason') }}">
                            <button class="btn btn-sm btn-warning" type="submit">{{ __('Reissue') }}</button>
                          </form>
                        </details>
                        <details>
                          <summary>{{ __('Revoke ticket') }}</summary>
                          <form method="post" action="{{ route('organizer.event_booking.ticket.revoke', $ticketUnit->id) }}" class="mt-2">
                            @csrf
                            <input class="form-control mb-2" name="reason" required maxlength="500" placeholder="{{ __('Revocation reason') }}">
                            <button class="btn btn-sm btn-danger" type="submit">{{ __('Revoke') }}</button>
                          </form>
                        </details>
                      @else
                        <small class="text-muted">{{ __('Final state') }}</small>
                      @endif
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
      </div>
    @endif

  </div>
@endsection
