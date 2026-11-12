@extends('frontend.layout')
@section('body-class', 'tica-transactional')

@section('pageHeading'){{ __('RSVP received') }}@endsection

@section('content')
  <section class="py-5" style="background:#f6f7f9;min-height:70vh;padding-top:150px!important">
    <div class="container text-center" style="max-width:680px">
      <div class="card border-0 shadow-sm" style="border-radius:20px"><div class="card-body p-5">
        <div class="mb-3" style="font-size:3rem;color:#fe9b59"><i class="fas {{ $invitation->status === 'accepted' ? 'fa-check-circle' : 'fa-envelope-open' }}"></i></div>
        <h1>{{ $invitation->status === 'accepted' ? __('You’re on the guest list') : __('Response received') }}</h1>
        <p class="lead">{{ $invitation->status === 'accepted' ? __('Your RSVP has been recorded. The organizer will see your party on the event guest list.') : __('The organizer has been told that you cannot attend.') }}</p>
        <p class="text-muted">{{ __('You can close this page. Contact the organizer if you need to change your response.') }}</p>
      </div></div>
    </div>
  </section>
@endsection
