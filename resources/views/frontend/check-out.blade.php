@extends('frontend.layout')

@section('pageHeading')
  {{ __('Secure checkout') }}
@endsection

@section('custom-style')
  <link rel="stylesheet" href="{{ asset('assets/admin/css/summernote-content.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/front/css/event-checkout-flow.css') }}">
@endsection

<meta name="csrf-token" content="{{ csrf_token() }}">

@php
  $authUser = Auth::guard('customer')->user();
  $attendeeFields = ['fname', 'lname', 'email', 'phone'];
  $hasAttendeeError = collect($attendeeFields)->contains(fn ($field) => $errors->has($field));
  $initialStep = $errors->any() || Session::has('error') || Session::has('currency_error')
      ? ($hasAttendeeError ? 2 : 3)
      : 1;
  $eventUrl = route('event.details', [$event->slug, $event->id]);
@endphp

@section('hero-section')
  <section class="checkout-hero" aria-labelledby="checkout-page-title">
    <div class="container">
      <a class="checkout-back-link" href="{{ $eventUrl }}">
        <i class="fas fa-arrow-left" aria-hidden="true"></i>
        <span>{{ __('Back to event') }}</span>
      </a>
      <p class="checkout-eyebrow">{{ __('Secure ticket checkout') }}</p>
      <h1 id="checkout-page-title">{{ __('Complete your booking') }}</h1>
      <p>{{ __('Review your tickets, add attendee details, then choose how you want to pay.') }}</p>
    </div>
  </section>
@endsection

