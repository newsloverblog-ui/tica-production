@extends('organizer.layout')

@section('content')
  <div class="t-page-head"><div><h1 class="page-title">{{ __('New support request') }}</h1><p>{{ __('Tell us what you need. We usually reply within one working day.') }}</p></div><a class="t-btn t-btn--secondary" href="{{ route('organizer.support_tickets') }}">{{ __('All requests') }}</a></div>

  <form class="t-card lg-form" action="{{ route('organizer.support_ticket.store') }}" enctype="multipart/form-data" method="POST" novalidate>
    @csrf
    <div class="lg-form__two">
      <label class="t-field"><span class="t-label">{{ __('Reply to this email') }}</span>
        <input type="email" class="t-input" name="email" value="{{ old('email', Auth::guard('organizer')->user()->email) }}" autocomplete="email" required @error('email') aria-invalid="true" @enderror>
        @error('email')<span class="t-error">{{ $message }}</span>@enderror
      </label>
      <label class="t-field"><span class="t-label">{{ __('Subject') }}</span>
        <input type="text" class="t-input" name="subject" value="{{ old('subject') }}" maxlength="255" placeholder="{{ __('e.g. Payout for Afterdark has not arrived') }}" required @error('subject') aria-invalid="true" @enderror>
        @error('subject')<span class="t-error">{{ $message }}</span>@enderror
      </label>
    </div>
    <label class="t-field"><span class="t-label">{{ __('What happened?') }}</span>
      <textarea name="description" rows="6" class="t-textarea" placeholder="{{ __('Include the event name, order number or payout reference if you have one.') }}">{{ old('description') }}</textarea>
    </label>
    <label class="t-field"><span class="t-label">{{ __('Attachment') }} <span class="t-muted">{{ __('optional') }}</span></span>
      <input type="file" name="attachment" accept=".zip" class="t-input lg-file">
      <span class="t-help">{{ __('A ZIP file up to 20 MB, for screenshots or documents.') }}</span>
      @error('attachment')<span class="t-error">{{ $message }}</span>@enderror
    </label>
    <div class="lg-form__foot"><button type="submit" class="t-btn t-btn--primary">{{ __('Send request') }}</button></div>
  </form>
@endsection
