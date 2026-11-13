@extends('frontend.layout')
@section('pageHeading')
  {{ $content->title }}
@endsection
@if(!empty($is_preview))
  @section('robots', 'noindex,nofollow,noarchive')
@endif

@php
  $og_title = $content->title;
  $eventDescription = trim(strip_tags((string) $content->description));
  if ($publicContentPolicy->containsPlaceholder($eventDescription)) {
      $eventDescription = __('Discover :event and view its date, venue and ticket options on Tica.', ['event' => $content->title]);
  }
  $og_description = $eventDescription;
  $og_image = route('event.share-card', ['event' => $content->id, 'v' => optional($content->updated_at)->timestamp]);
@endphp

@section('meta-keywords', "{{ $content->meta_keywords }}")
@section('meta-description', "$eventDescription")
@section('og-title', "$og_title")
@section('og-description', "$og_description")
@section('og-image', "$og_image")
@section('og-image-width', '1200')
@section('og-image-height', '630')
@section('og-type', 'event')

@section('structured-data')
  @php
    $schemaStart = $content->date_type === 'multiple' ? optional(eventLatestDates($content->id))->start_date_time : trim($content->start_date . ' ' . $content->start_time);
    $schemaEnd = $content->date_type === 'multiple' ? optional(eventLastEndDates($content->id))->end_date_time : trim($content->end_date . ' ' . $content->end_time);
    $schemaLocation = collect([$content->address, $content->city, $content->state, $content->country])->filter()->implode(', ');
    $eventSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'Event',
        'name' => $content->title,
        'description' => $eventDescription,
        'image' => [$og_image],
        'url' => route('event.details', [$content->eventSlug, $content->id]),
        'startDate' => $schemaStart ? \Carbon\Carbon::parse($schemaStart, $websiteInfo->timezone)->toIso8601String() : null,
        'endDate' => $schemaEnd ? \Carbon\Carbon::parse($schemaEnd, $websiteInfo->timezone)->toIso8601String() : null,
        'eventStatus' => ($content->lifecycle_status ?? 'scheduled') === 'cancelled'
            ? 'https://schema.org/EventCancelled'
            : 'https://schema.org/EventScheduled',
        'eventAttendanceMode' => $content->event_type === 'online'
            ? 'https://schema.org/OnlineEventAttendanceMode'
            : 'https://schema.org/OfflineEventAttendanceMode',
        'location' => $content->event_type === 'online' ? [
            '@type' => 'VirtualLocation',
            'url' => route('event.details', [$content->eventSlug, $content->id]),
        ] : [
            '@type' => 'Place',
            'name' => $content->address ?: $content->city,
            'address' => $schemaLocation,
        ],
        'offers' => [
            '@type' => 'Offer',
            'url' => route('event.details', [$content->eventSlug, $content->id]),
            'price' => is_numeric($content->price ?? null) ? (float) $content->price : 0,
            'priceCurrency' => $basicInfo->base_currency_text ?: 'NGN',
            'availability' => 'https://schema.org/InStock',
        ],
    ];
  @endphp
  <script type="application/ld+json">@json($eventSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)</script>
@endsection

@section('custom-style')
  <link rel="stylesheet" href="{{ asset('assets/admin/css/summernote-content.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/front/css/event-detail-pulse.css') }}?v={{ file_exists(public_path('assets/front/css/event-detail-pulse.css')) ? filemtime(public_path('assets/front/css/event-detail-pulse.css')) : '1' }}">
@endsection

@section('hero-section')
  <section class="event-detail-breadcrumb" aria-label="{{ __('Breadcrumb') }}">
    <div class="container">
      <a href="{{ route('events') }}"><i class="fas fa-arrow-left" aria-hidden="true"></i>{{ __('Explore events') }}</a>
      <span aria-hidden="true">/</span>
      <span>{{ $content->title }}</span>
    </div>
  </section>