@section('content')
  <main class="checkout-flow" data-checkout-flow data-initial-step="{{ $initialStep }}">
    <div class="container checkout-shell">
      <nav class="checkout-stepper" aria-label="{{ __('Checkout progress') }}">
        <button class="checkout-step" type="button" data-step-target="1" aria-current="step">
          <span class="checkout-step-number">1</span>
          <span><strong>{{ __('Tickets') }}</strong><small>{{ __('Review selection') }}</small></span>
        </button>
        <span class="checkout-step-line" aria-hidden="true"></span>
        <button class="checkout-step" type="button" data-step-target="2">
          <span class="checkout-step-number">2</span>
          <span><strong>{{ __('Your details') }}</strong><small>{{ __('Ticket recipient') }}</small></span>
        </button>
        <span class="checkout-step-line" aria-hidden="true"></span>
        <button class="checkout-step" type="button" data-step-target="3">
          <span class="checkout-step-number">3</span>
          <span><strong>{{ __('Review & pay') }}</strong><small>{{ __('Confirm booking') }}</small></span>
        </button>
      </nav>

      @if ($errors->any())
        <div class="checkout-alert" role="alert" tabindex="-1" data-checkout-error-summary>
          <strong>{{ __('Please check the highlighted information.') }}</strong>
          <ul>
            @foreach ($errors->all() as $error)
              <li>{{ $error }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      @if (Session::has('error') || Session::has('currency_error'))
        <div class="checkout-alert" role="alert" tabindex="-1" data-checkout-error-summary>
          {{ Session::get('error') ?: Session::get('currency_error') }}
        </div>
      @endif

      <form action="{{ route('ticket.booking', $event->id) }}" method="POST" enctype="multipart/form-data"
        id="payment-form">
        @csrf

        <section class="checkout-panel" data-checkout-panel="1" aria-labelledby="checkout-tickets-title">
          <div class="checkout-panel-heading">
            <div>
              <p class="checkout-eyebrow">{{ __('Step 1 of 3') }}</p>
              <h2 id="checkout-tickets-title">{{ __('Review your tickets') }}</h2>
              <p>{{ __('Make sure the event and ticket quantities are correct before continuing.') }}</p>
            </div>
            <a href="{{ $eventUrl }}" class="checkout-text-link">{{ __('Edit tickets') }}</a>
          </div>

          <article class="checkout-event-card">
            <a href="{{ $eventUrl }}" class="checkout-event-image">
              <img src="{{ asset('assets/admin/img/event/thumbnail/' . $event->thumbnail) }}" alt="{{ $event->title }}">
            </a>
            <div class="checkout-event-copy">
              <p class="checkout-eyebrow">{{ __('Your event') }}</p>
              <h3><a href="{{ $eventUrl }}">{{ $event->title }}</a></h3>
              <ul class="checkout-event-meta">
                <li><i class="far fa-calendar" aria-hidden="true"></i>{{ date('D, d M Y', strtotime($event->start_date)) }}</li>
                <li><i class="far fa-clock" aria-hidden="true"></i>{{ $event->start_time }}</li>
                <li>
                  <i class="fas {{ $event->event_type === 'venue' ? 'fa-map-marker-alt' : 'fa-wifi' }}" aria-hidden="true"></i>
                  {{ $event->event_type === 'venue' ? trim(($event->city ?: '') . ($event->country ? ', ' . $event->country : '')) : __('Online event') }}
                </li>
              </ul>
            </div>
          </article>

          <div class="checkout-ticket-list" aria-label="{{ __('Selected tickets') }}">
            @forelse ($selTickets as $selectedTicket)
              <div class="checkout-ticket-row">
                <div>
                  <strong>{{ $selectedTicket['name'] ?: __('General admission') }}</strong>
                  <small>{{ __('Quantity') }} {{ (int) $selectedTicket['qty'] }}</small>
                </div>
                <span>{{ symbolPrice(((float) $selectedTicket['price']) * ((int) $selectedTicket['qty'])) }}</span>
              </div>
            @empty
              <p>{{ __('No tickets are currently selected.') }}</p>
            @endforelse
          </div>

          <div class="checkout-panel-actions checkout-panel-actions-end">
            <button class="theme-btn checkout-next" type="button" data-checkout-next="2">
              {{ __('Continue to details') }} <i class="fas fa-arrow-right" aria-hidden="true"></i>
            </button>
          </div>
        </section>

        <section class="checkout-panel" data-checkout-panel="2" aria-labelledby="checkout-details-title">
          <div class="checkout-panel-heading">
            <div>
              <p class="checkout-eyebrow">{{ __('Step 2 of 3') }}</p>
              <h2 id="checkout-details-title">{{ __('Who should receive the tickets?') }}</h2>
              <p>{{ __('We will send the booking confirmation and tickets to this email address.') }}</p>
            </div>
          </div>

          <div class="checkout-form-grid">
            <div class="checkout-field">
              <label for="fname">{{ __('First name') }} <span aria-hidden="true">*</span></label>
              <input type="text" name="fname" id="fname" autocomplete="given-name" maxlength="100" required
                value="{{ old('fname', $authUser?->fname) }}" aria-describedby="fname-error"
                @error('fname') aria-invalid="true" @enderror>
              @error('fname')<p class="checkout-field-error" id="fname-error">{{ $message }}</p>@enderror
            </div>
            <div class="checkout-field">
              <label for="lname">{{ __('Last name') }} <span aria-hidden="true">*</span></label>
              <input type="text" name="lname" id="lname" autocomplete="family-name" maxlength="100" required
                value="{{ old('lname', $authUser?->lname) }}" aria-describedby="lname-error"
                @error('lname') aria-invalid="true" @enderror>
              @error('lname')<p class="checkout-field-error" id="lname-error">{{ $message }}</p>@enderror
            </div>
            <div class="checkout-field">
              <label for="email">{{ __('Email address') }} <span aria-hidden="true">*</span></label>
              <input type="email" name="email" id="email" autocomplete="email" maxlength="191" required
                value="{{ old('email', $authUser?->email) }}" aria-describedby="email-help email-error"
                @error('email') aria-invalid="true" @enderror>
              <small id="email-help">{{ __('Your tickets and receipt will be sent here.') }}</small>
              @error('email')<p class="checkout-field-error" id="email-error">{{ $message }}</p>@enderror
            </div>
            <div class="checkout-field">
              <label for="phone">{{ __('Phone number') }} <span aria-hidden="true">*</span></label>
              <input type="tel" name="phone" id="phone" autocomplete="tel" maxlength="40" required
                value="{{ old('phone', $authUser?->phone) }}" aria-describedby="phone-error"
                @error('phone') aria-invalid="true" @enderror>
              @error('phone')<p class="checkout-field-error" id="phone-error">{{ $message }}</p>@enderror
            </div>
          </div>

          <div class="checkout-privacy-note">
            <i class="fas fa-lock" aria-hidden="true"></i>
            <span>{{ __('Your contact details are used to fulfil this booking and send essential event updates.') }}</span>
          </div>

          <div class="checkout-panel-actions">
            <button class="checkout-secondary-button" type="button" data-checkout-back="1">{{ __('Back') }}</button>
            <button class="theme-btn checkout-next" type="button" data-checkout-next="3">
              {{ __('Review booking') }} <i class="fas fa-arrow-right" aria-hidden="true"></i>
            </button>
          </div>
        </section>

        <section class="checkout-panel" data-checkout-panel="3" aria-labelledby="checkout-review-title">
          <div class="checkout-panel-heading">
            <div>
              <p class="checkout-eyebrow">{{ __('Step 3 of 3') }}</p>
              <h2 id="checkout-review-title">{{ __('Review and confirm') }}</h2>
              <p>{{ __('Check the total and choose a payment method. You will not be charged twice if you refresh.') }}</p>
            </div>
          </div>

          <div class="checkout-review-grid">
            <div class="checkout-review-main">
              <div class="checkout-review-card">
                <div class="checkout-review-card-heading">
                  <h3>{{ __('Attendee') }}</h3>
                  <button type="button" data-checkout-back="2">{{ __('Edit') }}</button>
                </div>
                <p data-review-name>{{ old('fname', $authUser?->fname) }} {{ old('lname', $authUser?->lname) }}</p>
                <p data-review-contact>{{ old('email', $authUser?->email) }} · {{ old('phone', $authUser?->phone) }}</p>
              </div>

              @if ($grand_total > 0)
                <div class="checkout-review-card">
                  <h3>{{ __('Payment method') }}</h3>
                  <div class="checkout-field">
                    <label for="payment">{{ __('Choose how to pay') }} <span aria-hidden="true">*</span></label>
                    <select name="gateway" id="payment" required @error('gateway') aria-invalid="true" @enderror>
                      <option value="">{{ __('Select a payment method') }}</option>
                      @foreach ($online_gateways as $online_gateway)
                        <option value="{{ $online_gateway->keyword }}" {{ $online_gateway->keyword == old('gateway', 'paystack') ? 'selected' : '' }}>{{ $online_gateway->name }}</option>
                      @endforeach
                      @foreach ($offline_gateways as $offline_gateway)
                        <option value="{{ $offline_gateway->id }}" {{ (string) $offline_gateway->id === (string) old('gateway') ? 'selected' : '' }}>{{ $offline_gateway->name }}</option>
                      @endforeach
                    </select>
                    @error('gateway')<p class="checkout-field-error">{{ $message }}</p>@enderror
                  </div>

                  <div id="stripe-element" class="checkout-stripe-field d-none"></div>
                  <div id="stripe-errors" class="checkout-field-error" role="alert"></div>

                  @foreach ($offline_gateways as $offlineGateway)
                    <div class="offline-gateway-info d-none" id="offline-gateway-{{ $offlineGateway->id }}">
                      @if ($offlineGateway->short_description)<p>{{ $offlineGateway->short_description }}</p>@endif
                      @if ($offlineGateway->instructions)<div class="summernote-content">{!! $offlineGateway->instructions !!}</div>@endif
                      @if ($offlineGateway->has_attachment == 1)
                        <div class="checkout-field">
                          <label for="attachment-{{ $offlineGateway->id }}">{{ __('Payment proof') }} *</label>
                          <input id="attachment-{{ $offlineGateway->id }}" type="file" name="attachment">
                          @error('attachment')<p class="checkout-field-error">{{ $message }}</p>@enderror
                        </div>
                      @endif
                    </div>
                  @endforeach
                </div>

                <div class="checkout-review-card checkout-coupon-card">
                  <h3>{{ __('Have a discount code?') }}</h3>
                  <div class="checkout-coupon-row">
                    <label class="sr-only" for="coupon-code">{{ __('Discount code') }}</label>
                    <input type="text" id="coupon-code" name="coupon" autocomplete="off" placeholder="{{ __('Enter code') }}">
                    <button class="checkout-secondary-button base-btn" type="button">{{ __('Apply') }}</button>
                  </div>
                  <p class="checkout-coupon-status" data-coupon-status aria-live="polite"></p>
                </div>
              @else
                <div class="checkout-review-card checkout-free-note">
                  <i class="fas fa-ticket-alt" aria-hidden="true"></i>
                  <div><h3>{{ __('No payment required') }}</h3><p>{{ __('Confirm below to complete your free booking.') }}</p></div>
                </div>
              @endif
            </div>

            <aside class="checkout-order-card" aria-labelledby="order-summary-title">
              <h3 id="order-summary-title">{{ __('Order summary') }}</h3>
              <div id="couponReload">
                @foreach ($selTickets as $selectedTicket)
                  <div class="checkout-order-line">
                    <span>{{ (int) $selectedTicket['qty'] }}× {{ $selectedTicket['name'] ?: __('General admission') }}</span>
                    <strong>{{ symbolPrice(((float) $selectedTicket['price']) * ((int) $selectedTicket['qty'])) }}</strong>
                  </div>
                @endforeach
                <hr>
                <div class="checkout-order-line"><span>{{ __('Ticket subtotal') }}</span><strong>{{ symbolPrice($sub_total) }}</strong></div>
                @if ($total_early_bird_dicount > 0)
                  <div class="checkout-order-line checkout-order-discount"><span>{{ __('Early booking discount') }}</span><strong>−{{ symbolPrice($total_early_bird_dicount) }}</strong></div>
                @endif
                @if ($discount > 0)
                  <div class="checkout-order-line checkout-order-discount"><span>{{ __('Discount code') }}</span><strong>−{{ symbolPrice($discount) }}</strong></div>
                @endif
                <div class="checkout-order-line"><span>{{ __('Fees') }}</span><strong>{{ symbolPrice($tax) }}</strong></div>
                <div class="checkout-order-total"><span>{{ __('Total') }}</span><strong>{{ symbolPrice($grand_total) }}</strong></div>
              </div>

              <button type="submit" class="theme-btn checkout-submit-button" data-checkout-submit>
                <span>{{ $grand_total > 0 ? __('Proceed to secure payment') : __('Confirm free booking') }}</span>
                <i class="fas fa-arrow-right" aria-hidden="true"></i>
              </button>
              <p class="checkout-security-copy"><i class="fas fa-shield-alt" aria-hidden="true"></i>{{ __('Secure checkout. Your payment is processed by the selected provider.') }}</p>
            </aside>
          </div>

          <div class="checkout-panel-actions checkout-review-actions">
            <button class="checkout-secondary-button" type="button" data-checkout-back="2">{{ __('Back to details') }}</button>
          </div>
        </section>
      </form>
    </div>
  </main>
@endsection

@section('custom-script')
  <script src="https://js.stripe.com/v3/"></script>
  <script>
    window.eventCheckoutConfig = {
      couponUrl: @json(route('apply-coupon')),
      stripeKey: @json($stripe_key),
      eventId: {{ (int) $event->id }}
    };
  </script>
  <script src="{{ asset('assets/front/js/event_checkout.js') }}"></script>
@endsection
