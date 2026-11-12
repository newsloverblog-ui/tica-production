<?php

namespace Tests\Unit;

use App\Jobs\ProcessEventRegistrationImport;
use App\Jobs\SendEventRegistrationNotification;
use App\Models\Event;
use App\Models\EventRegistrationForm;
use App\Models\EventRegistrationImport;
use App\Models\EventRegistrationQuestion;
use App\Models\EventRegistrationSession;
use App\Models\Event\Ticket;
use App\Services\EventRegistrationService;
use App\Services\EventRegistrationImportService;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class EventRegistrationImportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]);
        config()->set('app.key', 'base64:'.base64_encode(str_repeat('i', 32)));
        config()->set('registration.import_disk', 'local');
        config()->set('registration.import_max_rows', 100);
        config()->set('registration.import_max_columns', 30);
        config()->set('registration.import_retention_days', 7);
        config()->set('registration.max_attendees_per_submission', 100);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->schema();
    }

    public function test_private_import_groups_attendees_reports_rejections_and_is_idempotent(): void
    {
        Storage::fake('local');
        Queue::fake();
        [$event, $form] = $this->publishedForm(10);
        $csv = implode("\n", [
            'registration_key,primary_name,primary_email,consent_confirmed,attendee_name,attendee_email,registrant.company,attendee.diet',
            'team-a,Jane Owner,jane@example.com,yes,Ada One,ada@example.com,Tica,Vegan',
            'team-a,Jane Owner,jane@example.com,yes,Bola Two,bola@example.com,Tica,Standard',
            'team-b,John Owner,john@example.com,yes,Invalid Diet,john@example.com,Example,Unknown',
        ])."\n";

        $import = app(EventRegistrationImportService::class)->queue(
            $event, 10, UploadedFile::fake()->createWithContent('delegates.csv', $csv), true
        );
        $this->assertSame('queued', $import->status);
        Storage::disk('local')->assertExists($import->path);
        Queue::assertPushed(ProcessEventRegistrationImport::class, fn ($job) => $job->importId === $import->id);

        (new ProcessEventRegistrationImport($import->id))->handle(app(\App\Services\EventRegistrationService::class));
        $ready = $import->fresh();
        $this->assertSame('completed_with_errors', $ready->status);
        $this->assertSame(3, $ready->total_rows);
        $this->assertSame(2, $ready->accepted_rows);
        $this->assertSame(1, $ready->rejected_rows);
        $this->assertNotNull($ready->source_deleted_at);
        Storage::disk('local')->assertMissing($ready->path);
        $this->assertDatabaseCount('event_registration_submissions', 1);
        $this->assertDatabaseCount('event_registration_attendees', 2);
        $this->assertDatabaseHas('event_registration_import_rows', ['row_number' => 4, 'status' => 'rejected', 'error_code' => 'validation_failed']);
        $this->assertDatabaseMissing('event_registration_import_rows', ['registration_key' => 'team-a']);
        Queue::assertPushed(SendEventRegistrationNotification::class);

        // Simulate a worker retry after submission commit but before final job acknowledgement.
        Storage::disk('local')->put($ready->path, $csv);
        $ready->forceFill(['status' => 'failed', 'source_deleted_at' => null, 'completed_at' => null])->save();
        (new ProcessEventRegistrationImport($ready->id))->handle(app(\App\Services\EventRegistrationService::class));
        $this->assertDatabaseCount('event_registration_submissions', 1);
        $this->assertDatabaseCount('event_registration_attendees', 2);
        $this->assertSame('completed_with_errors', $ready->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'event.registration_import_completed', 'subject_id' => $ready->id]);
    }

    public function test_import_requires_owner_attestation_and_a_published_form(): void
    {
        Storage::fake('local');
        Queue::fake();
        [$event, $form] = $this->publishedForm(10);
        $file = UploadedFile::fake()->createWithContent('delegates.csv', 'registration_key,primary_name,primary_email,consent_confirmed,attendee_name'."\n");

        foreach ([[10, false], [20, true]] as [$organizerId, $attested]) {
            try {
                app(EventRegistrationImportService::class)->queue($event, $organizerId, $file, $attested);
                $this->fail('The import boundary should reject this request.');
            } catch (DomainException) {
                $this->assertDatabaseCount('event_registration_imports', 0);
            }
        }
        $form->update(['status' => 'draft']);
        $this->expectException(DomainException::class);
        app(EventRegistrationImportService::class)->queue($event, 10, $file, true);
    }

    public function test_template_tracks_the_published_form_schema(): void
    {
        [, $form] = $this->publishedForm(10);
        $headers = app(EventRegistrationImportService::class)->headers($form->load('questions'), 10);

        $this->assertSame([
            'registration_key', 'primary_name', 'primary_email', 'consent_confirmed',
            'attendee_name', 'attendee_email', 'registrant.company', 'attendee.diet',
        ], $headers);
    }

    public function test_failed_source_is_retained_for_retry_then_pruned_at_expiry(): void
    {
        Storage::fake('local');
        Queue::fake();
        [$event] = $this->publishedForm(10);
        $csv = 'registration_key,primary_name,primary_email,consent_confirmed,attendee_name,unknown_column'."\n"
            .'a,Jane,jane@example.com,yes,Ada,value'."\n";
        $import = app(EventRegistrationImportService::class)->queue(
            $event, 10, UploadedFile::fake()->createWithContent('invalid.csv', $csv), true
        );

        try {
            (new ProcessEventRegistrationImport($import->id))->handle(app(\App\Services\EventRegistrationService::class));
            $this->fail('An unknown template column must fail the import.');
        } catch (\RuntimeException) {
            $this->assertSame('failed', $import->fresh()->status);
            Storage::disk('local')->assertExists($import->path);
        }

        $import->forceFill(['expires_at' => now()->subMinute()])->save();
        $this->artisan('registrations:prune-imports')->assertSuccessful();
        Storage::disk('local')->assertMissing($import->path);
        $this->assertSame('expired', $import->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'event.registration_import_source_pruned', 'subject_id' => $import->id,
        ]);
    }

    public function test_ticket_scoped_template_import_persists_each_attendees_registration_type(): void
    {
        Storage::fake('local');
        Queue::fake();
        [$event, $form] = $this->publishedForm(10);
        $speaker = Ticket::create(['event_id' => $event->id, 'title' => 'Speaker', 'pricing_type' => 'free']);
        app(EventRegistrationService::class)->addQuestion($form, 10, [
            'label' => 'Talk title', 'question_key' => 'talk_title', 'type' => 'text',
            'scope' => 'attendee', 'ticket_id' => $speaker->id, 'is_required' => true,
        ]);
        $form = app(EventRegistrationService::class)->publish($form->fresh(), 10);
        $headers = app(EventRegistrationImportService::class)->headers($form->load('questions'), 10);
        $this->assertContains('attendee_ticket_id', $headers);

        $row = array_fill_keys($headers, '');
        $row = array_merge($row, [
            'registration_key' => 'speaker-one', 'primary_name' => 'Event Lead',
            'primary_email' => 'lead@example.com', 'consent_confirmed' => 'yes',
            'attendee_name' => 'Ada Speaker', 'attendee_email' => 'ada@example.com',
            'attendee_ticket_id' => (string) $speaker->id, 'registrant.company' => 'Tica',
            'attendee.diet' => 'Vegan', 'attendee.talk_title' => 'Building Reliable Events',
        ]);
        $stream = fopen('php://temp', 'w+');
        fputcsv($stream, $headers, ',', '"', '');
        fputcsv($stream, array_map(fn ($header) => $row[$header] ?? '', $headers), ',', '"', '');
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        $import = app(EventRegistrationImportService::class)->queue($event, 10,
            UploadedFile::fake()->createWithContent('ticket-scoped.csv', $csv), true);
        (new ProcessEventRegistrationImport($import->id))->handle(app(EventRegistrationService::class));

        $this->assertSame('completed', $import->fresh()->status);
        $this->assertDatabaseHas('event_registration_attendees', ['ticket_id' => $speaker->id]);
        $this->assertDatabaseHas('event_registration_answers', ['question_key' => 'talk_title']);
    }

    public function test_variation_ticket_import_requires_and_persists_the_explicit_option(): void
    {
        Storage::fake('local');
        Queue::fake();
        [$event, $form] = $this->publishedForm(10);
        $delegate = Ticket::create([
            'event_id' => $event->id,
            'title' => 'Delegate',
            'pricing_type' => 'variation',
            'variations' => json_encode([
                ['name' => 'Standard', 'price' => 5000, 'ticket_available_type' => 'limited', 'ticket_available' => 10],
                ['name' => 'VIP', 'price' => 12000, 'ticket_available_type' => 'limited', 'ticket_available' => 4],
            ]),
        ]);
        $headers = app(EventRegistrationImportService::class)->headers($form->load('questions'), 10);

        $this->assertContains('attendee_ticket_id', $headers);
        $this->assertContains('attendee_variation_name', $headers);

        $row = array_fill_keys($headers, '');
        $row = array_merge($row, [
            'registration_key' => 'vip-delegate', 'primary_name' => 'Event Lead',
            'primary_email' => 'lead@example.com', 'consent_confirmed' => 'yes',
            'attendee_name' => 'Ada VIP', 'attendee_email' => 'ada@example.com',
            'attendee_ticket_id' => (string) $delegate->id, 'attendee_variation_name' => 'VIP',
            'registrant.company' => 'Tica', 'attendee.diet' => 'Vegan',
        ]);
        $stream = fopen('php://temp', 'w+');
        fputcsv($stream, $headers, ',', '"', '');
        fputcsv($stream, array_map(fn ($header) => $row[$header] ?? '', $headers), ',', '"', '');
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        $import = app(EventRegistrationImportService::class)->queue(
            $event,
            10,
            UploadedFile::fake()->createWithContent('variation-delegates.csv', $csv),
            true
        );
        (new ProcessEventRegistrationImport($import->id))->handle(app(EventRegistrationService::class));

        $this->assertSame('completed', $import->fresh()->status);
        $this->assertDatabaseHas('event_registration_attendees', [
            'ticket_id' => $delegate->id,
            'variation_name' => 'VIP',
        ]);
    }

    public function test_session_aware_import_uses_the_published_agenda_and_capacity_path(): void
    {
        Storage::fake('local'); Queue::fake();
        [$event, $form] = $this->publishedForm(10);
        $ticket = Ticket::create(['event_id' => $event->id, 'title' => 'Delegate', 'pricing_type' => 'free']);
        $session = EventRegistrationSession::create([
            'public_id' => (string) Str::ulid(), 'form_id' => $form->id, 'event_id' => $event->id,
            'organizer_id' => 10, 'title' => 'Opening plenary', 'starts_at' => now()->addMonth()->setTime(10, 0),
            'ends_at' => now()->addMonth()->setTime(11, 0), 'capacity' => 1, 'ticket_id' => $ticket->id,
            'is_required' => true, 'status' => 'published',
        ]);
        $headers = app(EventRegistrationImportService::class)->headers($form->load('questions', 'sessions'), 10);
        $this->assertContains('attendee_session_ids', $headers);
        $this->assertContains('attendee_ticket_id', $headers);
        $values = array_fill_keys($headers, '');
        $values = array_merge($values, [
            'registration_key' => 'team-session', 'primary_name' => 'Team Lead',
            'primary_email' => 'lead@example.com', 'consent_confirmed' => 'yes',
            'attendee_name' => 'Session Guest', 'attendee_email' => 'guest@example.com',
            'attendee_ticket_id' => (string) $ticket->id, 'attendee_session_ids' => (string) $session->id,
            'registrant.company' => 'Tica', 'attendee.diet' => 'Standard',
        ]);
        $stream = fopen('php://temp', 'w+'); fputcsv($stream, $headers, ',', '"', '');
        fputcsv($stream, array_map(fn ($header) => $values[$header], $headers), ',', '"', '');
        rewind($stream); $csv = stream_get_contents($stream); fclose($stream);
        $import = app(EventRegistrationImportService::class)->queue($event, 10,
            UploadedFile::fake()->createWithContent('sessions.csv', $csv), true);
        (new ProcessEventRegistrationImport($import->id))->handle(app(EventRegistrationService::class));
        $this->assertSame('completed', $import->fresh()->status);
        $this->assertDatabaseHas('event_registration_session_selections', ['session_id' => $session->id, 'cancelled_at' => null]);
    }

    private function publishedForm(int $organizerId): array
    {
        $event = Event::create([
            'organizer_id' => $organizerId, 'status' => 1, 'lifecycle_status' => 'scheduled',
            'event_type' => 'venue', 'registration_mode' => 'corporate',
            'start_date' => now()->addMonth()->toDateString(), 'start_time' => '09:00:00',
        ]);
        $form = EventRegistrationForm::create([
            'public_id' => (string) Str::ulid(), 'event_id' => $event->id, 'organizer_id' => $organizerId,
            'name' => 'Summit registration', 'status' => 'published', 'approval_mode' => 'manual',
            'version' => 1, 'privacy_notice' => 'Used only to administer this summit.',
            'consent_version' => 'test-v1', 'published_at' => now(),
        ]);
        EventRegistrationQuestion::create([
            'public_id' => (string) Str::ulid(), 'form_id' => $form->id, 'question_key' => 'company',
            'label' => 'Company', 'type' => 'text', 'scope' => 'registrant', 'is_required' => true, 'position' => 1,
        ]);
        EventRegistrationQuestion::create([
            'public_id' => (string) Str::ulid(), 'form_id' => $form->id, 'question_key' => 'diet',
            'label' => 'Diet', 'type' => 'select', 'scope' => 'attendee', 'is_required' => true,
            'options' => ['Vegan', 'Standard'], 'position' => 2,
        ]);

        return [$event, $form];
    }

    private function schema(): void
    {
        Schema::create('events', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('organizer_id'); $table->boolean('status');
            $table->string('lifecycle_status'); $table->string('event_type'); $table->string('registration_mode');
            $table->timestamp('cancelled_at')->nullable(); $table->date('start_date'); $table->time('start_time'); $table->timestamps();
        });
        Schema::create('event_registration_forms', function (Blueprint $table): void {
            $table->id(); $table->string('public_id')->unique(); $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('organizer_id'); $table->string('name'); $table->string('status');
            $table->string('approval_mode'); $table->unsignedInteger('version'); $table->text('intro')->nullable();
            $table->text('privacy_notice'); $table->string('consent_version'); $table->timestamp('published_at')->nullable();
            $table->timestamp('archived_at')->nullable(); $table->timestamps();
        });
        Schema::create('event_registration_questions', function (Blueprint $table): void {
            $table->id(); $table->string('public_id')->unique(); $table->unsignedBigInteger('form_id');
            $table->unsignedBigInteger('ticket_id')->nullable();
            $table->string('question_key'); $table->string('label'); $table->text('help_text')->nullable();
            $table->string('type'); $table->string('scope'); $table->boolean('is_required');
            $table->json('options')->nullable(); $table->json('condition')->nullable(); $table->unsignedSmallInteger('position'); $table->timestamps();
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
        Schema::create('event_registration_imports', function (Blueprint $table): void {
            $table->id(); $table->string('public_id')->unique(); $table->unsignedBigInteger('form_id');
            $table->unsignedBigInteger('event_id'); $table->unsignedBigInteger('organizer_id'); $table->string('status');
            $table->string('disk'); $table->string('path'); $table->string('original_filename');
            $table->unsignedInteger('total_rows')->default(0); $table->unsignedInteger('accepted_rows')->default(0);
            $table->unsignedInteger('rejected_rows')->default(0); $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('attestation_version'); $table->timestamp('attested_at'); $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable(); $table->timestamp('source_deleted_at')->nullable();
            $table->timestamp('expires_at')->nullable(); $table->text('last_error')->nullable(); $table->timestamps();
        });
        Schema::create('event_registration_import_rows', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('import_id'); $table->unsignedInteger('row_number');
            $table->string('registration_key'); $table->string('status'); $table->unsignedBigInteger('submission_id')->nullable();
            $table->string('error_code')->nullable(); $table->text('error_message')->nullable(); $table->char('row_fingerprint', 64); $table->timestamps();
            $table->unique(['import_id', 'row_number']);
        });
        Schema::create('event_registration_submissions', function (Blueprint $table): void {
            $table->id(); $table->string('public_id')->unique(); $table->unsignedBigInteger('form_id');
            $table->unsignedBigInteger('event_id'); $table->unsignedBigInteger('organizer_id');
            $table->unsignedBigInteger('customer_id')->nullable(); $table->unsignedBigInteger('booking_id')->nullable();
            $table->unsignedInteger('form_version'); $table->string('status'); $table->text('primary_name');
            $table->text('primary_email'); $table->char('primary_email_hash', 64); $table->unsignedSmallInteger('attendee_count');
            $table->string('consent_version'); $table->timestamp('consented_at'); $table->char('source_ip_hash', 64)->nullable();
            $table->unsignedBigInteger('source_import_id')->nullable(); $table->string('source_import_key')->nullable();
            $table->timestamp('submitted_at'); $table->unsignedBigInteger('reviewed_by_organizer_id')->nullable();
            $table->timestamp('reviewed_at')->nullable(); $table->text('review_note')->nullable();
            $table->timestamp('anonymized_at')->nullable(); $table->string('anonymization_reason')->nullable(); $table->timestamps();
            $table->unique(['source_import_id', 'source_import_key']);
        });
        Schema::create('event_registration_attendees', function (Blueprint $table): void {
            $table->id(); $table->string('public_id')->unique(); $table->unsignedBigInteger('submission_id');
            $table->unsignedBigInteger('ticket_id')->nullable(); $table->string('variation_name')->nullable();
            $table->unsignedBigInteger('booking_ticket_id')->nullable();
            $table->unsignedSmallInteger('sequence'); $table->text('name'); $table->text('email')->nullable();
            $table->char('email_hash', 64)->nullable(); $table->string('status'); $table->timestamps();
        });
        Schema::create('event_registration_answers', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('submission_id'); $table->unsignedBigInteger('attendee_id')->nullable();
            $table->unsignedBigInteger('question_id'); $table->string('question_key'); $table->string('question_label');
            $table->string('question_type'); $table->text('value')->nullable(); $table->timestamps();
        });
        Schema::create('event_registration_notifications', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('submission_id'); $table->unsignedBigInteger('event_id');
            $table->string('kind'); $table->string('status'); $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('dispatch_attempts')->default(0);
            $table->timestamp('dispatch_last_attempt_at')->nullable(); $table->text('dispatch_last_error')->nullable();
            $table->timestamp('processing_at')->nullable(); $table->timestamp('sent_at')->nullable();
            $table->timestamp('alerted_at')->nullable(); $table->text('last_error')->nullable(); $table->timestamps();
            $table->unique(['submission_id', 'kind']);
        });
        Schema::create('event_registration_sessions', function (Blueprint $table): void {
            $table->id(); $table->string('public_id')->unique(); $table->unsignedBigInteger('form_id');
            $table->unsignedBigInteger('event_id'); $table->unsignedBigInteger('organizer_id');
            $table->unsignedBigInteger('ticket_id')->nullable(); $table->string('title'); $table->text('description')->nullable();
            $table->string('location')->nullable(); $table->timestamp('starts_at'); $table->timestamp('ends_at');
            $table->unsignedInteger('capacity')->nullable(); $table->boolean('is_required'); $table->string('status');
            $table->timestamp('cancelled_at')->nullable(); $table->timestamps();
        });
        Schema::create('event_registration_session_selections', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('session_id'); $table->unsignedBigInteger('submission_id');
            $table->unsignedBigInteger('attendee_id'); $table->timestamp('selected_at'); $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable(); $table->timestamps(); $table->unique(['session_id', 'attendee_id']);
        });
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id(); $table->string('actor_type'); $table->unsignedBigInteger('actor_id')->nullable(); $table->string('action');
            $table->string('subject_type')->nullable(); $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('before')->nullable(); $table->json('after')->nullable(); $table->json('metadata')->nullable();
            $table->string('request_id')->nullable(); $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable(); $table->timestamp('created_at')->nullable();
        });
    }
}
