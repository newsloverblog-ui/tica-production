@extends('organizer.layout')

@section('content')
  <div class="tica-workspace-hero">
    <div>
      <p class="tica-eyebrow">{{ __('Attendee operations') }}</p>
      <h1>{{ __('Event bookings') }}</h1>
      <p class="tica-lede">{{ __('Find purchases, confirm payment status, export attendee records and resolve booking issues.') }}</p>
    </div>
    <div class="tica-quick-actions">
      <a class="btn btn-outline-primary" href="{{ route('organizer.data_exports.index') }}">{{ __('Export history') }}</a>
    </div>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="card">
        <div class="card-header booking-toolbar">
          <div class="booking-toolbar__head">
            <div>
              <div class="card-title">{{ __('Event bookings') }}</div>
              <p>{{ __('Search purchases or narrow the list by payment status.') }}</p>
            </div>
            <div class="booking-toolbar__actions">
              <button class="btn btn-danger btn-sm d-none bulk-delete" data-href="{{ route('organizer.event_booking.bulk_delete') }}">
                <i class="flaticon-interface-5" aria-hidden="true"></i> {{ __('Delete selected') }}
              </button>
              <form class="booking-export" action="{{ route('organizer.event_bookings.export') }}" method="POST">
                @csrf
                <input type="hidden" name="booking_id" value="{{ request('booking_id') }}">
                <input type="hidden" name="event_title" value="{{ request('event_title') }}">
                <input type="hidden" name="status" value="{{ request('status') }}">
                <label class="sr-only" for="organizer-booking-export">{{ __('Export format') }}</label>
                <select id="organizer-booking-export" class="form-control form-control-sm" name="fileType">
                  <option value="csv">CSV</option><option value="xlsx">XLSX</option><option value="pdf">PDF</option>
                </select>
                <button class="btn btn-outline-primary btn-sm" type="submit">{{ __('Export') }}</button>
              </form>
              <a class="btn btn-outline-primary btn-sm" href="{{ route('organizer.data_exports.index') }}">{{ __('Export history') }}</a>
            </div>
          </div>
          <form class="booking-filters" action="{{ route('organizer.event.booking') }}" method="GET">
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
              @if(request()->hasAny(['booking_id', 'event_title', 'status']))
                <a class="btn btn-outline-secondary" href="{{ route('organizer.event.booking') }}">{{ __('Clear') }}</a>
              @endif
            </div>
          </form>
        </div>

        <div class="card-body">
          <div class="row">
            <div class="col-lg-12">
              @if (count($bookings) == 0)
                <h3 class="text-center mt-2">{{ __('NO EVENT BOOKING FOUND') . '!' }}</h3>
              @else
                <div class="table-responsive">
                  <table class="table table-striped mt-3">
                    <thead>
                      <tr>
                        <th scope="col">
                          <input type="checkbox" class="bulk-check" data-val="all">
                        </th>
                        <th scope="col">{{ __('Booking ID') }}</th>
                        <th scope="col" width="25%">{{ __('Event') }}</th>
                        <th scope="col">{{ __('Customer') }}</th>
                        <th scope="col">{{ __('Cust. Paid') }}</th>
                        <th scope="col">{{ __('Org. Received') }}</th>
                        <th scope="col">{{ __('Paid via') }}</th>
                        <th scope="col">{{ __('Payment Status') }}</th>
                        <th scope="col">{{ __('Tickect Scan Status') }}</th>
                        <th scope="col">{{ __('Actions') }}</th>
                      </tr>
                    </thead>
                    <tbody>
                      @foreach ($bookings as $booking)
                        <tr>
                          <td>
                            <input type="checkbox" class="bulk-check" data-val="{{ $booking->id }}">
                          </td>
                          <td>{{ '#' . $booking->booking_id }}</td>

                          @php
                            $eventInfo = \App\Models\Event\EventContent::where('language_id', $defaultLang->id)
                                ->where('event_id', $booking->event_id)
                                ->first();
                            $title = $eventInfo ? $eventInfo->title : '';
                            $slug = $eventInfo ? $eventInfo->slug : '';
                          @endphp

                          <td>
                            @if ($eventInfo)
                              <a href="{{ route('event.details', ['slug' => $slug, 'id' => $eventInfo ? $eventInfo->event_id : 0]) }}"
                                target="_blank">
                                {{ strlen($title) > 30 ? mb_substr($title, 0, 30, 'utf-8') . '...' : $title }}
                              </a>
                            @else
                              {{ '-' }}
                            @endif

                          </td>

                          @php
                            $customer = $booking->customerInfo()->first();
                          @endphp
                          <td>
                            @if ($customer)
                              {{ $customer->fname }}
                              {{ $customer->lname }}
                            @else
                              @if (is_null($booking->customer_id))
                                {{ __('Guest') }}
                              @else
                                {{ '-' }}
                              @endif
                            @endif
                          </td>
                          @php
                            $position = $booking->currencyTextPosition;
                            $symbol = $booking->currencySymbol;
                          @endphp

                          <td>
                            {{ $position == 'left' ? $symbol . ' ' : '' }}{{ $booking->price + $booking->tax }}{{ $position == 'right' ? ' ' . $symbol : '' }}
                          </td>
                          <td>
                            {{ $position == 'left' ? $symbol . ' ' : '' }}{{ $booking->price - $booking->commission }}{{ $position == 'right' ? ' ' . $symbol : '' }}

                          </td>
                          <td>{{ !is_null($booking->paymentMethod) ? $booking->paymentMethod : '-' }}</td>
                          <td>
                            @if ($booking->gatewayType == 'online')
                              <h2 class="d-inline-block"><span class="badge badge-success">{{ __('Completed') }}</span>
                              </h2>
                            @elseif ($booking->gatewayType == 'offline')
                              <h2 class="d-inline-block">
                                <span
                                  class="badge @if ($booking->paymentStatus == 'completed') badge-success @elseif ($booking->paymentStatus == 'pending') badge-warning text-dark @else badge-danger @endif">
                                  @if ($booking->paymentStatus == 'pending')
                                    {{ __('Pending') }}
                                  @elseif ($booking->paymentStatus == 'completed')
                                    {{ __('Completed') }}
                                  @elseif ($booking->paymentStatus == 'rejected')
                                    {{ __('Rejected') }}
                                  @endif
                                </span>
                              </h2>
                            @else
                              @if ($booking->paymentStatus == 'free')
                                <span class="badge badge-primary">{{ ucfirst($booking->paymentStatus) }}</span>
                              @else
                                -
                              @endif
                            @endif
                          </td>
                          <td>
                            @if ($booking->scan_status == 1)
                              <span class="badge badge-success">{{ __('Already Scanned') }}</span>
                            @else
                              <span class="badge badge-danger">{{ __('Not Scanned') }}</span>
                            @endif
                          </td>

                          <td>
                            <div class="dropdown">
                              <button class="btn btn-secondary btn-sm dropdown-toggle" type="button"
                                id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true"
                                aria-expanded="false">
                                {{ __('Select') }}
                              </button>

                              <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                                <a href="{{ route('organizer.event_booking.details', ['id' => $booking->id]) }}"
                                  class="dropdown-item">
                                  {{ __('Details') }}
                                </a>

                                @if (filled($booking->attachmentFile))
                                  <a href="#" class="dropdown-item" target="_blank" data-toggle="modal"
                                    data-target="#attachmentModal-{{ $booking->id }}">
                                    {{ __('Attachment') }}
                                  </a>
                                @endif

                                <a href="{{ route('organizer.event_booking.invoice', $booking->id) }}"
                                  class="dropdown-item" target="_blank">
                                  {{ __('Invoice') }}
                                </a>

                                <form class="deleteForm d-block"
                                  action="{{ route('organizer.event_booking.delete', ['id' => $booking->id]) }}"
                                  method="post">
                                  @csrf
                                  <button type="submit" class="deleteBtn">
                                    {{ __('Delete') }}
                                  </button>
                                </form>
                              </div>
                            </div>
                          </td>
                        </tr>

                        @includeIf('organizer.event.booking.show-attachment')
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
            {{ $bookings->appends([
                    'booking_id' => request()->input('booking_id'),
                    'status' => request()->input('status'),
                ])->links() }}
          </div>
        </div>
      </div>
    </div>
  </div>
@endsection
