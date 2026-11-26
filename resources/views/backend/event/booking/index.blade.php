@extends('backend.layout')

@section('content')
  @php
    $canManageTransactions = Auth::guard('admin')->user()?->hasPermission('Transaction') ?? false;
    $hasFilters = request()->anyFilled(['booking_id', 'event_title', 'status']);
  @endphp

  <section class="admin-bookings" aria-labelledby="admin-bookings-title">
    <div class="page-header">
      <h1 class="page-title" id="admin-bookings-title">{{ __('Event bookings') }}</h1>
    </div>

    <div class="card">
      <div class="card-header booking-toolbar">
        <form class="booking-filters" action="{{ route('admin.event.booking') }}" method="GET">
          <label>
            <span>{{ __('Booking ID') }}</span>
            <input name="booking_id" type="search" class="form-control" placeholder="{{ __('Search booking ID') }}" value="{{ request('booking_id') }}">
          </label>
          <label>
            <span>{{ __('Event') }}</span>
            <input name="event_title" type="search" class="form-control" placeholder="{{ __('Search event title') }}" value="{{ request('event_title') }}">
          </label>
          <label>
            <span>{{ __('Payment status') }}</span>
            <select class="form-control" name="status">
              <option value="" @selected(!request()->filled('status'))>{{ __('All statuses') }}</option>
              <option value="completed" @selected(request('status') === 'completed')>{{ __('Completed') }}</option>
              <option value="pending" @selected(request('status') === 'pending')>{{ __('Pending') }}</option>
              <option value="rejected" @selected(request('status') === 'rejected')>{{ __('Rejected') }}</option>
            </select>
          </label>
          <div class="booking-filters__actions">
            <button class="btn btn-primary" type="submit">{{ __('Apply filters') }}</button>
            @if ($hasFilters)
              <a class="btn btn-outline-secondary" href="{{ route('admin.event.booking') }}">{{ __('Clear') }}</a>
            @endif
          </div>
        </form>

        <div class="admin-bookings__toolbar">
          <div class="admin-bookings__selection">
            @if ($bookings->isNotEmpty())
              <span class="admin-bookings__count t-num">
                {{ __('Showing :first–:last of :total', ['first' => $bookings->firstItem(), 'last' => $bookings->lastItem(), 'total' => number_format($bookings->total())]) }}
              </span>
              <label class="admin-bookings__select-all">
                <input type="checkbox" class="bulk-check" data-val="all">
                {{ __('Select all') }}<span class="sr-only"> {{ __('bookings on this page') }}</span>
              </label>
              <button type="button" class="btn btn-danger btn-sm d-none bulk-delete" data-href="{{ route('admin.event_booking.bulk_delete') }}">
                {{ __('Delete selected') }}
              </button>
            @endif
          </div>
          <div class="booking-toolbar__actions">
            <form class="booking-export" action="{{ route('admin.event_bookings.export') }}" method="POST">
              @csrf
              <input type="hidden" name="booking_id" value="{{ request('booking_id') }}">
              <input type="hidden" name="event_title" value="{{ request('event_title') }}">
              <input type="hidden" name="status" value="{{ request('status') }}">
              <label class="sr-only" for="admin-booking-export">{{ __('Export format') }}</label>
              <select id="admin-booking-export" class="form-control form-control-sm" name="fileType">
                <option value="csv">CSV</option>
                <option value="xlsx">XLSX</option>
                <option value="pdf">PDF</option>
              </select>
              <button class="btn btn-outline-primary btn-sm" type="submit">{{ __('Export') }}</button>
            </form>
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.event_booking.report') }}">{{ __('Report') }}</a>
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.data_exports.index') }}">{{ __('Export history') }}</a>
          </div>
        </div>
      </div>

      <div class="card-body">
        @if ($bookings->isEmpty())
          <p class="admin-bookings__empty">
            {{ $hasFilters ? __('No bookings match these filters.') : __('No bookings yet.') }}
          </p>
        @else
          <div class="admin-bookings__results">
            <table class="admin-bookings__table" role="table" aria-labelledby="admin-bookings-title">
              <thead role="rowgroup">
                <tr role="row">
                  <th scope="col" role="columnheader">{{ __('Booking ID') }}</th>
                  <th scope="col" role="columnheader">{{ __('Event') }}</th>
                  <th scope="col" role="columnheader">{{ __('Customer') }}</th>
                  <th scope="col" role="columnheader" class="t-num">{{ __('Customer paid') }}</th>
                  <th scope="col" role="columnheader" class="t-num">{{ __('Organizer received') }}</th>
                  <th scope="col" role="columnheader">{{ __('Payment') }}</th>
                  <th scope="col" role="columnheader">{{ __('Ticket scan') }}</th>
                  <th scope="col" role="columnheader">{{ __('Actions') }}</th>
                </tr>
              </thead>
              <tbody role="rowgroup">
                @foreach ($bookings as $booking)
                  @php
                    $eventInfo = \App\Models\Event\EventContent::where('event_id', $booking->event_id)
                        ->where('language_id', $defaultLang->id)->first()
                        ?? \App\Models\Event\EventContent::where('event_id', $booking->event_id)->first();
                    $customer = $booking->customerInfo;
                    $detailsUrl = route('admin.event_booking.details', ['id' => $booking->id]);
                    $currencyBefore = $booking->currencyTextPosition === 'left' ? $booking->currencySymbol . ' ' : '';
                    $currencyAfter = $booking->currencyTextPosition === 'right' ? ' ' . $booking->currencySymbol : '';
                  @endphp
                  <tr role="row">
                    <td role="cell" class="admin-bookings__reference">
                      <label class="admin-bookings__select-row">
                        <input type="checkbox" class="bulk-check" data-val="{{ $booking->id }}" aria-label="{{ __('Select booking :id', ['id' => $booking->booking_id]) }}">
                        <span dir="ltr">#{{ $booking->booking_id }}</span>
                      </label>
                    </td>
                    <td role="cell" class="admin-bookings__event">
                      <span class="admin-bookings__label" aria-hidden="true">{{ __('Event') }}</span>
                      @if ($eventInfo)
                        <a class="admin-bookings__event-title" href="{{ route('event.details', ['slug' => $eventInfo->slug, 'id' => $eventInfo->event_id]) }}" target="_blank" rel="noopener">
                          {{ $eventInfo->title }}
                        </a>
                      @else
                        {{ __('Event unavailable') }}
                      @endif
                      <span class="admin-bookings__secondary">
                        @if ($booking->organizer)
                          <a href="{{ route('admin.organizer_management.organizer_details', ['id' => $booking->organizer_id, 'language' => $defaultLang->code]) }}" target="_blank" rel="noopener">
                            {{ $booking->organizer->username }}
                          </a>
                        @else
                          {{ __('Admin') }}
                        @endif
                      </span>
                    </td>
                    <td role="cell" class="admin-bookings__customer">
                      <span class="admin-bookings__label" aria-hidden="true">{{ __('Customer') }}</span>
                      @if ($customer)
                        <a href="{{ route('admin.customer_management.customer_details', ['id' => $customer->id, 'language' => $defaultLang->code]) }}">
                          {{ $customer->fname }} {{ $customer->lname }}
                        </a>
                      @else
                        {{ is_null($booking->customer_id) ? __('Guest') : '—' }}
                      @endif
                    </td>
                    <td role="cell" class="t-num">
                      <span class="admin-bookings__label" aria-hidden="true">{{ __('Customer paid') }}</span>
                      {{ $currencyBefore . number_format($booking->price + $booking->tax, 2) . $currencyAfter }}
                    </td>
                    <td role="cell" class="t-num">
                      <span class="admin-bookings__label" aria-hidden="true">{{ __('Organizer received') }}</span>
                      {{ $booking->organizer ? $currencyBefore . number_format($booking->price - $booking->commission, 2) . $currencyAfter : '—' }}
                    </td>
                    <td role="cell">
                      <span class="admin-bookings__label" aria-hidden="true">{{ __('Payment') }}</span>
                      @if ($booking->gatewayType === 'online')
                        <span class="badge badge-success">{{ __('Completed') }}</span>
                      @elseif ($booking->gatewayType === 'offline')
                        @if ($booking->paymentStatus === 'pending' && $canManageTransactions)
                          <form class="paymentStatusForm" action="{{ route('admin.event_booking.update_payment_status', $booking->id) }}" method="post">
                            @csrf
                            <select class="form-control paymentStatusBtn form-control-sm bg-warning text-dark"
                              name="payment_status" aria-label="{{ __('Payment status for booking :id', ['id' => $booking->booking_id]) }}">
                              <option value="pending" selected>{{ __('Pending') }}</option>
                              <option value="completed">{{ __('Completed') }}</option>
                              <option value="rejected">{{ __('Rejected') }}</option>
                            </select>
                          </form>
                        @else
                          <span class="badge badge-{{ $booking->paymentStatus === 'rejected' ? 'danger' : ($booking->paymentStatus === 'pending' ? 'warning' : 'success') }}">
                            {{ $booking->paymentStatus === 'pending' ? __('Pending finance review') : __(ucfirst($booking->paymentStatus)) }}
                          </span>
                        @endif
                      @elseif ($booking->paymentStatus === 'free')
                        <span class="badge badge-primary">{{ __('Free') }}</span>
                      @else
                        —
                      @endif
                      @if (filled($booking->paymentMethod))
                        <span class="admin-bookings__secondary">{{ $booking->paymentMethod }}</span>
                      @endif
                    </td>
                    <td role="cell">
                      <span class="admin-bookings__label" aria-hidden="true">{{ __('Ticket scan') }}</span>
                      @if ($booking->scan_status == 1)
                        <span class="badge badge-success">{{ __('Scanned') }}</span>
                      @else
                        <span class="badge badge-light">{{ __('Not scanned') }}</span>
                      @endif
                    </td>
                    <td role="cell" class="admin-bookings__action-cell">
                      <div class="dropdown admin-bookings__row-actions">
                        <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle"
                          data-toggle="dropdown" data-display="static" aria-expanded="false"
                          aria-controls="booking-actions-{{ $booking->id }}"
                          aria-label="{{ __('Actions for booking :id', ['id' => $booking->booking_id]) }}">
                          {{ __('Actions') }}
                        </button>
                        <div class="dropdown-menu" id="booking-actions-{{ $booking->id }}">
                          <a class="dropdown-item" href="{{ $detailsUrl }}">{{ __('Details') }}</a>
                          @if (filled($booking->attachmentFile))
                            <button class="dropdown-item" type="button" data-toggle="modal" data-target="#attachmentModal-{{ $booking->id }}">
                              {{ __('Attachment') }}
                            </button>
                          @endif
                          @if (pathinfo((string) $booking->invoice, PATHINFO_EXTENSION) === 'pdf')
                            <a class="dropdown-item" href="{{ route('admin.event_booking.invoice', $booking->id) }}" target="_blank" rel="noopener">
                              {{ __('Invoice') }}
                            </a>
                          @endif
                          <div class="dropdown-divider"></div>
                          <form class="deleteForm" action="{{ route('admin.event_booking.delete', ['id' => $booking->id]) }}" method="post">
                            @csrf
                            <button type="submit" class="dropdown-item text-danger deleteBtn">{{ __('Delete') }}</button>
                          </form>
                        </div>
                      </div>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif
      </div>

      @if ($bookings->hasPages())
        <div class="card-footer admin-bookings__pagination">
          {{ $bookings->links() }}
        </div>
      @endif
    </div>
  </section>

  @foreach ($bookings as $booking)
    @includeIf('backend.event.booking.show-attachment')
  @endforeach
@endsection
