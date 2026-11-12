<?php

namespace Tests\Unit;

use App\Jobs\SendEventRegistrationNotification;
use App\Mail\EventRegistrationStatusMail;
use App\Models\Event;
use App\Models\EventRegistrationAnswer;
use App\Models\EventRegistrationForm;
use App\Models\EventRegistrationFormTemplate;
use App\Models\EventRegistrationFile;
use App\Models\EventRegistrationNotification;
use App\Models\EventRegistrationSubmission;
use App\Models\EventRegistrationSession;
use App\Services\EventRegistrationService;
use App\Services\EventRegistrationNotificationDispatchService;
use App\Services\EventRegistrationSessionService;
use App\Services\EventRegistrationInvoiceService;
use App\Services\EventRegistrationBadgeService;
use App\Services\EventRegistrationEmbedService;
use App\Models\Event\Ticket;
use App\Models\Organizer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EventRegistrationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        config()->set('registration.consent_version', 'registration-test-v1');
        config()->set('registration.max_attendees_per_submission', 5);
        config()->set('registration.max_questions_per_form', 10);
        config()->set('registration.upload_disk', 'local');
        config()->set('registration.upload_max_file_kb', 5120);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->createSchema();
        Queue::fake();
        Storage::fake('local');
    }

    public function test_form_builder_is_owner_scoped_versioned_and_publishable(): void
    {
        $service = app(EventRegistrationService::class);
        $event = $this->event(41);

        $form = $service->saveForm($event, 41, [
            'name' => 'Delegate registration',
            'approval_mode' => 'manual',
            'intro' => 'Register your delegation.',
            'privacy_notice' => 'We use these details only to administer the conference.',
        ]);
        $this->assertSame('corporate', $event->fresh()->registration_mode);
        $this->assertSame('draft', $form->status);

        $attendanceType = $service->addQuestion($form, 41, [
            'label' => 'Attendance type',
            'question_key' => 'attendance_type',
            'type' => 'select',
            'scope' => 'attendee',
            'is_required' => true,
            'options' => "In person\nVirtual",
        ]);
        $service->addQuestion($form, 41, [
            'label' => 'Accessibility request',
            'question_key' => 'accessibility_request',
            'type' => 'textarea',
            'scope' => 'attendee',
            'condition_question_key' => $attendanceType->question_key,
            'condition_operator' => 'equals',
            'condition_value' => 'In person',
        ]);
        $published = $service->publish($form->fresh(), 41);
        $this->assertSame('published', $published->status);
        $this->assertNotNull($published->published_at);

        $service->addQuestion($published, 41, [
            'label' => 'Company',
            'type' => 'text',
            'scope' => 'registrant',
        ]);
        $changed = $published->fresh();
        $this->assertSame('draft', $changed->status);
        $this->assertSame(2, $changed->version);
        $this->assertDatabaseHas('audit_logs', ['action' => 'event.registration_form_published']);

        $this->expectException(AuthorizationException::class);
        $service->publish($changed, 99);
    }

    public function test_submission_validates_conditions_encrypts_pii_and_snapshots_questions(): void
    {
        $service = app(EventRegistrationService::class);
        [$event, $form] = $this->publishedForm($service, 17, 'manual');
        $service->addQuestion($form, 17, [
            'label' => 'Organisation',
            'question_key' => 'organisation',
            'type' => 'text',
            'scope' => 'registrant',
            'is_required' => true,
        ]);
        $meal = $service->addQuestion($form->fresh(), 17, [
            'label' => 'Meal preference',
            'question_key' => 'meal',
            'type' => 'select',
            'scope' => 'attendee',
            'is_required' => true,
            'options' => ['Standard', 'Special diet'],
        ]);
        $service->addQuestion($form->fresh(), 17, [
            'label' => 'Diet details',
            'question_key' => 'diet_details',
            'type' => 'text',
            'scope' => 'attendee',
            'is_required' => true,
            'condition_question_key' => $meal->question_key,
            'condition_operator' => 'equals',
            'condition_value' => 'Special diet',
        ]);
        $form = $service->publish($form->fresh(), 17);

        try {
            $service->submit($event->fresh(), [
                'registrant' => ['name' => 'Ada Manager', 'email' => 'ADA@example.com'],
                'answers' => ['organisation' => 'Northwind'],
                'attendees' => [[
                    'name' => 'Grace Delegate',
                    'email' => 'grace@example.com',
                    'answers' => ['meal' => 'Special diet'],
                ]],
                'consent' => true,
            ], null, '192.0.2.12');
            $this->fail('A required conditional answer should be enforced.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('attendees.0.answers.diet_details', $exception->errors());
        }

        $submission = $service->submit($event->fresh(), [
            'registrant' => ['name' => 'Ada Manager', 'email' => 'ADA@example.com'],
            'answers' => ['organisation' => 'Northwind'],
            'attendees' => [
                [
                    'name' => 'Grace Delegate',
                    'email' => 'grace@example.com',
                    'answers' => ['meal' => 'Special diet', 'diet_details' => 'No dairy'],
                ],
                [
                    'name' => 'Lin Delegate',
                    'email' => '',
                    'answers' => ['meal' => 'Standard'],
                ],
            ],
            'consent' => true,
        ], null, '192.0.2.12');

        $this->assertSame('submitted', $submission->status);
        $this->assertSame(2, $submission->attendee_count);
        $this->assertSame('ada@example.com', $submission->primary_email);
        $this->assertCount(2, $submission->attendees);
        $rawSubmission = DB::table('event_registration_submissions')->where('id', $submission->id)->first();
        $rawAttendee = DB::table('event_registration_attendees')->where('submission_id', $submission->id)->first();
        $rawAnswer = DB::table('event_registration_answers')->where('submission_id', $submission->id)->first();
        $this->assertNotSame('Ada Manager', $rawSubmission->primary_name);
        $this->assertNotSame('ada@example.com', $rawSubmission->primary_email);
        $this->assertNotSame('Grace Delegate', $rawAttendee->name);
        $this->assertStringNotContainsString('Northwind', (string) $rawAnswer->value);
        $this->assertSame(hash_hmac('sha256', 'ada@example.com', (string) config('app.key')), $rawSubmission->primary_email_hash);
        $this->assertDatabaseHas('event_registration_answers', [
            'submission_id' => $submission->id,
            'question_key' => 'organisation',
            'question_label' => 'Organisation',
            'question_type' => 'text',
        ]);
        $this->assertSame('Northwind', EventRegistrationAnswer::where('question_key', 'organisation')->first()->value['value']);
        $this->assertDatabaseHas('event_registration_notifications', [
            'submission_id' => $submission->id,
            'kind' => 'submitted',
            'status' => 'pending',
        ]);
        Queue::assertPushed(SendEventRegistrationNotification::class);
    }

    public function test_extended_form_fields_enforce_radio_and_character_rules_without_storing_terms_as_answers(): void
    {
        $service = app(EventRegistrationService::class);
        [$event, $form] = $this->publishedForm($service, 18, 'manual');
        $company = $service->addQuestion($form, 18, [
            'label' => 'Company', 'question_key' => 'company', 'type' => 'company',
            'scope' => 'registrant', 'is_required' => true, 'placeholder' => 'Organisation name',
            'min_length' => 3, 'max_length' => 40,
        ]);
        $attendance = $service->addQuestion($form->fresh(), 18, [
            'label' => 'Attendance format', 'question_key' => 'attendance_format', 'type' => 'radio',
            'scope' => 'registrant', 'is_required' => true, 'options' => "In person\nVirtual",
        ]);
        $terms = $service->addQuestion($form->fresh(), 18, [
            'label' => 'Participation terms', 'question_key' => 'participation_terms', 'type' => 'terms',
            'scope' => 'attendee', 'ticket_id' => null, 'is_required' => true,
            'help_text' => 'Bring a valid photo ID. Admission remains subject to the event code of conduct.',
        ]);
        $service->publish($form->fresh(), 18);

        $this->assertSame('Organisation name', $company->settings['placeholder']);
        $this->assertSame(3, $company->settings['min_length']);
        $this->assertSame(40, $company->settings['max_length']);
        $this->assertSame(['In person', 'Virtual'], $attendance->options);
        $this->assertSame('registrant', $terms->scope);
        $this->assertFalse($terms->is_required);

        $invalid = $this->submissionPayload();
        $invalid['answers'] = ['company' => 'AB', 'attendance_format' => 'Hybrid'];
        try {
            $service->submit($event, $invalid);
            $this->fail('The server must enforce field length and radio-option rules.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('answers.company', $exception->errors());
        }

        $invalidRadio = $this->submissionPayload();
        $invalidRadio['answers'] = ['company' => 'Tica Limited', 'attendance_format' => 'Hybrid'];
        try {
            $service->submit($event, $invalidRadio);
            $this->fail('Radio answers must be one of the configured options.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('answers.attendance_format', $exception->errors());
        }

        $payload = $this->submissionPayload();
        $payload['answers'] = ['company' => 'Tica Limited', 'attendance_format' => 'In person'];
        $submission = $service->submit($event, $payload);

        $this->assertDatabaseHas('event_registration_answers', [
            'submission_id' => $submission->id, 'question_key' => 'company',
        ]);
        $this->assertDatabaseHas('event_registration_answers', [
            'submission_id' => $submission->id, 'question_key' => 'attendance_format',
        ]);
        $this->assertDatabaseMissing('event_registration_answers', [
            'submission_id' => $submission->id, 'question_key' => 'participation_terms',
        ]);
    }

    public function test_organizer_consent_question_persists_explicit_yes_and_no_answers(): void
    {
        $service = app(EventRegistrationService::class);
        [$event, $form] = $this->publishedForm($service, 19, 'manual');
        $service->addQuestion($form, 19, [
            'label' => 'May the organizer contact you about future events?',
            'question_key' => 'organizer_contact_consent',
            'type' => 'consent',
            'scope' => 'attendee',
            'is_required' => false,
            'help_text' => 'Optional. You can withdraw this permission at any time.',
        ]);
        $service->publish($form->fresh(), 19);

        $declinedPayload = $this->submissionPayload();
        $declined = $service->submit($event->fresh(), $declinedPayload, null, '192.0.2.19');
        $declinedAnswer = EventRegistrationAnswer::query()
            ->where('submission_id', $declined->id)
            ->where('question_key', 'organizer_contact_consent')
            ->firstOrFail();

        $this->assertFalse($declinedAnswer->value['value']);
        $this->assertSame('consent', $declinedAnswer->question_type);

        $acceptedPayload = $this->submissionPayload();
        $acceptedPayload['attendees'][0]['answers']['organizer_contact_consent'] = '1';
        $accepted = $service->submit($event->fresh(), $acceptedPayload, null, '192.0.2.20');
        $acceptedAnswer = EventRegistrationAnswer::query()
            ->where('submission_id', $accepted->id)
            ->where('question_key', 'organizer_contact_consent')
            ->firstOrFail();

        $this->assertTrue($acceptedAnswer->value['value']);
    }

    public function test_question_order_is_owner_scoped_transactional_and_preserves_condition_dependencies(): void
    {
        $service = app(EventRegistrationService::class);
        [$event, $form] = $this->publishedForm($service, 20, 'manual');
        $controller = $service->addQuestion($form, 20, [
            'label' => 'Attendance format', 'question_key' => 'attendance_format',
            'type' => 'select', 'scope' => 'registrant', 'options' => "In person\nVirtual",
        ]);
        $dependent = $service->addQuestion($form->fresh(), 20, [
            'label' => 'Dietary requirements', 'question_key' => 'dietary_requirements',
            'type' => 'text', 'scope' => 'registrant',
            'condition_question_key' => 'attendance_format', 'condition_operator' => 'equals',
            'condition_value' => 'In person',
        ]);
        $notes = $service->addQuestion($form->fresh(), 20, [
            'label' => 'Additional notes', 'question_key' => 'additional_notes',
            'type' => 'textarea', 'scope' => 'registrant',
        ]);
        $published = $service->publish($form->fresh(), 20);

        try {
            $service->reorderQuestions($published, 21, [
                $notes->public_id, $controller->public_id, $dependent->public_id,
            ]);
            $this->fail('Another organizer must not reorder this form.');
        } catch (AuthorizationException) {
            $this->assertSame('published', $published->fresh()->status);
        }

        $reordered = $service->reorderQuestions($published, 20, [
            $notes->public_id, $controller->public_id, $dependent->public_id,
        ]);
        $this->assertSame('draft', $reordered->status);
        $this->assertSame(
            ['additional_notes', 'attendance_format', 'dietary_requirements'],
            $reordered->questions->pluck('question_key')->all()
        );
        $this->assertSame([1, 2, 3], $reordered->questions->pluck('position')->all());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'event.registration_questions_reordered', 'subject_id' => $form->id,
        ]);

        try {
            $service->reorderQuestions($reordered, 20, [
                $dependent->public_id, $controller->public_id, $notes->public_id,
            ]);
            $this->fail('A conditional field must not move before its controller.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('ordered_questions', $exception->errors());
        }
        $this->assertSame(
            ['additional_notes', 'attendance_format', 'dietary_requirements'],
            $form->fresh('questions')->questions->pluck('question_key')->all()
        );
    }

    public function test_question_editing_is_owner_scoped_versioned_and_preserves_dependencies_and_history(): void
    {
        $service = app(EventRegistrationService::class);
        [$event, $form] = $this->publishedForm($service, 22, 'manual');
        $controller = $service->addQuestion($form, 22, [
            'label' => 'Attendance format', 'question_key' => 'attendance_format',
            'type' => 'select', 'scope' => 'registrant', 'is_required' => true,
            'options' => "In person\nVirtual",
        ]);
        $dependent = $service->addQuestion($form->fresh(), 22, [
            'label' => 'Dietary requirements', 'question_key' => 'dietary_requirements',
            'type' => 'text', 'scope' => 'registrant',
            'condition_question_key' => 'attendance_format', 'condition_operator' => 'equals',
            'condition_value' => 'In person',
        ]);
        $published = $service->publish($form->fresh(), 22);
        $payload = $this->submissionPayload();
        $payload['answers'] = ['attendance_format' => 'In person', 'dietary_requirements' => 'Vegetarian'];
        $submission = $service->submit($event->fresh(), $payload);

        $edit = [
            'label' => 'How will you attend?', 'question_key' => 'attendance_mode',
            'type' => 'select', 'scope' => 'registrant', 'is_required' => true,
            'options' => "In person\nVirtual\nHybrid",
        ];
        try {
            $service->updateQuestion($controller, 23, $edit);
            $this->fail('Another organizer must not edit this question.');
        } catch (AuthorizationException) {
            $this->assertSame('published', $published->fresh()->status);
        }

        $updated = $service->updateQuestion($controller, 22, $edit);
        $this->assertSame('attendance_mode', $updated->question_key);
        $this->assertSame('How will you attend?', $updated->label);
        $this->assertSame(['In person', 'Virtual', 'Hybrid'], $updated->options);
        $this->assertSame('draft', $published->fresh()->status);
        $this->assertSame('attendance_mode', $dependent->fresh()->condition['question_key']);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'event.registration_question_updated', 'subject_id' => $controller->id,
        ]);
        $historical = EventRegistrationAnswer::query()->where('submission_id', $submission->id)
            ->where('question_key', 'attendance_format')->firstOrFail();
        $this->assertSame('Attendance format', $historical->question_label);
        $this->assertSame('In person', $historical->value['value']);

        try {
            $service->updateQuestion($updated, 22, array_merge($edit, ['options' => "Virtual\nHybrid"]));
            $this->fail('An option used by a dependent condition must not disappear silently.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('options', $exception->errors());
        }
        $this->assertSame(['In person', 'Virtual', 'Hybrid'], $updated->fresh()->options);

        $republished = $service->publish($published->fresh(), 22);
        $version = $republished->version;
        $same = $service->updateQuestion($updated->fresh(), 22, $edit);
        $this->assertSame('published', $republished->fresh()->status);
        $this->assertSame($version, $republished->fresh()->version);
        $this->assertSame('attendance_mode', $same->question_key);
    }

    public function test_conditional_rules_reject_impossible_options_and_normalize_boolean_values(): void
    {
        $service = app(EventRegistrationService::class);
        [$event, $form] = $this->publishedForm($service, 24, 'manual');
        $service->addQuestion($form, 24, [
            'label' => 'Attendance format', 'question_key' => 'attendance_format',
            'type' => 'select', 'scope' => 'registrant', 'options' => "In person\nVirtual",
        ]);
        try {
            $service->addQuestion($form->fresh(), 24, [
                'label' => 'Travel details', 'question_key' => 'travel_details',
                'type' => 'text', 'scope' => 'registrant',
                'condition_question_key' => 'attendance_format', 'condition_operator' => 'equals',
                'condition_value' => 'Hybrid',
            ]);
            $this->fail('A conditional rule must not accept an impossible selection value.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('condition_value', $exception->errors());
        }

        $service->addQuestion($form->fresh(), 24, [
            'label' => 'May we contact you?', 'question_key' => 'contact_consent',
            'type' => 'consent', 'scope' => 'registrant',
        ]);
        $followUp = $service->addQuestion($form->fresh(), 24, [
            'label' => 'Preferred contact time', 'question_key' => 'preferred_contact_time',
            'type' => 'text', 'scope' => 'registrant', 'is_required' => true,
            'condition_question_key' => 'contact_consent', 'condition_operator' => 'equals',
            'condition_value' => 'Yes',
        ]);
        $this->assertSame('1', $followUp->condition['value']);
        $service->publish($form->fresh(), 24);

        $declined = $this->submissionPayload();
        $declined['answers'] = ['attendance_format' => 'Virtual'];
        $service->submit($event->fresh(), $declined);

        $accepted = $this->submissionPayload();
        $accepted['answers'] = ['attendance_format' => 'Virtual', 'contact_consent' => '1'];
        try {
            $service->submit($event->fresh(), $accepted);
            $this->fail('A normalized accepted consent condition must reveal and require its dependent field.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('answers.preferred_contact_time', $exception->errors());
        }
    }

    public function test_manual_decisions_are_owner_scoped_reasoned_and_idempotent(): void
    {
        $service = app(EventRegistrationService::class);
        [$event] = $this->publishedForm($service, 23, 'manual');
        $submission = $service->submit($event->fresh(), $this->submissionPayload(), null, '192.0.2.25');

        try {
            $service->decide($submission, 24, 'approved', null);
            $this->fail('A different organizer should not review the registration.');
        } catch (AuthorizationException) {
            $this->assertSame('submitted', $submission->fresh()->status);
        }

        try {
            $service->decide($submission, 23, 'rejected', null);
            $this->fail('A rejection must include a reason.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('note', $exception->errors());
        }

        $approved = $service->decide($submission, 23, 'approved', 'Identity verified.');
        $same = $service->decide($approved, 23, 'approved', 'This must not overwrite the first review.');
        $this->assertSame('approved', $same->status);
        $this->assertSame('Identity verified.', $same->review_note);
        $this->assertNotNull($same->reviewed_at);
        $this->assertDatabaseHas('event_registration_notifications', [
            'submission_id' => $submission->id,
            'kind' => 'approved',
            'status' => 'pending',
        ]);
    }

    public function test_automatic_approval_and_ticket_checkout_guard_are_explicit(): void
    {
        $service = app(EventRegistrationService::class);
        [$event] = $this->publishedForm($service, 31, 'automatic');
        $submission = $service->submit($event->fresh(), $this->submissionPayload(), null, '192.0.2.31');

        $this->assertSame('approved', $submission->status);
        $this->assertFalse($event->fresh()->isBookable());
        $this->assertDatabaseHas('event_registration_notifications', [
            'submission_id' => $submission->id,
            'kind' => 'approved',
        ]);
    }

    public function test_registration_status_notification_is_delivered_once_from_durable_ledger(): void
    {
        Mail::fake();
        $service = app(EventRegistrationService::class);
        [$event] = $this->publishedForm($service, 44, 'automatic');
        $submission = $service->submit($event->fresh(), $this->submissionPayload(), null, '192.0.2.44');
        $notification = EventRegistrationNotification::where('submission_id', $submission->id)->firstOrFail();
        EventRegistrationAnswer::create([
            'submission_id' => $submission->id,
            'attendee_id' => $submission->attendees->first()->id,
            'question_id' => 991,
            'question_key' => 'contact_consent',
            'question_label' => 'Organizer contact consent',
            'question_type' => 'consent',
            'value' => ['value' => false],
        ]);

        $job = new SendEventRegistrationNotification($notification->id);
        $job->handle();
        $job->handle();

        $this->assertSame('sent', $notification->fresh()->status);
        $this->assertSame(1, $notification->fresh()->attempts);
        $this->assertNull($notification->fresh()->processing_at);
        $this->assertNotNull($notification->fresh()->sent_at);
        Mail::assertSent(EventRegistrationStatusMail::class, 1);
        Mail::assertSent(EventRegistrationStatusMail::class, function (EventRegistrationStatusMail $mail) use ($submission): bool {
            return $mail->hasTo('contact@example.com')
                && $mail->registrationReference === $submission->public_id
                && $mail->statusLabel === 'Registration approved'
                && collect($mail->answerSummary)->flatMap(fn (array $section): array => $section['answers'])
                    ->contains(fn (array $answer): bool => $answer['label'] === 'Organizer contact consent'
                        && $answer['value'] === 'No');
        });
    }

    public function test_failed_registration_notification_handoff_is_durable_and_recovered(): void
    {
        $notification = EventRegistrationNotification::create([
            'submission_id' => 81,
            'event_id' => 91,
            'kind' => 'approved',
            'status' => 'pending',
        ]);
        $failing = new class extends EventRegistrationNotificationDispatchService {
            protected function pushNotificationJob(int $notificationId): void
            {
                throw new \RuntimeException('queue unavailable');
            }
        };

        $this->assertFalse($failing->dispatchNotification((int) $notification->id));
        $notification->refresh();
        $this->assertSame('pending', $notification->status);
        $this->assertSame(1, $notification->dispatch_attempts);
        $this->assertSame('queue unavailable', $notification->dispatch_last_error);

        $this->artisan('registrations:dispatch-notifications')
            ->expectsOutputToContain('Registration notification dispatch attempted: 1 | failed: 0 | alerted: 0')
            ->assertSuccessful();

        $notification->refresh();
        $this->assertSame(2, $notification->dispatch_attempts);
        $this->assertNull($notification->dispatch_last_error);
        Queue::assertPushed(SendEventRegistrationNotification::class, function ($job) use ($notification): bool {
            return $job->notificationId === $notification->id;
        });
    }

    public function test_ticket_scoped_questions_and_reusable_templates_are_owner_scoped(): void
    {
        $service = app(EventRegistrationService::class);
        $event = $this->event(55);
        $delegate = Ticket::create(['event_id' => $event->id, 'title' => 'Delegate', 'pricing_type' => 'free']);
        $speaker = Ticket::create(['event_id' => $event->id, 'title' => 'Speaker', 'pricing_type' => 'free']);
        $form = $service->saveForm($event, 55, [
            'name' => 'Conference roles', 'approval_mode' => 'manual',
            'intro' => 'Choose the right registration type.',
            'privacy_notice' => 'Used only to administer this conference.',
        ]);
        $companyQuestion = $service->addQuestion($form, 55, [
            'label' => 'Company', 'question_key' => 'company', 'type' => 'text',
            'scope' => 'attendee', 'is_required' => true,
            'placeholder' => 'Organisation', 'min_length' => 2, 'max_length' => 100,
        ]);
        $this->assertSame([
            'placeholder' => 'Organisation',
            'min_length' => 2,
            'max_length' => 100,
        ], $companyQuestion->settings);
        $service->addQuestion($form->fresh(), 55, [
            'label' => 'Talk title', 'question_key' => 'talk_title', 'type' => 'text',
            'scope' => 'attendee', 'ticket_id' => $speaker->id, 'is_required' => true,
        ]);
        $published = $service->publish($form->fresh(), 55);

        $submission = $service->submit($event->fresh(), [
            'registrant' => ['name' => 'Conference Lead', 'email' => 'lead@example.com'],
            'answers' => [], 'consent' => true,
            'attendees' => [[
                'name' => 'Speaker One', 'email' => 'speaker@example.com', 'ticket_id' => $speaker->id,
                'answers' => ['company' => 'Tica', 'talk_title' => 'Reliable Events'],
            ], [
                'name' => 'Delegate One', 'email' => 'delegate@example.com', 'ticket_id' => $delegate->id,
                'answers' => ['company' => 'Northwind'],
            ]],
        ]);
        $this->assertSame($speaker->id, $submission->attendees[0]->ticket_id);
        $this->assertSame($delegate->id, $submission->attendees[1]->ticket_id);
        $this->assertSame(1, EventRegistrationAnswer::where('question_key', 'talk_title')->count());

        $template = $service->saveTemplate($published, 55, 'Conference template', 'Reusable attendee questions.');
        $this->assertSame(1, $template->version);
        $this->assertCount(2, $template->definition['questions']);
        $this->assertSame('Organisation', $template->definition['questions'][0]['settings']['placeholder']);

        $otherEvent = $this->event(55);
        $otherForm = $service->saveForm($otherEvent, 55, [
            'name' => 'New event form', 'approval_mode' => 'automatic', 'intro' => null,
            'privacy_notice' => 'New event privacy notice.',
        ]);
        $applied = $service->applyTemplate($otherForm, $template, 55);
        $this->assertCount(2, $applied->questions);
        $this->assertTrue($applied->questions->every(fn ($question) => $question->ticket_id === null));
        $this->assertSame([
            'placeholder' => 'Organisation',
            'min_length' => 2,
            'max_length' => 100,
        ], $applied->questions->firstWhere('question_key', 'company')->settings);
        $this->assertDatabaseHas('audit_logs', ['action' => 'event.registration_template_applied']);

        $this->expectException(AuthorizationException::class);
        $service->applyTemplate($applied, EventRegistrationFormTemplate::findOrFail($template->id), 99);
    }

    public function test_file_question_uses_private_validated_storage_and_encrypted_metadata(): void
    {
        $service = app(EventRegistrationService::class);
        [$event, $form] = $this->publishedForm($service, 73, 'manual');
        $service->addQuestion($form, 73, [
            'label' => 'Company authorization letter',
            'question_key' => 'authorization_letter',
            'type' => 'file',
            'scope' => 'registrant',
            'is_required' => true,
        ]);
        $service->addQuestion($form->fresh(), 73, [
            'label' => 'Supporting document',
            'question_key' => 'supporting_document',
            'type' => 'file',
            'scope' => 'registrant',
        ]);
        $service->addQuestion($form->fresh(), 73, [
            'label' => 'Document note',
            'question_key' => 'document_note',
            'type' => 'text',
            'scope' => 'registrant',
            'condition_question_key' => 'authorization_letter',
            'condition_operator' => 'not_empty',
        ]);
        $service->publish($form->fresh(), 73);

        $payload = $this->submissionPayload();
        $payload['answers']['authorization_letter'] = UploadedFile::fake()->createWithContent(
            'authorization.pdf', "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n%%EOF"
        );
        $payload['answers']['document_note'] = 'Board-approved letter';
        $submission = $service->submit($event->fresh(), $payload);

        $stored = EventRegistrationFile::where('submission_id', $submission->id)->firstOrFail();
        $this->assertSame('application/pdf', $stored->mime_type);
        $this->assertSame('authorization.pdf', $stored->original_name);
        Storage::disk('local')->assertExists($stored->path);
        $rawFile = DB::table('event_registration_files')->where('id', $stored->id)->first();
        $this->assertStringNotContainsString('authorization.pdf', (string) $rawFile->original_name);
        $rawAnswer = DB::table('event_registration_answers')->where('id', $stored->answer_id)->first();
        $this->assertStringNotContainsString($stored->path, (string) $rawAnswer->value);

        config()->set('registration.upload_max_files_per_submission', 1);
        $overBudgetPayload = $this->submissionPayload();
        $overBudgetPayload['answers']['authorization_letter'] = UploadedFile::fake()->createWithContent(
            'authorization.pdf', "%PDF-1.4\n%%EOF"
        );
        $overBudgetPayload['answers']['supporting_document'] = UploadedFile::fake()->createWithContent(
            'support.pdf', "%PDF-1.4\n%%EOF"
        );
        try {
            $service->submit($event->fresh(), $overBudgetPayload);
            $this->fail('A submission upload count must be bounded.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('files', $exception->errors());
        }

        $invalidPayload = $this->submissionPayload();
        $invalidPayload['answers']['authorization_letter'] = UploadedFile::fake()->createWithContent(
            'unsafe.pdf', "%PDF-1.4\n/JavaScript (alert)\n%%EOF"
        );
        try {
            $service->submit($event->fresh(), $invalidPayload);
            $this->fail('Active PDF content must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('answers.authorization_letter', $exception->errors());
        }
    }

    public function test_agenda_sessions_enforce_required_choices_registration_types_and_capacity(): void
    {
        $registrations = app(EventRegistrationService::class);
        $agenda = app(EventRegistrationSessionService::class);
        [$event, $form] = $this->publishedForm($registrations, 66, 'manual');
        $delegate = Ticket::create(['event_id' => $event->id, 'title' => 'Delegate', 'pricing_type' => 'free']);
        $speaker = Ticket::create(['event_id' => $event->id, 'title' => 'Speaker', 'pricing_type' => 'free']);
        $plenary = $agenda->save($form, 66, [
            'title' => 'Opening plenary', 'starts_at' => now()->addMonth()->setTime(10, 0),
            'ends_at' => now()->addMonth()->setTime(11, 0), 'capacity' => 1, 'is_required' => true,
        ]);
        $speakerRoom = $agenda->save($form, 66, [
            'title' => 'Speaker briefing', 'starts_at' => now()->addMonth()->setTime(11, 0),
            'ends_at' => now()->addMonth()->setTime(12, 0), 'capacity' => 10, 'ticket_id' => $speaker->id,
        ]);

        $payload = $this->submissionPayload();
        $payload['attendees'][0]['session_ids'] = [$plenary->id];
        try {
            $registrations->submit($event, $payload);
            $this->fail('A ticket-scoped agenda must require a registration type.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('attendees.0.ticket_id', $exception->errors());
        }
        $payload['attendees'][0]['ticket_id'] = $delegate->id;
        unset($payload['attendees'][0]['session_ids']);
        try {
            $registrations->submit($event, $payload);
            $this->fail('Required sessions must be selected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('attendees.0.session_ids', $exception->errors());
        }
        $payload['attendees'][0]['session_ids'] = [$plenary->id, $speakerRoom->id];
        try {
            $registrations->submit($event, $payload);
            $this->fail('Ticket-restricted sessions must reject other registration types.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('attendees.0.session_ids', $exception->errors());
        }
        $payload['attendees'][0]['session_ids'] = [$plenary->id];
        $first = $registrations->submit($event, $payload);
        $this->assertDatabaseHas('event_registration_session_selections', [
            'session_id' => $plenary->id, 'submission_id' => $first->id, 'cancelled_at' => null,
        ]);

        try {
            $agenda->save($form, 66, [
                'title' => 'Renamed plenary', 'starts_at' => $plenary->starts_at,
                'ends_at' => $plenary->ends_at, 'capacity' => 1, 'is_required' => true,
            ], $plenary);
            $this->fail('A populated agenda session must not be silently changed.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('session', $exception->errors());
        }

        try {
            $agenda->cancel($plenary, 66);
            $this->fail('Sessions with active selections cannot be silently cancelled.');
        } catch (ValidationException) {
            $this->assertSame('published', $plenary->fresh()->status);
        }
        try {
            $registrations->submit($event, $payload);
            $this->fail('Session capacity must be enforced under the session row lock.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('attendees', $exception->errors());
        }

        $registrations->decide($first, 66, 'rejected', 'Capacity released for another delegate.');
        $this->assertDatabaseMissing('event_registration_session_selections', [
            'submission_id' => $first->id, 'cancelled_at' => null,
        ]);
        $second = $registrations->submit($event, $payload);
        $this->assertSame(1, $second->sessionSelections()->whereNull('cancelled_at')->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'event.registration_session.saved']);
    }

    public function test_company_invoice_reserves_inventory_and_issues_attendee_tickets_only_after_verified_payment(): void
    {
        Storage::fake('local');
        config()->set('registration.invoice_disk', 'local');
        DB::table('organizers')->insert(['id' => 72, 'email' => 'organizer@example.com', 'status' => 1, 'amount' => 0, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('basic_settings')->insert([
            'tax' => 100, 'commission' => 5, 'base_currency_text' => 'NGN', 'base_currency_symbol' => '₦',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('earnings')->insert(['total_revenue' => 0, 'total_earning' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $registrations = app(EventRegistrationService::class);
        [$event, $form] = $this->publishedForm($registrations, 72, 'manual');
        DB::table('event_contents')->insert(['event_id' => $event->id, 'title' => 'Company Summit', 'created_at' => now(), 'updated_at' => now()]);
        $delegate = Ticket::create([
            'event_id' => $event->id, 'title' => 'Delegate', 'pricing_type' => 'normal', 'price' => 5000,
            'ticket_available_type' => 'limited', 'ticket_available' => 2,
        ]);
        $payload = $this->submissionPayload();
        $payload['attendees'][0]['ticket_id'] = $delegate->id;
        $submission = $registrations->submit($event, $payload);
        $submission = $registrations->decide($submission, 72, 'approved', null);

        $invoice = app(EventRegistrationInvoiceService::class)->issue($submission, 72, [
            'purchase_order_number' => 'PO-2026-17', 'due_at' => now()->addDays(7)->toDateString(),
        ]);
        $this->assertSame('issued', $invoice->status);
        $this->assertSame(1, $delegate->fresh()->ticket_available);
        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('booking_tickets', 0);
        Storage::disk('local')->assertExists($invoice->invoice_path);

        $paid = app(EventRegistrationInvoiceService::class)->markPaid($invoice, 72, [
            'payment_reference' => 'BANK-VERIFIED-001', 'payment_note' => 'Matched on company bank statement.',
        ]);
        $this->assertSame('paid', $paid->status);
        $this->assertNotNull($paid->booking_id);
        $this->assertDatabaseHas('event_registration_submissions', ['id' => $submission->id, 'status' => 'ticketed', 'booking_id' => $paid->booking_id]);
        $this->assertDatabaseHas('event_registration_attendees', ['submission_id' => $submission->id, 'status' => 'ticketed']);
        $this->assertDatabaseHas('booking_tickets', ['booking_id' => $paid->booking_id, 'ticket_id' => $delegate->id, 'status' => 'issued']);
        $this->assertDatabaseHas('transactions', ['gateway' => 'invoice_manual', 'gateway_reference' => 'BANK-VERIFIED-001', 'booking_id' => $paid->booking_id]);
        $this->assertDatabaseHas('booking_deliveries', ['booking_id' => $paid->booking_id, 'status' => 'pending']);
        $this->assertSame(4845.0, (float) Organizer::find(72)->amount);

        $badgeService = app(EventRegistrationBadgeService::class);
        $setting = $badgeService->save($form->fresh(), 72, [
            'badge_title' => 'Company Summit Delegate', 'accent_color' => '#FE9B59',
        ]);
        $badges = $badgeService->badgeData($form->fresh(), 72);
        $this->assertSame('Company Summit Delegate', $setting->badge_title);
        $this->assertCount(1, $badges);
        $this->assertSame('Primary Contact', $badges->first()['name']);
        $this->assertSame('Delegate', $badges->first()['registration_type']);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $badges->first()['qr_data_uri']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'event.registration_badge.settings_saved']);
        $badgeDownload = $badgeService->download($form->fresh(), 72);
        $this->assertSame('application/pdf', $badgeDownload->headers->get('content-type'));
        $this->assertStringContainsString('no-store', (string) $badgeDownload->headers->get('cache-control'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'event.registration_badge.downloaded']);

        app(EventRegistrationInvoiceService::class)->markPaid($paid, 72, ['payment_reference' => 'BANK-VERIFIED-001']);
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('transactions', 1);

        $secondPayload = $payload;
        $secondPayload['registrant']['email'] = 'second@example.com';
        $secondPayload['attendees'][0]['email'] = 'second@example.com';
        $secondSubmission = $registrations->decide($registrations->submit($event, $secondPayload), 72, 'approved', null);
        $overdue = app(EventRegistrationInvoiceService::class)->issue($secondSubmission, 72, [
            'due_at' => now()->addDay()->toDateString(),
        ]);
        $this->assertSame(0, $delegate->fresh()->ticket_available);
        $overdue->forceFill(['due_at' => now()->subMinute()])->save();
        $this->assertSame(1, app(EventRegistrationInvoiceService::class)->expireOverdue());
        $this->assertSame('expired', $overdue->fresh()->status);
        $this->assertSame(1, $delegate->fresh()->ticket_available);

        $this->expectException(AuthorizationException::class);
        $badgeService->badgeData($form->fresh(), 999);
    }

    public function test_company_invoice_prices_reserves_and_issues_an_explicit_ticket_variation(): void
    {
        Storage::fake('local');
        config()->set('registration.invoice_disk', 'local');
        DB::table('organizers')->insert(['id' => 73, 'email' => 'variations@example.com', 'status' => 1, 'amount' => 0, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('basic_settings')->insert([
            'tax' => 0, 'commission' => 5, 'base_currency_text' => 'NGN', 'base_currency_symbol' => '₦',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('earnings')->insert(['total_revenue' => 0, 'total_earning' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $registrations = app(EventRegistrationService::class);
        [$event] = $this->publishedForm($registrations, 73, 'manual');
        DB::table('event_contents')->insert(['event_id' => $event->id, 'title' => 'Variation Summit', 'created_at' => now(), 'updated_at' => now()]);
        $ticket = Ticket::create([
            'event_id' => $event->id, 'title' => 'Delegate', 'pricing_type' => 'variation',
            'variations' => json_encode([
                ['name' => 'Standard', 'price' => 5000, 'ticket_available_type' => 'limited', 'ticket_available' => 5],
                ['name' => 'VIP', 'price' => 12000, 'ticket_available_type' => 'limited', 'ticket_available' => 2],
            ]),
        ]);
        $payload = $this->submissionPayload();
        $payload['attendees'][0]['ticket_id'] = $ticket->id;
        $payload['attendees'][0]['variation_name'] = 'VIP';
        $submission = $registrations->decide($registrations->submit($event, $payload), 73, 'approved', null);

        $invoice = app(EventRegistrationInvoiceService::class)->issue($submission, 73, [
            'due_at' => now()->addDays(7)->toDateString(),
        ]);

        $this->assertSame('VIP', $invoice->line_items[0]['variation_name']);
        $this->assertSame(12000.0, (float) $invoice->line_items[0]['unit_price']);
        $this->assertSame(1, collect(json_decode((string) $ticket->fresh()->variations, true))->firstWhere('name', 'VIP')['ticket_available']);

        $paid = app(EventRegistrationInvoiceService::class)->markPaid($invoice, 73, [
            'payment_reference' => 'BANK-VARIATION-001',
        ]);
        $this->assertDatabaseHas('booking_tickets', [
            'booking_id' => $paid->booking_id, 'ticket_id' => $ticket->id,
            'variation_name' => 'VIP', 'status' => 'issued',
        ]);
        $this->assertDatabaseHas('event_registration_attendees', [
            'submission_id' => $submission->id, 'ticket_id' => $ticket->id,
            'variation_name' => 'VIP', 'status' => 'ticketed',
        ]);
        $badges = app(EventRegistrationBadgeService::class)->badgeData($submission->form, 73);
        $this->assertSame('Delegate — VIP', $badges->first()['registration_type']);
    }

    public function test_embed_settings_are_owner_scoped_exact_origin_only_and_brandable(): void
    {
        $registrations = app(EventRegistrationService::class);
        [, $form] = $this->publishedForm($registrations, 81, 'manual');
        $embeds = app(EventRegistrationEmbedService::class);

        $setting = $embeds->save($form, 81, [
            'enabled' => true,
            'allowed_origins' => "https://events.example.com/\nhttp://localhost:5173",
            'accent_color' => '#fe9b59',
            'background_color' => '#f4f6fa',
        ]);
        $this->assertTrue($setting->enabled);
        $this->assertSame(['https://events.example.com', 'http://localhost:5173'], $setting->allowed_origins);
        $this->assertSame('#FE9B59', $setting->accent_color);
        $this->assertDatabaseHas('audit_logs', ['action' => 'event.registration_embed.settings_saved']);

        config()->set('security.content_security_policy', "default-src 'self'; frame-ancestors 'self'; form-action 'self'");
        config()->set('security.enforce_content_security_policy', false);
        $embedPage = $this->get(route('event.registration.embed.show', $form->event_id));
        $embedPage->assertOk()->assertSee('Registration request');
        $this->assertNull($embedPage->headers->get('X-Frame-Options'));
        $this->assertStringContainsString(
            "frame-ancestors 'self' https://events.example.com http://localhost:5173",
            (string) $embedPage->headers->get('Content-Security-Policy')
        );

        $unsigned = $this->post(route('event.registration.embed.store', $form->event_id), $this->submissionPayload());
        $unsigned->assertForbidden();
        $signedAction = URL::temporarySignedRoute(
            'event.registration.embed.store', now()->addHour(), ['event' => $form->event_id]
        );
        $this->post($signedAction, ['consent' => true])
            ->assertUnprocessable()->assertSee('Please review the highlighted fields.');
        $this->post($signedAction, $this->submissionPayload())
            ->assertCreated()->assertSee('Registration received');
        $this->assertDatabaseHas('event_registration_submissions', [
            'form_id' => $form->id, 'status' => 'submitted',
        ]);

        try {
            $embeds->save($form->fresh(), 81, [
                'enabled' => true,
                'allowed_origins' => 'https://*.example.com',
                'accent_color' => '#FE9B59',
                'background_color' => '#F4F6FA',
            ]);
            $this->fail('Wildcard frame origins must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('allowed_origins', $exception->errors());
        }

        $this->expectException(AuthorizationException::class);
        $embeds->save($form->fresh(), 999, [
            'enabled' => false, 'allowed_origins' => '',
            'accent_color' => '#FE9B59', 'background_color' => '#F4F6FA',
        ]);
    }

    private function publishedForm(EventRegistrationService $service, int $organizerId, string $approvalMode): array
    {
        $event = $this->event($organizerId);
        $form = $service->saveForm($event, $organizerId, [
            'name' => 'Corporate registration',
            'approval_mode' => $approvalMode,
            'intro' => 'Register your attendees.',
            'privacy_notice' => 'Your details are used only to administer this event.',
        ]);

        return [$event->fresh(), $service->publish($form, $organizerId)];
    }

    private function submissionPayload(): array
    {
        return [
            'registrant' => ['name' => 'Primary Contact', 'email' => 'contact@example.com'],
            'answers' => [],
            'attendees' => [[
                'name' => 'Primary Contact',
                'email' => 'contact@example.com',
                'answers' => [],
            ]],
            'consent' => true,
        ];
    }

    private function event(int $organizerId): Event
    {
        return Event::create([
            'organizer_id' => $organizerId,
            'status' => 1,
            'lifecycle_status' => 'scheduled',
            'event_type' => 'venue',
            'registration_mode' => 'ticketing',
            'start_date' => now()->addMonth()->toDateString(),
            'start_time' => '09:00:00',
            'end_date_time' => now()->addMonth()->endOfDay(),
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organizer_id')->nullable();
            $table->boolean('status')->default(true);
            $table->string('lifecycle_status')->default('scheduled');
            $table->string('event_type')->default('venue');
            $table->string('registration_mode')->default('ticketing');
            $table->date('start_date')->nullable();
            $table->time('start_time')->nullable();
            $table->timestamp('sales_closed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('end_date_time')->nullable();
            $table->timestamps();
        });
        Schema::create('event_registration_forms', function (Blueprint $table): void {
            $table->id(); $table->string('public_id', 26)->unique(); $table->unsignedBigInteger('event_id')->unique();
            $table->unsignedBigInteger('organizer_id'); $table->string('name', 150); $table->string('status', 24)->default('draft');
            $table->string('approval_mode', 24)->default('manual'); $table->unsignedInteger('version')->default(1);
            $table->text('intro')->nullable(); $table->text('privacy_notice'); $table->string('consent_version', 32);
            $table->timestamp('published_at')->nullable(); $table->timestamp('archived_at')->nullable(); $table->timestamps();
        });
        Schema::create('event_registration_questions', function (Blueprint $table): void {
            $table->id(); $table->string('public_id', 26)->unique(); $table->unsignedBigInteger('form_id');
            $table->unsignedBigInteger('ticket_id')->nullable();
            $table->string('question_key', 80); $table->string('label', 191); $table->text('help_text')->nullable();
            $table->string('type', 24); $table->string('scope', 24); $table->boolean('is_required')->default(false);
            $table->json('options')->nullable(); $table->json('condition')->nullable(); $table->json('settings')->nullable(); $table->unsignedSmallInteger('position');
            $table->timestamps(); $table->unique(['form_id', 'question_key']);
        });
        Schema::create('event_registration_form_templates', function (Blueprint $table): void {
            $table->id(); $table->string('public_id', 26)->unique(); $table->unsignedBigInteger('organizer_id');
            $table->string('name', 150); $table->text('description')->nullable(); $table->json('definition');
            $table->unsignedInteger('version')->default(1); $table->timestamps(); $table->unique(['organizer_id', 'name']);
        });
        Schema::create('tickets', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('event_id'); $table->string('event_type')->nullable();
            $table->string('title'); $table->string('ticket_available_type')->nullable(); $table->integer('ticket_available')->nullable();
            $table->string('max_ticket_buy_type')->nullable(); $table->integer('max_buy_ticket')->nullable();
            $table->text('description')->nullable(); $table->string('pricing_type')->nullable(); $table->decimal('price', 12, 2)->nullable();
            $table->decimal('f_price', 12, 2)->nullable(); $table->string('early_bird_discount_type')->nullable();
            $table->decimal('early_bird_discount', 12, 2)->nullable(); $table->decimal('early_bird_discount_amount', 12, 2)->nullable();
            $table->date('early_bird_discount_date')->nullable(); $table->time('early_bird_discount_time')->nullable();
            $table->json('variations')->nullable(); $table->json('trans_vars')->nullable(); $table->timestamps();
        });
        Schema::create('event_registration_submissions', function (Blueprint $table): void {
            $table->id(); $table->string('public_id', 26)->unique(); $table->unsignedBigInteger('form_id');
            $table->unsignedBigInteger('event_id'); $table->unsignedBigInteger('organizer_id'); $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('booking_id')->nullable(); $table->unsignedInteger('form_version'); $table->string('status', 24);
            $table->text('primary_name'); $table->text('primary_email'); $table->char('primary_email_hash', 64);
            $table->unsignedSmallInteger('attendee_count'); $table->string('consent_version', 32); $table->timestamp('consented_at');
            $table->char('source_ip_hash', 64)->nullable(); $table->timestamp('submitted_at');
            $table->unsignedBigInteger('reviewed_by_organizer_id')->nullable(); $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable(); $table->timestamps();
        });
        Schema::create('event_registration_attendees', function (Blueprint $table): void {
            $table->id(); $table->string('public_id', 26)->unique(); $table->unsignedBigInteger('submission_id');
            $table->unsignedBigInteger('ticket_id')->nullable(); $table->string('variation_name')->nullable(); $table->unsignedBigInteger('booking_ticket_id')->nullable();
            $table->unsignedSmallInteger('sequence'); $table->text('name'); $table->text('email')->nullable();
            $table->char('email_hash', 64)->nullable(); $table->string('status', 24); $table->timestamps();
            $table->unique(['submission_id', 'sequence']);
        });
        Schema::create('event_registration_answers', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('submission_id'); $table->unsignedBigInteger('attendee_id')->nullable();
            $table->unsignedBigInteger('question_id'); $table->string('question_key', 80); $table->string('question_label', 191);
            $table->string('question_type', 24); $table->text('value')->nullable(); $table->timestamps();
        });
        Schema::create('event_registration_files', function (Blueprint $table): void {
            $table->id(); $table->string('public_id', 26)->unique(); $table->unsignedBigInteger('answer_id')->unique();
            $table->unsignedBigInteger('submission_id'); $table->unsignedBigInteger('attendee_id')->nullable();
            $table->unsignedBigInteger('question_id'); $table->string('disk', 64); $table->string('path', 500)->unique();
            $table->text('original_name'); $table->string('mime_type', 100); $table->unsignedBigInteger('size');
            $table->char('sha256', 64); $table->timestamps();
        });
        Schema::create('event_registration_notifications', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('submission_id'); $table->unsignedBigInteger('event_id');
            $table->string('kind', 24); $table->string('status', 24)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('dispatch_attempts')->default(0);
            $table->timestamp('dispatch_last_attempt_at')->nullable(); $table->text('dispatch_last_error')->nullable();
            $table->timestamp('processing_at')->nullable();
            $table->timestamp('sent_at')->nullable(); $table->timestamp('alerted_at')->nullable();
            $table->text('last_error')->nullable(); $table->timestamps(); $table->unique(['submission_id', 'kind']);
        });
        Schema::create('event_registration_sessions', function (Blueprint $table): void {
            $table->id(); $table->string('public_id')->unique(); $table->unsignedBigInteger('form_id');
            $table->unsignedBigInteger('event_id'); $table->unsignedBigInteger('organizer_id');
            $table->unsignedBigInteger('ticket_id')->nullable(); $table->string('title'); $table->text('description')->nullable();
            $table->string('location')->nullable(); $table->timestamp('starts_at'); $table->timestamp('ends_at');
            $table->unsignedInteger('capacity')->nullable(); $table->boolean('is_required')->default(false);
            $table->string('status'); $table->timestamp('cancelled_at')->nullable(); $table->timestamps();
        });
        Schema::create('event_registration_session_selections', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('session_id'); $table->unsignedBigInteger('submission_id');
            $table->unsignedBigInteger('attendee_id'); $table->timestamp('selected_at');
            $table->timestamp('cancelled_at')->nullable(); $table->string('cancellation_reason')->nullable(); $table->timestamps();
            $table->unique(['session_id', 'attendee_id']);
        });
        Schema::create('event_contents', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('event_id'); $table->string('title')->nullable(); $table->timestamps();
        });
        Schema::create('organizers', function (Blueprint $table): void {
            $table->id(); $table->string('email')->nullable(); $table->string('username')->nullable();
            $table->string('password')->nullable(); $table->integer('status')->default(1);
            $table->decimal('amount', 18, 2)->default(0); $table->timestamps();
        });
        Schema::create('basic_settings', function (Blueprint $table): void {
            $table->id(); $table->decimal('tax', 12, 2)->default(0); $table->decimal('commission', 12, 2)->default(0);
            $table->string('base_currency_text')->nullable(); $table->string('base_currency_symbol')->nullable(); $table->timestamps();
        });
        Schema::create('earnings', function (Blueprint $table): void {
            $table->id(); $table->decimal('total_revenue', 18, 2)->default(0); $table->decimal('total_earning', 18, 2)->default(0); $table->timestamps();
        });
        Schema::create('event_registration_invoices', function (Blueprint $table): void {
            $table->id(); $table->string('public_id', 26)->unique(); $table->string('invoice_number', 40)->unique();
            $table->unsignedBigInteger('submission_id')->unique(); $table->unsignedBigInteger('form_id'); $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('organizer_id'); $table->unsignedBigInteger('booking_id')->nullable()->unique();
            $table->uuid('reservation_token')->nullable()->unique(); $table->string('status', 24); $table->string('currency', 12);
            $table->string('currency_symbol', 12)->nullable(); $table->decimal('subtotal', 14, 2); $table->decimal('tax', 14, 2);
            $table->decimal('commission', 14, 2); $table->decimal('total', 14, 2); $table->text('line_items');
            $table->text('purchase_order_number')->nullable(); $table->text('payment_reference')->nullable(); $table->text('payment_note')->nullable();
            $table->string('invoice_path')->nullable(); $table->timestamp('issued_at'); $table->timestamp('due_at');
            $table->timestamp('paid_at')->nullable(); $table->timestamp('cancelled_at')->nullable();
            $table->unsignedBigInteger('marked_paid_by_organizer_id')->nullable(); $table->timestamps();
        });
        Schema::create('event_registration_badge_settings', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('form_id')->unique(); $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('organizer_id'); $table->string('badge_title', 100)->nullable();
            $table->string('company_question_key', 80)->nullable(); $table->string('role_question_key', 80)->nullable();
            $table->string('accent_color', 7)->default('#FE9B59'); $table->string('paper_size', 16)->default('a6');
            $table->timestamps();
        });
        Schema::create('event_registration_embed_settings', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('form_id')->unique(); $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('organizer_id'); $table->boolean('enabled')->default(false);
            $table->json('allowed_origins')->nullable(); $table->string('accent_color', 7)->default('#FE9B59');
            $table->string('background_color', 7)->default('#F4F6FA'); $table->timestamps();
        });
        Schema::create('ticket_reservations', function (Blueprint $table): void {
            $table->id(); $table->uuid('token'); $table->unsignedBigInteger('event_id'); $table->unsignedBigInteger('ticket_id');
            $table->string('variation_name')->nullable(); $table->unsignedInteger('quantity'); $table->string('status', 24);
            $table->string('session_hash', 64)->nullable(); $table->unsignedBigInteger('customer_id')->nullable();
            $table->timestamp('expires_at'); $table->timestamp('consumed_at')->nullable(); $table->timestamp('released_at')->nullable(); $table->timestamps();
        });
        Schema::create('bookings', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('customer_id')->nullable(); $table->string('booking_id');
            $table->unsignedBigInteger('event_id'); $table->unsignedBigInteger('organizer_id')->nullable(); $table->unsignedBigInteger('ticket_id')->nullable();
            $table->string('fname')->nullable(); $table->string('lname')->nullable(); $table->string('email')->nullable(); $table->string('phone')->nullable();
            $table->string('country')->nullable(); $table->string('state')->nullable(); $table->string('city')->nullable(); $table->string('zip_code')->nullable(); $table->text('address')->nullable();
            $table->text('variation')->nullable(); $table->decimal('price', 14, 2); $table->decimal('tax_percentage', 12, 2)->default(0);
            $table->decimal('commission_percentage', 12, 2)->default(0); $table->decimal('tax', 14, 2)->default(0); $table->decimal('commission', 14, 2)->default(0);
            $table->unsignedInteger('quantity'); $table->decimal('discount', 14, 2)->default(0); $table->decimal('early_bird_discount', 14, 2)->default(0);
            $table->string('currencyText')->nullable(); $table->string('currencyTextPosition')->nullable(); $table->string('currencySymbol')->nullable(); $table->string('currencySymbolPosition')->nullable();
            $table->string('paymentMethod')->nullable(); $table->string('gatewayType')->nullable(); $table->string('paymentStatus')->nullable();
            $table->decimal('refunded_amount', 14, 2)->default(0); $table->string('refund_status')->nullable();
            $table->string('invoice')->nullable(); $table->string('attachmentFile')->nullable(); $table->timestamp('event_date')->nullable();
            $table->integer('scan_status')->default(0); $table->unsignedBigInteger('conversation_id')->nullable(); $table->timestamps();
        });
        Schema::create('booking_tickets', function (Blueprint $table): void {
            $table->id(); $table->uuid('public_id')->unique(); $table->unsignedBigInteger('booking_id'); $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('organizer_id')->nullable(); $table->unsignedBigInteger('ticket_id')->nullable(); $table->unsignedInteger('sequence');
            $table->string('variation_name')->nullable(); $table->string('holder_name')->nullable(); $table->string('holder_email')->nullable();
            $table->string('status', 32); $table->timestamp('checked_in_at')->nullable(); $table->unsignedBigInteger('checked_in_by_organizer_id')->nullable();
            $table->timestamp('revoked_at')->nullable(); $table->string('revoked_reason', 500)->nullable(); $table->timestamps(); $table->unique(['booking_id', 'sequence']);
        });
        Schema::create('transactions', function (Blueprint $table): void {
            $table->id(); $table->string('transcation_id')->nullable(); $table->string('gateway', 40)->nullable(); $table->string('gateway_reference', 191)->nullable();
            $table->string('booking_id')->nullable(); $table->integer('transcation_type'); $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('organizer_id')->nullable(); $table->string('payment_status'); $table->string('payment_method')->nullable();
            $table->decimal('grand_total', 18, 2); $table->decimal('pre_balance', 18, 2)->default(0); $table->decimal('after_balance', 18, 2)->nullable();
            $table->decimal('commission', 18, 2)->default(0); $table->decimal('tax', 18, 2)->default(0); $table->string('gateway_type')->nullable();
            $table->string('currency_symbol')->nullable(); $table->string('currency_symbol_position')->nullable(); $table->timestamps();
            $table->unique(['gateway', 'gateway_reference']);
        });
        Schema::create('booking_deliveries', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('booking_id'); $table->string('kind'); $table->string('status');
            $table->unsignedSmallInteger('attempts')->default(0); $table->unsignedSmallInteger('dispatch_attempts')->default(0);
            $table->timestamp('dispatch_last_attempt_at')->nullable(); $table->text('dispatch_last_error')->nullable();
            $table->timestamp('processing_at')->nullable(); $table->timestamp('sent_at')->nullable();
            $table->timestamp('alerted_at')->nullable(); $table->text('last_error')->nullable(); $table->timestamps(); $table->unique(['booking_id', 'kind']);
        });
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id(); $table->string('actor_type'); $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action'); $table->string('subject_type')->nullable(); $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('before')->nullable(); $table->json('after')->nullable(); $table->json('metadata')->nullable();
            $table->string('request_id')->nullable(); $table->string('ip_address')->nullable(); $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }
}
