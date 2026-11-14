@extends('organizer.layout')

@section('style')
  @include('organizer.forms._styles')
@endsection

@section('content')
  @php
    $eventTitle = fn ($event) => optional(optional($event)->information)->title ?: __('Untitled event');
    $typeLabels = [
      'text' => __('Short answer'), 'textarea' => __('Paragraph'), 'email' => __('Email'), 'phone' => __('Phone number'),
      'number' => __('Number'), 'date' => __('Date'), 'select' => __('Dropdown'), 'radio' => __('Single choice'),
      'multi_select' => __('Multiple choice'), 'checkbox' => __('Yes / no tick box'), 'company' => __('Company'),
      'job_title' => __('Job title'), 'country' => __('Country'), 'state' => __('State'), 'consent' => __('Consent'),
      'terms' => __('Terms text'), 'file' => __('File upload'),
    ];
    $choiceTypes = ['select', 'radio', 'multi_select'];
    $max = (int) config('registration.max_questions_per_form');
    $available = $events->reject(fn ($event) => in_array($event->id, $usedOn, true));
    $copyStatus = ['published' => ['green', __('Live')], 'draft' => ['orange', __('Draft')]];
    $f = $editing;
  @endphp

  <div class="t-page-head">
    <div>
      <p class="t-help" style="margin:0 0 4px"><a href="{{ route('organizer.forms.index') }}">← {{ __('All forms') }}</a></p>
      <h1 class="page-title">{{ $form->name }}</h1>
      <p>{{ trans_choice(':count field|:count fields', $form->questions->count(), ['count' => $form->questions->count()]) }} · {{ trans_choice('Used on :count event|Used on :count events', $form->copies->count(), ['count' => $form->copies->count()]) }}</p>
    </div>
  </div>

  @if ($errors->any())
    <div class="rf-errors" role="alert"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
  @endif

  <div class="rf-builder">
    <div class="rf-side">
      {{-- Fields --}}
      <section class="t-card" aria-labelledby="rf-fields-h">
        <div class="t-card__head"><h2 class="t-card__title" id="rf-fields-h">{{ __('Fields') }}</h2><span class="t-badge">{{ $form->questions->count() }} / {{ $max }}</span></div>
        <ul class="rf-fields">
          <li class="rf-field rf-fixed"><div class="rf-field__body"><div class="rf-field__label">{{ __('Full name') }}</div><div class="rf-field__type">{{ __('Always asked') }}</div></div></li>
          <li class="rf-field rf-fixed"><div class="rf-field__body"><div class="rf-field__label">{{ __('Email') }}</div><div class="rf-field__type">{{ __('Always asked') }}</div></div></li>
          @foreach ($form->questions as $question)
            <li class="rf-field{{ $f && $f->id === $question->id ? ' is-editing' : '' }}">
              <div class="rf-field__body">
                <div class="rf-field__label">{{ $question->label }}@if ($question->is_required) <span aria-label="{{ __('required') }}">*</span>@endif</div>
                <div class="rf-field__type">
                  {{ $typeLabels[$question->type] ?? $question->type }}
                  @if ($question->scope === 'attendee') · {{ __('per attendee') }}@endif
                  @if ($question->condition) · {{ __('shown only sometimes') }}@endif
                </div>
              </div>
              <div class="rf-field__tools">
                <form method="POST" action="{{ route('organizer.event.registrations.questions.move', $question) }}">@csrf<input type="hidden" name="direction" value="up"><button class="t-icon-btn" type="submit" @disabled($loop->first) aria-label="{{ __('Move :q up', ['q' => $question->label]) }}">↑</button></form>
                <form method="POST" action="{{ route('organizer.event.registrations.questions.move', $question) }}">@csrf<input type="hidden" name="direction" value="down"><button class="t-icon-btn" type="submit" @disabled($loop->last) aria-label="{{ __('Move :q down', ['q' => $question->label]) }}">↓</button></form>
                <a class="t-btn t-btn--ghost t-btn--sm" href="{{ route('organizer.forms.show', ['form' => $form, 'field' => $question->public_id]) }}#rf-editor">{{ __('Edit') }}</a>
                <form method="POST" action="{{ route('organizer.event.registrations.questions.delete', $question) }}" onsubmit="return confirm('{{ __('Remove this field?') }}')">@csrf<button class="t-btn t-btn--ghost t-btn--sm" type="submit" aria-label="{{ __('Remove :q', ['q' => $question->label]) }}">{{ __('Remove') }}</button></form>
              </div>
            </li>
          @endforeach
        </ul>
      </section>

      {{-- Add / edit a field --}}
      <section class="t-card" aria-labelledby="rf-editor-h" id="rf-editor">
        <div class="t-card__head">
          <h2 class="t-card__title" id="rf-editor-h">{{ $f ? __('Edit field') : __('Add a field') }}</h2>
          @if ($f)<a class="t-btn t-btn--ghost t-btn--sm" href="{{ route('organizer.forms.show', $form) }}">{{ __('Cancel') }}</a>@endif
        </div>
        <form method="POST" action="{{ $f ? route('organizer.event.registrations.questions.update', $f) : route('organizer.event.registrations.questions.store', $form) }}" data-rf-field-form>
          @csrf
          <div class="rf-grid">
            <label class="t-field rf-wide">
              <span class="t-label">{{ __('Question') }}</span>
              <input class="t-input" name="label" maxlength="191" required value="{{ old('label', $f?->label) }}" placeholder="{{ __('e.g. Which company do you work for?') }}">
            </label>
            <label class="t-field">
              <span class="t-label">{{ __('Answer type') }}</span>
              <select class="t-select" name="type" required data-rf-type>
                @foreach (App\Services\EventRegistrationService::QUESTION_TYPES as $type)
                  <option value="{{ $type }}" @selected(old('type', $f?->type ?? 'text') === $type)>{{ $typeLabels[$type] ?? $type }}</option>
                @endforeach
              </select>
            </label>
            <label class="t-check" style="align-self:end;padding-bottom:10px">
              <input type="checkbox" name="is_required" value="1" @checked(old('is_required', session()->hasOldInput() ? false : ($f ? $f->is_required : true)))>
              <span>{{ __('Required') }}</span>
            </label>
            <label class="t-field rf-wide" data-rf-options>
              <span class="t-label">{{ __('Choices') }}</span>
              <textarea class="t-textarea" name="options" rows="4" placeholder="{{ __('One choice per line') }}">{{ old('options', $f ? implode("\n", $f->options ?? []) : null) }}</textarea>
              <span class="t-help">{{ __('Needed for dropdown, single choice and multiple choice.') }}</span>
            </label>
            <label class="t-field rf-wide">
              <span class="t-label">{{ __('Help text (optional)') }}</span>
              <textarea class="t-textarea" name="help_text" rows="2" maxlength="5000">{{ old('help_text', $f?->help_text) }}</textarea>
            </label>
          </div>

          <details class="rf-more" @if (old('condition_question_key', $f?->condition['question_key'] ?? null) || old('scope', $f?->scope) === 'attendee') open @endif>
            <summary>{{ __('More options') }}</summary>
            <div class="rf-grid">
              <label class="t-field">
                <span class="t-label">{{ __('Ask this') }}</span>
                <select class="t-select" name="scope">
                  <option value="registrant" @selected(old('scope', $f?->scope ?: 'registrant') === 'registrant')>{{ __('Once per registration') }}</option>
                  <option value="attendee" @selected(old('scope', $f?->scope) === 'attendee')>{{ __('For every attendee') }}</option>
                </select>
              </label>
              <label class="t-field">
                <span class="t-label">{{ __('Placeholder') }}</span>
                <input class="t-input" name="placeholder" maxlength="191" value="{{ old('placeholder', $f?->settings['placeholder'] ?? null) }}">
              </label>
              <label class="t-field">
                <span class="t-label">{{ __('Minimum characters') }}</span>
                <input class="t-input" type="number" name="min_length" min="0" max="5000" value="{{ old('min_length', $f?->settings['min_length'] ?? null) }}">
              </label>
              <label class="t-field">
                <span class="t-label">{{ __('Maximum characters') }}</span>
                <input class="t-input" type="number" name="max_length" min="1" max="5000" value="{{ old('max_length', $f?->settings['max_length'] ?? null) }}">
              </label>
              <label class="t-field">
                <span class="t-label">{{ __('Show only when') }}</span>
                <select class="t-select" name="condition_question_key">
                  <option value="">{{ __('Always show') }}</option>
                  @foreach ($form->questions as $other)
                    @continue($f && $other->id === $f->id)
                    <option value="{{ $other->question_key }}" @selected(old('condition_question_key', $f?->condition['question_key'] ?? null) === $other->question_key)>{{ $other->label }}</option>
                  @endforeach
                </select>
              </label>
              <label class="t-field">
                <span class="t-label">{{ __('Condition') }}</span>
                <select class="t-select" name="condition_operator">
                  <option value="equals" @selected(old('condition_operator', $f?->condition['operator'] ?? 'equals') === 'equals')>{{ __('Equals') }}</option>
                  <option value="not_equals" @selected(old('condition_operator', $f?->condition['operator'] ?? null) === 'not_equals')>{{ __('Does not equal') }}</option>
                  <option value="not_empty" @selected(old('condition_operator', $f?->condition['operator'] ?? null) === 'not_empty')>{{ __('Is answered') }}</option>
                </select>
              </label>
              <label class="t-field">
                <span class="t-label">{{ __('Value') }}</span>
                <input class="t-input" name="condition_value" maxlength="500" value="{{ old('condition_value', $f?->condition['value'] ?? null) }}">
              </label>
              <label class="t-field">
                <span class="t-label">{{ __('Data key') }}</span>
                <input class="t-input" name="question_key" maxlength="80" value="{{ old('question_key', $f?->question_key) }}" placeholder="{{ __('Made for you') }}" @required($f)>
                <span class="t-help">{{ __('Used as the column name in exports.') }}</span>
              </label>
            </div>
          </details>

          <button class="t-btn t-btn--primary" type="submit">{{ $f ? __('Save field') : __('Add field') }}</button>
        </form>
      </section>
    </div>

    <div class="rf-side">
      {{-- Events --}}
      <section class="t-card" aria-labelledby="rf-use-h">
        <div class="t-card__head"><h2 class="t-card__title" id="rf-use-h">{{ __('Events using this form') }}</h2></div>
        @if ($form->copies->isEmpty())
          <p class="t-help">{{ __('Not on any event yet.') }}</p>
        @else
          <ul class="rf-linked">
            @foreach ($form->copies as $copy)
              @php [$tone, $label] = $copyStatus[$copy->status] ?? ['', ucfirst($copy->status)]; @endphp
              <li>
                <span>{{ $eventTitle($copy->event) }} <span class="t-badge{{ $tone ? ' t-badge--' . $tone : '' }}">{{ $label }}</span>
                  <span class="rf-meta" style="display:block">{{ trans_choice(':count registration|:count registrations', $copy->submissions_count, ['count' => $copy->submissions_count]) }}</span></span>
                <span class="rf-actions">
                  <a class="t-btn t-btn--ghost t-btn--sm" href="{{ route('organizer.event.registrations.index', ['event_id' => $copy->event_id]) }}">{{ __('Manage') }}</a>
                  <form method="POST" action="{{ route('organizer.forms.stop', $copy) }}" onsubmit="return confirm('{{ __('Stop registration on this event? It goes back to selling tickets. Past registrations are kept.') }}')">@csrf<button class="t-btn t-btn--ghost t-btn--sm" type="submit">{{ __('Stop') }}</button></form>
                </span>
              </li>
            @endforeach
          </ul>
          <form method="POST" action="{{ route('organizer.forms.push', $form) }}" style="margin-top:12px">
            @csrf
            <button class="t-btn t-btn--secondary t-btn--block" type="submit">{{ __('Update events with my changes') }}</button>
            <p class="t-help">{{ __('Sends the current fields and settings to every event above. Past registrations keep the answers they gave.') }}</p>
          </form>
        @endif
      </section>

      <section class="t-card" aria-labelledby="rf-add-h">
        <div class="t-card__head"><h2 class="t-card__title" id="rf-add-h">{{ __('Use on more events') }}</h2></div>
        @if ($available->isEmpty())
          <p class="t-help">{{ $events->isEmpty() ? __('Create an event first.') : __('Every one of your events already uses this form.') }}</p>
        @else
          <form method="POST" action="{{ route('organizer.forms.events', $form) }}">
            @csrf
            <ul class="rf-events">
              @foreach ($available as $event)
                <li>
                  <label>
                    <input type="checkbox" name="events[]" value="{{ $event->id }}" @checked(in_array($event->id, array_map('intval', (array) old('events', $preselect ? [$preselect] : [])), true))>
                    <span>{{ $eventTitle($event) }}
                      @if ($event->registrationForm && !$event->registrationForm->archived_at)
                        <span class="rf-meta" style="display:block">{{ __('Replaces its current form “:name”', ['name' => $event->registrationForm->name]) }}@if ($event->registrationForm->submissions_count) · {{ trans_choice(':count registration kept|:count registrations kept', $event->registrationForm->submissions_count, ['count' => $event->registrationForm->submissions_count]) }}@endif</span>
                      @elseif (($event->registration_mode ?: 'ticketing') !== 'corporate')
                        <span class="rf-meta" style="display:block">{{ __('Currently selling tickets') }}</span>
                      @endif
                    </span>
                  </label>
                </li>
              @endforeach
            </ul>
            <label class="t-check" style="margin-bottom:8px"><input type="checkbox" name="publish" value="1" @checked(old('publish'))><span>{{ __('Open registration now') }}</span></label>
            <label class="t-check" style="margin-bottom:12px"><input type="checkbox" name="confirm_switch" value="1" required><span>{{ __('I understand these events will take registrations through this form instead of selling tickets.') }}</span></label>
            <button class="t-btn t-btn--primary t-btn--block" type="submit">{{ __('Add to selected events') }}</button>
          </form>
        @endif
      </section>

      {{-- Settings --}}
      <section class="t-card" aria-labelledby="rf-set-h">
        <div class="t-card__head"><h2 class="t-card__title" id="rf-set-h">{{ __('Form settings') }}</h2></div>
        <form method="POST" action="{{ route('organizer.forms.settings', $form) }}">
          @csrf
          <label class="t-field"><span class="t-label">{{ __('Name') }}</span><input class="t-input" name="name" maxlength="150" required value="{{ old('name', $form->name) }}"></label>
          <label class="t-field"><span class="t-label">{{ __('New registrations') }}</span>
            <select class="t-select" name="approval_mode">
              <option value="manual" @selected(old('approval_mode', $form->approval_mode) === 'manual')>{{ __('I approve each one') }}</option>
              <option value="automatic" @selected(old('approval_mode', $form->approval_mode) === 'automatic')>{{ __('Approve automatically') }}</option>
            </select>
          </label>
          <label class="t-field"><span class="t-label">{{ __('Introduction (optional)') }}</span><textarea class="t-textarea" name="intro" rows="3" maxlength="5000">{{ old('intro', $form->intro) }}</textarea></label>
          <label class="t-field"><span class="t-label">{{ __('Privacy notice') }}</span><textarea class="t-textarea" name="privacy_notice" rows="4" maxlength="5000" required>{{ old('privacy_notice', $form->privacy_notice) }}</textarea></label>
          <button class="t-btn t-btn--secondary" type="submit">{{ __('Save settings') }}</button>
        </form>
      </section>

      <section class="t-card" aria-labelledby="rf-more-h">
        <div class="t-card__head"><h2 class="t-card__title" id="rf-more-h">{{ __('More') }}</h2></div>
        <div class="rf-actions">
          <form method="POST" action="{{ route('organizer.forms.duplicate', $form) }}">@csrf<button class="t-btn t-btn--secondary t-btn--sm" type="submit">{{ __('Duplicate form') }}</button></form>
          <form method="POST" action="{{ route('organizer.forms.delete', $form) }}" onsubmit="return confirm('{{ __('Delete this form? Events using it keep their own copy.') }}')">@csrf<button class="t-btn t-btn--danger t-btn--sm" type="submit">{{ __('Delete form') }}</button></form>
        </div>
      </section>
    </div>
  </div>

  <script>
    (function () {
      var form = document.querySelector('[data-rf-field-form]');
      if (!form) return;
      var type = form.querySelector('[data-rf-type]');
      var options = form.querySelector('[data-rf-options]');
      var choice = @json($choiceTypes);
      function sync() { options.hidden = choice.indexOf(type.value) === -1; }
      type.addEventListener('change', sync);
      sync();
    })();
  </script>
@endsection
