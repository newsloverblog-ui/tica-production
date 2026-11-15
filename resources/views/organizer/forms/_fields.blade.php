@php
  $typeLabels = [
    'text' => __('Short answer'), 'textarea' => __('Paragraph'), 'email' => __('Email'), 'phone' => __('Phone number'),
    'number' => __('Number'), 'date' => __('Date'), 'select' => __('Dropdown'), 'radio' => __('Single choice'),
    'multi_select' => __('Multiple choice'), 'checkbox' => __('Yes / no'), 'company' => __('Company'),
    'job_title' => __('Job title'), 'country' => __('Country'), 'state' => __('State'), 'consent' => __('Consent'),
    'terms' => __('Terms text'), 'file' => __('File upload'),
  ];
  $choiceTypes = ['select', 'radio', 'multi_select'];
@endphp
@foreach ($form->questions as $question)
  @php
    $data = [
      'id' => $question->public_id,
      'label' => $question->label,
      'type' => $question->type,
      'key' => $question->question_key,
      'required' => (bool) $question->is_required,
      'help' => $question->help_text,
      'options' => implode("\n", $question->options ?? []),
      'scope' => $question->scope ?: 'registrant',
      'placeholder' => $question->settings['placeholder'] ?? '',
      'min' => $question->settings['min_length'] ?? '',
      'max' => $question->settings['max_length'] ?? '',
      'condKey' => $question->condition['question_key'] ?? '',
      'condOp' => $question->condition['operator'] ?? 'equals',
      'condValue' => $question->condition['value'] ?? '',
      'update' => route('organizer.event.registrations.questions.update', $question),
      'delete' => route('organizer.event.registrations.questions.delete', $question),
    ];
  @endphp
  <li class="rf-field" draggable="true" data-rf-item data-id="{{ $question->public_id }}" data-q='@json($data)'>
    <span class="rf-handle" role="button" tabindex="0" aria-label="{{ __('Move :q. Use the up and down arrow keys.', ['q' => $question->label]) }}" title="{{ __('Drag to move') }}"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="6" r="1.4"/><circle cx="15" cy="6" r="1.4"/><circle cx="9" cy="12" r="1.4"/><circle cx="15" cy="12" r="1.4"/><circle cx="9" cy="18" r="1.4"/><circle cx="15" cy="18" r="1.4"/></svg></span>
    <div class="rf-field__body" data-rf-edit>
      <span class="rf-field__label">{{ $question->label }}@if ($question->is_required) <span class="rf-req" aria-label="{{ __('required') }}">*</span>@endif</span>
      <span class="rf-field__type">
        {{ $typeLabels[$question->type] ?? $question->type }}@if ($question->scope === 'attendee') · {{ __('per attendee') }}@endif @if ($question->condition) · {{ __('shown only sometimes') }}@endif @if (in_array($question->type, $choiceTypes, true)) · {{ trans_choice(':count choice|:count choices', count($question->options ?? []), ['count' => count($question->options ?? [])]) }}@endif
      </span>
    </div>
    <span class="rf-field__tools">
      <button class="rf-tool" type="button" data-rf-edit>{{ __('Edit') }}</button>
      <button class="rf-tool rf-tool--danger" type="button" data-rf-remove aria-label="{{ __('Remove :q', ['q' => $question->label]) }}">{{ __('Remove') }}</button>
    </span>
  </li>
@endforeach
@if ($form->questions->isEmpty())
  <li class="rf-drop-empty" data-rf-empty>{{ __('Drop a field here, or click one above.') }}</li>
@endif
