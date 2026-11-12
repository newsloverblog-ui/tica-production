@extends('frontend.layout')
@section('body-class', 'tica-transactional')

@section('pageHeading'){{ __('Event RSVP') }}@endsection

@section('hero-section')
  <section class="page-banner overlay pt-120 pb-80 lazy" data-bg="{{ asset('assets/admin/img/' . $basicInfo->breadcrumb) }}">
    <div class="container"><div class="banner-inner"><h1 class="page-title">{{ __('You’re invited') }}</h1></div></div>
  </section>
@endsection

@section('content')
  @php
    $eventTitle = $invitation->event?->information?->title ?: __('Your event');
    $available = $invitation->status === 'pending' && $invitation->expires_at->isFuture();
  @endphp
  <section class="py-5" style="background:#f6f7f9;min-height:55vh">
    <div class="container" style="max-width:760px">
      <div class="card border-0 shadow-sm" style="border-radius:20px">
        <div class="card-body p-4 p-md-5">
          <p class="text-uppercase font-weight-bold mb-2" style="color:#c95719;letter-spacing:.1em">{{ __('Private RSVP') }}</p>
          <h2 class="mb-3">{{ $eventTitle }}</h2>
          <p class="lead">{{ $invitation->name }}, {{ __('you have been invited for a party of') }} {{ $invitation->party_size }}.</p>
          <div class="alert alert-light border">
            <strong>{{ __('Respond by') }}:</strong> {{ $invitation->expires_at->translatedFormat('D, M j, Y g:i a') }}<br>
            {{ __('This RSVP is not a paid ticket and cannot be transferred.') }}
          </div>

          @if (!$available)
            <div class="alert alert-warning mb-0">{{ $invitation->status === 'pending' ? __('This invitation has expired.') : __('This invitation has already been answered or cancelled.') }}</div>
          @else
            <form method="POST" action="{{ url()->full() }}">
              @csrf
              <fieldset class="mb-4">
                <legend class="h5">{{ __('Will you attend?') }}</legend>
                <div class="custom-control custom-radio mb-2">
                  <input class="custom-control-input" type="radio" id="accept-rsvp" name="response" value="accepted" required @checked(old('response') === 'accepted')>
                  <label class="custom-control-label" for="accept-rsvp">{{ __('Yes, I’ll be there') }}</label>
                </div>
                <div class="custom-control custom-radio">
                  <input class="custom-control-input" type="radio" id="decline-rsvp" name="response" value="declined" required @checked(old('response') === 'declined')>
                  <label class="custom-control-label" for="decline-rsvp">{{ __('No, I can’t attend') }}</label>
                </div>
                @error('response')<p class="text-danger mt-2">{{ $message }}</p>@enderror
              </fieldset>

              <div class="custom-control custom-checkbox mb-3">
                <input class="custom-control-input" type="checkbox" id="rsvp-consent" name="rsvp_consent" value="1" @checked(old('rsvp_consent'))>
                <label class="custom-control-label" for="rsvp-consent">{{ __('If I accept, I agree that Tica may process this RSVP and send essential updates for this event.') }}</label>
                @error('rsvp_consent')<p class="text-danger mt-2">{{ $message }}</p>@enderror
              </div>
              <div class="custom-control custom-checkbox mb-4">
                <input class="custom-control-input" type="checkbox" id="marketing-consent" name="marketing_consent" value="1" @checked(old('marketing_consent'))>
                <label class="custom-control-label" for="marketing-consent">{{ __('Optional: send me future event recommendations and marketing from Tica.') }}</label>
              </div>
              <button class="theme-btn" type="submit">{{ __('Submit RSVP') }}</button>
            </form>
          @endif
        </div>
      </div>
    </div>
  </section>
@endsection
