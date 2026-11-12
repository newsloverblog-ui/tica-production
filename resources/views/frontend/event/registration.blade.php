@extends(($embedMode ?? false) ? 'frontend.embed-layout' : 'frontend.layout')

@section('pageHeading'){{ $form->name }}@endsection
@section('meta-description'){{ Str::limit(strip_tags($form->intro ?: $event->information?->title), 150) }}@endsection
@section('body-class', ($embedMode ?? false) ? 'registration-page registration-embed-page' : 'registration-page')

@section('custom-style')
  <link rel="stylesheet" href="{{ asset('assets/front/css/event-registration.css') }}?v={{ file_exists(public_path('assets/front/css/event-registration.css')) ? filemtime(public_path('assets/front/css/event-registration.css')) : '1' }}">
  @if ($embedMode ?? false)
    <style>:root{--registration-brand:{{ $embedSetting->accent_color }};--registration-soft:{{ $embedSetting->background_color }};}</style>
  @endif
@endsection

@section('content')
  @php
    $eventTitle = $event->information?->title ?: __('Event registration');
    $registrantQuestions = $form->questions->where('scope', 'registrant');
    $attendeeQuestions = $form->questions->where('scope', 'attendee');
    $publishedSessions = $form->sessions->where('status', 'published');
    $requiresTicketSelection = $event->tickets->isNotEmpty()
      || $attendeeQuestions->contains(fn ($question) => (int) ($question->ticket_id ?? 0) > 0)
      || $publishedSessions->contains(fn ($session) => (int) ($session->ticket_id ?? 0) > 0);
    $isAllocatedRegistration = isset($allocationClaim);
    $allocatedName = $isAllocatedRegistration ? $allocationClaim->name : '';
    $allocatedEmail = $isAllocatedRegistration ? $allocationClaim->email : '';
    $oldAttendees = old('attendees', [['name' => $allocatedName, 'email' => $allocatedEmail, 'answers' => []]]);
    $oldAttendees = is_array($oldAttendees) && $oldAttendees !== [] ? array_values($oldAttendees) : [['name' => '', 'email' => '', 'answers' => []]];
    $registrationAction = $registrationAction ?? ($isAllocatedRegistration ? request()->fullUrl() : route('event.registration.store', $event));
    $eventDetailsUrl = $event->information?->slug ? route('event.details', [$event->information->slug, $event->id]) : route('index');
  @endphp
  <main class="registration-shell">
    <div class="registration-container">
      @unless ($embedMode ?? false)<a class="registration-back" href="{{ $eventDetailsUrl }}"><i class="fas fa-arrow-left" aria-hidden="true"></i>{{ __('Back to event') }}</a>@endunless
      <div class="registration-grid">
        <aside class="registration-summary">
          <span class="registration-kicker">{{ __('Corporate registration') }}</span>
          <h1>{{ $eventTitle }}</h1>
          @if ($form->intro)<p>{{ $form->intro }}</p>@endif
          <dl>
            <div><dt><i class="far fa-calendar" aria-hidden="true"></i>{{ __('Date') }}</dt><dd>{{ $event->start_date ? \Carbon\Carbon::parse($event->start_date)->translatedFormat('D, d M Y') : __('To be announced') }}</dd></div>
            <div><dt><i class="far fa-clock" aria-hidden="true"></i>{{ __('Time') }}</dt><dd>{{ $event->start_time ? \Carbon\Carbon::parse($event->start_time)->format('g:i A') : __('To be announced') }}</dd></div>
          </dl>
          <div class="registration-process-note"><i class="fas fa-shield-alt" aria-hidden="true"></i><span>{{ $form->approval_mode === 'manual' ? __('Your request will be reviewed by the organizer before it is confirmed.') : __('Your registration will be confirmed immediately after submission.') }}</span></div>
          @if ($isAllocatedRegistration)
            <div class="registration-process-note"><i class="fas fa-building" aria-hidden="true"></i><span>{{ __('Your place was allocated by :sponsor. This private form is tied to your email address.', ['sponsor' => $allocationClaim->allocation->sponsor_name]) }}</span></div>
          @endif
        </aside>

        <section class="registration-card" aria-labelledby="registration-heading">
          <div class="registration-card-heading">
            <span>{{ __('Registration request') }}</span>
            <h2 id="registration-heading">{{ $form->name }}</h2>
            <p>{{ __('Complete the primary contact and attendee details below.') }}</p>
          </div>
          @if ($errors->any())
            <div class="registration-alert" role="alert"><strong>{{ __('Please review the highlighted fields.') }}</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
          @endif
          <form method="POST" action="{{ $registrationAction }}" enctype="multipart/form-data" data-registration-form novalidate>
            @csrf
            <fieldset class="registration-section">
              <legend><span>1</span>{{ __('Primary contact') }}</legend>
              <div class="registration-two-column">
                <div class="registration-field"><label for="registrant-name">{{ __('Full name') }} *</label><input id="registrant-name" name="registrant[name]" value="{{ old('registrant.name', $allocatedName) }}" maxlength="150" autocomplete="name" required @readonly($isAllocatedRegistration)>@error('registrant.name')<span class="registration-error">{{ $message }}</span>@enderror</div>
                <div class="registration-field"><label for="registrant-email">{{ __('Email address') }} *</label><input id="registrant-email" type="email" name="registrant[email]" value="{{ old('registrant.email', $allocatedEmail) }}" maxlength="191" autocomplete="email" required @readonly($isAllocatedRegistration)>@error('registrant.email')<span class="registration-error">{{ $message }}</span>@enderror</div>
              </div>
              <div data-question-scope="registrant">
                @foreach ($registrantQuestions as $question)
                  @include('frontend.event.partials.registration-question', ['namePrefix' => '', 'oldPrefix' => '', 'idPrefix' => 'registrant'])
                @endforeach
              </div>
            </fieldset>

            <fieldset class="registration-section">
              <legend><span>2</span>{{ __('Attendees') }}</legend>
              <p class="registration-section-copy">{{ __('Add every person attending, including the primary contact when applicable.') }}</p>
              <div data-attendee-list>
                @foreach ($oldAttendees as $index => $oldAttendee)
                  @php
                    $selectedTicketId = (string) old("attendees.$index.ticket_id", $oldAttendee['ticket_id'] ?? '');
                    $selectedTicket = $event->tickets->firstWhere('id', (int) $selectedTicketId);
                    $selectedVariation = (string) old("attendees.$index.variation_name", $oldAttendee['variation_name'] ?? '');
                    $showVariation = $selectedTicket?->pricing_type === 'variation';
                  @endphp
                  <article class="registration-attendee" data-attendee>
                    <div class="registration-attendee-heading"><h3>{{ __('Attendee') }} <span data-attendee-number>{{ $index + 1 }}</span></h3><button type="button" data-remove-attendee @if($loop->first) hidden @endif>{{ __('Remove') }}</button></div>
                    <div class="registration-two-column">
                      <div class="registration-field"><label for="attendee-{{ $index }}-name">{{ __('Full name') }} *</label><input id="attendee-{{ $index }}-name" name="attendees[{{ $index }}][name]" value="{{ old("attendees.$index.name", $oldAttendee['name'] ?? '') }}" maxlength="150" autocomplete="name" required @readonly($isAllocatedRegistration)></div>
                      <div class="registration-field"><label for="attendee-{{ $index }}-email">{{ __('Email address') }}</label><input id="attendee-{{ $index }}-email" type="email" name="attendees[{{ $index }}][email]" value="{{ old("attendees.$index.email", $oldAttendee['email'] ?? '') }}" maxlength="191" autocomplete="email" @readonly($isAllocatedRegistration)></div>
                    </div>
                    @if ($requiresTicketSelection)
                      <div class="registration-field">
                        <label for="attendee-{{ $index }}-ticket">{{ __('Registration type') }} *</label>
                        <select id="attendee-{{ $index }}-ticket" name="attendees[{{ $index }}][ticket_id]" data-ticket-select required>
                          <option value="">{{ __('Choose a type') }}</option>
                          @foreach ($event->tickets as $ticket)
                            <option value="{{ $ticket->id }}" @selected($selectedTicketId === (string) $ticket->id)>{{ $ticket->title }}</option>
                          @endforeach
                        </select>
                        @error("attendees.$index.ticket_id")<span class="registration-error">{{ $message }}</span>@enderror
                      </div>
                      <div class="registration-field" data-variation-field @if(!$showVariation) hidden @endif>
                        <label for="attendee-{{ $index }}-variation">{{ __('Ticket option') }} *</label>
                        <select id="attendee-{{ $index }}-variation" name="attendees[{{ $index }}][variation_name]" data-variation-select @required($showVariation) @disabled(!$showVariation)>
                          <option value="">{{ __('Choose an option') }}</option>
                          @foreach ($event->tickets as $ticket)
                            @foreach ((json_decode((string) $ticket->variations, true) ?: []) as $option)
                              <option value="{{ $option['name'] }}" data-ticket-id="{{ $ticket->id }}" @selected($selectedTicketId === (string) $ticket->id && $selectedVariation === (string) $option['name']) @disabled($selectedTicketId !== (string) $ticket->id)>{{ $option['name'] }}</option>
                            @endforeach
                          @endforeach
                        </select>
                        @error("attendees.$index.variation_name")<span class="registration-error">{{ $message }}</span>@enderror
                      </div>
                    @endif
                    @if ($publishedSessions->isNotEmpty())
                      <fieldset class="registration-field" data-session-choices>
                        <legend>{{ __('Agenda sessions') }}</legend>
                        <p class="registration-section-copy">{{ __('Choose the sessions this attendee plans to join. Required sessions are preselected by the organizer.') }}</p>
                        @foreach ($publishedSessions as $session)
                          @php
                            $oldSessionIds = old("attendees.$index.session_ids", []);
                            $oldSessionIds = is_array($oldSessionIds) ? array_map('strval', $oldSessionIds) : [];
                            $sessionSelected = in_array((string) $session->id, $oldSessionIds, true);
                          @endphp
                          <label data-session-option data-ticket-id="{{ $session->ticket_id ?: '' }}">
                            <input type="checkbox" name="attendees[{{ $index }}][session_ids][]" value="{{ $session->id }}" @checked($sessionSelected || $session->is_required) data-session-required="{{ $session->is_required ? '1' : '0' }}">
                            <span><strong>{{ $session->title }}</strong><br><small>{{ $session->starts_at->translatedFormat('D, d M · g:i A') }}–{{ $session->ends_at->format('g:i A') }}@if($session->location) · {{ $session->location }}@endif @if($session->capacity) · {{ __(':count places', ['count' => number_format($session->capacity)]) }}@endif</small></span>
                          </label>
                        @endforeach
                      </fieldset>
                    @endif
                    <div data-question-scope="attendee">
                      @foreach ($attendeeQuestions as $question)
                        @include('frontend.event.partials.registration-question', ['namePrefix' => "attendees[$index]", 'oldPrefix' => "attendees.$index", 'idPrefix' => "attendee-$index"])
                      @endforeach
                    </div>
                  </article>
                @endforeach
              </div>
              @unless ($isAllocatedRegistration)
                <button class="registration-add" type="button" data-add-attendee><i class="fas fa-plus" aria-hidden="true"></i>{{ __('Add another attendee') }}</button>
              @endunless
            </fieldset>

            <div class="registration-consent">
              <label><input type="checkbox" name="consent" value="1" required @checked(old('consent'))><span>{{ __('I have read the privacy notice below and consent to Tica and the event organizer processing this registration for event administration.') }}</span></label>
              @error('consent')<span class="registration-error">{{ $message }}</span>@enderror
              <details><summary>{{ __('Read privacy notice') }}</summary><p>{{ $form->privacy_notice }}</p></details>
            </div>
            <button class="registration-submit" type="submit"><span>{{ __('Submit registration') }}</span><i class="fas fa-arrow-right" aria-hidden="true"></i></button>
            <p class="registration-fine-print">{{ __('Submitting this form does not create a paid ticket. The organizer will communicate any payment or attendance requirements separately.') }}</p>
          </form>
        </section>
      </div>
    </div>
  </main>

  <template id="registration-attendee-template">
    <article class="registration-attendee" data-attendee>
      <div class="registration-attendee-heading"><h3>{{ __('Attendee') }} <span data-attendee-number></span></h3><button type="button" data-remove-attendee>{{ __('Remove') }}</button></div>
      <div class="registration-two-column">
        <div class="registration-field"><label for="attendee-__INDEX__-name">{{ __('Full name') }} *</label><input id="attendee-__INDEX__-name" name="attendees[__INDEX__][name]" maxlength="150" autocomplete="name" required></div>
        <div class="registration-field"><label for="attendee-__INDEX__-email">{{ __('Email address') }}</label><input id="attendee-__INDEX__-email" type="email" name="attendees[__INDEX__][email]" maxlength="191" autocomplete="email"></div>
      </div>
      @if ($requiresTicketSelection)
        <div class="registration-field">
          <label for="attendee-__INDEX__-ticket">{{ __('Registration type') }} *</label>
          <select id="attendee-__INDEX__-ticket" name="attendees[__INDEX__][ticket_id]" data-ticket-select required>
            <option value="">{{ __('Choose a type') }}</option>
            @foreach ($event->tickets as $ticket)<option value="{{ $ticket->id }}">{{ $ticket->title }}</option>@endforeach
          </select>
        </div>
        <div class="registration-field" data-variation-field hidden>
          <label for="attendee-__INDEX__-variation">{{ __('Ticket option') }} *</label>
          <select id="attendee-__INDEX__-variation" name="attendees[__INDEX__][variation_name]" data-variation-select disabled>
            <option value="">{{ __('Choose an option') }}</option>
            @foreach ($event->tickets as $ticket)
              @foreach ((json_decode((string) $ticket->variations, true) ?: []) as $option)
                <option value="{{ $option['name'] }}" data-ticket-id="{{ $ticket->id }}" disabled>{{ $option['name'] }}</option>
              @endforeach
            @endforeach
          </select>
        </div>
      @endif
      @if ($publishedSessions->isNotEmpty())
        <fieldset class="registration-field" data-session-choices>
          <legend>{{ __('Agenda sessions') }}</legend>
          @foreach ($publishedSessions as $session)
            <label data-session-option data-ticket-id="{{ $session->ticket_id ?: '' }}">
              <input type="checkbox" name="attendees[__INDEX__][session_ids][]" value="{{ $session->id }}" @checked($session->is_required) data-session-required="{{ $session->is_required ? '1' : '0' }}">
              <span><strong>{{ $session->title }}</strong><br><small>{{ $session->starts_at->translatedFormat('D, d M · g:i A') }}–{{ $session->ends_at->format('g:i A') }}@if($session->location) · {{ $session->location }}@endif</small></span>
            </label>
          @endforeach
        </fieldset>
      @endif
      <div data-question-scope="attendee">
        @foreach ($attendeeQuestions as $question)
          @include('frontend.event.partials.registration-question', ['namePrefix' => 'attendees[__INDEX__]', 'oldPrefix' => 'template', 'idPrefix' => 'attendee-__INDEX__'])
        @endforeach
      </div>
    </article>
  </template>
