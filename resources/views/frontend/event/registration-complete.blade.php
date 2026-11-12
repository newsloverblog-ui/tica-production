@extends(($embedMode ?? false) ? 'frontend.embed-layout' : 'frontend.layout')

@section('pageHeading'){{ __('Registration received') }}@endsection
@section('body-class', ($embedMode ?? false) ? 'registration-page registration-embed-page' : 'registration-page')
@section('custom-style')
  <link rel="stylesheet" href="{{ asset('assets/front/css/event-registration.css') }}?v={{ file_exists(public_path('assets/front/css/event-registration.css')) ? filemtime(public_path('assets/front/css/event-registration.css')) : '1' }}">
  @if ($embedMode ?? false)
    <style>:root{--registration-brand:{{ $embedSetting->accent_color }};--registration-soft:{{ $embedSetting->background_color }};}</style>
  @endif
@endsection

@section('content')
  @php($eventDetailsUrl = $event->information?->slug ? route('event.details', [$event->information->slug, $event->id]) : route('index'))
  <main class="registration-shell registration-complete-shell">
    <section class="registration-complete" aria-labelledby="registration-complete-heading">
      <div class="registration-complete-icon"><i class="fas fa-check" aria-hidden="true"></i></div>
      <span class="registration-kicker">{{ __('Registration received') }}</span>
      <h1 id="registration-complete-heading">{{ $event->information?->title ?: __('Your event') }}</h1>
      <p>{{ $submission->status === 'approved' ? __('Your registration is confirmed.') : __('Your request has been sent to the organizer for review.') }}</p>
      <dl>
        <div><dt>{{ __('Reference') }}</dt><dd>{{ $submission->public_id }}</dd></div>
        <div><dt>{{ __('Attendees') }}</dt><dd>{{ $submission->attendee_count }}</dd></div>
        <div><dt>{{ __('Status') }}</dt><dd>{{ ucfirst($submission->status) }}</dd></div>
      </dl>
      <div class="registration-process-note"><i class="far fa-envelope" aria-hidden="true"></i><span>{{ __('Keep this reference. The organizer will contact the primary email address if further action is needed.') }}</span></div>
      <a class="registration-submit" @if($embedMode ?? false) target="_top" @endif href="{{ $eventDetailsUrl }}"><span>{{ __('Return to event') }}</span><i class="fas fa-arrow-right" aria-hidden="true"></i></a>
    </section>
  </main>
@endsection
