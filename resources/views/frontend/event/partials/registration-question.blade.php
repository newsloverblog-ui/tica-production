@php
  $fieldName = ($namePrefix !== '' ? $namePrefix . '[answers]' : 'answers') . '[' . $question->question_key . ']';
  $oldKey = ($oldPrefix !== '' ? $oldPrefix . '.answers' : 'answers') . '.' . $question->question_key;
  $fieldId = $idPrefix . '-' . $question->question_key;
  $oldValue = old($oldKey);
  $condition = $question->condition ?: [];
  $settings = $question->settings ?: [];
  $isConditional = !empty($condition['question_key']);
  $isRequiredNow = $question->is_required && !$isConditional && !$question->ticket_id;
  $autocomplete = match ($question->type) {
    'email' => 'email', 'phone' => 'tel', 'company' => 'organization',
    'job_title' => 'organization-title', 'country' => 'country-name',
    'state' => 'address-level1', default => null,
  };
@endphp
<div class="registration-field" data-registration-field
  @if ($question->ticket_id) data-ticket-id="{{ $question->ticket_id }}" @endif
  @if ($question->ticket_id) hidden @endif
  @if ($isConditional)
    data-condition-key="{{ $condition['question_key'] }}"
    data-condition-operator="{{ $condition['operator'] ?? 'equals' }}"
    data-condition-value="{{ $condition['value'] ?? '' }}"
  @endif>
  @if ($question->type === 'terms')
    <div class="registration-terms" role="note" aria-labelledby="{{ $fieldId }}-title">
      <h3 id="{{ $fieldId }}-title">{{ $question->label }}</h3>
      @if ($question->help_text)<p>{{ $question->help_text }}</p>@endif
    </div>
  @elseif ($question->type === 'consent')
    <label class="registration-check registration-consent" for="{{ $fieldId }}">
      <input id="{{ $fieldId }}" type="checkbox" name="{{ $fieldName }}" value="1"
        @checked(filter_var($oldValue, FILTER_VALIDATE_BOOL)) @required($isRequiredNow)>
      <span>{{ $question->label }} @if ($question->is_required)<span class="registration-required" aria-hidden="true">*</span>@endif</span>
    </label>
    @if ($question->help_text)<small>{{ $question->help_text }}</small>@endif
  @elseif ($question->type === 'radio')
    <fieldset class="registration-choice-group">
      <legend>{{ $question->label }} @if ($question->is_required)<span class="registration-required" aria-hidden="true">*</span>@endif</legend>
      @foreach ($question->options ?: [] as $optionIndex => $option)
        <label for="{{ $fieldId }}-{{ $optionIndex }}">
          <input id="{{ $fieldId }}-{{ $optionIndex }}" type="radio" name="{{ $fieldName }}" value="{{ $option }}"
            @checked((string) $oldValue === (string) $option) @required($isRequiredNow)>
          <span>{{ $option }}</span>
        </label>
      @endforeach
    </fieldset>
    @if ($question->help_text)<small>{{ $question->help_text }}</small>@endif
  @else
    <label for="{{ $fieldId }}">{{ $question->label }} @if ($question->is_required)<span class="registration-required" aria-hidden="true">*</span>@endif</label>
    @if ($question->type === 'textarea')
      <textarea id="{{ $fieldId }}" name="{{ $fieldName }}" rows="4"
        @if(!empty($settings['placeholder'])) placeholder="{{ $settings['placeholder'] }}" @endif
        @if(isset($settings['min_length'])) minlength="{{ $settings['min_length'] }}" @endif
        @if(isset($settings['max_length'])) maxlength="{{ $settings['max_length'] }}" @endif
        @required($isRequiredNow)>{{ $oldValue }}</textarea>
    @elseif ($question->type === 'select')
      <select id="{{ $fieldId }}" name="{{ $fieldName }}" @required($isRequiredNow)>
        <option value="">{{ __('Choose an option') }}</option>
        @foreach ($question->options ?: [] as $option)<option value="{{ $option }}" @selected((string) $oldValue === (string) $option)>{{ $option }}</option>@endforeach
      </select>
    @elseif ($question->type === 'multi_select')
      <select id="{{ $fieldId }}" name="{{ $fieldName }}[]" multiple @required($isRequiredNow)>
        @foreach ($question->options ?: [] as $option)<option value="{{ $option }}" @selected(in_array($option, (array) $oldValue, true))>{{ $option }}</option>@endforeach
      </select>
    @elseif ($question->type === 'checkbox')
      <label class="registration-check" for="{{ $fieldId }}">
        <input id="{{ $fieldId }}" type="checkbox" name="{{ $fieldName }}" value="1" @checked($oldValue) @required($isRequiredNow)>
        <span>{{ __('Yes') }}</span>
      </label>
    @elseif ($question->type === 'file')
      <input id="{{ $fieldId }}" type="file" name="{{ $fieldName }}" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" @required($isRequiredNow)>
      <small>{{ __('PDF, JPG or PNG. Maximum :size MB. Files are stored privately.', ['size' => round(config('registration.upload_max_file_kb', 5120) / 1024, 1)]) }}</small>
    @else
      <input id="{{ $fieldId }}" name="{{ $fieldName }}" value="{{ $oldValue }}"
        type="{{ $question->type === 'phone' ? 'tel' : (in_array($question->type, ['email', 'number', 'date'], true) ? $question->type : 'text') }}"
        @if($question->type === 'number') step="any" @endif
        @if($autocomplete) autocomplete="{{ $autocomplete }}" @endif
        @if(!empty($settings['placeholder'])) placeholder="{{ $settings['placeholder'] }}" @endif
        @if(isset($settings['min_length'])) minlength="{{ $settings['min_length'] }}" @endif
        @if(isset($settings['max_length'])) maxlength="{{ $settings['max_length'] }}" @endif
        @required($isRequiredNow)>
    @endif
    @if ($question->help_text)<small>{{ $question->help_text }}</small>@endif
  @endif
  @error($oldKey)<span class="registration-error">{{ $message }}</span>@enderror
</div>
