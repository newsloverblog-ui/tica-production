@extends('organizer.layout')

@section('style')
  @include('organizer.forms._styles')
@endsection

@section('content')
  @php
    $eventTitle = fn ($event) => optional(optional($event)->information)->title ?: __('Untitled event');
    $statusBadge = fn ($status) => ['published' => ['green', __('Live')], 'draft' => ['orange', __('Draft')], 'archived' => ['', __('Stopped')]][$status] ?? ['', ucfirst((string) $status)];
  @endphp

  <div class="t-page-head">
    <div>
      <h1 class="page-title">{{ __('Registration forms') }}</h1>
      <p>{{ __('Build a form once, add the fields you need, then use it on any of your events.') }}</p>
    </div>
  </div>

  @if ($errors->any())
    <div class="rf-errors" role="alert"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
  @endif

  <div class="rf-start">
    <section class="t-card" aria-labelledby="rf-new-h">
      <div class="t-card__head"><h2 class="t-card__title" id="rf-new-h">{{ __('Create a form') }}</h2></div>
      <form method="POST" action="{{ route('organizer.forms.store') }}">
        @csrf
        <label class="t-field">
          <span class="t-label">{{ __('Form name') }}</span>
          <input class="t-input" name="name" maxlength="150" required value="{{ old('name') }}" placeholder="{{ __('e.g. Conference delegate form') }}">
        </label>
        <label class="t-field">
          <span class="t-label">{{ __('For an event (optional)') }}</span>
          <select class="t-select" name="event_id">
            <option value="">{{ __('Decide later') }}</option>
            @foreach ($events as $event)
              <option value="{{ $event->id }}" @selected((int) old('event_id') === $event->id)>{{ $eventTitle($event) }}</option>
            @endforeach
          </select>
        </label>
        <button class="t-btn t-btn--primary" type="submit">{{ __('Create form') }}</button>
      </form>
    </section>
    <section class="t-card" aria-labelledby="rf-how-h">
      <div class="t-card__head"><h2 class="t-card__title" id="rf-how-h">{{ __('How it works') }}</h2></div>
      <ol class="rf-steps">
        <li>{{ __('Name your form. Name and email are always collected for you.') }}</li>
        <li>{{ __('Add fields: text, choices, dates, files, consent and more.') }}</li>
        <li>{{ __('Choose the events that use it. One form can serve many events.') }}</li>
      </ol>
    </section>
  </div>

  <section class="t-card" aria-labelledby="rf-mine-h">
    <div class="t-card__head"><h2 class="t-card__title" id="rf-mine-h">{{ __('Your forms') }}</h2><span class="t-badge">{{ $forms->count() }}</span></div>
    @if ($forms->isEmpty())
      <div class="t-empty"><p>{{ __('No forms yet. Create your first one above.') }}</p></div>
    @else
      <div class="rf-list">
        @foreach ($forms as $form)
          <div class="rf-row">
            <div>
              <h3><a href="{{ route('organizer.forms.show', $form) }}">{{ $form->name }}</a></h3>
              <p class="rf-meta">
                {{ trans_choice(':count field|:count fields', $form->questions_count, ['count' => $form->questions_count]) }}
                · {{ $form->approval_mode === 'automatic' ? __('Approved automatically') : __('You approve each registration') }}
              </p>
              <div class="rf-chips">
                @forelse ($form->copies as $copy)
                  @php [$tone, $label] = $statusBadge($copy->status); @endphp
                  <span class="t-badge{{ $tone ? ' t-badge--' . $tone : '' }}" title="{{ $label }}">{{ $eventTitle($copy->event) }} · {{ $label }}</span>
                @empty
                  <span class="t-badge">{{ __('Not on any event yet') }}</span>
                @endforelse
              </div>
            </div>
            <div class="rf-actions">
              <a class="t-btn t-btn--secondary t-btn--sm" href="{{ route('organizer.forms.show', $form) }}">{{ __('Open') }}</a>
              <form method="POST" action="{{ route('organizer.forms.duplicate', $form) }}">@csrf<button class="t-btn t-btn--ghost t-btn--sm" type="submit">{{ __('Duplicate') }}</button></form>
            </div>
          </div>
        @endforeach
      </div>
    @endif
  </section>

  @if ($standalone->isNotEmpty())
    <section class="t-card" aria-labelledby="rf-ev-h" style="margin-top:20px">
      <div class="t-card__head"><h2 class="t-card__title" id="rf-ev-h">{{ __('Forms made on an event') }}</h2></div>
      <p class="t-help">{{ __('These were built directly on an event. Save one to your forms to reuse it on other events.') }}</p>
      <div class="rf-list">
        @foreach ($standalone as $form)
          @php [$tone, $label] = $statusBadge($form->status); @endphp
          <div class="rf-row">
            <div>
              <h3>{{ $form->name }}</h3>
              <p class="rf-meta">{{ $eventTitle($form->event) }} · {{ trans_choice(':count field|:count fields', $form->questions_count, ['count' => $form->questions_count]) }} · {{ trans_choice(':count registration|:count registrations', $form->submissions_count, ['count' => $form->submissions_count]) }}</p>
              <div class="rf-chips"><span class="t-badge{{ $tone ? ' t-badge--' . $tone : '' }}">{{ $label }}</span></div>
            </div>
            <div class="rf-actions">
              <a class="t-btn t-btn--secondary t-btn--sm" href="{{ route('organizer.event.registrations.index', ['event_id' => $form->event_id]) }}">{{ __('Manage on event') }}</a>
              <form method="POST" action="{{ route('organizer.forms.adopt', $form) }}">@csrf<button class="t-btn t-btn--ghost t-btn--sm" type="submit">{{ __('Save to my forms') }}</button></form>
            </div>
          </div>
        @endforeach
      </div>
    </section>
  @endif
@endsection
