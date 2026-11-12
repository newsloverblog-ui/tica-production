@extends('frontend.layout')

@section('pageHeading'){{ __('Manage attendee allocation') }}@endsection
@section('body-class', 'registration-page')

@section('custom-style')
  <link rel="stylesheet" href="{{ asset('assets/front/css/event-registration.css') }}?v={{ file_exists(public_path('assets/front/css/event-registration.css')) ? filemtime(public_path('assets/front/css/event-registration.css')) : '1' }}">
@endsection

@section('content')
  @php
    $eventTitle = $allocation->event?->information?->title ?: __('Event registration');
    $assigned = $allocation->claims->whereIn('status', ['pending', 'completed'])->count();
    $remaining = max(0, $allocation->capacity - $assigned);
  @endphp
  <main class="registration-shell">
    <div class="registration-container">
      <div class="registration-grid">
        <aside class="registration-summary">
          <span class="registration-kicker">{{ __('Sponsor allocation') }}</span>
          <h1>{{ $eventTitle }}</h1>
          <p>{{ __('Invite the people who will use the places reserved for :sponsor. Each person completes their own private registration.', ['sponsor' => $allocation->sponsor_name]) }}</p>
          <dl>
            <div><dt>{{ __('Reserved') }}</dt><dd>{{ $allocation->capacity }}</dd></div>
            <div><dt>{{ __('Assigned') }}</dt><dd>{{ $assigned }}</dd></div>
            <div><dt>{{ __('Available') }}</dt><dd>{{ $remaining }}</dd></div>
            <div><dt>{{ __('Portal expires') }}</dt><dd>{{ $allocation->expires_at->timezone(config('app.timezone'))->translatedFormat('d M Y, g:i A') }}</dd></div>
          </dl>
          <div class="registration-process-note"><i class="fas fa-shield-alt" aria-hidden="true"></i><span>{{ __('Only invite people you are authorized to contact. Do not share this management link publicly.') }}</span></div>
        </aside>

        <section class="registration-card" aria-labelledby="allocation-heading">
          <div class="registration-card-heading"><span>{{ __('Attendee allocation') }}</span><h2 id="allocation-heading">{{ __('Invite attendees') }}</h2><p>{{ __('A private, expiring registration link is sent to each attendee.') }}</p></div>
          @if (session('success'))<div class="registration-alert" role="status"><strong>{{ session('success') }}</strong></div>@endif
          @if ($errors->any())<div class="registration-alert" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

          @if ($remaining > 0)
            <form method="POST" action="{{ request()->fullUrl() }}">
              @csrf
              <fieldset class="registration-section">
                <legend><span>+</span>{{ __('Assign a place') }}</legend>
                <div class="registration-two-column">
                  <div class="registration-field"><label for="allocation-name">{{ __('Attendee name') }} *</label><input id="allocation-name" name="name" value="{{ old('name') }}" maxlength="150" autocomplete="name" required></div>
                  <div class="registration-field"><label for="allocation-email">{{ __('Attendee email') }} *</label><input id="allocation-email" type="email" name="email" value="{{ old('email') }}" maxlength="191" autocomplete="email" required></div>
                </div>
                <div class="registration-consent"><label><input type="checkbox" name="source_attested" value="1" required @checked(old('source_attested'))><span>{{ __('I confirm that I am authorized to provide this contact and invite this person about the event.') }}</span></label></div>
                <button class="registration-submit" type="submit"><span>{{ __('Send private invitation') }}</span><i class="fas fa-arrow-right" aria-hidden="true"></i></button>
              </fieldset>
            </form>
          @else
            <div class="registration-alert" role="status"><strong>{{ __('Every reserved place is currently assigned.') }}</strong></div>
          @endif

          <div class="registration-section">
            <h3>{{ __('Allocation activity') }}</h3>
            @forelse ($allocation->claims as $claim)
              <div class="registration-process-note">
                <i class="{{ $claim->status === 'completed' ? 'fas fa-check-circle' : 'far fa-envelope' }}" aria-hidden="true"></i>
                <span><strong>{{ $claim->name }}</strong><br>{{ $claim->email }} · {{ ucfirst($claim->status) }}@if($claim->delivery_status !== 'sent' && $claim->status === 'pending') · {{ ucfirst($claim->delivery_status) }}@endif</span>
              </div>
            @empty
              <p>{{ __('No attendee places have been assigned yet.') }}</p>
            @endforelse
          </div>
        </section>
      </div>
    </div>
  </main>
@endsection
