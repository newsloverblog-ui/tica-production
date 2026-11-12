@extends('frontend.layout')
@section('body-class', 'tica-account-legacy')
@section('pageHeading')
  @if (!empty($pageHeading))
    {{ $pageHeading->customer_booking_details_page_title ?? __('Booking Details') }}
  @else
    {{ __('Booking Details') }}
  @endif
@endsection
@section('hero-section')
  <!-- Page Banner Start -->
  <section class="page-banner overlay pt-120 pb-125 rpt-90 rpb-95 lazy"
    data-bg="{{ asset('assets/admin/img/' . $basicInfo->breadcrumb) }}">
    <div class="container">
      <div class="banner-inner">
        <h2 class="page-title">
          @if (!empty($pageHeading))
            {{ $pageHeading->customer_booking_details_page_title ?? __('Booking Details') }}
          @else
            {{ __('Booking Details') }}
          @endif
        </h2>
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('customer.dashboard') }}">
                @if (!empty($pageHeading))
                  {{ $pageHeading->customer_dashboard_page_title ?? __('Dashboard') }}
                @else
                  {{ __('Dashboard') }}
                @endif
              </a></li>
            <li class="breadcrumb-item active">
              @if (!empty($pageHeading))
                {{ $pageHeading->customer_booking_details_page_title ?? __('Booking Details') }}
              @else
                {{ __('Booking Details') }}
              @endif
            </li>
          </ol>
        </nav>
      </div>
    </div>
  </section>
  <!-- Page Banner End -->
