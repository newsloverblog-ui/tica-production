@extends($layout ?? 'organizer.layout')

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
    $formRoute = fn (string $action, $parameters = []) => route((($adminMode ?? false) ? 'admin.registration_forms.' : 'organizer.forms.') . $action, $parameters);
    $questionRoute = fn (string $action, $parameters = []) => route((($adminMode ?? false) ? 'admin.registration_forms.questions.' : 'organizer.event.registrations.questions.') . $action, $parameters);
  @endphp

  <div class="rf-page">
  <div class="t-page-head rf-head">
    <div>
      <p class="t-help" style="margin:0 0 4px"><a href="{{ $formRoute('index', ($adminMode ?? false) ? ['organizer_id' => $form->organizer_id] : []) }}">← {{ __('All forms') }}</a></p>
      <h1 class="page-title" data-rf-name>{{ $form->name }}</h1>
      <p><span data-rf-summary>{{ trans_choice(':count field|:count fields', $form->questions->count(), ['count' => $form->questions->count()]) }}</span> · {{ trans_choice('Used on :count event|Used on :count events', $form->copies->count(), ['count' => $form->copies->count()]) }}</p>
    </div>
    <div class="rf-actions">
      <form method="POST" action="{{ $formRoute('duplicate', $form) }}">@csrf<button class="t-btn t-btn--secondary t-btn--sm" type="submit">{{ __('Duplicate') }}</button></form>
      <form method="POST" action="{{ $formRoute('delete', $form) }}" data-rf-confirm="{{ __('Delete form?') }}">@csrf<button class="t-btn t-btn--ghost t-btn--sm rf-danger" type="submit">{{ __('Delete form') }}</button></form>
    </div>
  </div>

  @if ($errors->any())
    <div class="rf-errors" role="alert"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
  @endif

  <div class="rf-builder">
    <div class="rf-side">
      {{-- Field picker: click a field to add it, or drag it into the list --}}
      @php
        $palette = [
          'Basics' => [['text', __('Short answer')], ['textarea', __('Paragraph')], ['email', __('Email')], ['phone', __('Phone number')], ['number', __('Number')], ['date', __('Date')]],
          'Choices' => [['select', __('Dropdown')], ['radio', __('Single choice')], ['multi_select', __('Multiple choice')], ['checkbox', __('Yes / no')]],
          'About the person' => [['company', __('Company')], ['job_title', __('Job title')], ['country', __('Country')], ['state', __('State')]],
          'More' => [['file', __('File upload')], ['consent', __('Consent')], ['terms', __('Terms text')]],
        ];
        $defaults = [
          'consent' => ['label' => __('I agree to be contacted about this event'), 'help' => __('We will only use your details for this event.')],
          'terms' => ['label' => __('Terms and conditions'), 'help' => __('Write your terms here.')],
          'checkbox' => ['label' => __('Will you attend in person?')],
        ];
      @endphp
      <section class="t-card" aria-labelledby="rf-pick-h">
        <div class="t-card__head"><h2 class="t-card__title" id="rf-pick-h">{{ __('Add fields') }}</h2></div>
        <p class="t-help">{{ __('Click a field to add it, or drag it into the list at the spot you want.') }}</p>
        <div class="rf-palette">
          @foreach ($palette as $group => $items)
            <div class="rf-palette__group">
              <p class="rf-palette__label">{{ __($group) }}</p>
              <div class="rf-palette__items">
                @foreach ($items as [$type, $label])
                  <form method="POST" action="{{ $questionRoute('store', $form) }}" class="rf-chip-form">
                    @csrf
                    <input type="hidden" name="quick" value="1">
                    <input type="hidden" name="type" value="{{ $type }}">
                    <input type="hidden" name="scope" value="registrant">
                    <input type="hidden" name="is_required" value="0">
                    <input type="hidden" name="label" value="{{ $defaults[$type]['label'] ?? $label }}">
                    @if (isset($defaults[$type]['help']))<input type="hidden" name="help_text" value="{{ $defaults[$type]['help'] }}">@endif
                    @if (in_array($type, $choiceTypes, true))<input type="hidden" name="options" value="{{ __('Option 1') }}&#10;{{ __('Option 2') }}">@endif
                    <input type="hidden" name="position" value="" data-rf-position>
                    <a class="rf-chip" href="#" role="button" draggable="true" data-rf-chip aria-label="{{ __('Add a :type field', ['type' => $label]) }}"><span aria-hidden="true">+</span>{{ $label }}</a>
                  </form>
                @endforeach
              </div>
            </div>
          @endforeach
        </div>
      </section>

      {{-- Fields in this form: drag to reorder --}}
      <section class="t-card" aria-labelledby="rf-fields-h">
        <div class="t-card__head"><h2 class="t-card__title" id="rf-fields-h">{{ __('Fields in this form') }}</h2><span class="t-badge"><span data-rf-count>{{ $form->questions->count() }}</span> / {{ $max }}</span></div>
                <p class="t-help">{{ __('Click a field to edit it. Drag the handle to move it. Name and email are always asked first.') }}</p>
        <ul class="rf-fields" data-rf-list data-reorder="{{ $questionRoute('reorder', $form) }}" data-add="{{ $questionRoute('store', $form) }}">
          <li class="rf-field rf-fixed"><span class="rf-handle is-off" aria-hidden="true"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg></span><div class="rf-field__body"><div class="rf-field__label">{{ __('Full name') }} <span class="rf-req" aria-label="{{ __('required') }}">*</span></div><div class="rf-field__type">{{ __('Short answer') }}</div></div><span class="rf-pill">{{ __('Always asked') }}</span></li>
          <li class="rf-field rf-fixed"><span class="rf-handle is-off" aria-hidden="true"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg></span><div class="rf-field__body"><div class="rf-field__label">{{ __('Email') }} <span class="rf-req" aria-label="{{ __('required') }}">*</span></div><div class="rf-field__type">{{ __('Short answer') }}</div></div><span class="rf-pill">{{ __('Always asked') }}</span></li>
          @include('organizer.forms._fields')
        </ul>
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
                <span class="rf-linked__text"><b>{{ $eventTitle($copy->event) }}</b> <span class="t-badge{{ $tone ? ' t-badge--' . $tone : '' }}">{{ $label }}</span>
                  <span class="rf-meta">{{ trans_choice(':count registration|:count registrations', $copy->submissions_count, ['count' => $copy->submissions_count]) }}</span></span>
                <span class="rf-actions">
                  <a class="t-btn t-btn--ghost t-btn--sm" href="{{ ($adminMode ?? false) ? route('admin.event_builder.edit', ['id' => $copy->event_id]) : route('organizer.event.registrations.index', ['event_id' => $copy->event_id]) }}">{{ __('Manage') }}</a>
                  <form method="POST" action="{{ $formRoute('stop', $copy) }}" data-rf-confirm="{{ __('Stop? Back to tickets') }}">@csrf<button class="t-btn t-btn--ghost t-btn--sm rf-danger" type="submit">{{ __('Stop') }}</button></form>
                </span>
              </li>
            @endforeach
          </ul>
          <form method="POST" action="{{ $formRoute('push', $form) }}" class="rf-push">
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
          <form method="POST" action="{{ $formRoute('events', $form) }}">
            @csrf
            <ul class="rf-events">
              @foreach ($available as $event)
                <li>
                  <label>
                    <input type="checkbox" name="events[]" value="{{ $event->id }}" @checked(in_array($event->id, array_map('intval', (array) old('events', $preselect ? [$preselect] : [])), true))>
                    <span class="rf-events__text"><b>{{ $eventTitle($event) }}</b>
                      @if ($event->registrationForm && !$event->registrationForm->archived_at)
                        <span class="rf-meta">{{ __('Replaces its current form “:name”', ['name' => $event->registrationForm->name]) }}@if ($event->registrationForm->submissions_count) · {{ trans_choice(':count registration kept|:count registrations kept', $event->registrationForm->submissions_count, ['count' => $event->registrationForm->submissions_count]) }}@endif</span>
                      @elseif (($event->registration_mode ?: 'ticketing') !== 'corporate')
                        <span class="rf-meta">{{ __('Currently selling tickets') }}</span>
                      @endif
                    </span>
                  </label>
                </li>
              @endforeach
            </ul>
            <div class="rf-checks">
            <label class="rf-check"><input type="checkbox" name="publish" value="1" @checked(old('publish'))><span>{{ __('Open registration now') }}</span></label>
            <label class="rf-check"><input type="checkbox" name="confirm_switch" value="1" required><span>{{ __('I understand these events will take registrations through this form instead of selling tickets.') }}</span></label>
            </div>
            <button class="t-btn t-btn--primary t-btn--block" type="submit">{{ __('Add to selected events') }}</button>
          </form>
        @endif
      </section>

      {{-- Settings --}}
      <section class="t-card" aria-labelledby="rf-set-h">
        <div class="t-card__head"><h2 class="t-card__title" id="rf-set-h">{{ __('Form settings') }}</h2></div>
        <form method="POST" action="{{ $formRoute('settings', $form) }}" data-rf-live>
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

    </div>
  </div>
  </div>

  @php
    $editTypes = ['text' => __('Short answer'), 'textarea' => __('Paragraph'), 'email' => __('Email'), 'phone' => __('Phone number'), 'number' => __('Number'), 'date' => __('Date'), 'select' => __('Dropdown'), 'radio' => __('Single choice'), 'multi_select' => __('Multiple choice'), 'checkbox' => __('Yes / no'), 'company' => __('Company'), 'job_title' => __('Job title'), 'country' => __('Country'), 'state' => __('State'), 'consent' => __('Consent'), 'terms' => __('Terms text'), 'file' => __('File upload')];
  @endphp
  <dialog class="rf-dlg" id="rf-edit" aria-labelledby="rf-edit-h">
    <form method="POST" class="rf-dlg__form" data-rf-edit-form novalidate>
      @csrf
      <input type="hidden" name="question_key">
      <div class="rf-dlg__head">
        <h2 id="rf-edit-h">{{ __('Edit field') }}</h2>
        <button class="rf-dlg__x" type="button" data-rf-close aria-label="{{ __('Close') }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button>
      </div>
      <div class="rf-dlg__body">
        <label class="t-field"><span class="t-label">{{ __('Question') }}</span><input class="t-input" name="label" maxlength="191" required></label>
        <div class="rf-grid">
          <label class="t-field"><span class="t-label">{{ __('Answer type') }}</span>
            <select class="t-select" name="type" data-rf-type>@foreach (App\Services\EventRegistrationService::QUESTION_TYPES as $type)<option value="{{ $type }}">{{ $editTypes[$type] ?? $type }}</option>@endforeach</select>
          </label>
          <label class="rf-check rf-check--inline"><input type="checkbox" name="is_required" value="1"><span>{{ __('Required') }}</span></label>
        </div>
        <label class="t-field" data-rf-show="choice"><span class="t-label">{{ __('Choices') }}</span><textarea class="t-textarea" name="options" rows="4" placeholder="{{ __('One choice per line') }}"></textarea></label>
        <label class="t-field"><span class="t-label">{{ __('Help text') }} <small>{{ __('(optional)') }}</small></span><textarea class="t-textarea" name="help_text" rows="2" maxlength="5000"></textarea></label>
        <details class="rf-more">
          <summary>{{ __('More options') }}</summary>
          <div class="rf-grid">
            <label class="t-field"><span class="t-label">{{ __('Ask this') }}</span><select class="t-select" name="scope"><option value="registrant">{{ __('Once per registration') }}</option><option value="attendee">{{ __('For every attendee') }}</option></select></label>
            <label class="t-field" data-rf-show="text"><span class="t-label">{{ __('Placeholder') }}</span><input class="t-input" name="placeholder" maxlength="191"></label>
            <label class="t-field" data-rf-show="text"><span class="t-label">{{ __('Minimum characters') }}</span><input class="t-input" type="number" name="min_length" min="0" max="5000"></label>
            <label class="t-field" data-rf-show="text"><span class="t-label">{{ __('Maximum characters') }}</span><input class="t-input" type="number" name="max_length" min="1" max="5000"></label>
            <label class="t-field"><span class="t-label">{{ __('Show only when') }}</span><select class="t-select" name="condition_question_key" data-rf-cond></select></label>
            <label class="t-field" data-rf-show="cond"><span class="t-label">{{ __('Condition') }}</span><select class="t-select" name="condition_operator"><option value="equals">{{ __('Equals') }}</option><option value="not_equals">{{ __('Does not equal') }}</option><option value="not_empty">{{ __('Is answered') }}</option></select></label>
            <label class="t-field rf-wide" data-rf-show="cond"><span class="t-label">{{ __('Value') }}</span><input class="t-input" name="condition_value" maxlength="500"></label>
          </div>
        </details>
        <p class="rf-dlg__msg" role="alert" data-rf-msg></p>
      </div>
      <div class="rf-dlg__foot">
        <button class="t-btn t-btn--ghost" type="button" data-rf-close>{{ __('Cancel') }}</button>
        <button class="t-btn t-btn--primary" type="submit">{{ __('Save field') }}</button>
      </div>
    </form>
  </dialog>
  <div class="rf-toast" role="status" aria-live="polite" data-rf-toast></div>
  <script>window.RF_OPEN = @json($editing?->public_id);</script>
  @include('organizer.forms._motion')
@endsection
