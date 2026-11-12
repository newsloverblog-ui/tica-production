@extends('organizer.layout')

@section('content')
  <div class="tica-workspace-hero">
    <div><p class="tica-eyebrow">{{ __('Data collection') }}</p><h1>{{ __('Registration forms') }}</h1><p class="tica-lede">{{ __('Build a clear, privacy-aware application flow and review submissions in one workspace.') }}</p></div>
    <div class="tica-quick-actions"><a class="btn btn-outline-primary" href="{{ route('organizer.event_management.event') }}">{{ __('All events') }}</a></div>
  </div>

  <div class="card mb-4">
    <div class="card-header"><div class="card-title">{{ __('Choose an event') }}</div></div>
    <div class="card-body">
      <form method="GET" action="{{ route('organizer.event.registrations.index') }}" class="form-inline">
        <label class="sr-only" for="registration-event">{{ __('Event') }}</label>
        <select id="registration-event" name="event_id" class="form-control mr-2" required>
          @forelse ($events as $eventOption)
            <option value="{{ $eventOption->id }}" @selected($event && $event->id === $eventOption->id)>
              {{ $eventOption->information?->title ?: 'Event #' . $eventOption->id }}
            </option>
          @empty
            <option value="">{{ __('No events available') }}</option>
          @endforelse
        </select>
        <button class="btn btn-primary" type="submit">{{ __('Manage registration') }}</button>
      </form>
    </div>
  </div>

  @if ($event)
    <div class="row">
      <div class="col-xl-5">
        <div class="card mb-4">
          <div class="card-header d-flex align-items-center justify-content-between">
            <div class="card-title mb-0">{{ $form ? __('Form settings') : __('Create a registration form') }}</div>
            @if ($form)
              <span class="badge badge-{{ $form->status === 'published' ? 'success' : 'warning' }}">
                {{ ucfirst($form->status) }} · v{{ $form->version }}
              </span>
            @endif
          </div>
          <div class="card-body">
            <div class="alert alert-light border">
              <strong>{{ $event->information?->title ?: 'Event #' . $event->id }}</strong><br>
              <small>{{ __('Saving this form changes the event from ticket sales to corporate registration. Existing ticket checkout will be disabled for this event.') }}</small>
            </div>
            <form method="POST" action="{{ route('organizer.event.registrations.form.save') }}">
              @csrf
              <input type="hidden" name="event_id" value="{{ $event->id }}">
              <div class="form-group">
                <label for="registration-name">{{ __('Form name') }} *</label>
                <input id="registration-name" class="form-control" name="name" maxlength="150" required
                  value="{{ old('name', $form?->name ?: ($event->information?->title . ' registration')) }}">
                @error('name')<small class="text-danger">{{ $message }}</small>@enderror
              </div>
              <div class="form-group">
                <label for="registration-approval">{{ __('Approval workflow') }} *</label>
                <select id="registration-approval" class="form-control" name="approval_mode" required>
                  <option value="manual" @selected(old('approval_mode', $form?->approval_mode) === 'manual')>{{ __('Review every submission') }}</option>
                  <option value="automatic" @selected(old('approval_mode', $form?->approval_mode) === 'automatic')>{{ __('Approve automatically') }}</option>
                </select>
                <small class="form-text text-muted">{{ __('Manual review is recommended for limited-capacity or invitation-style events.') }}</small>
              </div>
              <div class="form-group">
                <label for="registration-intro">{{ __('Introduction') }}</label>
                <textarea id="registration-intro" class="form-control" name="intro" rows="4" maxlength="5000">{{ old('intro', $form?->intro) }}</textarea>
              </div>
              <div class="form-group">
                <label for="registration-privacy">{{ __('Privacy notice') }} *</label>
                <textarea id="registration-privacy" class="form-control" name="privacy_notice" rows="5" maxlength="5000" required>{{ old('privacy_notice', $form?->privacy_notice) }}</textarea>
                <small class="form-text text-muted">{{ __('Explain why attendee information is collected, how it will be used, and who can access it.') }}</small>
                @error('privacy_notice')<small class="text-danger">{{ $message }}</small>@enderror
              </div>
              <button class="btn btn-primary btn-block" type="submit">
                <i class="fas fa-save mr-1"></i>{{ $form ? __('Save changes') : __('Create draft form') }}
              </button>
            </form>
            @if ($form)
              <form class="mt-3" method="POST" action="{{ route('organizer.event.registrations.form.publish', $form) }}">
                @csrf
                <button class="btn btn-success btn-block" type="submit" @disabled($form->status === 'published')>
                  <i class="fas fa-paper-plane mr-1"></i>{{ $form->status === 'published' ? __('Form is live') : __('Publish registration form') }}
                </button>
              </form>
              @if ($form->status === 'published')
                <a class="btn btn-link btn-block mt-2" target="_blank" rel="noopener" href="{{ route('event.registration.show', $event) }}">
                  <i class="fas fa-external-link-alt mr-1"></i>{{ __('Open public form') }}
                </a>
              @endif
              <hr>
              <h5>{{ __('Reusable template') }}</h5>
              <p class="text-muted">{{ __('Save this form structure for another event. Ticket assignments are intentionally cleared when a template is applied.') }}</p>
              <form method="POST" action="{{ route('organizer.event.registrations.templates.save', $form) }}">
                @csrf
                <div class="form-group">
                  <label for="template-name">{{ __('Template name') }} *</label>
                  <input id="template-name" class="form-control" name="template_name" maxlength="150" required placeholder="{{ __('e.g. Two-day conference') }}">
                </div>
                <div class="form-group">
                  <label for="template-description">{{ __('Description') }}</label>
                  <input id="template-description" class="form-control" name="template_description" maxlength="1000">
                </div>
                <button class="btn btn-outline-primary btn-block" type="submit"><i class="fas fa-copy mr-1"></i>{{ __('Save reusable template') }}</button>
              </form>
              @if ($templates->isNotEmpty())
                <div class="mt-3">
                  <label>{{ __('Apply an existing template') }}</label>
                  @foreach ($templates as $template)
                    <form class="d-flex align-items-center justify-content-between border rounded p-2 mb-2" method="POST"
                      action="{{ route('organizer.event.registrations.templates.apply', [$form, $template]) }}"
                      onsubmit="return confirm('{{ __('Replace the current questions with this template?') }}')">
                      @csrf
                      <span><strong>{{ $template->name }}</strong><br><small class="text-muted">v{{ $template->version }} · {{ count($template->definition['questions'] ?? []) }} {{ __('questions') }}</small></span>
                      <button class="btn btn-sm btn-outline-secondary" type="submit">{{ __('Apply') }}</button>
                    </form>
                  @endforeach
                </div>
              @endif
            @endif
          </div>
        </div>
      </div>

      <div class="col-xl-7">
        @if ($form)
          <div class="card mb-4">
            <div class="card-header d-flex align-items-center justify-content-between">
              <div class="card-title mb-0">{{ __('Form questions') }}</div>
              <span class="badge badge-secondary">{{ $form->questions->count() }} / {{ config('registration.max_questions_per_form') }}</span>
            </div>
            <div class="card-body">
              @if ($form->status === 'published')
                <div class="alert alert-warning">{{ __('Adding, removing or reordering a question returns the form to draft and increments its version. Current submissions keep their original question snapshots.') }}</div>
              @endif
              @if ($form->questions->isNotEmpty())
                <p class="text-muted mb-2">{{ __('Drag rows into order, or use the accessible arrow buttons. A field that controls conditional questions must remain before those questions.') }}</p>
                <form id="question-order-form" method="POST" action="{{ route('organizer.event.registrations.questions.reorder', $form) }}">
                  @csrf
                </form>
                <div id="question-order-status" class="sr-only" role="status" aria-live="polite"></div>
              @endif
              <div class="table-responsive mb-4">
                <table class="table table-striped">
                  <thead><tr><th><span class="sr-only">{{ __('Reorder') }}</span></th><th>#</th><th>{{ __('Question') }}</th><th>{{ __('Scope') }}</th><th>{{ __('Registration type') }}</th><th>{{ __('Type') }}</th><th>{{ __('Rule') }}</th><th></th></tr></thead>
                  <tbody id="question-order-list">
                    @forelse ($form->questions as $question)
                      <tr draggable="true" data-question-row data-question-label="{{ $question->label }}">
                        <td>
                          <button class="btn btn-link btn-sm text-muted px-1" type="button" data-drag-handle aria-label="{{ __('Drag :question to reorder', ['question' => $question->label]) }}" title="{{ __('Drag to reorder') }}">
                            <i class="fas fa-grip-vertical" aria-hidden="true"></i>
                          </button>
                          <input form="question-order-form" type="hidden" name="ordered_questions[]" value="{{ $question->public_id }}">
                        </td>
                        <td data-question-position>{{ $question->position }}</td>
                        <td>
                          <strong>{{ $question->label }}</strong>
                          <br><code>{{ $question->question_key }}</code>
                          @if ($question->help_text)<br><small class="text-muted">{{ $question->help_text }}</small>@endif
                        </td>
                        <td><span class="badge badge-info">{{ ucfirst($question->scope) }}</span></td>
                        <td>{{ $question->ticket?->title ?: __('All types') }}</td>
                        <td>{{ str_replace('_', ' ', ucfirst($question->type)) }}</td>
                        <td>
                          {{ $question->is_required ? __('Required') : __('Optional') }}
                          @if ($question->condition)
                            <br><small>{{ __('When :key :operator :value', [
                              'key' => $question->condition['question_key'],
                              'operator' => str_replace('_', ' ', $question->condition['operator']),
                              'value' => $question->condition['value'] ?? '',
                            ]) }}</small>
                          @endif
                        </td>
                        <td class="text-right">
                          <a class="btn btn-outline-primary btn-sm mb-1" href="{{ route('organizer.event.registrations.index', ['event_id' => $event->id, 'edit_question' => $question->public_id]) }}#question-editor" aria-label="{{ __('Edit :question', ['question' => $question->label]) }}">
                            <i class="fas fa-edit" aria-hidden="true"></i>
                          </a>
                          <div class="btn-group btn-group-sm mb-1" role="group" aria-label="{{ __('Move :question', ['question' => $question->label]) }}">
                            <form method="POST" action="{{ route('organizer.event.registrations.questions.move', $question) }}">
                              @csrf<input type="hidden" name="direction" value="up">
                              <button class="btn btn-outline-secondary" type="submit" @disabled($loop->first) aria-label="{{ __('Move :question up', ['question' => $question->label]) }}"><i class="fas fa-arrow-up" aria-hidden="true"></i></button>
                            </form>
                            <form method="POST" action="{{ route('organizer.event.registrations.questions.move', $question) }}">
                              @csrf<input type="hidden" name="direction" value="down">
                              <button class="btn btn-outline-secondary" type="submit" @disabled($loop->last) aria-label="{{ __('Move :question down', ['question' => $question->label]) }}"><i class="fas fa-arrow-down" aria-hidden="true"></i></button>
                            </form>
                          </div>
                          <form method="POST" action="{{ route('organizer.event.registrations.questions.delete', $question) }}" onsubmit="return confirm('{{ __('Remove this question?') }}')">
                            @csrf
                            <button class="btn btn-outline-danger btn-sm" type="submit" aria-label="{{ __('Remove :question', ['question' => $question->label]) }}"><i class="fas fa-trash-alt"></i></button>
                          </form>
                        </td>
                      </tr>
                    @empty
                      <tr><td colspan="8" class="text-center py-4">{{ __('No custom questions yet. Name and email are collected securely by default.') }}</td></tr>
                    @endforelse
                  </tbody>
                </table>
              </div>
              @if ($form->questions->isNotEmpty())
                <button class="btn btn-outline-primary btn-sm mb-4" type="submit" form="question-order-form">
                  <i class="fas fa-save mr-1" aria-hidden="true"></i>{{ __('Save question order') }}
                </button>
              @endif

              <div id="question-editor"></div>
              <h5>{{ $editingQuestion ? __('Edit question') : __('Add a question') }}</h5>
              @if ($editingQuestion)
                <p class="text-muted">{{ __('Saved registrations keep their original field snapshots. Updating this definition affects only future submissions and returns a published form to draft.') }}</p>
              @endif
              <form method="POST" action="{{ $editingQuestion ? route('organizer.event.registrations.questions.update', $editingQuestion) : route('organizer.event.registrations.questions.store', $form) }}">
                @csrf
                <div class="row">
                  <div class="col-md-8 form-group">
                    <label for="question-label">{{ __('Question label') }} *</label>
                    <input id="question-label" class="form-control" name="label" value="{{ old('label', $editingQuestion?->label) }}" maxlength="191" required>
                  </div>
                  <div class="col-md-4 form-group">
                    <label for="question-key">{{ __('Data key') }}</label>
                    <input id="question-key" class="form-control" name="question_key" value="{{ old('question_key', $editingQuestion?->question_key) }}" maxlength="80" placeholder="{{ __('Auto-generated') }}" @required($editingQuestion)>
                  </div>
                  <div class="col-md-6 form-group">
                    <label for="question-type">{{ __('Answer type') }} *</label>
                    <select id="question-type" class="form-control" name="type" required>
                      @foreach (App\Services\EventRegistrationService::QUESTION_TYPES as $type)
                        <option value="{{ $type }}" @selected(old('type', $editingQuestion?->type) === $type)>{{ str_replace('_', ' ', ucfirst($type)) }}</option>
                      @endforeach
                    </select>
                  </div>
                  <div class="col-md-6 form-group">
                    <label for="question-ticket">{{ __('Registration type') }}</label>
                    <select id="question-ticket" class="form-control" name="ticket_id">
                      <option value="">{{ __('All attendee types') }}</option>
                      @foreach ($event->tickets as $ticket)
                        <option value="{{ $ticket->id }}" @selected((string) old('ticket_id', $editingQuestion?->ticket_id) === (string) $ticket->id)>{{ $ticket->title }}</option>
                      @endforeach
                    </select>
                    <small class="form-text text-muted">{{ __('Ticket-specific questions must use the per-attendee scope.') }}</small>
                  </div>
                  <div class="col-md-6 form-group">
                    <label for="question-scope">{{ __('Ask once or per attendee?') }} *</label>
                    <select id="question-scope" class="form-control" name="scope" required>
                      <option value="registrant" @selected(old('scope', $editingQuestion?->scope ?: 'registrant') === 'registrant')>{{ __('Once per registration') }}</option>
                      <option value="attendee" @selected(old('scope', $editingQuestion?->scope) === 'attendee')>{{ __('For every attendee') }}</option>
                    </select>
                  </div>
                  <div class="col-12 form-group">
                    <label for="question-help">{{ __('Help text') }}</label>
                    <textarea id="question-help" class="form-control" name="help_text" rows="2" maxlength="5000">{{ old('help_text', $editingQuestion?->help_text) }}</textarea>
                    <small class="form-text text-muted">{{ __('For a terms block, place the full informational text here. For consent, explain exactly what the attendee is agreeing to.') }}</small>
                  </div>
                  <div class="col-md-6 form-group">
                    <label for="question-placeholder">{{ __('Placeholder') }}</label>
                    <input id="question-placeholder" class="form-control" name="placeholder" value="{{ old('placeholder', $editingQuestion?->settings['placeholder'] ?? null) }}" maxlength="191" placeholder="{{ __('Example answer or instruction') }}">
                  </div>
                  <div class="col-md-3 form-group">
                    <label for="question-min-length">{{ __('Minimum characters') }}</label>
                    <input id="question-min-length" class="form-control" type="number" name="min_length" value="{{ old('min_length', $editingQuestion?->settings['min_length'] ?? null) }}" min="0" max="5000" placeholder="0">
                  </div>
                  <div class="col-md-3 form-group">
                    <label for="question-max-length">{{ __('Maximum characters') }}</label>
                    <input id="question-max-length" class="form-control" type="number" name="max_length" value="{{ old('max_length', $editingQuestion?->settings['max_length'] ?? null) }}" min="1" max="5000" placeholder="5000">
                  </div>
                  <div class="col-12 form-group">
                    <label for="question-options">{{ __('Options') }}</label>
                    <textarea id="question-options" class="form-control" name="options" rows="3" placeholder="{{ __('One option per line; required for select, radio and multi-select fields') }}">{{ old('options', $editingQuestion ? implode("\n", $editingQuestion->options ?? []) : null) }}</textarea>
                  </div>
                  <div class="col-md-5 form-group">
                    <label for="condition-question">{{ __('Show only when') }}</label>
                    <select id="condition-question" class="form-control" name="condition_question_key">
                      <option value="">{{ __('Always show') }}</option>
                      @foreach ($form->questions as $existingQuestion)
                        @continue($editingQuestion && $existingQuestion->id === $editingQuestion->id)
                        <option value="{{ $existingQuestion->question_key }}" @selected(old('condition_question_key', $editingQuestion?->condition['question_key'] ?? null) === $existingQuestion->question_key)>
                          {{ $existingQuestion->label }} ({{ $existingQuestion->scope }})
                        </option>
                      @endforeach
                    </select>
                  </div>
                  <div class="col-md-3 form-group">
                    <label for="condition-operator">{{ __('Condition') }}</label>
                    <select id="condition-operator" class="form-control" name="condition_operator">
                      <option value="equals" @selected(old('condition_operator', $editingQuestion?->condition['operator'] ?? 'equals') === 'equals')>{{ __('Equals') }}</option>
                      <option value="not_equals" @selected(old('condition_operator', $editingQuestion?->condition['operator'] ?? null) === 'not_equals')>{{ __('Does not equal') }}</option>
                      <option value="not_empty" @selected(old('condition_operator', $editingQuestion?->condition['operator'] ?? null) === 'not_empty')>{{ __('Is answered') }}</option>
                    </select>
                  </div>
                  <div class="col-md-4 form-group">
                    <label for="condition-value">{{ __('Value') }}</label>
                    <input id="condition-value" class="form-control" name="condition_value" value="{{ old('condition_value', $editingQuestion?->condition['value'] ?? null) }}" maxlength="500">
                    <small class="form-text text-muted">{{ __('Use an exact configured option. For checkbox or consent fields, enter Yes or No. “Is answered” does not need a value.') }}</small>
                  </div>
                </div>
                <div class="form-check mb-3">
                  <input id="question-required" class="form-check-input" type="checkbox" name="is_required" value="1" @checked(old('is_required', session()->hasOldInput() ? false : $editingQuestion?->is_required))>
                  <label class="form-check-label" for="question-required">{{ __('This question is required when shown') }}</label>
                </div>
                @if ($errors->any())
                  <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                @endif
                <button class="btn btn-outline-primary" type="submit"><i class="fas fa-{{ $editingQuestion ? 'save' : 'plus' }} mr-1"></i>{{ $editingQuestion ? __('Save question') : __('Add question') }}</button>
                @if ($editingQuestion)
                  <a class="btn btn-link" href="{{ route('organizer.event.registrations.index', ['event_id' => $event->id]) }}#question-editor">{{ __('Cancel editing') }}</a>
                @endif
              </form>
            </div>
          </div>
        @endif
      </div>
    </div>

    @if ($form)
      <div class="card mb-4">
        <div class="card-header d-flex align-items-center justify-content-between">
          <div class="card-title mb-0">{{ __('Agenda and session capacity') }}</div>
          <span class="badge badge-secondary">{{ $form->sessions->where('status', 'published')->count() }}</span>
        </div>
        <div class="card-body">
          <p class="text-muted">{{ __('Publish bookable agenda sessions for each attendee. Capacity is enforced under a database lock; registration-type restrictions are applied per attendee.') }}</p>
          <form class="border rounded p-3 mb-4" method="POST" action="{{ route('organizer.event.registrations.sessions.save', $form) }}">
            @csrf
            <div class="row">
              <div class="col-md-5 form-group"><label for="session-title">{{ __('Session title') }} *</label><input id="session-title" class="form-control" name="title" value="{{ old('title') }}" maxlength="191" required></div>
              <div class="col-md-3 form-group"><label for="session-location">{{ __('Location') }}</label><input id="session-location" class="form-control" name="location" value="{{ old('location') }}" maxlength="191"></div>
              <div class="col-md-2 form-group"><label for="session-capacity">{{ __('Capacity') }}</label><input id="session-capacity" class="form-control" type="number" name="capacity" value="{{ old('capacity') }}" min="1" max="100000" placeholder="{{ __('Unlimited') }}"></div>
              <div class="col-md-2 form-group"><label for="session-ticket">{{ __('Registration type') }}</label><select id="session-ticket" class="form-control" name="ticket_id"><option value="">{{ __('All types') }}</option>@foreach($event->tickets as $ticket)<option value="{{ $ticket->id }}">{{ $ticket->title }}</option>@endforeach</select></div>
              <div class="col-md-4 form-group"><label for="session-start">{{ __('Starts') }} *</label><input id="session-start" class="form-control" type="datetime-local" name="starts_at" value="{{ old('starts_at') }}" required></div>
              <div class="col-md-4 form-group"><label for="session-end">{{ __('Ends') }} *</label><input id="session-end" class="form-control" type="datetime-local" name="ends_at" value="{{ old('ends_at') }}" required></div>
              <div class="col-md-4 form-group"><label for="session-description">{{ __('Description') }}</label><input id="session-description" class="form-control" name="description" value="{{ old('description') }}" maxlength="5000"></div>
            </div>
            <div class="form-check mb-3"><input id="session-required" class="form-check-input" type="checkbox" name="is_required" value="1" @checked(old('is_required'))><label class="form-check-label" for="session-required">{{ __('This session is required for every applicable attendee') }}</label></div>
            <button class="btn btn-primary" type="submit"><i class="fas fa-calendar-plus mr-1"></i>{{ __('Publish agenda session') }}</button>
          </form>

          @if ($form->sessions->isNotEmpty())
            <div class="table-responsive"><table class="table table-striped">
              <thead><tr><th>{{ __('Session') }}</th><th>{{ __('Schedule') }}</th><th>{{ __('Type') }}</th><th>{{ __('Capacity') }}</th><th>{{ __('Selected') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
              <tbody>@foreach($form->sessions as $session)<tr>
                <td><strong>{{ $session->title }}</strong>@if($session->location)<br><small>{{ $session->location }}</small>@endif</td>
                <td>{{ $session->starts_at?->format('d M Y, H:i') }}–{{ $session->ends_at?->format('H:i') }}</td>
                <td>{{ $session->ticket?->title ?: __('All types') }}@if($session->is_required)<br><span class="badge badge-info">{{ __('Required') }}</span>@endif</td>
                <td>{{ $session->capacity ? number_format($session->capacity) : __('Unlimited') }}</td>
                <td>{{ number_format($session->selected_count ?? 0) }}</td>
                <td><span class="badge badge-{{ $session->status === 'published' ? 'success' : 'secondary' }}">{{ ucfirst($session->status) }}</span></td>
                <td>@if($session->status === 'published')<form method="POST" action="{{ route('organizer.event.registrations.sessions.cancel', $session) }}" onsubmit="return confirm('{{ __('Cancel this session? Sessions with active selections cannot be cancelled here.') }}')">@csrf<button class="btn btn-sm btn-outline-danger" type="submit">{{ __('Cancel') }}</button></form>@endif</td>
              </tr>@endforeach</tbody>
            </table></div>
          @endif
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header d-flex align-items-center justify-content-between">
          <div class="card-title mb-0">{{ __('Attendee badge design and printing') }}</div>
          <span class="badge badge-secondary">{{ number_format($ticketedAttendeeCount) }} {{ __('ticketed') }}</span>
        </div>
        <div class="card-body">
          <p class="text-muted">{{ __('Create print-ready A6 badges for attendees whose tickets have been issued. Each badge uses the attendee ticket’s signed check-in QR; printing does not create a separate credential.') }}</p>
          <form method="POST" action="{{ route('organizer.event.registrations.badges.settings', $form) }}">
            @csrf
            <div class="row">
              <div class="col-lg-4 form-group">
                <label for="badge-title">{{ __('Badge heading') }}</label>
                <input id="badge-title" class="form-control" name="badge_title" maxlength="100"
                  value="{{ old('badge_title', $form->badgeSetting?->badge_title) }}"
                  placeholder="{{ $event->information?->title ?: $form->name }}">
              </div>
              <div class="col-lg-3 form-group">
                <label for="badge-company-key">{{ __('Company field') }}</label>
                <select id="badge-company-key" class="form-control" name="company_question_key">
                  <option value="">{{ __('Do not print') }}</option>
                  @foreach ($form->questions->where('scope', 'attendee') as $question)
                    <option value="{{ $question->question_key }}" @selected(old('company_question_key', $form->badgeSetting?->company_question_key) === $question->question_key)>{{ $question->label }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-lg-3 form-group">
                <label for="badge-role-key">{{ __('Role field') }}</label>
                <select id="badge-role-key" class="form-control" name="role_question_key">
                  <option value="">{{ __('Do not print') }}</option>
                  @foreach ($form->questions->where('scope', 'attendee') as $question)
                    <option value="{{ $question->question_key }}" @selected(old('role_question_key', $form->badgeSetting?->role_question_key) === $question->question_key)>{{ $question->label }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-lg-2 form-group">
                <label for="badge-accent">{{ __('Accent colour') }}</label>
                <input id="badge-accent" class="form-control p-1" type="color" name="accent_color"
                  value="{{ old('accent_color', $form->badgeSetting?->accent_color ?: '#FE9B59') }}" required>
              </div>
            </div>
            <div class="d-flex flex-wrap align-items-center">
              <button class="btn btn-outline-primary mr-2 mb-2" type="submit"><i class="fas fa-id-badge mr-1"></i>{{ __('Save badge design') }}</button>
              @if ($ticketedAttendeeCount > 0)
                <a class="btn btn-primary mb-2" href="{{ route('organizer.event.registrations.badges.download', $form) }}">
                  <i class="fas fa-print mr-1"></i>{{ __('Download print-ready badges') }}
                </a>
              @else
                <small class="text-muted mb-2">{{ __('The download becomes available after approved registrations receive tickets.') }}</small>
              @endif
            </div>
          </form>
        </div>
      </div>

      @php
        $embedUrl = route('event.registration.embed.show', $event);
        $embedOrigin = parse_url($embedUrl, PHP_URL_SCHEME).'://'.parse_url($embedUrl, PHP_URL_HOST).(parse_url($embedUrl, PHP_URL_PORT) ? ':'.parse_url($embedUrl, PHP_URL_PORT) : '');
        $embedId = 'tica-registration-'.$form->public_id;
        $embedCode = '<iframe id="'.$embedId.'" src="'.$embedUrl.'" title="'.$form->name.'" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" sandbox="allow-forms allow-scripts allow-same-origin" style="width:100%;min-height:760px;border:0;border-radius:16px"></iframe>'."\n".
          '<script>window.addEventListener("message",function(e){var f=document.getElementById("'.$embedId.'");if(e.origin!=="'.$embedOrigin.'"||e.source!==f.contentWindow||!e.data||e.data.type!=="tica-registration-resize")return;f.style.height=Math.max(560,Math.min(4000,Number(e.data.height)||760))+"px";});</script>';
      @endphp
      <div class="card mb-4">
        <div class="card-header d-flex align-items-center justify-content-between">
          <div class="card-title mb-0">{{ __('Secure website embed') }}</div>
          <span class="badge badge-{{ $form->embedSetting?->enabled ? 'success' : 'secondary' }}">{{ $form->embedSetting?->enabled ? __('Enabled') : __('Disabled') }}</span>
        </div>
        <div class="card-body">
          <p class="text-muted">{{ __('Place this responsive registration form on a trusted sponsor or event website. Exact origins are enforced by the browser through Content Security Policy; wildcard domains and insecure public HTTP sites are rejected.') }}</p>
          <form method="POST" action="{{ route('organizer.event.registrations.embed.settings', $form) }}">
            @csrf
            <div class="form-check mb-3">
              <input id="embed-enabled" class="form-check-input" type="checkbox" name="enabled" value="1" @checked(old('enabled', $form->embedSetting?->enabled))>
              <label class="form-check-label" for="embed-enabled">{{ __('Enable embedding for the trusted origins below') }}</label>
            </div>
            <div class="form-group">
              <label for="embed-origins">{{ __('Allowed website origins') }}</label>
              <textarea id="embed-origins" class="form-control" name="allowed_origins" rows="3" maxlength="2000" placeholder="https://events.example.com">{{ old('allowed_origins', implode("\n", $form->embedSetting?->allowed_origins ?? [])) }}</textarea>
              <small class="form-text text-muted">{{ __('One exact origin per line, up to 10. Use only the scheme and hostname, with an optional port—no paths or wildcards.') }}</small>
            </div>
            <div class="row">
              <div class="col-md-3 form-group"><label for="embed-accent">{{ __('Accent colour') }}</label><input id="embed-accent" class="form-control p-1" type="color" name="accent_color" value="{{ old('accent_color', $form->embedSetting?->accent_color ?: '#FE9B59') }}" required></div>
              <div class="col-md-3 form-group"><label for="embed-background">{{ __('Background colour') }}</label><input id="embed-background" class="form-control p-1" type="color" name="background_color" value="{{ old('background_color', $form->embedSetting?->background_color ?: '#F4F6FA') }}" required></div>
            </div>
            <button class="btn btn-outline-primary" type="submit"><i class="fas fa-code mr-1"></i>{{ __('Save secure embed') }}</button>
          </form>
          @if ($form->embedSetting?->enabled)
            <hr>
            <label for="embed-code"><strong>{{ __('Installation code') }}</strong></label>
            <textarea id="embed-code" class="form-control" rows="7" readonly spellcheck="false">{{ $embedCode }}</textarea>
            <div class="mt-2"><a href="{{ $embedUrl }}" target="_blank" rel="noopener" class="btn btn-sm btn-link"><i class="fas fa-external-link-alt mr-1"></i>{{ __('Preview embedded form') }}</a></div>
          @endif
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header d-flex align-items-center justify-content-between">
          <div class="card-title mb-0">{{ __('Sponsor attendee allocations') }}</div>
          <span class="badge badge-secondary">{{ $allocations->count() }}</span>
        </div>
        <div class="card-body">
          <p class="text-muted">{{ __('Reserve places for a sponsor or partner. The sponsor receives a private portal, invites named attendees, and each attendee completes their own registration and consent.') }}</p>
          @if ($form->status !== 'published')
            <div class="alert alert-warning">{{ __('Publish the registration form before creating sponsor allocations.') }}</div>
          @else
            <form class="border rounded p-3 mb-4" method="POST" action="{{ route('organizer.event.registrations.allocations.store', $form) }}">
              @csrf
              <div class="row">
                <div class="col-md-4 form-group"><label for="allocation-sponsor-name">{{ __('Sponsor name') }} *</label><input id="allocation-sponsor-name" class="form-control" name="sponsor_name" value="{{ old('sponsor_name') }}" maxlength="150" required></div>
                <div class="col-md-4 form-group"><label for="allocation-sponsor-email">{{ __('Sponsor manager email') }} *</label><input id="allocation-sponsor-email" class="form-control" type="email" name="sponsor_email" value="{{ old('sponsor_email') }}" maxlength="191" required></div>
                <div class="col-md-2 form-group"><label for="allocation-capacity">{{ __('Places') }} *</label><input id="allocation-capacity" class="form-control" type="number" name="capacity" value="{{ old('capacity', 10) }}" min="1" max="{{ config('registration.allocation_max_capacity') }}" required></div>
                <div class="col-md-2 form-group"><label for="allocation-expiry">{{ __('Expires') }}</label><input id="allocation-expiry" class="form-control" type="date" name="expires_at" value="{{ old('expires_at', now()->addDays(config('registration.allocation_default_days'))->toDateString()) }}" min="{{ now()->addDay()->toDateString() }}" max="{{ now()->addYear()->toDateString() }}"></div>
              </div>
              <div class="form-check mb-3"><input id="allocation-attestation" class="form-check-input" type="checkbox" name="source_attested" value="1" required><label class="form-check-label" for="allocation-attestation">{{ __('I confirm that this sponsor is authorized to receive and manage attendee places for this event.') }}</label></div>
              <button class="btn btn-primary" type="submit"><i class="fas fa-user-friends mr-1"></i>{{ __('Create allocation and send secure portal') }}</button>
            </form>
          @endif

          @if ($allocations->isNotEmpty())
            <div class="table-responsive">
              <table class="table table-striped">
                <thead><tr><th>{{ __('Sponsor') }}</th><th>{{ __('Usage') }}</th><th>{{ __('Completed') }}</th><th>{{ __('Portal delivery') }}</th><th>{{ __('Expires') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                  @foreach ($allocations as $allocation)
                    <tr>
                      <td><strong>{{ $allocation->sponsor_name }}</strong><br><small>{{ $allocation->sponsor_email }}</small></td>
                      <td>{{ $allocation->assigned_count }} / {{ $allocation->capacity }}</td>
                      <td>{{ $allocation->completed_count }}</td>
                      <td>{{ ucfirst($allocation->delivery_status) }}@if($allocation->delivery_attempts) · {{ $allocation->delivery_attempts }} {{ __('attempts') }}@endif</td>
                      <td>{{ $allocation->expires_at?->format('d M Y') }}</td>
                      <td><span class="badge badge-{{ $allocation->status === 'active' ? 'success' : 'secondary' }}">{{ ucfirst($allocation->status) }}</span></td>
                      <td>
                        @if ($allocation->status === 'active')
                          <form method="POST" action="{{ route('organizer.event.registrations.allocations.cancel', $allocation) }}" onsubmit="return confirm('{{ __('Cancel this allocation and invalidate outstanding attendee links?') }}')">@csrf<button class="btn btn-sm btn-outline-danger" type="submit">{{ __('Cancel') }}</button></form>
                        @endif
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          @endif
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header d-flex align-items-center justify-content-between">
          <div class="card-title mb-0">{{ __('Bulk registration import') }}</div>
          <a class="btn btn-outline-secondary btn-sm" href="{{ route('organizer.event.registrations.import-template', $form) }}">
            <i class="fas fa-download mr-1"></i>{{ __('Download CSV template') }}
          </a>
        </div>
        <div class="card-body">
          <p class="text-muted">{{ __('Use one row per attendee. Rows with the same registration_key become one registration. Separate multi-select answers with |. The published form validates every imported answer.') }}</p>
          @if ($form->status !== 'published')
            <div class="alert alert-warning">{{ __('Publish the current form before uploading registrations. Download a new template whenever the form questions change.') }}</div>
          @else
            <form method="POST" enctype="multipart/form-data" action="{{ route('organizer.event.registrations.import') }}">
              @csrf
              <input type="hidden" name="event_id" value="{{ $event->id }}">
              <div class="form-group">
                <label for="registration-import-file">{{ __('Completed CSV template') }} *</label>
                <input id="registration-import-file" class="form-control-file" type="file" name="file" accept=".csv,text/csv" required>
                <small class="form-text text-muted">{{ __('Maximum :size KB and :rows data rows. Source files are kept in private storage and deleted after processing.', ['size' => config('registration.import_max_file_kb'), 'rows' => number_format(config('registration.import_max_rows'))]) }}</small>
              </div>
              <div class="form-check mb-3">
                <input id="registration-import-attestation" class="form-check-input" type="checkbox" name="contact_attested" value="1" required>
                <label class="form-check-label" for="registration-import-attestation">{{ __('I confirm that I am authorized to register and contact every person in this file under the published privacy notice.') }}</label>
              </div>
              <button class="btn btn-primary" type="submit"><i class="fas fa-file-import mr-1"></i>{{ __('Queue secure import') }}</button>
            </form>
          @endif

          @if ($imports->isNotEmpty())
            <hr>
            <h5>{{ __('Recent imports') }}</h5>
            <div class="table-responsive">
              <table class="table table-sm table-striped">
                <thead><tr><th>{{ __('Queued') }}</th><th>{{ __('Status') }}</th><th>{{ __('Rows') }}</th><th>{{ __('Accepted') }}</th><th>{{ __('Rejected') }}</th><th>{{ __('Source') }}</th><th>{{ __('Details') }}</th></tr></thead>
                <tbody>
                  @foreach ($imports as $import)
                    @php
                      $importBadge = match ($import->status) {
                        'completed' => 'success',
                        'completed_with_errors' => 'warning',
                        'failed', 'expired' => 'danger',
                        default => 'info',
                      };
                    @endphp
                    <tr>
                      <td>{{ $import->created_at?->format('d M Y, H:i') }}</td>
                      <td><span class="badge badge-{{ $importBadge }}">{{ str_replace('_', ' ', ucfirst($import->status)) }}</span></td>
                      <td>{{ number_format($import->total_rows) }}</td>
                      <td>{{ number_format($import->accepted_rows) }}</td>
                      <td>{{ number_format($import->rejected_rows) }}</td>
                      <td>{{ $import->source_deleted_at ? __('Deleted') : __('Private until :date', ['date' => $import->expires_at?->format('d M')]) }}</td>
                      <td><small class="text-muted">{{ $import->last_error ?: '—' }}</small></td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          @endif

          @if ($latestImport && $rejectedImportRows->isNotEmpty())
            <div class="alert alert-warning mb-0">
              <strong>{{ __('Latest import: rows needing correction') }}</strong>
              <ul class="mb-0 mt-2">
                @foreach ($rejectedImportRows as $row)
                  <li>{{ __('Row :row — :message', ['row' => $row->row_number, 'message' => $row->error_message]) }}</li>
                @endforeach
              </ul>
              @if ($latestImport->rejected_rows > $rejectedImportRows->count())
                <small>{{ __('Showing the first :count rejected rows.', ['count' => $rejectedImportRows->count()]) }}</small>
              @endif
            </div>
          @endif
        </div>
      </div>

      <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
          <div class="card-title mb-0">{{ __('Registration submissions') }}</div>
          <span class="badge badge-info">{{ $submissions?->total() ?: 0 }}</span>
        </div>
        <div class="card-body">
          <div class="alert alert-light border"><strong>{{ __('Privacy:') }}</strong> {{ __('Registrant and attendee details are encrypted at rest. Only use this information to fulfil this event and follow the published privacy notice.') }}</div>
          @if (($submissions?->total() ?: 0) > 0)
            <form class="border rounded p-3 mb-4" method="POST" action="{{ route('organizer.event.registrations.export') }}">
              @csrf
              <input type="hidden" name="event_id" value="{{ $event->id }}">
              <div class="d-flex flex-wrap align-items-end" style="gap:12px">
                <div class="form-group mb-0">
                  <label for="registration-export-status">{{ __('Status') }}</label>
                  <select id="registration-export-status" class="form-control" name="status">
                    <option value="">{{ __('All statuses') }}</option>
                    @foreach (['submitted', 'approved', 'ticketed', 'rejected', 'cancelled'] as $status)
                      <option value="{{ $status }}">{{ ucfirst($status) }}</option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group mb-0"><label for="registration-export-from">{{ __('From') }}</label><input id="registration-export-from" class="form-control" type="date" name="from_date"></div>
                <div class="form-group mb-0"><label for="registration-export-to">{{ __('To') }}</label><input id="registration-export-to" class="form-control" type="date" name="to_date"></div>
                <div class="form-group mb-0">
                  <label for="registration-export-type">{{ __('Format') }}</label>
                  <select id="registration-export-type" class="form-control" name="fileType" required><option value="csv">CSV</option><option value="xlsx">XLSX</option></select>
                </div>
              </div>
              <div class="form-check my-3">
                <input id="registration-export-privacy" class="form-check-input" type="checkbox" name="privacy_confirmed" value="1" required>
                <label class="form-check-label" for="registration-export-privacy">{{ __('I will use this personal data only to administer this event under the published privacy notice.') }}</label>
              </div>
              <button class="btn btn-outline-primary" type="submit"><i class="fas fa-file-export mr-1"></i>{{ __('Queue private registration export') }}</button>
              <a class="btn btn-link" href="{{ route('organizer.data_exports.index') }}">{{ __('Export history') }}</a>
            </form>
          @endif
          <div class="table-responsive">
            <table class="table table-striped">
              <thead><tr><th>{{ __('Registrant') }}</th><th>{{ __('Attendees') }}</th><th>{{ __('Files') }}</th><th>{{ __('Submitted') }}</th><th>{{ __('Status') }}</th><th>{{ __('Review') }}</th></tr></thead>
              <tbody>
                @forelse ($submissions as $submission)
                  <tr>
                    <td><strong>{{ $submission->primary_name }}</strong><br><small>{{ $submission->primary_email }}</small><br><small class="text-muted">{{ $submission->public_id }}</small></td>
                    <td>{{ $submission->attendees_count }}</td>
                    <td>
                      @forelse ($submission->files as $file)
                        <a class="d-block" href="{{ route('organizer.event.registrations.files.download', $file) }}"><i class="fas fa-lock mr-1"></i>{{ $file->original_name }}</a>
                      @empty
                        <span class="text-muted">—</span>
                      @endforelse
                    </td>
                    <td>{{ $submission->submitted_at?->format('d M Y, H:i') }}</td>
                    <td><span class="badge badge-{{ in_array($submission->status, ['approved', 'ticketed'], true) ? 'success' : ($submission->status === 'rejected' || $submission->status === 'cancelled' ? 'danger' : 'warning') }}">{{ ucfirst($submission->status) }}</span></td>
                    <td style="min-width:260px">
                      @if ($submission->status === 'submitted')
                        <form class="mb-2" method="POST" action="{{ route('organizer.event.registrations.submissions.decide', $submission) }}">
                          @csrf<input type="hidden" name="decision" value="approved">
                          <button class="btn btn-success btn-sm" type="submit"><i class="fas fa-check mr-1"></i>{{ __('Approve') }}</button>
                        </form>
                        <form method="POST" action="{{ route('organizer.event.registrations.submissions.decide', $submission) }}">
                          @csrf<input type="hidden" name="decision" value="rejected">
                          <div class="input-group input-group-sm">
                            <input class="form-control" name="note" maxlength="2000" required placeholder="{{ __('Reason for rejection') }}">
                            <div class="input-group-append"><button class="btn btn-outline-danger" type="submit">{{ __('Reject') }}</button></div>
                          </div>
                        </form>
                      @elseif ($submission->status === 'approved' && !$submission->invoice)
                        <form method="POST" action="{{ route('organizer.event.registrations.invoices.issue', $submission) }}">
                          @csrf
                          <div class="form-group mb-2"><input class="form-control form-control-sm" name="purchase_order_number" maxlength="191" placeholder="{{ __('Purchase order number (optional)') }}"></div>
                          <div class="input-group input-group-sm">
                            <input class="form-control" type="date" name="due_at" min="{{ now()->addDay()->toDateString() }}" max="{{ now()->addDays(90)->toDateString() }}" value="{{ now()->addDays((int) config('registration.invoice_default_days', 14))->toDateString() }}" required>
                            <div class="input-group-append"><button class="btn btn-outline-primary" type="submit">{{ __('Issue invoice') }}</button></div>
                          </div>
                          <small class="text-muted">{{ __('Reserves the selected registration types until the due date. No tickets are issued yet.') }}</small>
                        </form>
                      @elseif ($submission->invoice && $submission->invoice->status === 'issued')
                        <a class="btn btn-outline-secondary btn-sm mb-2" target="_blank" rel="noopener" href="{{ route('organizer.event.registrations.invoices.show', $submission->invoice) }}"><i class="fas fa-file-invoice mr-1"></i>{{ __('View invoice') }}</a>
                        <form method="POST" action="{{ route('organizer.event.registrations.invoices.paid', $submission->invoice) }}">
                          @csrf
                          <input class="form-control form-control-sm mb-2" name="payment_reference" maxlength="191" required placeholder="{{ __('Verified payment reference') }}">
                          <input class="form-control form-control-sm mb-2" name="payment_note" maxlength="2000" placeholder="{{ __('Internal verification note (optional)') }}">
                          <label class="small d-block"><input type="checkbox" name="payment_verified" value="1" required> {{ __('I verified this payment against the company invoice.') }}</label>
                          <button class="btn btn-success btn-sm" type="submit">{{ __('Mark paid & issue tickets') }}</button>
                        </form>
                      @elseif ($submission->invoice)
                        <a class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener" href="{{ route('organizer.event.registrations.invoices.show', $submission->invoice) }}">{{ __('View :status invoice', ['status' => $submission->invoice->status]) }}</a>
                      @else
                        <small class="text-muted">{{ $submission->reviewed_at?->format('d M Y, H:i') ?: __('No action required') }}</small>
                      @endif
                    </td>
                  </tr>
                @empty
                  <tr><td colspan="6" class="text-center py-5">{{ __('No registrations have been submitted yet.') }}</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
          @if ($submissions){{ $submissions->links() }}@endif
        </div>
      </div>
    @endif
  @endif
@endsection

@section('script')
<script>
  (function () {
    'use strict';
    var list = document.getElementById('question-order-list');
    if (!list) return;
    var dragged = null;
    var status = document.getElementById('question-order-status');

    function renumber() {
      Array.prototype.forEach.call(list.querySelectorAll('[data-question-row]'), function (row, index) {
        var position = row.querySelector('[data-question-position]');
        if (position) position.textContent = String(index + 1);
      });
    }

    list.addEventListener('dragstart', function (event) {
      var handle = event.target.closest('[data-drag-handle]');
      if (!handle) {
        event.preventDefault();
        return;
      }
      dragged = handle.closest('[data-question-row]');
      dragged.classList.add('table-active');
      event.dataTransfer.effectAllowed = 'move';
      event.dataTransfer.setData('text/plain', dragged.dataset.questionLabel || 'question');
    });

    list.addEventListener('dragover', function (event) {
      if (!dragged) return;
      var target = event.target.closest('[data-question-row]');
      if (!target || target === dragged) return;
      event.preventDefault();
      var bounds = target.getBoundingClientRect();
      list.insertBefore(dragged, event.clientY < bounds.top + bounds.height / 2 ? target : target.nextSibling);
      renumber();
    });

    list.addEventListener('drop', function (event) {
      if (!dragged) return;
      event.preventDefault();
      if (status) status.textContent = (dragged.dataset.questionLabel || 'Question') + ' moved. Save the question order to apply this change.';
    });

    list.addEventListener('dragend', function () {
      if (dragged) dragged.classList.remove('table-active');
      dragged = null;
      renumber();
    });
  })();
</script>
@endsection
