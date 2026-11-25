@extends('backend.layout')

@section('content')
  @php
    $canManageTransactions = Auth::guard('admin')->user()?->hasPermission('Transaction') ?? false;
  @endphp
  <div class="page-header">
    <h4 class="page-title">{{ __('Event Booking') }}</h4>
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
        <a href="#">{{ __('Event Booking') }}</a>
      </li>
      @if (!request()->filled('status'))
        <li class="separator">
          <i class="flaticon-right-arrow"></i>
        </li>
        <li class="nav-item">
          <a href="#">{{ __('All Bookings') }}</a>
        </li>
      @endif
      @if (request()->filled('status') && request()->input('status') == 'completed')
        <li class="separator">
          <i class="flaticon-right-arrow"></i>
        </li>
        <li class="nav-item">
          <a href="#">{{ __('Completed Bookings') }}</a>
        </li>
      @endif
      @if (request()->filled('status') && request()->input('status') == 'pending')
        <li class="separator">
          <i class="flaticon-right-arrow"></i>
        </li>
        <li class="nav-item">
          <a href="#">{{ __('Pending Bookings') }}</a>
        </li>
      @endif
      @if (request()->filled('status') && request()->input('status') == 'rejected')
        <li class="separator">
          <i class="flaticon-right-arrow"></i>
        </li>
        <li class="nav-item">
          <a href="#">{{ __('Rejected Bookings') }}</a>
        </li>
      @endif

    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="card">
        <div class="card-header booking-toolbar">
          <div class="booking-toolbar__head">
            <div>
              <div class="card-title">{{ __('Event bookings') }}</div>
              <p>{{ __('Search purchases, review payment status and export the current result set.') }}</p>
            </div>
            <div class="booking-toolbar__actions">
              <button class="btn btn-danger btn-sm d-none bulk-delete" data-href="{{ route('admin.event_booking.bulk_delete') }}">
                <i class="flaticon-interface-5" aria-hidden="true"></i> {{ __('Delete selected') }}
              </button>
              <form class="booking-export" action="{{ route('admin.event_bookings.export') }}" method="POST">
                @csrf
                <input type="hidden" name="booking_id" value="{{ request('booking_id') }}">
                <input type="hidden" name="event_title" value="{{ request('event_title') }}">
                <input type="hidden" name="status" value="{{ request('status') }}">
                <label class="sr-only" for="admin-booking-export">{{ __('Export format') }}</label>
                <select id="admin-booking-export" class="form-control form-control-sm" name="fileType">
                  <option value="csv">CSV</option><option value="xlsx">XLSX</option><option value="pdf">PDF</option>
                </select>
                <button class="btn btn-outline-primary btn-sm" type="submit">{{ __('Export') }}</button>
              </form>
              <a class="btn btn-outline-primary btn-sm" href="{{ route('admin.event_booking.report') }}">{{ __('Report') }}</a>
              <a class="btn btn-outline-primary btn-sm" href="{{ route('admin.data_exports.index') }}">{{ __('Export history') }}</a>
            </div>
          </div>
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
              @if(request()->hasAny(['booking_id', 'event_title', 'status']))
                <a class="btn btn-outline-secondary" href="{{ route('admin.event.booking') }}">{{ __('Clear') }}</a>
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
                        <th scope="col">{{ __('Organizer') }}</th>
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
                            if (is_null($eventInfo)) {
                                $eventInfo = \App\Models\Event\EventContent::where('event_id', $booking->event_id)->first();
                            }
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

                          <td>
                            @if ($booking->organizer)
                              <a target="_blank"
                                href="{{ route('admin.organizer_management.organizer_details', ['id' => $booking->organizer_id, 'language' => $defaultLang->code]) }}">{{ strlen($booking->organizer->username) > 20 ? mb_substr($booking->organizer->username, 0, 20, 'UTF-8') . '....' : $booking->organizer->username }}</a>
                            @else
                              <span class="badge badge-success">{{ __('Admin') }}</span>
                            @endif
                          </td>

                          @php
                            $customer = $booking->customerInfo()->first();
                          @endphp

                          <td>
                            @if ($customer)
                              <a
                                href="{{ route('admin.customer_management.customer_details', ['id' => $customer->id, 'language' => $defaultLang->code]) }}">{{ $customer->fname }}
                                {{ $customer->lname }}</a>
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
                            @if ($booking->organizer)
                              {{ $position == 'left' ? $symbol . ' ' : '' }}{{ $booking->price - $booking->commission }}{{ $position == 'right' ? ' ' . $symbol : '' }}
                            @else
                              -
                            @endif

                          </td>
                          <td>{{ !is_null($booking->paymentMethod) ? $booking->paymentMethod : '-' }}</td>
                          <td>
                            @if ($booking->gatewayType == 'online')
                              <h2 class="d-inline-block"><span class="badge badge-success">{{ __('Completed') }}</span>
                              </h2>
                            @elseif ($booking->gatewayType == 'offline')
                              @if ($booking->paymentStatus == 'pending' && $canManageTransactions)
                                <form class="d-inline-block paymentStatusForm"
                                  action="{{ route('admin.event_booking.update_payment_status', $booking->id) }}"
                                  method="post">
                                  @csrf
                                  <select
                                    class="form-control paymentStatusBtn form-control-sm @if ($booking->paymentStatus == 'completed') bg-success @elseif ($booking->paymentStatus == 'pending') bg-warning text-dark @else bg-danger @endif"
                                    name="payment_status">
                                    <option value="completed"
                                      {{ $booking->paymentStatus == 'completed' ? 'selected' : '' }}>
                                      {{ __('Completed') }}
                                    </option>
                                    <option value="pending"
                                      {{ $booking->paymentStatus == 'pending' ? 'selected' : '' }}>
                                      {{ __('Pending') }}
                                    </option>
                                    <option value="rejected"
                                      {{ $booking->paymentStatus == 'rejected' ? 'selected' : '' }}>
                                      {{ __('Rejected') }}
                                    </option>
                                  </select>
                                </form>
                              @else
                                <span
                                  class="badge badge-{{ $booking->paymentStatus == 'rejected' ? 'danger' : ($booking->paymentStatus == 'pending' ? 'warning' : 'success') }}">{{ $booking->paymentStatus == 'pending' ? __('Pending finance review') : ucfirst($booking->paymentStatus) }}</span>
                              @endif
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
                                <a href="{{ route('admin.event_booking.details', ['id' => $booking->id]) }}"
                                  class="dropdown-item">
                                  {{ __('Details') }}
                                </a>

                                @if (filled($booking->attachmentFile))
                                  <a href="#" data-toggle="modal"
                                    data-target="#attachmentModal-{{ $booking->id }}" class="dropdown-item"
                                    target="_blank">
                                    {{ __('Attachment') }}
                                  </a>
                                @endif
                                @php
                                  $exts = explode('.', $booking->invoice);
                                  $info = new SplFileInfo($booking->invoice);
                                  $ext = $info->getExtension();
                                @endphp
                                @if ($ext == 'pdf')
                                  <a href="{{ route('admin.event_booking.invoice', $booking->id) }}"
                                    class="dropdown-item" target="_blank">
                                    {{ __('Invoice') }}
                                  </a>
                                @endif


                                <form class="deleteForm d-block"
                                  action="{{ route('admin.event_booking.delete', ['id' => $booking->id]) }}"
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

                        @includeIf('backend.event.booking.show-attachment')
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
