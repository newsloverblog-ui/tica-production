<?php

namespace Tests\Unit;

use App\Jobs\GenerateRegistrationExport;
use App\Models\Event;
use App\Models\EventRegistrationAnswer;
use App\Models\EventRegistrationAttendee;
use App\Models\EventRegistrationSubmission;
use App\Services\DataExportService;
use App\Services\RegistrationExportQueryService;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class EventRegistrationExportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]);
        config()->set('app.key', 'base64:'.base64_encode(str_repeat('r', 32)));
        config()->set('exports.disk', 'local');
        config()->set('exports.retention_days', 7);
        config()->set('exports.max_rows', 250000);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->schema();
    }

    public function test_registration_export_is_owner_scoped_private_snapshot_bound_and_formula_safe(): void
    {
        Storage::fake('local');
        Queue::fake();
        $event = $this->event(10, 'Corporate Summit');
        $submission = $this->submission($event, 10, 'approved', null);
        $first = $this->attendee($submission, 1, '=HYPERLINK("https://bad.test")');
        $second = $this->attendee($submission, 2, 'Second Delegate');
        $firstGate = DB::table('scanner_devices')->insertGetId([
            'public_id' => (string) Str::uuid(), 'organizer_id' => 10, 'name' => 'Main gate',
            'secret_hash' => hash('sha256', 'first-gate'), 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $futureGate = DB::table('scanner_devices')->insertGetId([
            'public_id' => (string) Str::uuid(), 'organizer_id' => 10, 'name' => 'Future gate',
            'secret_hash' => hash('sha256', 'future-gate'), 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $firstTicketPublicId = (string) Str::uuid();
        $firstTicket = DB::table('booking_tickets')->insertGetId([
            'public_id' => $firstTicketPublicId, 'booking_id' => 100, 'event_id' => $event->id,
            'organizer_id' => 10, 'sequence' => 1, 'status' => 'checked_in',
            'checked_in_at' => now()->subMinute(), 'checked_in_scanner_device_id' => $firstGate,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $futureTicket = DB::table('booking_tickets')->insertGetId([
            'public_id' => (string) Str::uuid(), 'booking_id' => 100, 'event_id' => $event->id,
            'organizer_id' => 10, 'sequence' => 2, 'status' => 'issued',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $first->update(['booking_ticket_id' => $firstTicket]);
        $second->update(['booking_ticket_id' => $futureTicket]);
        $sessionId = DB::table('event_registration_sessions')->insertGetId([
            'public_id' => (string) Str::ulid(), 'form_id' => 1, 'event_id' => $event->id,
            'organizer_id' => 10, 'title' => 'Opening plenary', 'starts_at' => now()->addMonth(),
            'ends_at' => now()->addMonth()->addHour(), 'is_required' => true, 'status' => 'published',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('event_registration_session_selections')->insert([
            'session_id' => $sessionId, 'submission_id' => $submission->id, 'attendee_id' => $first->id,
            'selected_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        EventRegistrationAnswer::create([
            'submission_id' => $submission->id, 'attendee_id' => null, 'question_id' => 11,
            'question_key' => 'organisation', 'question_label' => 'Organisation', 'question_type' => 'text',
            'value' => ['value' => '+Unsafe Corp'],
        ]);
        EventRegistrationAnswer::create([
            'submission_id' => $submission->id, 'attendee_id' => $first->id, 'question_id' => 12,
            'question_key' => 'diet', 'question_label' => 'Diet', 'question_type' => 'text',
            'value' => ['value' => '@Formula-like answer'],
        ]);
        EventRegistrationAnswer::create([
            'submission_id' => $submission->id, 'attendee_id' => null, 'question_id' => 13,
            'question_key' => 'authorization', 'question_label' => 'Authorization', 'question_type' => 'file',
            'value' => ['value' => [
                'file_public_id' => '01PRIVATEFILEEXPORT', 'original_name' => 'authorization.pdf',
                'mime_type' => 'application/pdf', 'size' => 1024,
            ]],
        ]);
        EventRegistrationAnswer::create([
            'submission_id' => $submission->id, 'attendee_id' => $first->id, 'question_id' => 14,
            'question_key' => 'organizer_contact_consent', 'question_label' => 'Organizer contact consent',
            'question_type' => 'consent', 'value' => ['value' => false],
        ]);
        $this->submission($event, 10, 'approved', now());
        $otherEvent = $this->event(20, 'Other Organizer Event');
        $this->submission($otherEvent, 20, 'approved', null);

        $export = app(DataExportService::class)->queueRegistrations(
            10, $event->id, ['status' => 'approved'], 'csv', true
        );

        $this->assertSame('event_registrations', $export->export_type);
        $this->assertSame(2, $export->row_count);
        $this->assertSame($submission->id, $export->filters['max_submission_id']);
        $this->assertCount(4, $export->filters['question_columns']);
        $this->assertNotEmpty($export->filters['admission_snapshot_at']);
        $rawFilters = (string) DB::table('data_exports')->where('id', $export->id)->value('filters');
        $this->assertStringNotContainsString('Organisation', $rawFilters);
        Queue::assertPushed(GenerateRegistrationExport::class, fn ($job) => $job->exportId === $export->id);

        DB::table('booking_tickets')->where('id', $futureTicket)->update([
            'status' => 'checked_in', 'checked_in_at' => now()->addMinute(),
            'checked_in_scanner_device_id' => $futureGate, 'updated_at' => now(),
        ]);

        (new GenerateRegistrationExport($export->id))->handle(app(RegistrationExportQueryService::class));
        $ready = $export->fresh();
        $this->assertSame('ready', $ready->status);
        Storage::disk('local')->assertExists($ready->path);
        $csv = Storage::disk('local')->get($ready->path);
        $this->assertStringContainsString('Corporate Summit', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString("'+Unsafe Corp", $csv);
        $this->assertStringContainsString("'@Formula-like answer", $csv);
        $this->assertStringContainsString('authorization.pdf (private file 01PRIVATEFILEEXPORT)', $csv);
        $this->assertStringContainsString('[Attendee] Organizer contact consent', $csv);
        $this->assertStringContainsString('No', $csv);
        $this->assertStringContainsString('Opening plenary', $csv);
        $this->assertStringContainsString('"Registration Type","Ticket Option","Attendee Name"', $csv);
        $this->assertStringContainsString('"Ticket Unit","Admission Status","Checked In At","Check-in Gate"', $csv);
        $this->assertStringContainsString($firstTicketPublicId, $csv);
        $this->assertStringContainsString('Checked in', $csv);
        $this->assertStringContainsString('Main gate', $csv);
        $this->assertStringNotContainsString('Future gate', $csv);
        $this->assertStringNotContainsString('Other Organizer Event', $csv);
        $this->assertDatabaseHas('audit_logs', ['action' => 'data_export.ready', 'subject_id' => $export->id]);
    }

    public function test_registration_export_requires_owner_scope_and_explicit_privacy_confirmation(): void
    {
        $event = $this->event(10, 'Private Summit');
        $this->submission($event, 10, 'submitted', null);

        foreach ([[20, true], [10, false]] as [$organizerId, $confirmed]) {
            try {
                app(DataExportService::class)->queueRegistrations($organizerId, $event->id, [], 'csv', $confirmed);
                $this->fail('The export boundary should reject this request.');
            } catch (DomainException) {
                $this->assertDatabaseCount('data_exports', 0);
            }
        }
    }

    private function event(int $organizerId, string $title): Event
    {
        $event = Event::create([
            'organizer_id' => $organizerId, 'status' => 1, 'lifecycle_status' => 'scheduled',
            'event_type' => 'venue', 'registration_mode' => 'corporate',
            'start_date' => now()->addMonth()->toDateString(), 'start_time' => '09:00:00',
            'end_date' => now()->addMonth()->toDateString(), 'end_date_time' => now()->addMonth(),
        ]);
        DB::table('event_contents')->insert(['event_id' => $event->id, 'title' => $title, 'created_at' => now(), 'updated_at' => now()]);

        return $event;
    }

    private function submission(Event $event, int $organizerId, string $status, $anonymizedAt): EventRegistrationSubmission
    {
        return EventRegistrationSubmission::create([
            'public_id' => (string) Str::ulid(), 'form_id' => 1, 'event_id' => $event->id,
            'organizer_id' => $organizerId, 'form_version' => 2, 'status' => $status,
            'primary_name' => 'Primary Contact', 'primary_email' => 'primary@example.com',
            'primary_email_hash' => hash('sha256', 'primary@example.com'), 'attendee_count' => 2,
            'consent_version' => 'test-v1', 'consented_at' => now(), 'submitted_at' => now(),
            'anonymized_at' => $anonymizedAt, 'anonymization_reason' => $anonymizedAt ? 'retention_policy' : null,
        ]);
    }

    private function attendee(EventRegistrationSubmission $submission, int $sequence, string $name): EventRegistrationAttendee
    {
        return EventRegistrationAttendee::create([
            'public_id' => (string) Str::ulid(), 'submission_id' => $submission->id, 'sequence' => $sequence,
            'name' => $name, 'email' => "delegate{$sequence}@example.com",
            'email_hash' => hash('sha256', "delegate{$sequence}@example.com"), 'status' => 'registered',
        ]);
    }

    private function schema(): void
    {
        Schema::create('events', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('organizer_id'); $table->boolean('status');
            $table->string('lifecycle_status'); $table->string('event_type'); $table->string('registration_mode');
            $table->date('start_date'); $table->time('start_time'); $table->date('end_date')->nullable();
            $table->timestamp('end_date_time')->nullable(); $table->timestamps();
        });
        Schema::create('event_contents', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('event_id'); $table->string('title'); $table->timestamps();
        });
        Schema::create('event_registration_submissions', function (Blueprint $table): void {
            $table->id(); $table->string('public_id')->unique(); $table->unsignedBigInteger('form_id');
            $table->unsignedBigInteger('event_id'); $table->unsignedBigInteger('organizer_id');
            $table->unsignedBigInteger('customer_id')->nullable(); $table->unsignedBigInteger('booking_id')->nullable();
            $table->unsignedInteger('form_version'); $table->string('status'); $table->text('primary_name');
            $table->text('primary_email'); $table->char('primary_email_hash', 64); $table->unsignedSmallInteger('attendee_count');
            $table->string('consent_version'); $table->timestamp('consented_at'); $table->char('source_ip_hash', 64)->nullable();
            $table->timestamp('submitted_at'); $table->unsignedBigInteger('reviewed_by_organizer_id')->nullable();
            $table->timestamp('reviewed_at')->nullable(); $table->text('review_note')->nullable();
            $table->timestamp('anonymized_at')->nullable(); $table->string('anonymization_reason')->nullable(); $table->timestamps();
        });
        Schema::create('event_registration_attendees', function (Blueprint $table): void {
            $table->id(); $table->string('public_id')->unique(); $table->unsignedBigInteger('submission_id');
            $table->unsignedBigInteger('ticket_id')->nullable(); $table->unsignedBigInteger('booking_ticket_id')->nullable();
            $table->unsignedSmallInteger('sequence'); $table->text('name'); $table->text('email')->nullable();
            $table->char('email_hash', 64)->nullable(); $table->string('status'); $table->timestamps();
        });
        Schema::create('scanner_devices', function (Blueprint $table): void {
            $table->id(); $table->uuid('public_id')->unique(); $table->unsignedBigInteger('organizer_id');
            $table->string('name'); $table->char('secret_hash', 64); $table->string('status');
            $table->timestamp('last_seen_at')->nullable(); $table->string('last_ip')->nullable();
            $table->text('last_user_agent')->nullable(); $table->timestamp('revoked_at')->nullable();
            $table->unsignedBigInteger('revoked_by_organizer_id')->nullable(); $table->timestamps();
        });
        Schema::create('booking_tickets', function (Blueprint $table): void {
            $table->id(); $table->uuid('public_id')->unique(); $table->unsignedBigInteger('booking_id');
            $table->unsignedBigInteger('event_id'); $table->unsignedBigInteger('organizer_id')->nullable();
            $table->unsignedBigInteger('ticket_id')->nullable(); $table->unsignedInteger('sequence');
            $table->string('variation_name')->nullable(); $table->string('holder_name')->nullable();
            $table->string('holder_email')->nullable(); $table->unsignedBigInteger('holder_customer_id')->nullable();
            $table->string('status'); $table->timestamp('checked_in_at')->nullable();
            $table->unsignedBigInteger('checked_in_by_organizer_id')->nullable();
            $table->unsignedBigInteger('checked_in_scanner_device_id')->nullable();
            $table->timestamp('revoked_at')->nullable(); $table->string('revoked_reason')->nullable();
            $table->timestamps();
        });
        Schema::create('event_registration_answers', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('submission_id'); $table->unsignedBigInteger('attendee_id')->nullable();
            $table->unsignedBigInteger('question_id'); $table->string('question_key'); $table->string('question_label');
            $table->string('question_type'); $table->text('value')->nullable(); $table->timestamps();
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
            $table->string('cancellation_reason')->nullable(); $table->timestamps();
        });
        Schema::create('data_exports', function (Blueprint $table): void {
            $table->id(); $table->uuid('public_id')->unique(); $table->string('requested_by_type');
            $table->unsignedBigInteger('requested_by_id'); $table->unsignedBigInteger('organizer_id')->nullable();
            $table->string('export_type'); $table->string('format'); $table->string('status'); $table->longText('filters')->nullable();
            $table->string('disk'); $table->string('path')->nullable(); $table->string('filename');
            $table->unsignedBigInteger('row_count'); $table->integer('attempts')->default(0); $table->text('last_error')->nullable();
            $table->timestamp('started_at')->nullable(); $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable(); $table->timestamp('downloaded_at')->nullable(); $table->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id(); $table->string('actor_type'); $table->unsignedBigInteger('actor_id')->nullable(); $table->string('action');
            $table->string('subject_type')->nullable(); $table->unsignedBigInteger('subject_id')->nullable(); $table->json('before')->nullable();
            $table->json('after')->nullable(); $table->json('metadata')->nullable(); $table->string('request_id')->nullable();
            $table->string('ip_address')->nullable(); $table->text('user_agent')->nullable(); $table->timestamp('created_at')->nullable();
        });
    }
}
