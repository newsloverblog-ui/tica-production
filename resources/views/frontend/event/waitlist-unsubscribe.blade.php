@extends('frontend.layout')
@section('body-class', 'tica-transactional')

@section('pageHeading', __('Waiting list preferences'))

@section('content')
  <section class="py-5">
    <div class="container" style="max-width: 680px">
      <div class="card border-0 shadow-sm">
        <div class="card-body p-4 p-md-5">
          @if (!empty($unsubscribed) || $waitlist->status === 'unsubscribed')
            <h1 class="h3 mb-3">{{ __('You have left this waiting list') }}</h1>
            <p class="text-muted mb-4">{{ __('We will not send more availability emails for this ticket unless you join again.') }}</p>
            <a class="btn btn-primary" href="{{ route('events') }}">{{ __('Explore events') }}</a>
          @else
            <h1 class="h3 mb-3">{{ __('Leave this waiting list?') }}</h1>
            <p class="text-muted">{{ __('You will stop receiving availability emails for this ticket. You can join again from the event page if it sells out.') }}</p>
            <form method="POST" action="{{ request()->fullUrl() }}">
              @csrf
              <button class="btn btn-danger" type="submit">{{ __('Stop availability emails') }}</button>
              <a class="btn btn-light ml-2" href="{{ route('events') }}">{{ __('Keep me subscribed') }}</a>
            </form>
          @endif
        </div>
      </div>
    </div>
  </section>
@endsection