@endsection
@section('content')
  <!-- Event Page Start -->
  @php
    $eventLocationLabel = $content->address ?: collect([$content->city, $content->state, $content->country])->filter()->implode(', ');
    $map_address = preg_replace('/\s+/u', ' ', trim($eventLocationLabel));
    $map_address = str_replace('/', ' ', $map_address);
    $map_address = str_replace('?', ' ', $map_address);
    $map_address = str_replace(',', ' ', $map_address);
    $checkWishList = false;
    if (Auth::guard('customer')->check()) {
      $checkWishList = checkWishList($content->id, Auth::guard('customer')->user()->id);
    }
  @endphp
  <section class="event-details-section pulse-event-page">
    <div class="container">
      @if(!empty($is_preview))
        <div class="alert alert-warning" role="status">
          <strong>{{ __('Private preview') }}</strong>
          {{ __('Only you and authorized administrators can view this unpublished event URL.') }}
        </div>
      @endif
      <div class="event-details-content pulse-event-layout">
        <div class="event-top pulse-event-identity">
          @php
            if ($content->date_type == 'multiple') {
                $event_date = eventLatestDates($content->id);
                $date = strtotime(@$event_date->start_date);
            } else {
                $date = strtotime($content->start_date);
            }
          @endphp
          @if ($content->date_type != 'multiple')
            <div class="event-top-date">
              <div class="event-month">
                {{ \Carbon\Carbon::parse($date)->timezone($websiteInfo->timezone)->translatedFormat('M') }}</div>
              <div class="event-date">
                {{ \Carbon\Carbon::parse($date)->timezone($websiteInfo->timezone)->translatedFormat('d') }}</div>
            </div>
          @endif
          <div class="event-bottom-content">
            @php
              if ($content->date_type == 'multiple') {
                  $event_date = eventLatestDates($content->id);
                  $startDateTime = @$event_date->start_date_time;
                  $endDateTime = @$event_date->end_date_time;
                  //for multiple get last end date
                  $last_end_date = eventLastEndDates($content->id);
                  $last_end_date = $last_end_date->end_date_time;

                  $now_time = \Carbon\Carbon::now()
                      ->timezone($websiteInfo->timezone)
                      ->translatedFormat('Y-m-d H:i:s');
              } else {
                  $now_time = \Carbon\Carbon::now()
                      ->timezone($websiteInfo->timezone)
                      ->translatedFormat('Y-m-d H:i:s');
                  $startDateTime = $content->start_date . ' ' . $content->start_time;
                  $endDateTime = $content->end_date . ' ' . $content->end_time;
              }
              $displayStartDateTime = $startDateTime ?: null;
              $displayEndDateTime = $endDateTime ?: $displayStartDateTime;
              $over = false;

            @endphp
            @if ($content->date_type == 'single' && $content->countdown_status == 1)
              <div class="event-details-top">
                @if ($startDateTime >= $now_time)
                  <h1 class="title">{{ $content->title }} <span class="badge badge-info">{{ __('Upcoming') }}</span>
                  </h1>
                @elseif ($startDateTime <= $endDateTime && $endDateTime >= $now_time)
                  <h1 class="title">
                    {{ $content->title }}
                    <span class="badge badge-success">{{ __('Running') }}</span>
                  </h1>
                @else
                  @php
                    $over = true;
                  @endphp
                  <h1 class="title">
                    {{ $content->title }}
                    <span class="badge badge-danger">{{ __('Over') }}</span>
                  </h1>
                @endif
              </div>
            @elseif ($content->date_type == 'multiple')
              <div class="event-details-top">
                <h1 class="title">{{ $content->title }}
                  @if ($startDateTime >= $now_time)
                    <span class="badge badge-info">{{ __('Upcoming') }}</span>
                  @elseif ($startDateTime <= $last_end_date && $last_end_date >= $now_time)
                    <span class="badge badge-success">{{ __('Running') }}</span>
                  @else
                    @php
                      $over = true;
                    @endphp
                    <span class="badge badge-danger">{{ __('Over') }}</span>
                  @endif
                </h1>
              </div>
            @else
              <div class="event-details-top">
                <h1 class="title">{{ $content->title }}</h1>
              </div>

            @endif

            <div class="pulse-event-organizer-line">
              <span>{{ __('Hosted by') }}</span>
              @if ($organizer == '')
                @php $eventAdmin = App\Models\Admin::first(); @endphp
                <a href="{{ route('frontend.organizer.details', [$eventAdmin->id, str_replace(' ', '-', $eventAdmin->username), 'admin' => 'true']) }}">{{ $eventAdmin->username }}</a>
              @else
                <a href="{{ route('frontend.organizer.details', [$organizer->id, str_replace(' ', '-', $organizer->username)]) }}">{{ $organizer->username }}</a>
              @endif
              @if ($organizer != '' && $organizer->kyc_status === 'verified')
                <i class="fas fa-check-circle" aria-label="{{ __('Verified organizer') }}" title="{{ __('Identity verified by Tica') }}"></i>
              @endif
            </div>

            @if (!empty($content->price))
              <p class="pulse-event-start-price">{{ __('Tickets from') }} <strong>₦{{ number_format($content->price) }}</strong></p>
            @endif

            <div class="buttons pulse-event-actions">
              <form method="POST" class="wishlist-action-form" action="{{ $checkWishList == false ? route('addto.wishlist', $content->id) : route('remove.wishlist', $content->id) }}">
                @csrf
                <button type="submit" class="{{ $checkWishList == true ? 'text-success' : '' }}"><i class="fas fa-bookmark"></i><span>{{ __('Save') }}</span></button>
              </form>
              <a href="javascript:void(0)" data-toggle="modal" data-target=".share-event">
                <i class="fas fa-share-alt"></i><span>{{ __('Share') }}</span></a>
            </div>
            @if (!$over)
              <a class="pulse-mobile-ticket-cta" href="#event-ticket-panel">
                <span>{{ ($content->registration_mode ?? 'ticketing') === 'corporate' ? __('Register') : __('Get tickets') }}</span>
                <i class="fas fa-arrow-right" aria-hidden="true"></i>
              </a>
            @endif
          </div>
        </div>
        @php
          $eventPosterPath = 'assets/admin/img/event/thumbnail/' . $content->thumbnail;
          $eventPoster = !empty($content->thumbnail)
              ? \App\Support\PublicImage::flyer($content->thumbnail)
              : asset('assets/front/images/home/city-pulse-hero.png');
        @endphp
        <div class="event-details-image">
          <div class="pulse-event-poster-stage" style="--event-art: url('{{ $eventPoster }}')">
            <a href="{{ $eventPoster }}" aria-label="{{ __('Open event poster') }}">
              <img src="{{ $eventPoster }}" alt="{{ $content->title }}"
                style="width:auto!important;max-width:100%!important;height:100%!important;max-height:100%!important;object-fit:contain!important;">
            </a>
          </div>
        </div>
        <div class="row">
          <div class="col-lg-7">
            <div class="event-details-content-inner">
              <section class="pulse-about-panel">
                <div class="event-info pulse-event-category">
                  <span>
                    <a href="{{ route('events', ['category' => $content->slug]) }}">{{ $content->name }}</a>
                  </span>
                </div>
                @if (Session::has('paypal_error'))
                  <div class="alert alert-danger">{{ Session::get('paypal_error') }}</div>
                @endif
                @php
                  Session::put('paypal_error', null);
                @endphp
                <h2 class="inner-title">{{ __('About this event') }}</h2>

                @if ($publicContentPolicy->containsPlaceholder((string) $content->description))
                  <p class="pulse-event-description">{{ $eventDescription }}</p>
                @else
                  <div class="summernote-content pulse-event-description" data-event-description>
                    {!! $content->description !!}
                  </div>
                  <button class="pulse-description-toggle" type="button" data-description-toggle aria-expanded="false">
                    <span>{{ __('Show more') }}</span><i class="fas fa-chevron-down" aria-hidden="true"></i>
                  </button>
                @endif
              </section>

              <section class="pulse-schedule-panel" aria-labelledby="event-schedule-heading">
                <h2 class="inner-title" id="event-schedule-heading">{{ __('Date & time') }}</h2>
                <dl class="pulse-schedule-grid">
                  <div>
                    <dt>{{ __('Start date') }}</dt>
                    <dd>{{ $displayStartDateTime ? \Carbon\Carbon::parse($displayStartDateTime)->timezone($websiteInfo->timezone)->translatedFormat('d M Y') : __('To be announced') }}</dd>
                  </div>
                  <div>
                    <dt>{{ __('End date') }}</dt>
                    <dd>{{ $displayEndDateTime ? \Carbon\Carbon::parse($displayEndDateTime)->timezone($websiteInfo->timezone)->translatedFormat('d M Y') : __('To be announced') }}</dd>
                  </div>
                  <div>
                    <dt>{{ __('Time') }}</dt>
                    <dd>{{ $displayStartDateTime ? \Carbon\Carbon::parse($displayStartDateTime)->timezone($websiteInfo->timezone)->translatedFormat('h:i A') : __('To be announced') }}</dd>
                  </div>
                </dl>
              </section>

              @if ($content->event_type != 'online')
                <section class="pulse-location-panel" id="event-location">
                  <div class="pulse-location-heading">
                    <div>
                      <span>{{ __('Venue') }}</span>
                      <h2 class="inner-title">{{ $content->city ?: __('Event location') }}</h2>
                      <p>{{ $eventLocationLabel }}</p>
                    </div>
                    <a href="https://www.google.com/maps/search/?api=1&query={{ urlencode($eventLocationLabel) }}" target="_blank" rel="noopener">
                      <i class="fas fa-directions" aria-hidden="true"></i>{{ __('Directions') }}
                    </a>
                  </div>
                <div class="our-location">
                  <iframe
                    title="{{ __('Map showing :venue', ['venue' => $content->city ?: __('event location')]) }}"
                    src="//maps.google.com/maps?width=100%25&amp;height=385&amp;hl=en&amp;q={{ $map_address }}&amp;t=&amp;z=14&amp;ie=UTF8&amp;iwloc=B&amp;output=embed"
                    height="385" class="map-h" allowfullscreen="" loading="eager"></iframe>
                </div>
                </section>
              @endif

              @if (!empty($content->refund_policy) && !$publicContentPolicy->containsPlaceholder((string) $content->refund_policy))
                <section class="pulse-policy-panel">
                  <h2>{{ __('Ticket policy') }}</h2>
                  <p>{{ @$content->refund_policy }}</p>
                </section>
              @endif

            </div>
          </div>
          <div class="col-lg-5">
            <div class="sidebar-sticky" id="event-ticket-panel">
              @if (($content->registration_mode ?? 'ticketing') === 'corporate')
                <section class="pulse-registration-panel" aria-labelledby="registration-panel-title">
                  <div class="pulse-ticket-panel-heading">
                    <div>
                      <span>{{ __('Registration') }}</span>
                      <h2 id="registration-panel-title">{{ __('Request your place') }}</h2>
                    </div>
                    <i class="fas fa-clipboard-check" aria-hidden="true"></i>
                  </div>
                  <div class="pulse-registration-body">
                    <p>{{ __('This event uses an attendee registration form instead of ticket checkout.') }}</p>
                    <ul>
                      <li><i class="fas fa-user-friends" aria-hidden="true"></i>{{ __('Register one or more attendees') }}</li>
                      <li><i class="fas fa-lock" aria-hidden="true"></i>{{ __('Details are encrypted and used for this event') }}</li>
                      <li><i class="far fa-envelope" aria-hidden="true"></i>{{ $registration_form?->approval_mode === 'automatic' ? __('Confirmation follows submission') : __('The organizer reviews each request') }}</li>
                    </ul>
                    @if ($registration_form)
                      <a class="theme-btn pulse-registration-submit" href="{{ route('event.registration.show', $content->id) }}">
                        {{ __('Start registration') }} <i class="fas fa-arrow-right" aria-hidden="true"></i>
                      </a>
                    @else
                      <div class="pulse-registration-unavailable">{{ __('Registration will open soon.') }}</div>
                    @endif
                    <small>{{ __('A registration request is not a paid ticket. Any payment requirements will be communicated separately.') }}</small>
                  </div>
                </section>
              @else
              <form action="{{ route('check-out2') }}" method="post"
                @if ($over == true) onsubmit="return false" @endif>
                @csrf
                <input type="hidden" name="event_id" value="{{ $content->id }}" id="">
                <input type="hidden" name="pricing_type" value="{{ $content->pricing_type }}" id="">
                <div class="event-details-information pulse-ticket-panel">
                  <div class="pulse-ticket-panel-heading">
                    <div>
                      <span>{{ __('Secure checkout') }}</span>
                      <h2>{{ __('Ticket price') }}</h2>
                    </div>
                    <i class="fas fa-ticket-alt" aria-hidden="true"></i>
                  </div>
                  <input type="hidden" name="date_type" value="{{ $content->date_type }}">
                  @if ($content->date_type == 'multiple')
                    @php
                      $dates = eventDates($content->id);
                      $exp_dates = eventExpDates($content->id);
                    @endphp

                    <div class="form-group">
                      <label for="">{{ __('Select Date') }}</label>
                      <select name="event_date" id="" class="form-control">
                        @if (count($dates) > 0)
                          @foreach ($dates as $date)
                            <option value="{{ FullDateTime($date->start_date_time) }}">
                              {{ FullDateTime($date->start_date_time) }}
                              ({{ timeZoneOffset($websiteInfo->timezone) }}
                              {{ __('GMT') }})
                            </option>
                          @endforeach
                        @endif
                        @if (count($exp_dates) > 0)
                          @foreach ($exp_dates as $exp_date)
                            <option disabled value="">
                              {{ FullDateTime($exp_date->start_date_time) }}
                              ({{ timeZoneOffset($websiteInfo->timezone) }}
                              {{ __('GMT') }})
                            </option>
                          @endforeach
                        @endif
                      </select>
                      @error('event_date')
                        <p class="text-danger">{{ $message }}</p>
                      @enderror
                    </div>
                  @else
                    <input type="hidden" name="event_date"
                      value="{{ FullDateTime($content->start_date . $content->start_time) }}">
                  @endif

                  <div class="pulse-ticket-support" aria-hidden="true">
                  {{-- Count down start --}}
                  @if ($content->date_type == 'single' && $content->countdown_status == 1)
                    <div class="event-details-top">
                      @if ($startDateTime >= $now_time)
                        <b>{{ __('Event Starts In') }}</b>
                        <hr>
                        @php
                          $dt = Carbon\Carbon::parse($startDateTime);
                          $year = $dt->year;
                          $month = $dt->month;
                          $day = $dt->day;
                          $end_time = Carbon\Carbon::parse($startDateTime);
                          $hour = $end_time->hour;
                          $minute = $end_time->minute;
                          $now = str_replace('+00:00', '.000' . timeZoneOffset($websiteInfo->timezone) . '00:00', gmdate('c'));
                        @endphp
                        <div class="count-down mb-3" dir="ltr">
                          <div class="event-countdown" data-now="{{ $now }}" data-year="{{ $year }}"
                            data-month="{{ $month }}" data-day="{{ $day }}"
                            data-hour="{{ $hour }}" data-minute="{{ $minute }}"
                            data-timezone="{{ timeZoneOffset($websiteInfo->timezone) }}">
                          </div>
                        </div>
                      @elseif ($startDateTime <= $endDateTime && $endDateTime >= $now_time)
                        <p>{{ __('The Event is Running') }}</p>
                      @else
                        <p>{{ __('The Event is Over') }}</p>
                      @endif
                    </div>
                  @endif

                  {{-- Countdown end --}}
                  <b>{{ __('Organised By') }}</b>
                  <hr>
                  @if ($organizer == '')
                    @php
                      $admin = App\Models\Admin::first();
                    @endphp
                    <div class="author">
                      <a
                        href="{{ route('frontend.organizer.details', [$admin->id, str_replace(' ', '-', $admin->username), 'admin' => 'true']) }}"><img
                          class="lazy" data-src="{{ asset('assets/admin/img/admins/' . $admin->image) }}"
                          alt="Author"></a>
                      <div class="content">
                        <h6><a
                            href="{{ route('frontend.organizer.details', [$admin->id, str_replace(' ', '-', $admin->username), 'admin' => 'true']) }}">{{ $admin->username }}</a>
                        </h6>
                      </div>
                    </div>
                  @else
                    <div class="author">
                      <a
                        href="{{ route('frontend.organizer.details', [$organizer->id, str_replace(' ', '-', $organizer->username)]) }}">
                        @if ($organizer->photo != null)
                          <img class="lazy"
                            data-src="{{ $organizer->avatar_url }}"
                            alt="Author">
                        @else
                          <img class="lazy" data-src="{{ asset('assets/front/images/user.png') }}" alt="Author">
                        @endif

                      </a>

                      <div class="content">
                        <h6><a
                            href="{{ route('frontend.organizer.details', [$organizer->id, str_replace(' ', '-', $organizer->username)]) }}">{{ $organizer->username }}</a>
                        </h6>
                        <a
                          href="{{ route('frontend.organizer.details', [$organizer->id, str_replace(' ', '-', $organizer->username)]) }}">{{ __('View  Profile') }}</a>
                      </div>
                    </div>
                  @endif
                  @if ($content->address != null)
                    <b><i class="fas fa-map-marker-alt"></i> {{ $content->address }}</b>
                    <hr>
                  @endif

                  {{-- Add to calendar --}}
                  @php
                    $start_date = str_replace('-', '', $content->start_date);
                    $start_time = str_replace(':', '', $content->start_time);
                    $end_date = str_replace('-', '', $content->end_date);
                    $end_time = str_replace(':', '', $content->end_time);
                  @endphp
                  <div class="dropdown show pt-4 pb-4">
                    <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink"
                      data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                      <i class="fas fa-calendar-alt"></i> {{ __('Add to Calendar') }}
                    </a>

                    <div class="dropdown-menu" aria-labelledby="dropdownMenuLink">
                      <a target="_blank" class="dropdown-item"
                        href="//calendar.google.com/calendar/u/0/r/eventedit?text={{ $content->title }}&dates={{ $start_date }}T{{ $start_time }}/{{ $end_date }}T{{ $end_time }}&ctz={{ $websiteInfo->timezone }}&details=For+details,+click+here:+{{ route('event.details', [$content->eventSlug, $content->id]) }}&location={{ $content->event_type == 'online' ? 'Online' : $content->address }}&sf=true">{{ __('Google Calendar') }}</a>
                      <a target="_blank" class="dropdown-item"
                        href="//calendar.yahoo.com/?v=60&view=d&type=20&TITLE={{ $content->title }}&ST={{ $start_date }}T{{ $start_time }}&ET={{ $end_date }}T{{ $end_time }}&DUR=9959&DESC=For%20details%2C%20click%20here%3A%20{{ route('event.details', [$content->eventSlug, $content->id]) }}&in_loc={{ $content->event_type == 'online' ? 'Online' : $content->address }}">{{ __('Yahoo') }}</a>
                    </div>
                  </div>
                  </div>


                  @if ($content->event_type == 'online' && $content->pricing_type == 'normal')

                    @php
                      $ticket = App\Models\Event\Ticket::where('event_id', $content->id)->first();
                      $event_count = App\Models\Event\Ticket::where('event_id', $content->id)
                          ->get()
                          ->count();
                      if ($ticket->ticket_available_type == 'limited') {
                          $stock = $ticket->ticket_available;
                      } else {
                          $stock = 'unlimited';
                      }
                      //ticket purchase or not check
                      if (Auth::guard('customer')->user() && $ticket->max_ticket_buy_type == 'limited') {
                          $purchase = isTicketPurchaseOnline($ticket->event_id, $ticket->max_buy_ticket);
                      } else {
                          $purchase = ['status' => 'false', 'p_qty' => 0];
                      }
                    @endphp
                    @if ($ticket)

                      <b>{{ __('Select Tickets') }}</b>
                      <hr>
                      <div class="price-count">
                        <h6 dir="ltr">

                          @if ($ticket->early_bird_discount == 'enable')
                            @php
                              $discount_date = Carbon\Carbon::parse($ticket->early_bird_discount_date . $ticket->early_bird_discount_time);
                            @endphp

                            @if ($ticket->early_bird_discount_type == 'fixed' && !$discount_date->isPast())
                              @php
                                $calculate_price = $ticket->price - $ticket->early_bird_discount_amount;
                              @endphp
                              {{ symbolPrice($calculate_price) }}
                              <del>
                                {{ symbolPrice($ticket->price) }}
                              </del>
                            @elseif ($ticket->early_bird_discount_type == 'percentage' && !$discount_date->isPast())
                              @php
                                $c_price = ($ticket->price * $ticket->early_bird_discount_amount) / 100;
                                $calculate_price = $ticket->price - $c_price;
                              @endphp
                              {{ symbolPrice($calculate_price) }}
                              <del>
                                {{ symbolPrice($ticket->price) }}
                              </del>
                            @else
                              @php
                                $calculate_price = $ticket->price;
                              @endphp
                              {{ symbolPrice($calculate_price) }}
                            @endif
                          @else
                            @php
                              $calculate_price = $ticket->price;
                            @endphp
                            {{ symbolPrice($calculate_price) }}
                          @endif


                        </h6>
                        <div class="quantity-input">
                          <button class="quantity-down" type="button" id="quantityDown">
                            -
                          </button>
                          <input class="quantity" type="number" readonly value="0"
                            data-price="{{ $calculate_price }}" data-max_buy_ticket="{{ $ticket->max_buy_ticket }}"
                            name="quantity" data-ticket_id="{{ $ticket->id }}" data-stock="{{ $stock }}"
                            data-purchase="{{ $purchase['status'] }}" data-p_qty="{{ $purchase['p_qty'] }}">
                          <button class="quantity-up" type="button" id="quantityUP">
                            +
                          </button>
                        </div>



                        @if ($ticket->early_bird_discount == 'enable')
                          @php
                            $discount_date = Carbon\Carbon::parse($ticket->early_bird_discount_date . $ticket->early_bird_discount_time);
                          @endphp
                          @if (!$discount_date->isPast())
                            <p>{{ __('Discount available') . ' ' }} :
                              ({{ __('till') . ' ' }} :
                              <span
                                dir="ltr">{{ \Carbon\Carbon::parse($discount_date)->timezone($websiteInfo->timezone)->translatedFormat('Y/m/d h:i a') }}</span>)
                            </p>
                          @endif
                        @endif


                      </div>
                      <p
                        class="text-warning max_error_{{ $ticket->id }}{{ $ticket->max_ticket_buy_type == 'limited' ? $ticket->max_buy_ticket : '' }} ">
                      </p>

                    @endif
                  @elseif($content->event_type == 'online' && $content->pricing_type == 'free')
                    <b>{{ __('Select Tickets') }}</b>
                    <hr>
                    @php
                      $ticket = App\Models\Event\Ticket::where('event_id', $content->id)->first();
                      $event_count = App\Models\Event\Ticket::where('event_id', $content->id)
                          ->get()
                          ->count();

                      if ($ticket->ticket_available_type == 'limited') {
                          $stock = $ticket->ticket_available;
                      } else {
                          $stock = 'unlimited';
                      }

                      //ticket purchase or not check
                      if (Auth::guard('customer')->user() && $ticket->max_ticket_buy_type == 'limited') {
                          $purchase = isTicketPurchaseOnline($ticket->event_id, $ticket->max_buy_ticket);
                          $max_buy_ticket = $ticket->max_buy_ticket;
                      } else {
                          $purchase = ['status' => 'false', 'p_qty' => 0];
                          $max_buy_ticket = 999999;
                      }
                    @endphp
                    <div class="price-count">
                      <h6>
                        {{ __('Free') }}
                      </h6>
                      <div class="quantity-input">
                        <button class="quantity-down" type="button" id="quantityDown">
                          -
                        </button>
                        <input class="quantity" readonly type="number" value="0"
                          data-price="{{ $content->price }}" data-max_buy_ticket="{{ $max_buy_ticket }}"
                          name="quantity" data-ticket_id="{{ $ticket->id }}" data-stock="{{ $stock }}"
                          data-purchase="{{ $purchase['status'] }}" data-p_qty="{{ $purchase['p_qty'] }}">
                        <button class="quantity-up" type="button" id="quantityUP">
                          +
                        </button>
                      </div>

                    </div>
                    <p
                      class="text-warning max_error_{{ $ticket->id }}{{ $ticket->max_ticket_buy_type == 'limited' ? $ticket->max_buy_ticket : '' }} ">
                    </p>
                  @elseif($content->event_type == 'venue')
                    @php
                      $tickets = DB::table('tickets')
                          ->where('event_id', $content->id)
                          ->get();
                    @endphp
                    @if (count($tickets) > 0)
                      <b>{{ __('Select Tickets') }}</b>
                      <hr>
                      @foreach ($tickets as $ticket)
                        @if ($ticket->pricing_type == 'normal')
                          @php
                            if ($ticket->ticket_available_type == 'limited') {
                                $stock = $ticket->ticket_available;
                            } else {
                                $stock = 'unlimited';
                            }

                            //ticket purchase or not check
                            $ticket_content = App\Models\Event\TicketContent::where([['language_id', $currentLanguageInfo->id], ['ticket_id', $ticket->id]])->first();

                            if (Auth::guard('customer')->user() && $ticket->max_ticket_buy_type == 'limited') {
                                $purchase = isTicketPurchaseVenue($ticket->event_id, $ticket->max_buy_ticket, $ticket->id, @$ticket_content->title);
                            } else {
                                $purchase = ['status' => 'false', 'p_qty' => 0];
                            }

                          @endphp
                          <p class="mb-0 pulse-ticket-name"><strong>{{ @$ticket_content->title }}</strong>
                            <span class="pulse-ticket-stock">{{ $stock === 'unlimited' ? __('Tickets available') : __(':count tickets left', ['count' => $stock]) }}</span>
                          </p>
                          <div class="click-show">
                            <div class="show-content">
                              {!! @$ticket_content->description !!}
                            </div>
                            @if (strlen(@$ticket_content->description) > 50)
                              <div class="read-more-btn">
                                <span>{{ __('Read more') }}</span>
                                <span>{{ __('Read less') }}</span>
                              </div>
                            @endif
                          </div>
                          <div class="price-count">
                            <h6 dir="ltr">
                              @if ($ticket->early_bird_discount == 'enable')
                                @php
                                  $discount_date = Carbon\Carbon::parse($ticket->early_bird_discount_date . $ticket->early_bird_discount_time);
                                @endphp

                                @if ($ticket->early_bird_discount_type == 'fixed' && !$discount_date->isPast())
                                  @php
                                    $calculate_price = $ticket->price - $ticket->early_bird_discount_amount;
                                  @endphp
                                  {{ symbolPrice($calculate_price) }}
                                  <del>

                                    {{ symbolPrice($ticket->price) }}
                                  </del>
                                @elseif ($ticket->early_bird_discount_type == 'percentage' && !$discount_date->isPast())
                                  @php
                                    $c_price = ($ticket->price * $ticket->early_bird_discount_amount) / 100;
                                    $calculate_price = $ticket->price - $c_price;
                                  @endphp
                                  {{ symbolPrice($calculate_price) }}

                                  <del>
                                    {{ symbolPrice($ticket->price) }}
                                  </del>
                                @else
                                  @php
                                    $calculate_price = $ticket->price;
                                  @endphp
                                  {{ symbolPrice($calculate_price) }}
                                @endif
                              @else
                                @php
                                  $calculate_price = $ticket->price;
                                @endphp
                                {{ symbolPrice($calculate_price) }}
                              @endif


                            </h6>
                            <div class="quantity-input">
                              <button class="quantity-down" type="button" id="quantityDown">
                                -
                              </button>
                              <input class="quantity" readonly type="number" value="0"
                                data-price="{{ $calculate_price }}"
                                data-max_buy_ticket="{{ $ticket->max_buy_ticket }}" name="quantity[]"
                                data-ticket_id="{{ $ticket->id }}" data-stock="{{ $stock }}"
                                data-purchase="{{ $purchase['status'] }}" data-p_qty="{{ $purchase['p_qty'] }}">
                              <button class="quantity-up" type="button" id="quantityUP">
                                +
                              </button>
                            </div>


                            @if ($ticket->early_bird_discount == 'enable')
                              @php
                                $discount_date = Carbon\Carbon::parse($ticket->early_bird_discount_date . $ticket->early_bird_discount_time);
                              @endphp
                              @if (!$discount_date->isPast())
                                <p>{{ __('Discount available') . ' ' }} :
                                  ({{ __('till') . ' ' }} :
                                  <span
                                    dir="ltr">{{ \Carbon\Carbon::parse($discount_date)->timezone($websiteInfo->timezone)->translatedFormat('Y/m/d h:i a') }}</span>)
                                </p>
                              @endif
                            @endif

                          </div>
                          <p
                            class="text-warning max_error_{{ $ticket->id }}{{ $ticket->max_ticket_buy_type == 'limited' ? $ticket->max_buy_ticket : '' }} ">
                          </p>
                        @elseif($ticket->pricing_type == 'variation')
                          @php
                            $variations = json_decode($ticket->variations);

                            $varition_names = App\Models\Event\VariationContent::where([['ticket_id', $ticket->id], ['language_id', $currentLanguageInfo->id]])->get();
                            if (empty($varition_names)) {
                                $varition_names = App\Models\Event\VariationContent::where('ticket_id', $ticket->id)->get();
                            }

                            $de_lang = App\Models\Language::where('is_default', 1)->first();
                            $de_varition_names = App\Models\Event\VariationContent::where([['ticket_id', $ticket->id], ['language_id', $de_lang->id]])->get();
                            if (empty($de_varition_names)) {
                                $de_varition_names = App\Models\Event\VariationContent::where([['ticket_id', $ticket->id]])->get();
                            }
                          @endphp
                          @foreach ($variations as $key => $item)
                            @php
                              //ticket purchase or not check
                              if (Auth::guard('customer')->user()) {
                                  if (count($de_varition_names) > 0) {
                                      $purchase = isTicketPurchaseVenue($ticket->event_id, $item->v_max_ticket_buy, $ticket->id, $de_varition_names[$key]['name']);
                                  }
                              } else {
                                  $purchase = ['status' => 'false', 'p_qty' => 0];
                              }
                              $ticket_content = App\Models\Event\TicketContent::where([['language_id', $currentLanguageInfo->id], ['ticket_id', $ticket->id]])->first();
                              if (empty($ticket_content)) {
                                  $ticket_content = App\Models\Event\TicketContent::where([['ticket_id', $ticket->id]])->first();
                              }
                            @endphp
                            <p class="mb-0 pulse-ticket-name"><strong>{{ @$ticket_content->title }} -
                                {{ @$varition_names[$key]['name'] }}</strong>
                              <span class="pulse-ticket-stock">{{ $item->ticket_available_type === 'limited' ? __(':count tickets left', ['count' => $item->ticket_available]) : __('Tickets available') }}</span>
                            </p>
                            <div class="click-show">
                              <div class="show-content">
                                {!! @$ticket_content->description !!}
                              </div>
                              @if (strlen(@$ticket_content->description) > 50)
                                <div class="read-more-btn">
                                  <span>{{ __('Read more') }}</span>
                                  <span>{{ __('Read less') }}</span>
                                </div>
                              @endif
                            </div>
                            <div class="price-count">
                              <h6 dir="ltr">
                                @if ($ticket->early_bird_discount == 'enable')
                                  @php
                                    $discount_date = Carbon\Carbon::parse($ticket->early_bird_discount_date . $ticket->early_bird_discount_time);
                                  @endphp
                                  @if ($ticket->early_bird_discount_type == 'fixed' && !$discount_date->isPast())
                                    @php
                                      $calculate_price = $item->price - $ticket->early_bird_discount_amount;
                                    @endphp
                                    {{ symbolPrice($calculate_price) }}

                                    <del>
                                      {{ symbolPrice($item->price) }}
                                    </del>
                                  @elseif ($ticket->early_bird_discount_type == 'percentage' && !$discount_date->isPast())
                                    @php
                                      $c_price = ($item->price * $ticket->early_bird_discount_amount) / 100;
                                      $calculate_price = $item->price - $c_price;
                                    @endphp
                                    {{ symbolPrice($calculate_price) }}

                                    <del>
                                      {{ symbolPrice($item->price) }}
                                    </del>
                                  @else
                                    @php
                                      $calculate_price = $item->price;
                                    @endphp
                                    {{ symbolPrice($calculate_price) }}
                                  @endif
                                @else
                                  @php
                                    $calculate_price = $item->price;
                                  @endphp
                                  {{ symbolPrice($calculate_price) }}
                                @endif

                              </h6>

                              <div class="quantity-input">
                                <button class="quantity-down_variation" type="button" id="quantityDown">
                                  -
                                </button>
                                <input type="hidden" name="v_name[]" value="{{ $item->name }}">
                                @php
                                  if ($item->ticket_available_type == 'limited') {
                                      $stock = $item->ticket_available;
                                  } else {
                                      $stock = 'unlimited';
                                  }
                                  if ($item->max_ticket_buy_type == 'limited') {
                                      $max_buy = $item->v_max_ticket_buy;
                                  } else {
                                      $max_buy = 'unlimited';
                                  }
                                @endphp
                                <input type="number" value="0" class="quantity"
                                  data-price="{{ $calculate_price }}" data-max_buy_ticket="{{ $max_buy }}"
                                  data-name="{{ $item->name }}" name="quantity[]"
                                  data-ticket_id="{{ $ticket->id }}" readonly data-stock="{{ $stock }}"
                                  data-purchase="{{ $purchase['status'] }}" data-p_qty="{{ $purchase['p_qty'] }}">
                                <button class="quantity-up" type="button" id="quantityUP">
                                  +
                                </button>
                              </div>
                              @if ($ticket->early_bird_discount == 'enable')
                                @php
                                  $discount_date = Carbon\Carbon::parse($ticket->early_bird_discount_date . $ticket->early_bird_discount_time);
                                @endphp
                                @if (!$discount_date->isPast())
                                  <p>{{ __('Discount available') . ' ' }} :
                                    ({{ __('till') . ' ' }} :
                                    <span
                                      dir="ltr">{{ \Carbon\Carbon::parse($discount_date)->timezone($websiteInfo->timezone)->translatedFormat('Y/m/d h:i a') }}</span>)
                                  </p>
                                @endif
                              @endif
                            </div>
                            <p class="text-warning max_error_{{ $ticket->id }}{{ $item->v_max_ticket_buy }} ">
                            </p>
                          @endforeach
                        @elseif($ticket->pricing_type == 'free')
                          @php
                            if ($ticket->ticket_available_type == 'limited') {
                                $stock = $ticket->ticket_available;
                            } else {
                                $stock = 'unlimited';
                            }

                            //ticket purchase or not check
                            $de_lang = App\Models\Language::where('is_default', 1)->first();
                            $ticket_content_default = App\Models\Event\TicketContent::where([['language_id', $de_lang->id], ['ticket_id', $ticket->id]])->first();
                            if (Auth::guard('customer')->user() && $ticket->max_ticket_buy_type == 'limited') {
                                $purchase = isTicketPurchaseVenue($ticket->event_id, $ticket->max_buy_ticket, $ticket->id, @$ticket_content_default->title);
                            } else {
                                $purchase = ['status' => 'false', 'p_qty' => 1];
                            }
                            $ticket_content = App\Models\Event\TicketContent::where([['language_id', $currentLanguageInfo->id], ['ticket_id', $ticket->id]])->first();
                          @endphp
                          <p class="mb-0 pulse-ticket-name"><strong>{{ @$ticket_content->title }}</strong>
                            <span class="pulse-ticket-stock">{{ $stock === 'unlimited' ? __('Tickets available') : __(':count tickets left', ['count' => $stock]) }}</span>
                          </p>
                          <div class="click-show">
                            <div class="show-content">
                              {!! @$ticket_content->description !!}
                            </div>
                            @if (strlen(@$ticket_content->description) > 50)
                              <div class="read-more-btn">
                                <span>{{ __('Read more') }}</span>
                                <span>{{ __('Read less') }}</span>
                              </div>
                            @endif
                          </div>
                          <div class="price-count">
                            <h6>
                              <span class="">{{ __('free') }}</span>
                            </h6>
                            <div class="quantity-input">
                              <button class="quantity-down" type="button" id="quantityDown">
                                -
                              </button>
                              <input class="quantity" data-max_buy_ticket="{{ $ticket->max_buy_ticket }}"
                                type="number" value="0" data-price="{{ $ticket->price }}" name="quantity[]"
                                data-ticket_id="{{ $ticket->id }}" readonly data-stock="{{ $stock }}"
                                data-purchase="{{ $purchase['status'] }}" data-p_qty="{{ $purchase['p_qty'] }}">
                              <button class="quantity-up" type="button" id="quantityUP">
                                +
                              </button>
                            </div>
                          </div>
                          <p
                            class="text-warning max_error_{{ $ticket->id }}{{ $ticket->max_ticket_buy_type == 'limited' ? $ticket->max_buy_ticket : '' }} ">
                          </p>
                        @endif
                      @endforeach
                    @endif
                  @endif
                  @if ($tickets_count > 0)
                    <div class="total">
                      <b>{{ __('Total Price') . ' :' }} </b>
                      <span class="h4" dir="ltr">
                        <span>{{ $basicInfo->base_currency_symbol_position == 'left' ? $basicInfo->base_currency_symbol : '' }}</span>
                        <span id="total_price">0</span>
                        <span>{{ $basicInfo->base_currency_symbol_position == 'right' ? $basicInfo->base_currency_symbol : '' }}</span>

                      </span>
                      <input type="hidden" name="total" id="total">
                    </div>
                    <button class="theme-btn pulse-ticket-submit" data-ticket-submit type="submit" disabled>
                      <span>{{ __('Select tickets above') }}</span><i class="fas fa-arrow-right" aria-hidden="true"></i>
                    </button>
                    <ul class="pulse-checkout-trust" aria-label="{{ __('Checkout assurances') }}">
                      <li><i class="fas fa-lock" aria-hidden="true"></i>{{ __('Secure payment processing') }}</li>
                      <li><i class="far fa-envelope" aria-hidden="true"></i>{{ __('Ticket delivered by email') }}</li>
                      <li><i class="fas fa-shield-alt" aria-hidden="true"></i>{{ __('Ticket policy shown before purchase') }}</li>
                    </ul>
                  @endif
                </div>
              </form>
              @if (!empty($waitlist_options))
                <section class="pulse-waitlist-panel" aria-labelledby="waitlist-heading">
                  <span class="pulse-waitlist-eyebrow">{{ __('Sold out?') }}</span>
                  <h2 id="waitlist-heading">{{ __('Join the waiting list') }}</h2>
                  <p>{{ __('Choose a sold-out ticket and we will email you if availability returns. Joining does not reserve a ticket.') }}</p>

                  @if ($errors->has('waitlist'))
                    <div class="alert alert-danger" role="alert">{{ $errors->first('waitlist') }}</div>
                  @endif
                  <form method="POST" action="{{ route('event.waitlist.store', $content->id) }}">
                    @csrf
                    <div class="form-group">
                      <label for="waitlist-ticket">{{ __('Sold-out ticket') }}</label>
                      <select class="form-control" id="waitlist-ticket" name="waitlist_selection" required>
                        @foreach ($waitlist_options as $index => $option)
                          <option value="{{ $index }}" @selected((string) old('waitlist_selection') === (string) $index)>
                            {{ $option['label'] }}
                          </option>
                        @endforeach
                      </select>
                    </div>
                    @foreach ($waitlist_options as $index => $option)
                      <input type="hidden" data-waitlist-ticket="{{ $index }}" value="{{ $option['ticket_id'] }}">
                      <input type="hidden" data-waitlist-variation="{{ $index }}" value="{{ $option['variation_name'] }}">
                    @endforeach
                    <input type="hidden" id="waitlist-ticket-id" name="ticket_id" value="{{ old('ticket_id', $waitlist_options[0]['ticket_id']) }}">
                    <input type="hidden" id="waitlist-variation-name" name="variation_name" value="{{ old('variation_name', $waitlist_options[0]['variation_name']) }}">
                    <div class="form-group">
                      <label for="waitlist-name">{{ __('Name') }}</label>
                      <input class="form-control" id="waitlist-name" name="name" maxlength="120" required
                        value="{{ old('name', optional(Auth::guard('customer')->user())->fname) }}" autocomplete="name">
                      @error('name')<small class="text-danger">{{ $message }}</small>@enderror
                    </div>
                    <div class="form-group">
                      <label for="waitlist-email">{{ __('Email') }}</label>
                      <input class="form-control" id="waitlist-email" type="email" name="email" maxlength="190" required
                        value="{{ old('email', optional(Auth::guard('customer')->user())->email) }}" autocomplete="email">
                      @error('email')<small class="text-danger">{{ $message }}</small>@enderror
                    </div>
                    <label class="pulse-waitlist-consent">
                      <input type="checkbox" name="waitlist_consent" value="1" required @checked(old('waitlist_consent'))>
                      <span>{{ __('Email me only about availability for this ticket. I can unsubscribe at any time.') }}</span>
                    </label>
                    @error('waitlist_consent')<small class="text-danger d-block mb-2">{{ $message }}</small>@enderror
                    <button class="theme-btn pulse-waitlist-submit" type="submit">
                      {{ __('Notify me') }} <i class="far fa-envelope" aria-hidden="true"></i>
                    </button>
                  </form>
                </section>
              @endif
              @endif
            </div>
          </div>
        </div>
        @if (!empty(showAd(3)))
          <div class="text-center mt-4">
            {!! showAd(3) !!}
          </div>
        @endif
      </div>
      @if (count($related_events) > 0)
        <hr>
        <div class="releted-event-header mt-50">
          <h3>{{ __('Other events you may like') }}</h3>
          <div class="slick-next-prev mb-10">
            <button class="prev" type="button" aria-label="{{ __('Previous related events') }}"><i class="fas fa-chevron-left" aria-hidden="true"></i></button>
            <button class="next" type="button" aria-label="{{ __('Next related events') }}"><i class="fas fa-chevron-right" aria-hidden="true"></i></button>
          </div>
        </div>
        <div class="related-event-wrap">
          @foreach ($related_events as $event)
            @php
              $relatedThumbnailPath = 'assets/admin/img/event/thumbnail/' . $event->thumbnail;
              $hasRelatedThumbnail = !empty($event->thumbnail);
            @endphp
            <div class="event-item">
              <div class="event-image">
                <a href="{{ route('event.details', [$event->slug, $event->id]) }}">
                  @if ($hasRelatedThumbnail)
                    <img src="{{ \App\Support\PublicImage::flyer($event->thumbnail) }}" alt="{{ $event->title }}">
                  @else
                    <img src="{{ asset('assets/admin/img/event/thumbnail/1776131871.jpeg') }}"
                      alt="{{ $event->title }}">
                  @endif
                </a>
              </div>
              <div class="event-content">
                <ul class="time-info">
                  <li>
                    <i class="far fa-calendar-alt"></i>
                    <span>
                      @php
                        $date = strtotime($event->start_date);
                      @endphp
                      {{ \Carbon\Carbon::parse($date)->timezone($websiteInfo->timezone)->translatedFormat('d M Y') }}
                    </span>
                  </li>
                  @php
                    if ($event->date_type == 'multiple') {
                        $event_date = eventLatestDates($event->id);
                        $date = strtotime(@$event_date->start_date);
                    } else {
                        $date = strtotime($event->start_date);
                    }
                  @endphp
                  <li>
                    <i class="far fa-hourglass"></i>
                    <span
                      title="Event Duration">{{ $event->date_type == 'multiple' ? @$event_date->duration : $event->duration }}</span>
                  </li>
                  <li>
                    <i class="far fa-clock"></i>
                    <span>
                      @php
                        $start_time = strtotime($event->start_time);
                      @endphp
                      {{ \Carbon\Carbon::parse($start_time)->timezone($websiteInfo->timezone)->translatedFormat('h:i A') }}
                    </span>
                  </li>
                </ul>
                @if ($event->organizer_id != null)
                  @php
                    $organizer = App\Models\Organizer::where('id', $event->organizer_id)->first();
                  @endphp
                  @if ($organizer)
                    <a href="{{ route('frontend.organizer.details', [$organizer->id, str_replace(' ', '-', $organizer->username)]) }}"
                      class="organizer">{{ __('By') }}&nbsp;&nbsp;{{ $organizer->username }}</a>
                  @endif
                @else
                  @php
                    $admin = App\Models\Admin::first();
                  @endphp
                  <a href="{{ route('frontend.organizer.details', [$admin->id, str_replace(' ', '-', $admin->username), 'admin' => 'true']) }}"
                    class="organizer">{{ $admin->username }}</a>
                @endif
                <h5>
                  <a href="{{ route('event.details', [$event->slug, $event->id]) }}">
                    @if (strlen($event->title) > 30)
                      {{ mb_substr($event->title, 0, 30) . '...' }}
                    @else
                      {{ $event->title }}
                    @endif
                  </a>
                </h5>
                @php
                  $desc = strip_tags($event->description);
                @endphp

                @if (strlen($desc) > 45)
                  <p>{{ mb_substr($desc, 0, 50) . '....' }}</p>
                @else
                  <p>{{ $desc }}</p>
                @endif
                @php
                  $ticket = DB::table('tickets')
                      ->where('event_id', $event->id)
                      ->first();
                  $event_count = DB::table('tickets')
                      ->where('event_id', $event->id)
                      ->get()
                      ->count();
                @endphp

                <div class="price-remain">
                  <div class="location">
                    @if ($event->event_type == 'venue')
                      <i class="fas fa-map-marker-alt"></i>
                      <span>
                        @if ($event->city != null)
                          {{ $event->city }}
                        @endif
                        @if ($event->country)
                          , {{ $event->country }}
                        @endif
                      </span>
                    @else
                      <i class="fas fa-map-marker-alt"></i>
                      <span>{{ __('Online') }}</span>
                    @endif
                  </div>
                  <span>
                    @if ($ticket)
                      @if ($ticket->event_type == 'online')
                        @if ($ticket->price != null)
                          <span class="price" dir="ltr">
                            @if ($ticket->early_bird_discount == 'enable')
                              @php
                                $discount_date = Carbon\Carbon::parse($ticket->early_bird_discount_date . $ticket->early_bird_discount_time);
                              @endphp
                              @if ($ticket->early_bird_discount_type == 'fixed' && !$discount_date->isPast())
                                @php
                                  $calculate_price = $ticket->price - $ticket->early_bird_discount_amount;
                                @endphp
                                {{ symbolPrice($calculate_price) }}
                                <span>
                                  <del>
                                    {{ symbolPrice($ticket->price) }}
                                  </del>
                                </span>
                              @elseif ($ticket->early_bird_discount_type == 'percentage' && !$discount_date->isPast())
                                @php
                                  $p_price = ($ticket->price * $ticket->early_bird_discount_amount) / 100;
                                  $calculate_price = $ticket->price - $p_price;
                                @endphp
                                {{ symbolPrice($calculate_price) }}

                                <span>
                                  <del>
                                    {{ symbolPrice($ticket->price) }}
                                  </del>
                                </span>
                              @else
                                @php
                                  $calculate_price = $ticket->price;
                                @endphp
                                {{ symbolPrice($calculate_price) }}
                              @endif
                            @else
                              @php
                                $calculate_price = $ticket->price;
                              @endphp
                              {{ symbolPrice($calculate_price) }}
                            @endif
                          </span>
                        @else
                          <span class="price">{{ __('Free') }}</span>
                        @endif
                      @endif
                      @if ($ticket->event_type == 'venue')
                        @if ($ticket->pricing_type == 'variation')
                          <span class="price" dir="ltr">
                            @php
                              $variation = json_decode($ticket->variations, true);
                              $price = $variation[0]['price'];
                            @endphp
                            <span class="price">

                              @if ($ticket->early_bird_discount == 'enable')
                                @php
                                  $discount_date = Carbon\Carbon::parse($ticket->early_bird_discount_date . $ticket->early_bird_discount_time);
                                @endphp
                                @if ($ticket->early_bird_discount_type == 'fixed' && !$discount_date->isPast())
                                  @php
                                    $calculate_price = $price - $ticket->early_bird_discount_amount;
                                  @endphp
                                  {{ symbolPrice($calculate_price) }}
                                  <span><del>
                                      {{ symbolPrice($price) }}
                                    </del></span>
                                @elseif ($ticket->early_bird_discount_type == 'percentage' && !$discount_date->isPast())
                                  @php
                                    $p_price = ($price * $ticket->early_bird_discount_amount) / 100;
                                    $calculate_price = $p_price - $price;
                                  @endphp

                                  {{ symbolPrice($calculate_price) }}

                                  <span>
                                    <del>
                                      {{ symbolPrice($price) }}
                                    </del>
                                  </span>
                                @else
                                  @php
                                    $calculate_price = $price;
                                  @endphp
                                  {{ symbolPrice($calculate_price) }}
                                @endif
                              @else
                                @php
                                  $calculate_price = $price;
                                @endphp
                                {{ symbolPrice($calculate_price) }}
                              @endif
                              @if ($event_count > 1)<small>{{ __('from') }}</small>@endif
                            </span>
                          </span>
                        @elseif($ticket->pricing_type == 'normal')
                          <span class="price" dir="ltr">

                            @if ($ticket->early_bird_discount == 'enable')
                              @php
                                $discount_date = Carbon\Carbon::parse($ticket->early_bird_discount_date . $ticket->early_bird_discount_time);
                              @endphp
                              @if ($ticket->early_bird_discount_type == 'fixed' && !$discount_date->isPast())
                                @php
                                  $calculate_price = $ticket->price - $ticket->early_bird_discount_amount;
                                @endphp

                                {{ symbolPrice($calculate_price) }}
                                <span>
                                  <del>
                                    {{ symbolPrice($ticket->price) }}
                                  </del>
                                </span>
                              @elseif ($ticket->early_bird_discount_type == 'percentage' && !$discount_date->isPast())
                                @php
                                  $p_price = ($ticket->price * $ticket->early_bird_discount_amount) / 100;
                                  $calculate_price = $ticket->price - $p_price;
                                @endphp
                                {{ symbolPrice($calculate_price) }}

                                <span>
                                  <del>
                                    {{ symbolPrice($ticket->price) }}
                                  </del>
                                </span>
                              @else
                                @php
                                  $calculate_price = $ticket->price;
                                @endphp
                                {{ symbolPrice($calculate_price) }}
                              @endif
                            @else
                              @php
                                $calculate_price = $ticket->price;
                              @endphp
                              {{ symbolPrice($calculate_price) }}
                            @endif
                            @if ($event_count > 1)<small>{{ __('from') }}</small>@endif
                          </span>
                        @else
                          <span class="price">{{ __('Free') }}</span>
                        @endif
                      @endif
                    @endif
                  </span>
                </div>
              </div>
              @if (Auth::guard('customer')->check())
                @php
                  $customer_id = Auth::guard('customer')->user()->id;
                  $event_id = $event->id;
                  $checkWishList = checkWishList($event_id, $customer_id);
                @endphp
              @else
                @php
                  $checkWishList = false;
                @endphp
              @endif
              <form method="POST" class="wishlist-action-form" action="{{ $checkWishList == false ? route('addto.wishlist', $event->id) : route('remove.wishlist', $event->id) }}">
                @csrf
                <button type="submit" class="wishlist-btn {{ $checkWishList == true ? 'bg-success' : '' }}">
                  <i class="far fa-bookmark"></i>
                </button>
              </form>
            </div>
          @endforeach
        </div>

      @endif
    </div>
  </section>
  <!-- Event Page End -->