@endsection

@section('custom-script')
  <script>
    (function () {
      'use strict';
      var form = document.querySelector('[data-registration-form]');
      if (!form) return;
      var list = form.querySelector('[data-attendee-list]');
      var template = document.getElementById('registration-attendee-template');
      var addButton = form.querySelector('[data-add-attendee]');
      var maximum = {{ (int) config('registration.max_attendees_per_submission') }};

      function updateAttendees() {
        var attendees = list.querySelectorAll('[data-attendee]');
        attendees.forEach(function (attendee, index) {
          var number = attendee.querySelector('[data-attendee-number]');
          if (number) number.textContent = String(index + 1);
          var remove = attendee.querySelector('[data-remove-attendee]');
          if (remove) remove.hidden = attendees.length === 1;
        });
        if (addButton) {
          addButton.disabled = attendees.length >= maximum;
          addButton.hidden = attendees.length >= maximum;
        }
      }

      function inputValues(scope, key) {
        var selector = '[name$="[answers][' + key + ']"], [name$="[answers][' + key + '][]"]';
        return Array.prototype.slice.call(scope.querySelectorAll(selector)).filter(function (input) {
          return (input.type !== 'checkbox' && input.type !== 'radio') || input.checked;
        }).map(function (input) { return input.value; });
      }

      function updateConditions(scope) {
        var attendee = scope.closest('[data-attendee]');
        var ticketSelect = attendee ? attendee.querySelector('[data-ticket-select]') : null;
        var selectedTicket = ticketSelect ? ticketSelect.value : '';
        scope.querySelectorAll('[data-registration-field][data-condition-key]').forEach(function (field) {
          var values = inputValues(scope, field.dataset.conditionKey);
          var expected = field.dataset.conditionValue || '';
          var conditionMatches = field.dataset.conditionOperator === 'not_empty' ? values.some(Boolean)
            : field.dataset.conditionOperator === 'not_equals' ? !values.includes(expected)
            : values.includes(expected);
          var show = (!field.dataset.ticketId || field.dataset.ticketId === selectedTicket) && conditionMatches;
          field.hidden = !show;
          field.querySelectorAll('input,select,textarea').forEach(function (input) {
            input.disabled = !show;
            if (show && field.querySelector('.registration-required')) input.required = true;
            if (!show) input.required = false;
          });
        });
        scope.querySelectorAll('[data-registration-field][data-ticket-id]:not([data-condition-key])').forEach(function (field) {
          var show = field.dataset.ticketId === selectedTicket;
          field.hidden = !show;
          field.querySelectorAll('input,select,textarea').forEach(function (input) {
            input.disabled = !show;
            input.required = show && !!field.querySelector('.registration-required');
          });
        });
      }

      function updateSessionChoices(attendee) {
        if (!attendee) return;
        var ticketSelect = attendee.querySelector('[data-ticket-select]');
        var selectedTicket = ticketSelect ? ticketSelect.value : '';
        attendee.querySelectorAll('[data-session-option]').forEach(function (option) {
          var show = !option.dataset.ticketId || option.dataset.ticketId === selectedTicket;
          option.hidden = !show;
          var input = option.querySelector('input');
          input.disabled = !show;
          input.required = show && input.dataset.sessionRequired === '1';
          if (!show) input.checked = false;
          if (show && input.dataset.sessionRequired === '1') input.checked = true;
        });
      }

      function updateVariationChoice(attendee) {
        if (!attendee) return;
        var ticketSelect = attendee.querySelector('[data-ticket-select]');
        var field = attendee.querySelector('[data-variation-field]');
        var select = attendee.querySelector('[data-variation-select]');
        if (!field || !select) return;
        var selectedTicket = ticketSelect ? ticketSelect.value : '';
        var available = 0;
        Array.prototype.slice.call(select.options).forEach(function (option, index) {
          if (index === 0) return;
          var show = option.dataset.ticketId === selectedTicket;
          option.hidden = !show;
          option.disabled = !show;
          if (show) available++;
        });
        var selected = select.options[select.selectedIndex];
        if (selected && selected.value && selected.dataset.ticketId !== selectedTicket) select.value = '';
        field.hidden = available === 0;
        select.disabled = available === 0;
        select.required = available > 0;
        if (available === 0) select.value = '';
      }

      if (addButton) addButton.addEventListener('click', function () {
        var count = list.querySelectorAll('[data-attendee]').length;
        if (count >= maximum) return;
        var wrapper = document.createElement('div');
        wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(count)).trim();
        var attendee = wrapper.firstElementChild;
        list.appendChild(attendee);
        updateAttendees();
        updateConditions(attendee);
        updateSessionChoices(attendee);
        updateVariationChoice(attendee);
        attendee.querySelector('input').focus();
      });
      list.addEventListener('click', function (event) {
        var button = event.target.closest('[data-remove-attendee]');
        if (!button) return;
        button.closest('[data-attendee]').remove();
        updateAttendees();
      });
      form.addEventListener('change', function (event) {
        if (event.target.matches('[data-ticket-select]')) {
          var attendeeQuestions = event.target.closest('[data-attendee]').querySelector('[data-question-scope="attendee"]');
          if (attendeeQuestions) updateConditions(attendeeQuestions);
          updateSessionChoices(event.target.closest('[data-attendee]'));
          updateVariationChoice(event.target.closest('[data-attendee]'));
          return;
        }
        var scope = event.target.closest('[data-question-scope]');
        if (scope) updateConditions(scope);
      });
      form.querySelectorAll('[data-question-scope]').forEach(updateConditions);
      form.querySelectorAll('[data-attendee]').forEach(updateSessionChoices);
      form.querySelectorAll('[data-attendee]').forEach(updateVariationChoice);
      updateAttendees();
    })();
  </script>
@endsection