@endsection
@section('content')
  <!--====== Start Dashboard Section ======-->
  <section class="user-dashbord">
    <div class="container">
      <div class="row">
        @includeIf('frontend.customer.partials.sidebar')
        <div class="col-lg-9">
          <div class="row">
            <div class="col-lg-12">
              <div class="user-profile-details">
                <div class="order-details">

                  <div class="title">
                    <div class="row">
                      <div class="col-lg-8">
                        <h4>{{ __('Booking details') }}</h4>
                      </div>
                      <div class="col-lg-4">
                        <a href="{{ route('customer.booking.my_booking') }}" class="btn float-lg-right float-left">
                          {{ __('back') }}</a>
                      </div>
                    </div>
                  </div>
                  <div class="view-order-page">
                    <div class="order-info-area">
                      <div class="row align-items-center">
                        <div class="col-lg-9">
                          <div class="order-info">
                            <h3>{{ __('Booking') . ' #' }} {{ $booking->booking_id }}
                              [{{ __($booking->paymentStatus) }}]
                            </h3>
                            <p><b>{{ __('Booking Date') . ' : ' }}</b>
                              {{ \Carbon\Carbon::parse($booking->created_at)->translatedFormat('D, M d, Y h:i a') }}
                            </p>
                            <p><b>{{ __('Event Start Date') . ' : ' }}</b>
                              {{ \Carbon\Carbon::parse($booking->event_date)->translatedFormat('D, M d, Y h:i a') }}
                            </p>
                          </div>
                        </div>
                        <div class="col-lg-3">
                          <div class="prinit">
                            @if (!empty($booking->invoice))
                              <a href="{{ route('customer.booking_invoice', $booking->id) }}"
                                class="btn"><i class="fas fa-download"></i>{{ __('Download') }}</a>
                            @endif
                            @php
                              $customerId = (int) auth('customer')->id();
                              $hasActiveTicket = $booking->ticketUnits->isEmpty() || $booking->ticketUnits->contains(function ($ticket) use ($customerId) {
                                  return $ticket->status === 'issued'
                                      && ($ticket->holder_customer_id === null || (int) $ticket->holder_customer_id === $customerId);
                              });
                              $eventLifecycle = optional($booking->evnt)->lifecycle_status ?: 'scheduled';
                              $canResendTickets = in_array((string) $booking->paymentStatus, ['completed', 'free', '1'], true)
                                  && $booking->refund_status !== 'refunded'
                                  && $hasActiveTicket
                                  && !in_array($eventLifecycle, ['cancelled', 'cancellation_pending'], true);
                              $lastTicketDelivery = $booking->ticketDeliveryRequests->first();
                            @endphp
                            @if ($canResendTickets)
                              <a href="{{ route('customer.booking.current-tickets', $booking->id) }}" class="btn mt-2">
                                <i class="fas fa-ticket-alt"></i> {{ __('Download current tickets') }}
                              </a>
                              <form method="post" action="{{ route('customer.booking.resend-tickets', $booking->id) }}" class="mt-2">
                                @csrf
                                <button class="btn" type="submit">
                                  <i class="fas fa-envelope"></i> {{ __('Resend tickets') }}
                                </button>
                              </form>
                            @endif
                            @if ($lastTicketDelivery)
                              <small class="d-block mt-2 text-muted">
                                {{ __('Latest resend') }}: {{ __(ucfirst($lastTicketDelivery->status)) }}
                                · {{ $lastTicketDelivery->created_at->diffForHumans() }}
                              </small>
                            @endif
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="billing-add-area">
                    <div class="row">
                      <div class="col-md-4">
                        <div class="main-info">
                          <h5>{{ __('Billing Details') }}</h5>
                          <ul class="list">
                            <li>
                              <p><b>{{ __('Name') . ' : ' }}</b>{{ $booking->fname }}
                                {{ $booking->lname }}</p>
                            </li>
                            <li>
                              <p><b>{{ __('Email') . ' : ' }}</b>{{ $booking->email }}</p>
                            </li>
                            <li>
                              <p><b>{{ __('Phone') . ' : ' }} </b>{{ $booking->phone }}</p>
                            </li>
                            <li>
                              <p><b>{{ __('Country') . ' : ' }} </b>{{ $booking->country }}
                              </p>
                            </li>
                            <li>
                              <p><b>{{ __('State') . ' : ' }} </b>{{ $booking->state }}</p>
                            </li>
                            <li>
                              <p><b>{{ __('City') . ' : ' }} </b>{{ $booking->city }}</p>
                            </li>
                            <li>
                              <p><b>{{ __('Zip-code') . ' : ' }}
                                </b>{{ $booking->zip_code }}</p>
                            </li>
                            <li>
                              <p><b>{{ __('Address') . ' : ' }} </b> {{ $booking->address }}
                              </p>
                            </li>
                          </ul>
                        </div>
                      </div>
                      @php
                        $position = $booking->currencyTextPosition;
                        $currency = $booking->currencyText;
                      @endphp
                      <div class="col-md-4">
                        <div class="payment-information">
                          <h5>{{ __('Payment Info') }}</h5>

                          <p><b>{{ __('Event') . ' : ' }} </b> <span>
                              @php
                                $event = $booking
                                    ->event()
                                    ->where('language_id', $currentLanguageInfo->id)
                                    ->select('title', 'slug', 'event_id')
                                    ->first();
                                if (empty($event)) {
                                    $language = App\Models\Language::where('is_default', 1)->first();
                                    $event = $booking
                                        ->event()
                                        ->where('language_id', $language->id)
                                        ->select('title', 'slug', 'event_id')
                                        ->first();
                                }
                              @endphp
                              <a
                                href="{{ route('event.details', ['slug' => $event->slug, 'id' => $event->event_id]) }}">{{ strlen($event->title) > 30 ? mb_substr($event->title, 0, 30) . '...' : $event->title }}</a>
                            </span></p>

                          @if ($booking->early_bird_discount != null)
                            <p><b>{{ __('Total Early Bird Discount') . ' : ' }} </b> <span
                                class="amount">{{ $booking->currencySymbol }}
                                {{ $booking->early_bird_discount }}</span></p>
                          @endif
                          @if ($booking->discount != null)
                            <p><b>{{ __('Coupon Discount') . ' : ' }} </b> <span
                                class="amount">{{ $booking->currencySymbol }}
                                {{ $booking->discount }}</span></p>
                          @endif
                          @if (!is_null($booking->tax))
                            <p><b>{{ __('Tax') . ' : ' }} </b>
                              <span dir="ltr">
                                {{ $position == 'left' ? $currency . ' ' : '' }}{{ $booking->tax }}{{ $position == 'right' ? ' ' . $currency : '' }}
                              </span>
                            </p>
                          @endif

                          <p><b>{{ __('Total Paid') . ' : ' }} </b> <span class="amount"
                              dir="ltr">{{ $booking->currencySymbol }}{{ $booking->price + $booking->tax }}
                            </span></p>

                          <p><b>{{ __('Payment Status') . ' : ' }} </b> <span
                              class="badge {{ $booking->paymentStatus == 'completed' ? 'badge-success' : 'badge-danger' }} ">{{ __($booking->paymentStatus) }}</span>
                          </p>
                          <p><b>{{ __('Payment Method') . ' : ' }}</b>
                            {{ __($booking->paymentMethod) }}</p>
                          @if (is_null($booking->variation))
                            <p><b>{{ __('Quantity') . ' : ' }}</b>
                              {{ __($booking->quantity) }}</p>
                          @endif
                        </div>
                      </div>
                      @if ($booking->organizer)
                        <div class="col-md-4">
                          <div class="payment-information">
                            <h5>{{ __('Organizer') }}</h5>

                            <p><b>{{ __('Username') . ' : ' }}</b> <span>
                                <a target="_blank"
                                  href="{{ route('frontend.organizer.details', [$booking->organizer->id, str_replace(' ', '-', $booking->organizer->username)]) }}">{{ $booking->organizer->username }}</a>
                            </p>

                            <p><b>{{ __('Email') . ' : ' }}</b>
                              {{ @$booking->organizer->email }}</p>
                            <p><b>{{ __('Phone') . ' : ' }}</b>
                              {{ @$booking->organizer->phone }}</p>
                            <p><b>{{ __('City') . ' : ' }}</b>
                              {{ @$booking->organizer->organizer_info->city }}</p>
                            <p><b>{{ __('State') . ' : ' }}</b>
                              {{ @$booking->organizer->organizer_info->state }}</p>
                            <p><b>{{ __('Country') . ' : ' }}</b>
                              {{ @$booking->organizer->organizer_info->country }}
                            </p>
                            <p><b>{{ __('Address') . ' : ' }}</b>
                              {{ @$booking->organizer->organizer_info->address }}
                            </p>

                          </div>
                        </div>
                      @endif
                    </div>
                  </div>

                  @if ($booking->variation != null)
                    <div class="table-responsive product-list">
                      <h5>{{ __('Booked Tickets') }}</h5>
                      <table class="table table-bordered">
                        <thead>
                          <tr>
                            <th>{{ __('Ticket') }}</th>
                            <th>{{ __('Quantity') }}</th>
                            <th>{{ __('Price') }}</th>
                          </tr>
                        </thead>
                        <tbody>

                          @php
                            $variations = json_decode($booking->variation, true);
                          @endphp
                          @foreach ($variations as $variation)
                            <tr>
                              <td>
                                @php
                                  $ticket = App\Models\Event\Ticket::where('id', $variation['ticket_id'])->first();
                                  
                                  $ticketContent = App\Models\Event\TicketContent::where([['ticket_id', $ticket->id], ['language_id', $currentLanguageInfo->id]])->first();
                                @endphp
                                @if ($ticketContent && $ticket)
                                  {{ $ticketContent->title }}
                                  @if ($ticket->pricing_type == 'variation')
                                    @php
                                      $varition_key = App\Models\Event\VariationContent::where([['ticket_id', $ticket->id], ['name', $variation['name']]])
                                          ->select('key')
                                          ->first();
                                      
                                      $varition_name = App\Models\Event\VariationContent::where([['ticket_id', $ticket->id], ['language_id', $currentLanguageInfo->id], ['key', $varition_key->key]])->first();
                                      
                                      if ($varition_name) {
                                          $name = $varition_name->name;
                                      } else {
                                          $name = '';
                                      }
                                    @endphp
                                    <small>({{ $name }})</small>
                                  @endif
                                @endif
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
                        </tbody>
                      </table>
                    </div>
                  @endif

                  @if ($booking->ticketUnits->isNotEmpty())
                    <div class="table-responsive product-list mt-4">
                      <h5>{{ __('Individual Tickets') }}</h5>
                      <p class="text-muted">{{ __('Each attendee has a separate QR code. Transferring or reissuing a ticket immediately invalidates its previous QR code.') }}</p>
                      <table class="table table-bordered">
                        <thead>
                          <tr>
                            <th>#</th>
                            <th>{{ __('Holder') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Actions') }}</th>
                          </tr>
                        </thead>
                        <tbody>
                          @foreach ($booking->ticketUnits as $ticketUnit)
                            @php
                              $canManageTicket = $ticketUnit->holder_customer_id
                                  ? (int) $ticketUnit->holder_customer_id === (int) Auth::guard('customer')->id()
                                  : (int) $booking->customer_id === (int) Auth::guard('customer')->id();
                            @endphp
                            <tr>
                              <td>{{ $ticketUnit->sequence }}</td>
                              <td>
                                <strong>{{ $ticketUnit->holder_name ?: $booking->fname . ' ' . $booking->lname }}</strong><br>
                                <small>{{ $ticketUnit->holder_email ?: $booking->email }}</small>
                                @if ($ticketUnit->variation_name)<br><small>{{ $ticketUnit->variation_name }}</small>@endif
                              </td>
                              <td><span class="badge {{ $ticketUnit->status === 'issued' ? 'badge-success' : 'badge-secondary' }}">{{ __(str_replace('_', ' ', ucfirst($ticketUnit->status))) }}</span></td>
                              <td>
                                @if ($ticketUnit->status === 'issued' && $canManageTicket)
                                  <a class="btn btn-sm mb-2" href="{{ route('customer.booking.ticket.qr', $ticketUnit->id) }}">
                                    <i class="fas fa-qrcode"></i> {{ __('Download QR') }}
                                  </a>
                                  @if ($ticketUnit->pendingTransfer)
                                    <div class="alert alert-info py-2">
                                      {{ __('Transfer pending for') }} {{ $ticketUnit->pendingTransfer->recipient_email }}
                                      ({{ __('expires') }} {{ $ticketUnit->pendingTransfer->expires_at->diffForHumans() }}).
                                      <form method="post" action="{{ route('customer.booking.ticket.transfer.cancel', $ticketUnit->pendingTransfer->id) }}" class="mt-2">
                                        @csrf
                                        <button class="btn btn-sm" type="submit">{{ __('Cancel invitation') }}</button>
                                      </form>
                                    </div>
                                  @else
                                    <details class="mb-2">
                                      <summary>{{ __('Transfer ticket') }}</summary>
                                      <form method="post" action="{{ route('customer.booking.ticket.transfer', $ticketUnit->id) }}" class="mt-2">
                                        @csrf
                                        <input class="form-control mb-2" name="holder_name" required maxlength="255" placeholder="{{ __('New holder name') }}">
                                        <input class="form-control mb-2" type="email" name="holder_email" required maxlength="255" placeholder="{{ __('New holder email') }}">
                                        <button class="btn btn-sm" type="submit">{{ __('Send secure invitation') }}</button>
                                      </form>
                                    </details>
                                  @endif
                                  <details>
                                    <summary>{{ __('Replace lost QR') }}</summary>
                                    <form method="post" action="{{ route('customer.booking.ticket.reissue', $ticketUnit->id) }}" class="mt-2">
                                      @csrf
                                      <input class="form-control mb-2" name="reason" required maxlength="500" placeholder="{{ __('Reason for replacement') }}">
                                      <button class="btn btn-sm" type="submit">{{ __('Issue new QR') }}</button>
                                    </form>
                                  </details>
                                @elseif ($ticketUnit->status === 'issued')
                                  <small class="text-muted">{{ __('This ticket is now managed by the accepted recipient.') }}</small>
                                @else
                                  <small class="text-muted">{{ __('No changes are allowed after check-in, revocation, or refund.') }}</small>
                                @endif
                              </td>
                            </tr>
                            @if ($ticketUnit->events->isNotEmpty())
                              <tr>
                                <td></td>
                                <td colspan="3">
                                  <small class="text-muted">
                                    {{ __('History') }}:
                                    @foreach ($ticketUnit->events as $event)
                                      {{ __(ucfirst($event->action)) }} {{ $event->created_at->translatedFormat('M d, Y h:i a') }}@if (!$loop->last), @endif
                                    @endforeach
                                  </small>
                                </td>
                              </tr>
                            @endif
                          @endforeach
                        </tbody>
                      </table>
                    </div>
                  @endif
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
  <!--====== End Dashboard Section ======-->
@endsection
@section('script')
  <script src="{{ asset('assets/front/js/page.js') }}"></script>
@endsection