@endsection
@section('modals')
  @includeIf('frontend.partials.modals')
@endsection

@section('custom-script')
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      var description = document.querySelector('[data-event-description]');
      var descriptionToggle = document.querySelector('[data-description-toggle]');
      var ticketPanel = document.querySelector('.pulse-ticket-panel');
      var ticketSubmit = document.querySelector('[data-ticket-submit]');
      var shareModal = document.querySelector('.pulse-share-modal');
      var relatedEvents = document.querySelector('.related-event-wrap');
      var waitlistSelect = document.getElementById('waitlist-ticket');
      window.ticaTrack('view_event', {
        event_id: {{ (int) $content->id }},
        event_name: @json($content->title),
        category: @json($content->name)
      });

      function syncRelatedEventsLayout() {
        if (!relatedEvents || !window.jQuery || !window.jQuery.fn.slick) return;
        var $relatedEvents = window.jQuery(relatedEvents);
        var isMobile = window.matchMedia('(max-width: 767px)').matches;

        if (isMobile && $relatedEvents.hasClass('slick-initialized')) {
          $relatedEvents.slick('unslick');
        }
      }

      window.setTimeout(syncRelatedEventsLayout, 80);

      if (waitlistSelect) {
        var syncWaitlistSelection = function () {
          var key = waitlistSelect.value;
          var ticket = document.querySelector('[data-waitlist-ticket="' + key + '"]');
          var variation = document.querySelector('[data-waitlist-variation="' + key + '"]');
          document.getElementById('waitlist-ticket-id').value = ticket ? ticket.value : '';
          document.getElementById('waitlist-variation-name').value = variation ? variation.value : '';
        };
        waitlistSelect.addEventListener('change', syncWaitlistSelection);
        syncWaitlistSelection();
      }

      if (description && descriptionToggle) {
        if (description.scrollHeight <= description.clientHeight + 4) {
          descriptionToggle.hidden = true;
        }

        descriptionToggle.addEventListener('click', function () {
          var expanded = descriptionToggle.getAttribute('aria-expanded') === 'true';
          description.classList.toggle('is-expanded', !expanded);
          descriptionToggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
          descriptionToggle.querySelector('span').textContent = expanded
            ? '{{ __('Show more') }}'
            : '{{ __('Show less') }}';
        });
      }

      function syncTicketSubmit() {
        if (!ticketPanel || !ticketSubmit) return;
        var selected = Array.prototype.slice.call(ticketPanel.querySelectorAll('.quantity'))
          .some(function (input) { return Number(input.value) > 0; });

        ticketSubmit.disabled = !selected;
        ticketSubmit.setAttribute('aria-disabled', selected ? 'false' : 'true');
        ticketSubmit.querySelector('span').textContent = selected
          ? '{{ __('Continue to checkout') }}'
          : '{{ __('Select tickets above') }}';
      }

      function refreshTicketTotal() {
        if (!ticketPanel) return;
        var total = Array.prototype.slice.call(ticketPanel.querySelectorAll('.quantity'))
          .reduce(function (sum, input) {
            return sum + ((Number(input.getAttribute('data-price')) || 0) * (Number(input.value) || 0));
          }, 0);
        var totalLabel = ticketPanel.querySelector('#total_price');
        var totalInput = ticketPanel.querySelector('#total');
        if (totalLabel) totalLabel.textContent = total.toLocaleString('en-NG');
        if (totalInput) totalInput.value = total;
      }

      if (ticketPanel) {
        Array.prototype.slice.call(ticketPanel.querySelectorAll('.quantity-input')).forEach(function (quantityGroup) {
          var ticketBlock = quantityGroup.closest('.price-count');
          var ticketName = ticketBlock && ticketBlock.previousElementSibling
            ? ticketBlock.previousElementSibling.previousElementSibling
            : null;
          var name = ticketName && ticketName.querySelector('strong')
            ? ticketName.querySelector('strong').textContent.trim()
            : '{{ __('ticket') }}';
          var down = quantityGroup.querySelector('.quantity-down, .quantity-down_variation');
          var up = quantityGroup.querySelector('.quantity-up');
          var input = quantityGroup.querySelector('.quantity');
          if (down) down.setAttribute('aria-label', '{{ __('Decrease') }} ' + name + ' {{ __('quantity') }}');
          if (up) up.setAttribute('aria-label', '{{ __('Increase') }} ' + name + ' {{ __('quantity') }}');
          if (input) input.setAttribute('aria-label', name + ' {{ __('quantity') }}');
        });

        ticketPanel.addEventListener('click', function (event) {
          var control = event.target.closest('.quantity-up, .quantity-down, .quantity-down_variation');
          if (!control) return;
          var isIncrease = control.classList.contains('quantity-up');
          var input = isIncrease ? control.previousElementSibling : control.nextElementSibling;
          if (input && input.classList.contains('quantity')) {
            control.setAttribute('data-quantity-before-click', input.value);
          }
        }, true);

        ticketPanel.addEventListener('click', function (event) {
          var control = event.target.closest('.quantity-up, .quantity-down, .quantity-down_variation');
          if (!control) return;
          var isIncrease = control.classList.contains('quantity-up');
          var input = isIncrease ? control.previousElementSibling : control.nextElementSibling;
          if (!input || !input.classList.contains('quantity')) return;
          var previousValue = Number(control.getAttribute('data-quantity-before-click')) || 0;
          control.removeAttribute('data-quantity-before-click');

          window.setTimeout(function () {
            if ((Number(input.value) || 0) === previousValue) {
              var stockValue = input.getAttribute('data-stock');
              var stock = stockValue === 'unlimited' ? Infinity : Number(stockValue);
              var maxValue = Number(input.getAttribute('data-max_buy_ticket')) || Infinity;
              var nextValue = isIncrease
                ? Math.min(previousValue + 1, stock, maxValue)
                : Math.max(previousValue - 1, 0);
              input.value = Number.isFinite(nextValue) ? nextValue : previousValue + (isIncrease ? 1 : -1);
            }
            refreshTicketTotal();
            syncTicketSubmit();
            window.ticaTrack('select_ticket', {
              event_id: {{ (int) $content->id }},
              quantity: Number(input.value) || 0,
              ticket_id: input.getAttribute('data-ticket_id') || ''
            });
          }, 0);
        });
      }

      syncTicketSubmit();

      var ticketForm = ticketPanel ? ticketPanel.closest('form') : null;
      if (ticketForm) {
        ticketForm.addEventListener('submit', function () {
          window.ticaTrack('begin_checkout', {
            event_id: {{ (int) $content->id }},
            value: Number((document.getElementById('total_price') || {}).textContent || 0)
          });
        });
      }

      if (shareModal) {
        var shareUrl = shareModal.getAttribute('data-share-url');
        var shareTitle = shareModal.getAttribute('data-share-title');
        var copyButton = shareModal.querySelector('[data-copy-event-link]');
        var copyButtonLabel = copyButton ? copyButton.querySelector('span') : null;
        var shareStatus = shareModal.querySelector('[data-share-status]');
        var qrTarget = shareModal.querySelector('[data-share-qr]');
        var qrDownload = shareModal.querySelector('[data-download-qr]');
        var nativeShare = shareModal.querySelector('[data-native-share]');

        function setShareStatus(message) {
          if (!shareStatus) return;
          shareStatus.textContent = message;
          window.clearTimeout(setShareStatus.timer);
          setShareStatus.timer = window.setTimeout(function () {
            shareStatus.textContent = '';
          }, 2400);
        }

        function buildQrCode() {
          if (!qrTarget || !qrDownload || qrTarget.getAttribute('data-qr-prepared') === 'true') return;

          try {
            var svg = qrTarget.querySelector('svg');
            if (!svg) return;

            qrTarget.setAttribute('data-qr-prepared', 'true');
            if (!svg.getAttribute('viewBox')) {
              svg.setAttribute('viewBox', '0 0 160 160');
            }
            svg.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
            svg.setAttribute('shape-rendering', 'crispEdges');
            var background = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
            background.setAttribute('width', '100%');
            background.setAttribute('height', '100%');
            background.setAttribute('fill', '#ffffff');
            svg.insertBefore(background, svg.firstChild);

            var qrMarkup = new XMLSerializer().serializeToString(svg);
            var qrBlob = new Blob([qrMarkup], { type: 'image/svg+xml;charset=utf-8' });
            qrDownload.href = URL.createObjectURL(qrBlob);
          } catch (error) {
            qrTarget.innerHTML = '<i class="fas fa-qrcode" aria-hidden="true"></i>';
            qrDownload.hidden = true;
          }
        }

        function copyEventLink() {
          var fallbackCopy = function () {
            var linkInput = shareModal.querySelector('[data-share-url-input]');
            if (!linkInput) return false;
            linkInput.focus();
            linkInput.select();
            return document.execCommand('copy');
          };

          var copyPromise = navigator.clipboard && window.isSecureContext
            ? navigator.clipboard.writeText(shareUrl)
            : Promise.resolve(fallbackCopy());

          copyPromise.then(function () {
            if (copyButtonLabel) copyButtonLabel.textContent = '{{ __('Copied') }}';
            copyButton.classList.add('is-copied');
            setShareStatus('{{ __('Event link copied to your clipboard.') }}');
            window.setTimeout(function () {
              if (copyButtonLabel) copyButtonLabel.textContent = '{{ __('Copy link') }}';
              copyButton.classList.remove('is-copied');
            }, 1800);
          }).catch(function () {
            fallbackCopy();
            setShareStatus('{{ __('Select the link and copy it manually.') }}');
          });
        }

        buildQrCode();

        if (window.jQuery) {
          window.jQuery(shareModal)
            .on('shown.bs.modal', function () {
              document.body.classList.add('share-dialog-open');
              buildQrCode();
            })
            .on('hidden.bs.modal', function () {
              document.body.classList.remove('share-dialog-open');
            });
        }

        if (copyButton) {
          copyButton.addEventListener('click', copyEventLink);
        }

        if (nativeShare && navigator.share) {
          nativeShare.hidden = false;
          nativeShare.addEventListener('click', function () {
            navigator.share({ title: shareTitle, text: '{{ __('Discover this event on Tica') }}', url: shareUrl })
              .catch(function () {});
          });
        }
      }
    });
  </script>
@endsection
