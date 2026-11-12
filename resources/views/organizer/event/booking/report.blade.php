@extends('organizer.layout')

@section('content')
  @php
    $money = function ($amount) use ($abs) {
        $value = number_format((float) $amount, 2);
        return $abs->base_currency_symbol_position === 'left'
            ? $abs->base_currency_symbol . $value
            : $value . $abs->base_currency_symbol;
    };
  @endphp

  <header class="tica-report-heading">
    <div>
      <span class="tica-page-kicker">{{ __('Orders and attendees') }}</span>
      <h1>{{ __('Booking report') }}</h1>
      <p>{{ __('Review sales, payment status and attendee details, then export the current view.') }}</p>
    </div>
    <a class="btn btn-outline-primary" href="{{ url('/dashboard/orders') }}">{{ __('Back to orders') }}</a>
  </header>

  <section class="card tica-report-card" aria-labelledby="booking-report-filters">
    <div class="card-header tica-report-toolbar">
      <div class="tica-report-toolbar-copy">
        <h2 id="booking-report-filters">{{ __('Filter bookings') }}</h2>
        <p>{{ __('Use a date range, payment method or status. Export uses the same filters.') }}</p>
      </div>

      <form method="POST" action="{{ route('organizer.event_bookings.export') }}" class="tica-export-form">
        @csrf
        <input type="hidden" name="from_date" value="{{ request('from_date') }}">
        <input type="hidden" name="to_date" value="{{ request('to_date') }}">
        <input type="hidden" name="payment_method" value="{{ request('payment_method') }}">
        <input type="hidden" name="payment_status" value="{{ request('payment_status') }}">
        <label class="sr-only" for="booking-export-format">{{ __('Export format') }}</label>
        <select id="booking-export-format" class="form-control" name="fileType">
          <option value="csv">CSV</option>
          <option value="xlsx">XLSX</option>
          <option value="pdf">PDF</option>
        </select>
        <button type="submit" class="btn btn-outline-primary">{{ __('Export') }}</button>
        <a class="tica-report-history" href="{{ route('organizer.data_exports.index') }}">{{ __('Export history') }}</a>
      </form>
    </div>

    <form action="{{ url()->full() }}" method="GET" class="tica-report-filters">
      <div class="form-group">
        <label for="booking-from-date">{{ __('From') }}</label>
        <input id="booking-from-date" class="form-control datepicker" type="text" name="from_date"
          placeholder="{{ __('Start date') }}" value="{{ request('from_date') }}" autocomplete="off">
      </div>
      <div class="form-group">
        <label for="booking-to-date">{{ __('To') }}</label>
        <input id="booking-to-date" class="form-control datepicker" type="text" name="to_date"
          placeholder="{{ __('End date') }}" value="{{ request('to_date') }}" autocomplete="off">
      </div>
      <div class="form-group">
        <label for="booking-payment-method">{{ __('Payment method') }}</label>
        <select id="booking-payment-method" name="payment_method" class="form-control">
          <option value="">{{ __('All methods') }}</option>
          @foreach (($onPms ?? []) as $onPm)
            <option value="{{ $onPm->keyword }}" {{ request('payment_method') == $onPm->keyword ? 'selected' : '' }}>{{ $onPm->name }}</option>
          @endforeach
          @foreach (($offPms ?? []) as $offPm)
            <option value="{{ $offPm->name }}" {{ request('payment_method') == $offPm->name ? 'selected' : '' }}>{{ $offPm->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="form-group">
        <label for="booking-payment-status">{{ __('Payment status') }}</label>
        <select id="booking-payment-status" name="payment_status" class="form-control">
          <option value="">{{ __('All statuses') }}</option>
          <option value="Pending" {{ request('payment_status') == 'Pending' ? 'selected' : '' }}>{{ __('Pending') }}</option>
          <option value="Completed" {{ request('payment_status') == 'Completed' ? 'selected' : '' }}>{{ __('Completed') }}</option>
        </select>
      </div>
      <div class="tica-filter-actions">
        <button type="submit" class="btn btn-primary">{{ __('Apply filters') }}</button>
        @if (request()->hasAny(['from_date', 'to_date', 'payment_method', 'payment_status']))
          <a class="btn btn-outline-primary" href="{{ route('organizer.event_booking.report') }}">{{ __('Reset') }}</a>
        @endif
      </div>
    </form>

    <div class="card-body tica-report-body">
      @if (count($bookings) > 0)
        <div class="tica-report-summary">
          <strong>{{ trans_choice(':count booking|:count bookings', count($bookings), ['count' => count($bookings)]) }}</strong>
          <span>{{ __('Showing the current page and active filters.') }}</span>
        </div>
        <div class="table-responsive tica-report-table-wrap">
          <table class="table tica-report-table">
            <thead>
              <tr>
                <th scope="col">{{ __('Booking') }}</th>
                <th scope="col">{{ __('Event') }}</th>
                <th scope="col">{{ __('Attendee') }}</th>
                <th scope="col">{{ __('Discounts') }}</th>
                <th scope="col">{{ __('Order') }}</th>
                <th scope="col">{{ __('Payment') }}</th>
                <th scope="col">{{ __('Date') }}</th>
                <th scope="col" class="text-right">{{ __('Receipt') }}</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($bookings as $booking)
                <tr>
                  <td data-label="{{ __('Booking') }}">
                    <strong>#{{ $booking->booking_id }}</strong>
                    <small>{{ trim($booking->customerfname . ' ' . $booking->customerlname) }}</small>
                  </td>
                  <td data-label="{{ __('Event') }}">
                    <a href="{{ route('event.details', ['slug' => $booking->slug, 'id' => $booking->event_id]) }}" target="_blank" rel="noopener">
                      {{ strlen($booking->title) > 35 ? mb_substr($booking->title, 0, 35, 'utf-8') . '…' : $booking->title }}
                    </a>
                  </td>
                  <td data-label="{{ __('Attendee') }}">
                    <strong>{{ trim($booking->fname . ' ' . $booking->lname) }}</strong>
                    <small>{{ $booking->email }}@if ($booking->phone) · {{ $booking->phone }}@endif</small>
                    @if ($booking->city || $booking->state || $booking->country || $booking->zip_code)
                      <small>{{ collect([$booking->city, $booking->state, $booking->country, $booking->zip_code])->filter()->implode(', ') }}</small>
                    @endif
                  </td>
                  <td data-label="{{ __('Discounts') }}">
                    <span>{{ __('Standard') }}: {{ $money($booking->discount) }}</span>
                    <small>{{ __('Early bird') }}: {{ $money($booking->early_bird_discount) }}</small>
                  </td>
                  <td data-label="{{ __('Order') }}">
                    <strong>{{ $money($booking->price) }}</strong>
                    <small>{{ trans_choice(':count ticket|:count tickets', $booking->quantity, ['count' => $booking->quantity]) }}</small>
                  </td>
                  <td data-label="{{ __('Payment') }}">
                    <span>{{ ucfirst($booking->paymentMethod) }}</span>
                    @if (strtolower($booking->paymentStatus) === 'pending')
                      <span class="badge badge-warning">{{ __('Pending') }}</span>
                    @elseif (strtolower($booking->paymentStatus) === 'completed')
                      <span class="badge badge-success">{{ __('Completed') }}</span>
                    @else
                      <span class="badge badge-primary">{{ ucfirst($booking->paymentStatus) }}</span>
                    @endif
                  </td>
                  <td data-label="{{ __('Date') }}"><time datetime="{{ $booking->created_at }}">{{ $booking->created_at }}</time></td>
                  <td data-label="{{ __('Receipt') }}" class="text-right">
                    <button type="button" data-toggle="modal" data-target="#receiptModal{{ $booking->id }}" class="btn btn-outline-primary btn-sm">{{ __('View') }}</button>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @else
        <div class="tica-report-empty" role="status">
          <span aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="M6 3h9l4 4v14H6zM15 3v5h4M9 13h7M9 17h5"/></svg>
          </span>
          <h2>{{ __('No bookings match these filters') }}</h2>
          <p>{{ __('Try a wider date range or clear a payment filter. New bookings will appear here automatically.') }}</p>
          @if (request()->hasAny(['from_date', 'to_date', 'payment_method', 'payment_status']))
            <a class="btn btn-outline-primary" href="{{ route('organizer.event_booking.report') }}">{{ __('Clear filters') }}</a>
          @else
            <a class="btn btn-outline-primary" href="{{ url('/dashboard/events') }}">{{ __('View events') }}</a>
          @endif
        </div>
      @endif
    </div>

    @if (count($bookings) > 0)
      <footer class="card-footer tica-report-pagination">
        {{ $bookings->appends([
            'from_date' => request('from_date'),
            'to_date' => request('to_date'),
            'payment_method' => request('payment_method'),
            'payment_status' => request('payment_status'),
        ])->links() }}
      </footer>
    @endif
  </section>

  @foreach ($bookings as $booking)
    <div class="modal fade" id="receiptModal{{ $booking->id }}" tabindex="-1" role="dialog" aria-labelledby="receiptModalLabel{{ $booking->id }}" aria-hidden="true">
      <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h2 class="modal-title" id="receiptModalLabel{{ $booking->id }}">{{ __('Receipt for booking') }} #{{ $booking->booking_id }}</h2>
            <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('Close') }}"><span aria-hidden="true">&times;</span></button>
          </div>
          <div class="modal-body">
            <iframe src="{{ route('organizer.event_booking.invoice', $booking->id) }}" class="receipt" title="{{ __('Booking receipt') }} #{{ $booking->booking_id }}"></iframe>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-primary" data-dismiss="modal">{{ __('Close') }}</button>
          </div>
        </div>
      </div>
    </div>
  @endforeach
@endsection
