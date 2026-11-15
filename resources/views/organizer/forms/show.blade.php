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

  <div class="rf-page">
  <div class="t-page-head rf-head">
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
                  <form method="POST" action="{{ route('organizer.event.registrations.questions.store', $form) }}" class="rf-chip-form">
                    @csrf
                    <input type="hidden" name="quick" value="1">
                    <input type="hidden" name="type" value="{{ $type }}">
                    <input type="hidden" name="scope" value="registrant">
                    <input type="hidden" name="is_required" value="0">
                    <input type="hidden" name="label" value="{{ $defaults[$type]['label'] ?? $label }}">
                    @if (isset($defaults[$type]['help']))<input type="hidden" name="help_text" value="{{ $defaults[$type]['help'] }}">@endif
                    @if (in_array($type, $choiceTypes, true))<input type="hidden" name="options" value="{{ __('Option 1') }}&#10;{{ __('Option 2') }}">@endif
                    <input type="hidden" name="position" value="" data-rf-position>
                    <button class="rf-chip" type="submit" draggable="true" data-rf-chip aria-label="{{ __('Add a :type field', ['type' => $label]) }}">
                      <span aria-hidden="true">+</span>{{ $label }}
                    </button>
                  </form>
                @endforeach
              </div>
            </div>
          @endforeach
        </div>
      </section>

      {{-- Fields in this form: drag to reorder --}}
      <section class="t-card" aria-labelledby="rf-fields-h">
        <div class="t-card__head"><h2 class="t-card__title" id="rf-fields-h">{{ __('Fields in this form') }}</h2><span class="t-badge">{{ $form->questions->count() }} / {{ $max }}</span></div>
        <form id="rf-order" method="POST" action="{{ route('organizer.event.registrations.questions.reorder', $form) }}">@csrf</form>
        <p class="t-help">{{ __('Drag a field by its handle to move it. Name and email are always asked first.') }}</p>
        <ul class="rf-fields" data-rf-list>
          <li class="rf-field rf-fixed"><span class="rf-handle is-off" aria-hidden="true"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg></span><div class="rf-field__body"><div class="rf-field__label">{{ __('Full name') }} <span class="rf-req" aria-label="{{ __('required') }}">*</span></div><div class="rf-field__type">{{ __('Short answer') }}</div></div><span class="rf-pill">{{ __('Always asked') }}</span></li>
          <li class="rf-field rf-fixed"><span class="rf-handle is-off" aria-hidden="true"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg></span><div class="rf-field__body"><div class="rf-field__label">{{ __('Email') }} <span class="rf-req" aria-label="{{ __('required') }}">*</span></div><div class="rf-field__type">{{ __('Short answer') }}</div></div><span class="rf-pill">{{ __('Always asked') }}</span></li>
          @foreach ($form->questions as $question)
            <li class="rf-field{{ $f && $f->id === $question->id ? ' is-editing' : '' }}" draggable="true" data-rf-item data-id="{{ $question->public_id }}">
              <span class="rf-handle" aria-hidden="true" title="{{ __('Drag to move') }}"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="6" r="1.4"/><circle cx="15" cy="6" r="1.4"/><circle cx="9" cy="12" r="1.4"/><circle cx="15" cy="12" r="1.4"/><circle cx="9" cy="18" r="1.4"/><circle cx="15" cy="18" r="1.4"/></svg></span>
              <div class="rf-field__body">
                <div class="rf-field__label">{{ $question->label }}@if ($question->is_required) <span class="rf-req" aria-label="{{ __('required') }}">*</span>@endif</div>
                <div class="rf-field__type">
                  {{ $typeLabels[$question->type] ?? $question->type }}
                  @if ($question->scope === 'attendee') · {{ __('per attendee') }}@endif
                  @if ($question->condition) · {{ __('shown only sometimes') }}@endif
                  @if (in_array($question->type, $choiceTypes, true)) · {{ trans_choice(':count choice|:count choices', count($question->options ?? []), ['count' => count($question->options ?? [])]) }}@endif
                </div>
              </div>
              <div class="rf-field__tools">
                <form method="POST" action="{{ route('organizer.event.registrations.questions.move', $question) }}">@csrf<input type="hidden" name="direction" value="up"><button class="rf-icon" type="submit" @disabled($loop->first) aria-label="{{ __('Move :q up', ['q' => $question->label]) }}">↑</button></form>
                <form method="POST" action="{{ route('organizer.event.registrations.questions.move', $question) }}">@csrf<input type="hidden" name="direction" value="down"><button class="rf-icon" type="submit" @disabled($loop->last) aria-label="{{ __('Move :q down', ['q' => $question->label]) }}">↓</button></form>
                <a class="t-btn t-btn--ghost t-btn--sm" href="{{ route('organizer.forms.show', ['form' => $form, 'field' => $question->public_id]) }}#rf-editor">{{ __('Edit') }}</a>
                <form method="POST" action="{{ route('organizer.event.registrations.questions.delete', $question) }}" onsubmit="return confirm('{{ __('Remove this field?') }}')">@csrf<button class="t-btn t-btn--ghost t-btn--sm rf-danger" type="submit" aria-label="{{ __('Remove :q', ['q' => $question->label]) }}">{{ __('Remove') }}</button></form>
              </div>
            </li>
          @endforeach
          @if ($form->questions->isEmpty())
            <li class="rf-drop-empty" data-rf-empty>{{ __('Drop a field here, or click one above.') }}</li>
          @endif
        </ul>
      </section>

      {{-- Add / edit a field --}}
      <section class="t-card" aria-labelledby="rf-editor-h" id="rf-editor">
        <div class="t-card__head">
          <h2 class="t-card__title" id="rf-editor-h">{{ $f ? __('Edit field') : __('Add a custom field') }}</h2>
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
            <label class="rf-check rf-check--inline">
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
                <span class="rf-linked__text"><b>{{ $eventTitle($copy->event) }}</b> <span class="t-badge{{ $tone ? ' t-badge--' . $tone : '' }}">{{ $label }}</span>
                  <span class="rf-meta">{{ trans_choice(':count registration|:count registrations', $copy->submissions_count, ['count' => $copy->submissions_count]) }}</span></span>
                <span class="rf-actions">
                  <a class="t-btn t-btn--ghost t-btn--sm" href="{{ route('organizer.event.registrations.index', ['event_id' => $copy->event_id]) }}">{{ __('Manage') }}</a>
                  <form method="POST" action="{{ route('organizer.forms.stop', $copy) }}" onsubmit="return confirm('{{ __('Stop registration on this event? It goes back to selling tickets. Past registrations are kept.') }}')">@csrf<button class="t-btn t-btn--ghost t-btn--sm rf-danger" type="submit">{{ __('Stop') }}</button></form>
                </span>
              </li>
            @endforeach
          </ul>
          <form method="POST" action="{{ route('organizer.forms.push', $form) }}" class="rf-push">
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
  </div>

  <script>
    // Drag fields to reorder; drag a field type from the picker into the list to add it there.
    (function () {
      var list = document.querySelector('[data-rf-list]');
      if (!list) return;
      var dragging = null, chip = null, marker = document.createElement('li');
      marker.className = 'rf-marker';
      function items() { return [].slice.call(list.querySelectorAll('[data-rf-item]')); }
      function slotAfter(y) {
        var best = null;
        items().forEach(function (el) { if (el === dragging) return; var r = el.getBoundingClientRect(); if (y > r.top + r.height / 2) best = el; });
        return best;
      }
      function place(y) {
        var after = slotAfter(y), fixed = list.querySelectorAll('.rf-fixed');
        var anchor = after || fixed[fixed.length - 1];
        anchor.parentNode.insertBefore(marker, anchor.nextSibling);
      }
      list.addEventListener('dragstart', function (e) {
        var li = e.target.closest('[data-rf-item]'); if (!li) return;
        dragging = li; li.classList.add('is-dragging'); e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', li.dataset.id);
      });
      document.addEventListener('dragstart', function (e) {
        var c = e.target.closest && e.target.closest('[data-rf-chip]'); if (!c) return;
        chip = c.closest('form'); e.dataTransfer.effectAllowed = 'copy'; e.dataTransfer.setData('text/plain', 'new');
        list.classList.add('is-target');
      });
      list.addEventListener('dragover', function (e) { if (!dragging && !chip) return; e.preventDefault(); place(e.clientY); });
      list.addEventListener('dragleave', function (e) { if (!list.contains(e.relatedTarget)) marker.remove(); });
      list.addEventListener('drop', function (e) {
        if (!dragging && !chip) return; e.preventDefault();
        var before = [].slice.call(list.children).filter(function (el) { return el.matches('[data-rf-item]') && el !== dragging; });
        var index = before.filter(function (el) { return el.compareDocumentPosition(marker) & Node.DOCUMENT_POSITION_FOLLOWING; }).length;
        if (chip) {
          chip.querySelector('[data-rf-position]').value = index + 1;
          chip.submit();
        } else {
          marker.parentNode.insertBefore(dragging, marker);
          var order = document.getElementById('rf-order');
          items().forEach(function (el) { var i = document.createElement('input'); i.type = 'hidden'; i.name = 'ordered_questions[]'; i.value = el.dataset.id; order.appendChild(i); });
          order.submit();
        }
        marker.remove();
      });
      document.addEventListener('dragend', function () {
        if (dragging) dragging.classList.remove('is-dragging');
        dragging = null; chip = null; marker.remove(); list.classList.remove('is-target');
      });
    })();
  </script>
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
