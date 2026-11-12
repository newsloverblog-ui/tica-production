<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Event\Ticket;
use App\Models\EventRegistrationAnswer;
use App\Models\EventRegistrationAttendee;
use App\Models\EventRegistrationForm;
use App\Models\EventRegistrationFormTemplate;
use App\Models\EventRegistrationFile;
use App\Models\EventRegistrationNotification;
use App\Models\EventRegistrationQuestion;
use App\Models\EventRegistrationSubmission;
use App\Models\EventRegistrationSession;
use App\Models\EventRegistrationSessionSelection;
use Illuminate\Auth\Access\AuthorizationException;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class EventRegistrationService
{
    public const QUESTION_TYPES = [
        'text', 'textarea', 'email', 'phone', 'number', 'date', 'select', 'radio', 'multi_select', 'checkbox',
        'company', 'job_title', 'country', 'state', 'consent', 'terms', 'file',
    ];

    private const LENGTH_RULE_TYPES = [
        'text', 'textarea', 'email', 'phone', 'company', 'job_title', 'country', 'state',
    ];

    public function saveForm(Event $event, int $organizerId, array $data): EventRegistrationForm
    {
        $this->assertEventOwner($event, $organizerId);
        $name = trim((string) ($data['name'] ?? ''));
        $intro = trim((string) ($data['intro'] ?? ''));
        $privacyNotice = trim((string) ($data['privacy_notice'] ?? ''));
        $approvalMode = (string) ($data['approval_mode'] ?? 'manual');
        if ($name === '' || mb_strlen($name) > 150) {
            throw ValidationException::withMessages(['name' => 'Enter a form name of 150 characters or fewer.']);
        }
        if (!in_array($approvalMode, ['automatic', 'manual'], true)) {
            throw ValidationException::withMessages(['approval_mode' => 'Choose automatic or manual approval.']);
        }
        if ($privacyNotice === '' || mb_strlen($privacyNotice) > 5000) {
            throw ValidationException::withMessages(['privacy_notice' => 'Provide a privacy notice of 5,000 characters or fewer.']);
        }
        if (mb_strlen($intro) > 5000) {
            throw ValidationException::withMessages(['intro' => 'The introduction cannot exceed 5,000 characters.']);
        }

        return DB::transaction(function () use ($event, $organizerId, $name, $intro, $privacyNotice, $approvalMode): EventRegistrationForm {
            $form = EventRegistrationForm::where('event_id', $event->id)->lockForUpdate()->first();
            $before = $form?->only(['name', 'status', 'approval_mode', 'version']) ?: [];
            $form ??= new EventRegistrationForm([
                'public_id' => (string) Str::ulid(),
                'event_id' => $event->id,
                'organizer_id' => $organizerId,
                'status' => 'draft',
                'version' => 1,
                'consent_version' => (string) config('registration.consent_version'),
            ]);
            $this->returnPublishedFormToDraft($form);
            $form->forceFill([
                'name' => $name,
                'intro' => $intro !== '' ? $intro : null,
                'privacy_notice' => $privacyNotice,
                'approval_mode' => $approvalMode,
                'archived_at' => null,
            ])->save();
            $event->forceFill(['registration_mode' => 'corporate'])->save();

            AuditLogger::record('event.registration_form_saved', $form, $before, [
                'name' => $form->name,
                'status' => $form->status,
                'approval_mode' => $form->approval_mode,
                'version' => $form->version,
            ], ['event_id' => $event->id]);

            return $form->fresh();
        }, 3);
    }

    public function addQuestion(EventRegistrationForm $form, int $organizerId, array $data): EventRegistrationQuestion
    {
        $this->assertFormOwner($form, $organizerId);
        $label = trim((string) ($data['label'] ?? ''));
        $type = (string) ($data['type'] ?? 'text');
        $scope = (string) ($data['scope'] ?? 'registrant');
        if ($label === '' || mb_strlen($label) > 191) {
            throw ValidationException::withMessages(['label' => 'Enter a question label of 191 characters or fewer.']);
        }
        if (!in_array($type, self::QUESTION_TYPES, true)) {
            throw ValidationException::withMessages(['type' => 'Choose a supported question type.']);
        }
        if (!in_array($scope, ['registrant', 'attendee'], true)) {
            throw ValidationException::withMessages(['scope' => 'Choose registrant or attendee scope.']);
        }
        $ticketId = !empty($data['ticket_id']) ? (int) $data['ticket_id'] : null;
        if ($ticketId && $scope !== 'attendee') {
            throw ValidationException::withMessages(['ticket_id' => 'Ticket-specific questions must be asked per attendee.']);
        }
        if ($ticketId && !\App\Models\Event\Ticket::query()
            ->whereKey($ticketId)->where('event_id', $form->event_id)->exists()) {
            throw ValidationException::withMessages(['ticket_id' => 'Choose a ticket type from this event.']);
        }
        $key = Str::snake(trim((string) ($data['question_key'] ?? $label)));
        $key = trim((string) preg_replace('/[^a-z0-9_]+/', '_', strtolower($key)), '_');
        if ($key === '' || mb_strlen($key) > 80) {
            throw ValidationException::withMessages(['question_key' => 'Enter a valid key of 80 characters or fewer.']);
        }
        $options = $this->normalizeOptions($data['options'] ?? []);
        if (in_array($type, ['select', 'radio', 'multi_select'], true) && count($options) < 2) {
            throw ValidationException::withMessages(['options' => 'Selection questions require at least two unique options.']);
        }
        if ($type === 'terms') {
            $scope = 'registrant';
            $ticketId = null;
            $data['is_required'] = false;
            $data['condition_question_key'] = null;
        }
        $condition = $this->normalizeCondition($form, $scope, $ticketId, $data);
        $settings = $this->normalizeQuestionSettings($type, $data);
        $helpText = trim((string) ($data['help_text'] ?? ''));
        $helpTextLimit = $type === 'terms' ? 5000 : 1000;
        if (mb_strlen($helpText) > $helpTextLimit) {
            throw ValidationException::withMessages(['help_text' => "Help text cannot exceed {$helpTextLimit} characters."]);
        }

        return DB::transaction(function () use ($form, $label, $type, $scope, $ticketId, $key, $options, $condition, $settings, $helpText, $data): EventRegistrationQuestion {
            $lockedForm = EventRegistrationForm::whereKey($form->id)->lockForUpdate()->firstOrFail();
            $questions = EventRegistrationQuestion::where('form_id', $lockedForm->id)
                ->orderBy('position')
                ->lockForUpdate()
                ->get();
            if ($questions->count() >= (int) config('registration.max_questions_per_form')) {
                throw ValidationException::withMessages(['question' => 'This form has reached its question limit.']);
            }
            if ($questions->contains(fn (EventRegistrationQuestion $question): bool => $question->question_key === $key)) {
                throw ValidationException::withMessages(['question_key' => 'Question keys must be unique within the form.']);
            }
            if ($condition && !$questions->contains(function (EventRegistrationQuestion $question) use ($condition, $scope): bool {
                return $question->question_key === $condition['question_key'] && $question->scope === $scope;
            })) {
                throw ValidationException::withMessages([
                    'condition_question_key' => 'A condition must reference an earlier question with the same scope.',
                ]);
            }
            $this->returnPublishedFormToDraft($lockedForm);
            $lockedForm->save();
            $attributes = [
                'public_id' => (string) Str::ulid(),
                'form_id' => $lockedForm->id,
                'question_key' => $key,
                'label' => $label,
                'help_text' => $helpText !== '' ? $helpText : null,
                'type' => $type,
                'scope' => $scope,
                'is_required' => !empty($data['is_required']),
                'options' => $options ?: null,
                'condition' => $condition,
                'position' => ((int) $questions->max('position')) + 1,
            ];
            if ($ticketId && Schema::hasColumn('event_registration_questions', 'ticket_id')) {
                $attributes['ticket_id'] = $ticketId;
            }
            if (Schema::hasColumn('event_registration_questions', 'settings')) {
                $attributes['settings'] = $settings ?: null;
            }
            $question = EventRegistrationQuestion::create($attributes);
            AuditLogger::record('event.registration_question_created', $question, [], [
                'question_key' => $key,
                'type' => $type,
                'scope' => $scope,
                'ticket_id' => $ticketId,
                'required' => $question->is_required,
            ], ['form_id' => $lockedForm->id, 'event_id' => $lockedForm->event_id]);

            return $question;
        }, 3);
    }

    public function updateQuestion(EventRegistrationQuestion $question, int $organizerId, array $data): EventRegistrationQuestion
    {
        $question->loadMissing('form');
        $this->assertFormOwner($question->form, $organizerId);
        $label = trim((string) ($data['label'] ?? ''));
        $type = (string) ($data['type'] ?? 'text');
        $scope = (string) ($data['scope'] ?? 'registrant');
        if ($label === '' || mb_strlen($label) > 191) {
            throw ValidationException::withMessages(['label' => 'Enter a question label of 191 characters or fewer.']);
        }
        if (!in_array($type, self::QUESTION_TYPES, true)) {
            throw ValidationException::withMessages(['type' => 'Choose a supported question type.']);
        }
        if (!in_array($scope, ['registrant', 'attendee'], true)) {
            throw ValidationException::withMessages(['scope' => 'Choose registrant or attendee scope.']);
        }
        $ticketId = !empty($data['ticket_id']) ? (int) $data['ticket_id'] : null;
        if ($ticketId && $scope !== 'attendee') {
            throw ValidationException::withMessages(['ticket_id' => 'Ticket-specific questions must be asked per attendee.']);
        }
        if ($ticketId && !Ticket::query()->whereKey($ticketId)->where('event_id', $question->form->event_id)->exists()) {
            throw ValidationException::withMessages(['ticket_id' => 'Choose a ticket type from this event.']);
        }
        $key = Str::snake(trim((string) ($data['question_key'] ?? $label)));
        $key = trim((string) preg_replace('/[^a-z0-9_]+/', '_', strtolower($key)), '_');
        if ($key === '' || mb_strlen($key) > 80) {
            throw ValidationException::withMessages(['question_key' => 'Enter a valid key of 80 characters or fewer.']);
        }
        $options = $this->normalizeOptions($data['options'] ?? []);
        if (in_array($type, ['select', 'radio', 'multi_select'], true) && count($options) < 2) {
            throw ValidationException::withMessages(['options' => 'Selection questions require at least two unique options.']);
        }
        if ($type === 'terms') {
            $scope = 'registrant';
            $ticketId = null;
            $data['is_required'] = false;
            $data['condition_question_key'] = null;
        }
        $settings = $this->normalizeQuestionSettings($type, $data);
        $helpText = trim((string) ($data['help_text'] ?? ''));
        $helpTextLimit = $type === 'terms' ? 5000 : 1000;
        if (mb_strlen($helpText) > $helpTextLimit) {
            throw ValidationException::withMessages(['help_text' => "Help text cannot exceed {$helpTextLimit} characters."]);
        }

        return DB::transaction(function () use (
            $question, $label, $type, $scope, $ticketId, $key, $options, $settings, $helpText, $data
        ): EventRegistrationQuestion {
            $lockedForm = EventRegistrationForm::whereKey($question->form_id)->lockForUpdate()->firstOrFail();
            $questions = EventRegistrationQuestion::query()->where('form_id', $lockedForm->id)
                ->orderBy('position')->orderBy('id')->lockForUpdate()->get();
            $locked = $questions->firstWhere('id', $question->id);
            if (!$locked) {
                throw ValidationException::withMessages(['question' => 'The question is no longer part of this form.']);
            }
            if ($questions->contains(fn (EventRegistrationQuestion $candidate): bool =>
                $candidate->id !== $locked->id && $candidate->question_key === $key
            )) {
                throw ValidationException::withMessages(['question_key' => 'Question keys must be unique within the form.']);
            }

            $condition = null;
            $dependencyKey = trim((string) ($data['condition_question_key'] ?? ''));
            if ($dependencyKey !== '') {
                $dependency = $questions->firstWhere('question_key', $dependencyKey);
                if (!$dependency || $dependency->id === $locked->id || $dependency->scope !== $scope
                    || $dependency->position >= $locked->position) {
                    throw ValidationException::withMessages([
                        'condition_question_key' => 'A condition must reference an earlier question with the same scope.',
                    ]);
                }
                $dependencyTicketId = (int) ($dependency->ticket_id ?? 0) ?: null;
                if (($ticketId === null && $dependencyTicketId !== null)
                    || ($ticketId !== null && $dependencyTicketId !== null && $dependencyTicketId !== $ticketId)) {
                    throw ValidationException::withMessages([
                        'condition_question_key' => 'A condition cannot reference a question for a different ticket type.',
                    ]);
                }
                $operator = (string) ($data['condition_operator'] ?? 'equals');
                if (!in_array($operator, ['equals', 'not_equals', 'not_empty'], true)) {
                    throw ValidationException::withMessages(['condition_operator' => 'Choose a supported condition.']);
                }
                if ($dependency->type === 'terms' || ($dependency->type === 'file' && $operator !== 'not_empty')) {
                    throw ValidationException::withMessages([
                        'condition_operator' => $dependency->type === 'file'
                            ? 'File-upload conditions can only check whether a file was provided.'
                            : 'An information block cannot control another question.',
                    ]);
                }
                $condition = [
                    'question_key' => $dependency->question_key,
                    'operator' => $operator,
                    'value' => $this->normalizeConditionValue($dependency, $operator, $data['condition_value'] ?? null),
                ];
            }

            $dependents = $questions->filter(fn (EventRegistrationQuestion $candidate): bool =>
                ($candidate->condition['question_key'] ?? null) === $locked->question_key
            );
            foreach ($dependents as $dependent) {
                $dependentTicketId = (int) ($dependent->ticket_id ?? 0) ?: null;
                if ($dependent->scope !== $scope
                    || ($dependentTicketId === null && $ticketId !== null)
                    || ($dependentTicketId !== null && $ticketId !== null && $dependentTicketId !== $ticketId)) {
                    throw ValidationException::withMessages([
                        'scope' => "Update {$dependent->question_key} first because it depends on this field's current scope and registration type.",
                    ]);
                }
                $dependentOperator = $dependent->condition['operator'] ?? 'equals';
                if ($type === 'terms' || ($type === 'file' && $dependentOperator !== 'not_empty')) {
                    throw ValidationException::withMessages([
                        'type' => "Update {$dependent->question_key} first because its condition is not supported by the new field type.",
                    ]);
                }
                $expected = trim((string) ($dependent->condition['value'] ?? ''));
                if ($expected !== '' && in_array($type, ['select', 'radio', 'multi_select'], true)
                    && !in_array($expected, $options, true)) {
                    throw ValidationException::withMessages([
                        'options' => "Keep the option ‘{$expected}’ or update {$dependent->question_key} first.",
                    ]);
                }
            }

            $before = $locked->only([
                'question_key', 'label', 'help_text', 'type', 'scope', 'ticket_id', 'is_required',
                'options', 'condition', 'settings',
            ]);
            $locked->fill([
                'question_key' => $key,
                'label' => $label,
                'help_text' => $helpText !== '' ? $helpText : null,
                'type' => $type,
                'scope' => $scope,
                'ticket_id' => $ticketId,
                'is_required' => !empty($data['is_required']),
                'options' => $options ?: null,
                'condition' => $condition,
                'settings' => $settings ?: null,
            ]);
            $keyChanged = $locked->question_key !== $question->question_key;
            if (!$locked->isDirty() && !$keyChanged) {
                return $locked;
            }

            $this->returnPublishedFormToDraft($lockedForm);
            $lockedForm->save();
            $locked->save();
            if ($keyChanged) {
                foreach ($dependents as $dependent) {
                    $dependentCondition = $dependent->condition;
                    $dependentCondition['question_key'] = $key;
                    $dependent->forceFill(['condition' => $dependentCondition])->save();
                }
            }
            AuditLogger::record('event.registration_question_updated', $locked, $before, $locked->only([
                'question_key', 'label', 'help_text', 'type', 'scope', 'ticket_id', 'is_required',
                'options', 'condition', 'settings',
            ]), ['form_id' => $lockedForm->id, 'event_id' => $lockedForm->event_id]);

            return $locked->fresh();
        }, 3);
    }

    public function deleteQuestion(EventRegistrationQuestion $question, int $organizerId): void
    {
        $question->loadMissing('form');
        $this->assertFormOwner($question->form, $organizerId);
        DB::transaction(function () use ($question): void {
            $locked = EventRegistrationQuestion::whereKey($question->id)->lockForUpdate()->firstOrFail();
            if (EventRegistrationQuestion::where('form_id', $locked->form_id)
                ->where('condition->question_key', $locked->question_key)->exists()) {
                throw ValidationException::withMessages(['question' => 'Remove dependent conditions before deleting this question.']);
            }
            $form = EventRegistrationForm::whereKey($locked->form_id)->lockForUpdate()->firstOrFail();
            $this->returnPublishedFormToDraft($form);
            $form->save();
            AuditLogger::record('event.registration_question_deleted', $locked, [
                'question_key' => $locked->question_key,
                'type' => $locked->type,
                'scope' => $locked->scope,
            ], [], ['form_id' => $form->id, 'event_id' => $form->event_id]);
            $locked->delete();
        }, 3);
    }

    public function reorderQuestions(EventRegistrationForm $form, int $organizerId, array $orderedPublicIds): EventRegistrationForm
    {
        $this->assertFormOwner($form, $organizerId);
        $orderedPublicIds = array_values(array_map('strval', $orderedPublicIds));
        if ($orderedPublicIds === [] || count($orderedPublicIds) !== count(array_unique($orderedPublicIds))) {
            throw ValidationException::withMessages(['ordered_questions' => 'Submit every question exactly once.']);
        }

        return DB::transaction(function () use ($form, $orderedPublicIds): EventRegistrationForm {
            $lockedForm = EventRegistrationForm::whereKey($form->id)->lockForUpdate()->firstOrFail();
            $questions = EventRegistrationQuestion::query()->where('form_id', $lockedForm->id)
                ->orderBy('position')->orderBy('id')->lockForUpdate()->get();
            $expected = $questions->pluck('public_id')->map(fn ($id): string => (string) $id)->sort()->values()->all();
            $submitted = collect($orderedPublicIds)->sort()->values()->all();
            if ($expected !== $submitted) {
                throw ValidationException::withMessages([
                    'ordered_questions' => 'The question list changed. Refresh the page and try again.',
                ]);
            }

            $positions = collect($orderedPublicIds)->flip();
            foreach ($questions as $question) {
                $dependencyKey = $question->condition['question_key'] ?? null;
                if (!$dependencyKey) {
                    continue;
                }
                $dependency = $questions->firstWhere('question_key', $dependencyKey);
                $dependencyPosition = $positions->get((string) $dependency?->public_id);
                $questionPosition = $positions->get((string) $question->public_id);
                if ($dependencyPosition === null || $questionPosition === null || $dependencyPosition >= $questionPosition) {
                    throw ValidationException::withMessages([
                        'ordered_questions' => "Keep {$dependencyKey} before {$question->question_key} because it controls that field.",
                    ]);
                }
            }

            $before = $questions->pluck('question_key')->values()->all();
            $after = collect($orderedPublicIds)->map(
                fn (string $publicId): string => (string) $questions->firstWhere('public_id', $publicId)->question_key
            )->all();
            if ($before === $after) {
                return $lockedForm->fresh('questions');
            }

            $this->returnPublishedFormToDraft($lockedForm);
            $lockedForm->save();
            foreach ($orderedPublicIds as $index => $publicId) {
                EventRegistrationQuestion::query()->where('form_id', $lockedForm->id)
                    ->where('public_id', $publicId)->update(['position' => $index + 1, 'updated_at' => now()]);
            }
            AuditLogger::record('event.registration_questions_reordered', $lockedForm,
                ['question_order' => $before], ['question_order' => $after], ['event_id' => $lockedForm->event_id]);

            return $lockedForm->fresh('questions');
        }, 3);
    }

    public function moveQuestion(EventRegistrationQuestion $question, int $organizerId, string $direction): EventRegistrationForm
    {
        $question->loadMissing('form');
        $this->assertFormOwner($question->form, $organizerId);
        if (!in_array($direction, ['up', 'down'], true)) {
            throw ValidationException::withMessages(['direction' => 'Choose up or down.']);
        }
        $ordered = EventRegistrationQuestion::query()->where('form_id', $question->form_id)
            ->orderBy('position')->orderBy('id')->pluck('public_id')->map(fn ($id): string => (string) $id)->all();
        $index = array_search((string) $question->public_id, $ordered, true);
        if ($index === false) {
            throw ValidationException::withMessages(['question' => 'The question is no longer part of this form.']);
        }
        $other = $direction === 'up' ? $index - 1 : $index + 1;
        if (!array_key_exists($other, $ordered)) {
            return $question->form->fresh('questions');
        }
        [$ordered[$index], $ordered[$other]] = [$ordered[$other], $ordered[$index]];

        return $this->reorderQuestions($question->form, $organizerId, $ordered);
    }

    public function publish(EventRegistrationForm $form, int $organizerId): EventRegistrationForm
    {
        $this->assertFormOwner($form, $organizerId);

        return DB::transaction(function () use ($form): EventRegistrationForm {
            $locked = EventRegistrationForm::with('event')->whereKey($form->id)->lockForUpdate()->firstOrFail();
            if (!$locked->event || ($locked->event->registration_mode ?: 'ticketing') !== 'corporate') {
                throw ValidationException::withMessages(['form' => 'The event must use corporate registration mode.']);
            }
            if (trim((string) $locked->privacy_notice) === '') {
                throw ValidationException::withMessages(['privacy_notice' => 'Add a privacy notice before publishing.']);
            }
            if ($locked->status === 'published') {
                return $locked;
            }
            $locked->forceFill(['status' => 'published', 'published_at' => now(), 'archived_at' => null])->save();
            AuditLogger::record('event.registration_form_published', $locked, ['status' => 'draft'], [
                'status' => 'published',
                'version' => $locked->version,
                'question_count' => $locked->questions()->count(),
            ], ['event_id' => $locked->event_id]);

            return $locked->fresh();
        }, 3);
    }

    public function submit(
        Event $event,
        array $data,
        ?int $customerId = null,
        ?string $ip = null,
        ?array $sourceImport = null,
        ?int $sourceAllocationClaimId = null,
    ): EventRegistrationSubmission
    {
        if (($event->registration_mode ?: 'ticketing') !== 'corporate'
            || (int) $event->status !== 1
            || ($event->lifecycle_status ?: 'scheduled') !== 'scheduled'
            || $event->cancelled_at
        ) {
            throw ValidationException::withMessages(['event' => 'Registration is not open for this event.']);
        }
        $form = EventRegistrationForm::with('questions')
            ->where('event_id', $event->id)
            ->where('status', 'published')
            ->first();
        if (!$form) {
            throw ValidationException::withMessages(['event' => 'This event does not have a published registration form.']);
        }
        $name = trim((string) data_get($data, 'registrant.name'));
        $email = mb_strtolower(trim((string) data_get($data, 'registrant.email')));
        if ($name === '' || mb_strlen($name) > 150) {
            throw ValidationException::withMessages(['registrant.name' => 'Enter the primary registrant’s name.']);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 191) {
            throw ValidationException::withMessages(['registrant.email' => 'Enter a valid primary email address.']);
        }
        if (empty($data['consent'])) {
            throw ValidationException::withMessages(['consent' => 'Consent is required to submit this registration.']);
        }
        $attendees = array_values(is_array($data['attendees'] ?? null) ? $data['attendees'] : []);
        $maxAttendees = (int) config('registration.max_attendees_per_submission');
        if ($attendees === [] || count($attendees) > $maxAttendees) {
            throw ValidationException::withMessages(['attendees' => "Add between 1 and {$maxAttendees} attendees."]);
        }
        $registrantAnswers = is_array($data['answers'] ?? null) ? $data['answers'] : [];
        $registrantValidated = $this->validateQuestionSet(
            $form->questions->where('scope', 'registrant'),
            $registrantAnswers,
            'answers'
        );
        $validatedAttendees = [];
        $publishedSessions = Schema::hasTable('event_registration_sessions')
            ? EventRegistrationSession::query()->where('form_id', $form->id)->where('status', 'published')
                ->orderBy('starts_at')->get()->keyBy('id')
            : collect();
        $requiresTicketSelection = Ticket::query()->where('event_id', $event->id)->exists() || $form->questions->contains(
            fn (EventRegistrationQuestion $question): bool => (int) ($question->ticket_id ?? 0) > 0
        ) || $publishedSessions->contains(fn (EventRegistrationSession $session): bool => (int) ($session->ticket_id ?? 0) > 0);
        foreach ($attendees as $index => $attendee) {
            $attendeeName = trim((string) ($attendee['name'] ?? ''));
            $attendeeEmail = mb_strtolower(trim((string) ($attendee['email'] ?? '')));
            if ($attendeeName === '' || mb_strlen($attendeeName) > 150) {
                throw ValidationException::withMessages(["attendees.{$index}.name" => 'Enter the attendee’s name.']);
            }
            if ($attendeeEmail !== '' && (!filter_var($attendeeEmail, FILTER_VALIDATE_EMAIL) || mb_strlen($attendeeEmail) > 191)) {
                throw ValidationException::withMessages(["attendees.{$index}.email" => 'Enter a valid attendee email address.']);
            }
            $answerMap = is_array($attendee['answers'] ?? null) ? $attendee['answers'] : [];
            $ticketId = !empty($attendee['ticket_id']) ? (int) $attendee['ticket_id'] : null;
            if ($requiresTicketSelection && !$ticketId) {
                throw ValidationException::withMessages(["attendees.{$index}.ticket_id" => 'Choose a registration type for this attendee.']);
            }
            $ticket = $ticketId
                ? Ticket::query()->whereKey($ticketId)->where('event_id', $event->id)->first()
                : null;
            if ($ticketId && !$ticket) {
                throw ValidationException::withMessages(["attendees.{$index}.ticket_id" => 'Choose a valid registration type.']);
            }
            $variationName = trim((string) ($attendee['variation_name'] ?? ''));
            if ($ticket?->pricing_type === 'variation') {
                $variation = collect(json_decode((string) $ticket->variations, true) ?: [])->first(
                    fn (array $option): bool => hash_equals((string) ($option['name'] ?? ''), $variationName)
                );
                if (!$variation) {
                    throw ValidationException::withMessages(["attendees.{$index}.variation_name" => 'Choose a valid ticket option for this attendee.']);
                }
            } else {
                $variationName = '';
            }
            $sessionIds = collect(is_array($attendee['session_ids'] ?? null) ? $attendee['session_ids'] : [])
                ->map(fn ($id) => (int) $id)->filter()->unique()->values();
            if ($sessionIds->count() > 100) {
                throw ValidationException::withMessages(["attendees.{$index}.session_ids" => 'Choose no more than 100 sessions.']);
            }
            foreach ($sessionIds as $sessionId) {
                $session = $publishedSessions->get($sessionId);
                if (!$session || ($session->ticket_id && (int) $session->ticket_id !== $ticketId)) {
                    throw ValidationException::withMessages(["attendees.{$index}.session_ids" => 'Choose sessions available for this attendee’s registration type.']);
                }
            }
            $selectedSessions = $sessionIds->map(fn (int $sessionId) => $publishedSessions->get($sessionId))
                ->filter()->sortBy('starts_at')->values();
            for ($position = 1; $position < $selectedSessions->count(); $position++) {
                if ($selectedSessions[$position]->starts_at->lt($selectedSessions[$position - 1]->ends_at)) {
                    throw ValidationException::withMessages(["attendees.{$index}.session_ids" => 'Choose sessions that do not overlap.']);
                }
            }
            $requiredSessionIds = $publishedSessions->filter(fn (EventRegistrationSession $session): bool =>
                $session->is_required && (!$session->ticket_id || (int) $session->ticket_id === $ticketId)
            )->keys();
            if ($requiredSessionIds->diff($sessionIds)->isNotEmpty()) {
                throw ValidationException::withMessages(["attendees.{$index}.session_ids" => 'Choose every required session for this attendee.']);
            }
            $attendeeQuestions = $form->questions->where('scope', 'attendee')->filter(
                fn (EventRegistrationQuestion $question): bool => !$question->ticket_id || (int) $question->ticket_id === $ticketId
            );
            $validatedAttendees[] = [
                'name' => $attendeeName,
                'email' => $attendeeEmail,
                'ticket_id' => $ticketId,
                'variation_name' => $variationName !== '' ? $variationName : null,
                'session_ids' => $sessionIds->all(),
                'answers' => $this->validateQuestionSet(
                    $attendeeQuestions,
                    $answerMap,
                    "attendees.{$index}.answers"
                ),
            ];
        }

        $this->assertUploadBudget($registrantValidated, $validatedAttendees);

        $storedFiles = [];
        try {
            $this->storeValidatedFiles($registrantValidated, $event->id, $storedFiles);
            foreach ($validatedAttendees as &$validatedAttendee) {
                $this->storeValidatedFiles($validatedAttendee['answers'], $event->id, $storedFiles);
            }
            unset($validatedAttendee);

            $submission = DB::transaction(function () use ($form, $event, $customerId, $ip, $name, $email, $validatedAttendees, $registrantValidated, $sourceImport, $sourceAllocationClaimId): EventRegistrationSubmission {
            $locked = EventRegistrationForm::whereKey($form->id)->where('status', 'published')->lockForUpdate()->first();
            if (!$locked || $locked->version !== $form->version) {
                throw ValidationException::withMessages(['form' => 'The registration form changed. Review it and submit again.']);
            }
            $requestedSessions = collect($validatedAttendees)->flatMap(fn (array $attendee): array => $attendee['session_ids'])->countBy();
            $lockedSessions = collect();
            if ($requestedSessions->isNotEmpty()) {
                $lockedSessions = EventRegistrationSession::query()->whereIn('id', $requestedSessions->keys()->all())
                    ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                foreach ($requestedSessions as $sessionId => $requestedCount) {
                    $session = $lockedSessions->get((int) $sessionId);
                    if (!$session || $session->status !== 'published' || (int) $session->form_id !== (int) $locked->id) {
                        throw ValidationException::withMessages(['attendees' => 'The agenda changed. Review the session choices and submit again.']);
                    }
                    if ($session->capacity !== null) {
                        $selected = EventRegistrationSessionSelection::where('session_id', $session->id)->whereNull('cancelled_at')->count();
                        if ($selected + (int) $requestedCount > $session->capacity) {
                            throw ValidationException::withMessages(['attendees' => "{$session->title} no longer has enough places for this registration."]);
                        }
                    }
                }
            }
            $submissionAttributes = [
                'public_id' => (string) Str::ulid(),
                'form_id' => $locked->id,
                'event_id' => $event->id,
                'organizer_id' => $event->organizer_id,
                'customer_id' => $customerId,
                'form_version' => $locked->version,
                'status' => $locked->approval_mode === 'automatic' ? 'approved' : 'submitted',
                'primary_name' => $name,
                'primary_email' => $email,
                'primary_email_hash' => $this->contactHash($email),
                'attendee_count' => count($validatedAttendees),
                'consent_version' => $locked->consent_version,
                'consented_at' => now(),
                'source_ip_hash' => $ip ? $this->contactHash($ip) : null,
                'submitted_at' => now(),
            ];
            if ($sourceImport) {
                $submissionAttributes['source_import_id'] = $sourceImport['id'];
                $submissionAttributes['source_import_key'] = $sourceImport['key'];
            }
            if ($sourceAllocationClaimId) {
                $submissionAttributes['source_allocation_claim_id'] = $sourceAllocationClaimId;
            }
            $submission = EventRegistrationSubmission::create($submissionAttributes);
            $this->persistAnswers($submission, null, $registrantValidated);
            foreach ($validatedAttendees as $index => $attendeeData) {
                $attendeeAttributes = [
                    'public_id' => (string) Str::ulid(),
                    'submission_id' => $submission->id,
                    'sequence' => $index + 1,
                    'name' => $attendeeData['name'],
                    'email' => $attendeeData['email'] !== '' ? $attendeeData['email'] : null,
                    'email_hash' => $attendeeData['email'] !== '' ? $this->contactHash($attendeeData['email']) : null,
                    'status' => 'registered',
                ];
                if ($attendeeData['ticket_id']) {
                    $attendeeAttributes['ticket_id'] = $attendeeData['ticket_id'];
                }
                if ($attendeeData['variation_name']) {
                    $attendeeAttributes['variation_name'] = $attendeeData['variation_name'];
                }
                $attendee = EventRegistrationAttendee::create($attendeeAttributes);
                $this->persistAnswers($submission, $attendee, $attendeeData['answers']);
                foreach ($attendeeData['session_ids'] as $sessionId) {
                    EventRegistrationSessionSelection::create([
                        'session_id' => $sessionId, 'submission_id' => $submission->id,
                        'attendee_id' => $attendee->id, 'selected_at' => now(),
                    ]);
                }
            }
            AuditLogger::record('event.registration_submitted', $submission, [], [
                'status' => $submission->status,
                'form_version' => $submission->form_version,
                'attendee_count' => $submission->attendee_count,
                'registrant_answer_count' => count($registrantValidated),
            ], [
                'event_id' => $event->id,
                'form_id' => $locked->id,
                'source' => $sourceImport ? 'bulk_import' : ($sourceAllocationClaimId ? 'sponsor_allocation' : 'public_form'),
                'source_import_id' => $sourceImport['id'] ?? null,
                'source_allocation_claim_id' => $sourceAllocationClaimId,
            ]);

            return $submission->load('attendees', 'answers');
            }, 3);
        } catch (Throwable $exception) {
            foreach ($storedFiles as $storedFile) {
                app(EventRegistrationFileService::class)->deleteStored($storedFile);
            }
            throw $exception;
        }

        $this->queueStatusNotification($submission, $submission->status === 'approved' ? 'approved' : 'submitted');

        return $submission;
    }

    public function decide(EventRegistrationSubmission $submission, int $organizerId, string $decision, ?string $note): EventRegistrationSubmission
    {
        if ((int) $submission->organizer_id !== $organizerId) {
            throw new AuthorizationException('You cannot review another organizer’s registration.');
        }
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            throw ValidationException::withMessages(['decision' => 'Choose approve or reject.']);
        }
        $note = trim((string) $note);
        if ($decision === 'rejected' && $note === '') {
            throw ValidationException::withMessages(['note' => 'Give a reason when rejecting a registration.']);
        }
        if (mb_strlen($note) > 2000) {
            throw ValidationException::withMessages(['note' => 'The review note cannot exceed 2,000 characters.']);
        }

        $changed = false;
        $result = DB::transaction(function () use ($submission, $organizerId, $decision, $note, &$changed): EventRegistrationSubmission {
            $locked = EventRegistrationSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
            if ((int) $locked->organizer_id !== $organizerId) {
                throw new AuthorizationException('You cannot review another organizer’s registration.');
            }
            if ($locked->status === $decision) {
                return $locked;
            }
            if ($locked->status !== 'submitted') {
                throw ValidationException::withMessages(['decision' => 'Only a submitted registration can be reviewed.']);
            }
            $before = $locked->status;
            $locked->forceFill([
                'status' => $decision,
                'reviewed_by_organizer_id' => $organizerId,
                'reviewed_at' => now(),
                'review_note' => $note !== '' ? $note : null,
            ])->save();
            if ($decision === 'rejected' && Schema::hasTable('event_registration_session_selections')) {
                EventRegistrationSessionSelection::where('submission_id', $locked->id)
                    ->whereNull('cancelled_at')->update([
                        'cancelled_at' => now(), 'cancellation_reason' => 'registration_rejected', 'updated_at' => now(),
                    ]);
            }
            $changed = true;
            AuditLogger::record('event.registration_decided', $locked, ['status' => $before], [
                'status' => $decision,
                'has_review_note' => $note !== '',
            ], ['event_id' => $locked->event_id, 'form_id' => $locked->form_id]);

            return $locked;
        }, 3);

        if ($changed) {
            $this->queueStatusNotification($result, $decision);
        }

        return $result;
    }

    private function queueStatusNotification(EventRegistrationSubmission $submission, string $kind): void
    {
        $notification = EventRegistrationNotification::firstOrCreate([
            'submission_id' => $submission->id,
            'kind' => $kind,
        ], [
            'event_id' => $submission->event_id,
            'status' => 'pending',
        ]);

        if (!in_array($notification->status, ['sent', 'suppressed'], true)) {
            app(EventRegistrationNotificationDispatchService::class)
                ->dispatchNotification((int) $notification->id);
        }
    }

    private function validateQuestionSet(iterable $questions, array $answers, string $prefix): array
    {
        $validated = [];
        foreach ($questions as $question) {
            if ($question->type === 'terms') {
                continue;
            }
            if (!$this->conditionMatches($question->condition, $answers)) {
                continue;
            }
            // A visible optional consent must be retained as an explicit No,
            // not silently omitted as though the attendee was never asked.
            $value = $answers[$question->question_key] ?? ($question->type === 'consent' ? false : null);
            $empty = $value === null || $value === '' || $value === [] || $value === false || $value === '0';
            if ($question->is_required && $empty) {
                throw ValidationException::withMessages([
                    $prefix.'.'.$question->question_key => $question->label.' is required.',
                ]);
            }
            if ($empty && $question->type !== 'consent') {
                continue;
            }
            $validated[$question->question_key] = [
                'question' => $question,
                'path' => $prefix.'.'.$question->question_key,
                'value' => $this->validateAnswerValue($question, $value, $prefix.'.'.$question->question_key),
            ];
        }

        return $validated;
    }

    private function validateAnswerValue(EventRegistrationQuestion $question, mixed $value, string $path): mixed
    {
        if ($question->type === 'file') {
            if (!$value instanceof UploadedFile) {
                throw ValidationException::withMessages([$path => 'Upload a PDF, JPG or PNG file.']);
            }
            try {
                $inspection = app(EventRegistrationFileService::class)->inspect($value);
            } catch (DomainException $exception) {
                throw ValidationException::withMessages([$path => $exception->getMessage()]);
            }

            return ['upload' => $value, 'inspection' => $inspection];
        }
        if ($question->type === 'multi_select') {
            $values = array_values(array_unique(array_map('strval', is_array($value) ? $value : [$value])));
            if (array_diff($values, $question->options ?: [])) {
                throw ValidationException::withMessages([$path => 'Choose only the available options.']);
            }

            return $values;
        }
        if (in_array($question->type, ['checkbox', 'consent'], true)) {
            return filter_var($value, FILTER_VALIDATE_BOOL);
        }
        $string = trim((string) $value);
        $settings = $question->settings ?: [];
        $maximum = in_array($question->type, self::LENGTH_RULE_TYPES, true)
            ? (int) ($settings['max_length'] ?? 5000)
            : 5000;
        $minimum = in_array($question->type, self::LENGTH_RULE_TYPES, true)
            ? (int) ($settings['min_length'] ?? 0)
            : 0;
        if (mb_strlen($string) > $maximum) {
            throw ValidationException::withMessages([$path => "This answer cannot exceed {$maximum} characters."]);
        }
        if ($minimum > 0 && mb_strlen($string) < $minimum) {
            throw ValidationException::withMessages([$path => "This answer must contain at least {$minimum} characters."]);
        }
        if ($question->type === 'email' && !filter_var($string, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages([$path => 'Enter a valid email address.']);
        }
        if ($question->type === 'number' && !is_numeric($string)) {
            throw ValidationException::withMessages([$path => 'Enter a valid number.']);
        }
        if ($question->type === 'date' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $string)) {
            throw ValidationException::withMessages([$path => 'Enter a valid date.']);
        }
        if (in_array($question->type, ['select', 'radio'], true) && !in_array($string, $question->options ?: [], true)) {
            throw ValidationException::withMessages([$path => 'Choose one of the available options.']);
        }

        return $question->type === 'number' ? (float) $string : $string;
    }

    private function conditionMatches(?array $condition, array $answers): bool
    {
        if (!$condition || empty($condition['question_key'])) {
            return true;
        }
        $actual = $answers[$condition['question_key']] ?? null;
        $expected = $condition['value'] ?? null;
        if (($condition['operator'] ?? 'equals') === 'not_empty') {
            return !in_array($actual, [null, '', []], true);
        }

        $actualValues = array_map('strval', is_array($actual) ? $actual : [$actual]);
        $expectedValues = array_map('strval', is_array($expected) ? $expected : [$expected]);

        return match ($condition['operator'] ?? 'equals') {
            'not_equals' => array_intersect($actualValues, $expectedValues) === [],
            'in' => array_intersect($actualValues, $expectedValues) !== [],
            default => array_intersect($actualValues, $expectedValues) !== [],
        };
    }

    private function normalizeOptions(mixed $input): array
    {
        $values = is_array($input) ? $input : preg_split('/\r\n|\r|\n/', (string) $input);

        return array_values(array_unique(array_filter(array_map(
            static fn ($value) => mb_substr(trim((string) $value), 0, 150),
            $values ?: []
        ), static fn ($value) => $value !== '')));
    }

    private function normalizeQuestionSettings(string $type, array $data): array
    {
        if (!in_array($type, self::LENGTH_RULE_TYPES, true)) {
            return [];
        }
        $placeholder = trim((string) ($data['placeholder'] ?? ''));
        $minimum = ($data['min_length'] ?? '') !== '' ? (int) $data['min_length'] : null;
        $maximum = ($data['max_length'] ?? '') !== '' ? (int) $data['max_length'] : null;
        if (mb_strlen($placeholder) > 191) {
            throw ValidationException::withMessages(['placeholder' => 'Placeholder text cannot exceed 191 characters.']);
        }
        if ($minimum !== null && ($minimum < 0 || $minimum > 5000)) {
            throw ValidationException::withMessages(['min_length' => 'Minimum length must be between 0 and 5,000.']);
        }
        if ($maximum !== null && ($maximum < 1 || $maximum > 5000)) {
            throw ValidationException::withMessages(['max_length' => 'Maximum length must be between 1 and 5,000.']);
        }
        if ($minimum !== null && $maximum !== null && $minimum > $maximum) {
            throw ValidationException::withMessages(['min_length' => 'Minimum length cannot exceed maximum length.']);
        }

        return array_filter([
            'placeholder' => $placeholder !== '' ? $placeholder : null,
            'min_length' => $minimum,
            'max_length' => $maximum,
        ], static fn ($value): bool => $value !== null);
    }

    private function normalizeCondition(EventRegistrationForm $form, string $scope, ?int $ticketId, array $data): ?array
    {
        $key = trim((string) ($data['condition_question_key'] ?? ''));
        if ($key === '') {
            return null;
        }
        $dependency = EventRegistrationQuestion::where('form_id', $form->id)
            ->where('question_key', $key)->first();
        if (!$dependency || $dependency->scope !== $scope) {
            throw ValidationException::withMessages([
                'condition_question_key' => 'A condition must reference an earlier question with the same scope.',
            ]);
        }
        $dependencyTicketId = (int) ($dependency->ticket_id ?? 0) ?: null;
        if (($ticketId === null && $dependencyTicketId !== null)
            || ($ticketId !== null && $dependencyTicketId !== null && $dependencyTicketId !== $ticketId)) {
            throw ValidationException::withMessages([
                'condition_question_key' => 'A condition cannot reference a question for a different ticket type.',
            ]);
        }
        $operator = (string) ($data['condition_operator'] ?? 'equals');
        if (!in_array($operator, ['equals', 'not_equals', 'not_empty'], true)) {
            throw ValidationException::withMessages(['condition_operator' => 'Choose a supported condition.']);
        }
        if ($dependency->type === 'terms' || ($dependency->type === 'file' && $operator !== 'not_empty')) {
            throw ValidationException::withMessages([
                'condition_operator' => $dependency->type === 'file'
                    ? 'File-upload conditions can only check whether a file was provided.'
                    : 'An information block cannot control another question.',
            ]);
        }

        return [
            'question_key' => $dependency->question_key,
            'operator' => $operator,
            'value' => $this->normalizeConditionValue($dependency, $operator, $data['condition_value'] ?? null),
        ];
    }

    private function normalizeConditionValue(
        EventRegistrationQuestion $dependency,
        string $operator,
        mixed $rawValue
    ): ?string {
        if ($operator === 'not_empty') {
            return null;
        }
        $value = mb_substr(trim((string) $rawValue), 0, 500);
        if (in_array($dependency->type, ['checkbox', 'consent'], true)) {
            $normalized = match (mb_strtolower($value)) {
                '1', 'true', 'yes', 'on', 'accepted' => '1',
                '0', 'false', 'no', 'off', 'declined' => '0',
                default => null,
            };
            if ($normalized === null) {
                throw ValidationException::withMessages([
                    'condition_value' => 'Use Yes or No when a checkbox or consent field controls another question.',
                ]);
            }

            return $normalized;
        }
        if (in_array($dependency->type, ['select', 'radio', 'multi_select'], true)
            && !in_array($value, $dependency->options ?? [], true)) {
            throw ValidationException::withMessages([
                'condition_value' => 'Choose an exact option from the controlling question.',
            ]);
        }
        if ($value === '') {
            throw ValidationException::withMessages([
                'condition_value' => 'Enter the value that should show this question, or choose “Is answered”.',
            ]);
        }

        return $value;
    }

    public function saveTemplate(EventRegistrationForm $form, int $organizerId, string $name, ?string $description = null): EventRegistrationFormTemplate
    {
        $this->assertFormOwner($form, $organizerId);
        $name = trim($name);
        $description = trim((string) $description);
        if ($name === '' || mb_strlen($name) > 150) {
            throw ValidationException::withMessages(['template_name' => 'Enter a template name of 150 characters or fewer.']);
        }
        if (mb_strlen($description) > 1000) {
            throw ValidationException::withMessages(['template_description' => 'Template description cannot exceed 1,000 characters.']);
        }
        $form->loadMissing('questions');
        $definition = [
            'approval_mode' => $form->approval_mode,
            'intro' => $form->intro,
            'privacy_notice' => $form->privacy_notice,
            'questions' => $form->questions->map(fn (EventRegistrationQuestion $question): array => [
                'question_key' => $question->question_key, 'label' => $question->label,
                'help_text' => $question->help_text, 'type' => $question->type, 'scope' => $question->scope,
                'is_required' => $question->is_required, 'options' => $question->options,
                'condition' => $question->condition, 'settings' => $question->settings,
                'position' => $question->position,
            ])->values()->all(),
        ];

        return DB::transaction(function () use ($organizerId, $name, $description, $definition, $form): EventRegistrationFormTemplate {
            $template = EventRegistrationFormTemplate::query()
                ->where('organizer_id', $organizerId)->where('name', $name)->lockForUpdate()->first();
            $before = $template?->only(['name', 'version']) ?: [];
            $template ??= new EventRegistrationFormTemplate([
                'public_id' => (string) Str::ulid(), 'organizer_id' => $organizerId, 'name' => $name, 'version' => 0,
            ]);
            $template->forceFill(['description' => $description !== '' ? $description : null,
                'definition' => $definition, 'version' => (int) $template->version + 1])->save();
            AuditLogger::record('event.registration_template_saved', $template, $before,
                ['name' => $template->name, 'version' => $template->version, 'question_count' => count($definition['questions'])],
                ['source_form_id' => $form->id]);
            return $template->fresh();
        }, 3);
    }

    public function applyTemplate(EventRegistrationForm $form, EventRegistrationFormTemplate $template, int $organizerId): EventRegistrationForm
    {
        $this->assertFormOwner($form, $organizerId);
        if ((int) $template->organizer_id !== $organizerId) {
            throw new AuthorizationException('You cannot use another organizer’s registration template.');
        }
        $definition = $template->definition ?: [];
        $questions = is_array($definition['questions'] ?? null) ? $definition['questions'] : [];
        if (count($questions) > (int) config('registration.max_questions_per_form')) {
            throw ValidationException::withMessages(['template' => 'This template exceeds the form question limit.']);
        }

        return DB::transaction(function () use ($form, $template, $definition, $questions): EventRegistrationForm {
            $locked = EventRegistrationForm::whereKey($form->id)->lockForUpdate()->firstOrFail();
            $before = $locked->only(['status', 'version']);
            $this->returnPublishedFormToDraft($locked);
            EventRegistrationQuestion::where('form_id', $locked->id)->delete();
            foreach ($questions as $index => $question) {
                $attributes = [
                    'public_id' => (string) Str::ulid(), 'form_id' => $locked->id,
                    'question_key' => (string) $question['question_key'], 'label' => (string) $question['label'],
                    'help_text' => $question['help_text'] ?? null, 'type' => (string) $question['type'],
                    'scope' => (string) $question['scope'], 'is_required' => !empty($question['is_required']),
                    'options' => $question['options'] ?? null, 'condition' => $question['condition'] ?? null,
                    'position' => $index + 1,
                ];
                if (Schema::hasColumn('event_registration_questions', 'settings')) {
                    $attributes['settings'] = $question['settings'] ?? null;
                }
                EventRegistrationQuestion::create($attributes);
            }
            $locked->forceFill([
                'approval_mode' => in_array($definition['approval_mode'] ?? null, ['manual', 'automatic'], true)
                    ? $definition['approval_mode'] : 'manual',
                'intro' => $definition['intro'] ?? null,
                'privacy_notice' => trim((string) ($definition['privacy_notice'] ?? '')) !== ''
                    ? $definition['privacy_notice'] : $locked->privacy_notice,
            ])->save();
            AuditLogger::record('event.registration_template_applied', $locked, $before,
                ['status' => $locked->status, 'version' => $locked->version, 'question_count' => count($questions)],
                ['template_id' => $template->id, 'template_version' => $template->version]);
            return $locked->fresh('questions');
        }, 3);
    }

    private function persistAnswers(EventRegistrationSubmission $submission, ?EventRegistrationAttendee $attendee, array $answers): void
    {
        foreach ($answers as $answer) {
            $question = $answer['question'];
            $value = $answer['value'];
            $storedFile = $question->type === 'file' ? $value['stored'] : null;
            $persistedValue = $storedFile ? [
                'file_public_id' => $storedFile['public_id'],
                'original_name' => $storedFile['original_name'],
                'mime_type' => $storedFile['mime_type'],
                'size' => $storedFile['size'],
            ] : $value;
            $persistedAnswer = EventRegistrationAnswer::create([
                'submission_id' => $submission->id,
                'attendee_id' => $attendee?->id,
                'question_id' => $question->id,
                'question_key' => $question->question_key,
                'question_label' => $question->label,
                'question_type' => $question->type,
                'value' => ['value' => $persistedValue],
            ]);
            if ($storedFile) {
                EventRegistrationFile::create([
                    'public_id' => $storedFile['public_id'],
                    'answer_id' => $persistedAnswer->id,
                    'submission_id' => $submission->id,
                    'attendee_id' => $attendee?->id,
                    'question_id' => $question->id,
                    'disk' => $storedFile['disk'],
                    'path' => $storedFile['path'],
                    'original_name' => $storedFile['original_name'],
                    'mime_type' => $storedFile['mime_type'],
                    'size' => $storedFile['size'],
                    'sha256' => $storedFile['sha256'],
                ]);
            }
        }
    }

    private function storeValidatedFiles(array &$answers, int $eventId, array &$storedFiles): void
    {
        foreach ($answers as &$answer) {
            if (($answer['question']->type ?? null) !== 'file') {
                continue;
            }
            try {
                $stored = app(EventRegistrationFileService::class)->store(
                    $answer['value']['upload'],
                    $answer['value']['inspection'],
                    $eventId,
                );
            } catch (DomainException $exception) {
                throw ValidationException::withMessages([
                    $answer['path'] => $exception->getMessage(),
                ]);
            }
            $storedFiles[] = $stored;
            $answer['value'] = ['stored' => $stored];
        }
        unset($answer);
    }

    private function assertUploadBudget(array $registrantAnswers, array $attendees): void
    {
        $answers = collect($registrantAnswers)->merge(
            collect($attendees)->flatMap(fn (array $attendee): array => array_values($attendee['answers']))
        );
        $files = $answers->filter(fn (array $answer): bool => ($answer['question']->type ?? null) === 'file');
        $maximumFiles = (int) config('registration.upload_max_files_per_submission', 20);
        if ($files->count() > $maximumFiles) {
            throw ValidationException::withMessages([
                'files' => "Upload no more than {$maximumFiles} files in one registration.",
            ]);
        }
        $totalBytes = $files->sum(fn (array $answer): int => (int) ($answer['value']['inspection']['size'] ?? 0));
        $maximumBytes = (int) config('registration.upload_max_total_kb', 20480) * 1024;
        if ($totalBytes > $maximumBytes) {
            throw ValidationException::withMessages([
                'files' => 'The combined registration uploads exceed the permitted total size.',
            ]);
        }
    }

    private function returnPublishedFormToDraft(EventRegistrationForm $form): void
    {
        if ($form->exists && $form->status === 'published') {
            $form->status = 'draft';
            $form->version = (int) $form->version + 1;
            $form->published_at = null;
        }
    }

    private function contactHash(string $value): string
    {
        return hash_hmac('sha256', mb_strtolower(trim($value)), (string) config('app.key'));
    }

    private function assertEventOwner(Event $event, int $organizerId): void
    {
        if ((int) $event->organizer_id !== $organizerId) {
            throw new AuthorizationException('You cannot manage another organizer’s registration form.');
        }
    }

    private function assertFormOwner(EventRegistrationForm $form, int $organizerId): void
    {
        if ((int) $form->organizer_id !== $organizerId) {
            throw new AuthorizationException('You cannot manage another organizer’s registration form.');
        }
    }
}
