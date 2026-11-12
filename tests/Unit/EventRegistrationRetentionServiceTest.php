<?php

namespace Tests\Unit;

use App\Models\Event;
use App\Models\EventRegistrationAnswer;
use App\Models\EventRegistrationAttendee;
use App\Models\EventRegistrationFile;
use App\Models\EventRegistrationNotification;
use App\Models\EventRegistrationSubmission;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class EventRegistrationRetentionServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        config()->set('registration.retention_days', 90);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->schema();
        Storage::fake('local');
    }

    public function test_retention_is_dry_run_by_default_and_anonymizes_only_terminal_eligible_records(): void
    {
        $pastEvent = $this->event(now()->subDays(100));
        $futureEvent = $this->event(now()->addDays(30));
        $rejected = $this->submission($pastEvent, 'rejected', 120);
        $pendingDelivery = $this->submission($pastEvent, 'cancelled', 120);
        $approvedPast = $this->submission($pastEvent, 'approved', 120);
        $approvedFuture = $this->submission($futureEvent, 'approved', 120);

        $attendee = EventRegistrationAttendee::create([
            'public_id' => (string) Str::ulid(), 'submission_id' => $rejected->id, 'sequence' => 1,
            'name' => 'Ada Delegate', 'email' => 'ada@example.com', 'email_hash' => hash('sha256', 'ada@example.com'),
            'status' => 'registered',
        ]);
        $fileAnswer = EventRegistrationAnswer::create([
            'submission_id' => $rejected->id, 'attendee_id' => $attendee->id, 'question_id' => 8,
            'question_key' => 'authorization', 'question_label' => 'Authorization', 'question_type' => 'file',
            'value' => ['value' => ['file_public_id' => '01PRIVATEFILE']],
        ]);
        Storage::disk('local')->put('registration-uploads/private.pdf', 'private document');
        EventRegistrationFile::create([
            'public_id' => (string) Str::ulid(), 'answer_id' => $fileAnswer->id,
            'submission_id' => $rejected->id, 'attendee_id' => $attendee->id, 'question_id' => 8,
            'disk' => 'local', 'path' => 'registration-uploads/private.pdf',
            'original_name' => 'authorization.pdf', 'mime_type' => 'application/pdf',
            'size' => 16, 'sha256' => hash('sha256', 'private document'),
        ]);
        EventRegistrationNotification::create([
            'submission_id' => $pendingDelivery->id, 'event_id' => $pastEvent->id,
            'kind' => 'cancelled', 'status' => 'pending',
        ]);

        $this->artisan('registrations:apply-retention --days=90')->assertSuccessful();
        $this->assertNull($rejected->fresh()->anonymized_at);

        $this->artisan('registrations:apply-retention --apply --days=90')->assertSuccessful();

        $this->assertNotNull($rejected->fresh()->anonymized_at);
        $this->assertSame('Anonymized registrant', $rejected->fresh()->primary_name);
        $this->assertSame('Anonymized attendee', $attendee->fresh()->name);
        $this->assertNull($attendee->fresh()->email);
        $this->assertSame(0, EventRegistrationAnswer::where('submission_id', $rejected->id)->count());
        $this->assertSame(0, EventRegistrationFile::where('submission_id', $rejected->id)->count());
        Storage::disk('local')->assertMissing('registration-uploads/private.pdf');
        $this->assertNotNull($approvedPast->fresh()->anonymized_at);
        $this->assertNull($pendingDelivery->fresh()->anonymized_at);
        $this->assertNull($approvedFuture->fresh()->anonymized_at);
        $this->assertSame(2, DB::table('audit_logs')->where('action', 'event.registration_anonymized')->count());
    }

    private function event($end): Event
    {
        return Event::create([
            'organizer_id' => 7, 'status' => 1, 'lifecycle_status' => 'scheduled',
            'event_type' => 'venue', 'registration_mode' => 'corporate',
            'start_date' => $end->copy()->subDay()->toDateString(), 'start_time' => '09:00:00',
            'end_date' => $end->toDateString(), 'end_date_time' => $end,
        ]);
    }

    private function submission(Event $event, string $status, int $ageDays): EventRegistrationSubmission
    {
        return EventRegistrationSubmission::create([
            'public_id' => (string) Str::ulid(), 'form_id' => 1, 'event_id' => $event->id,
            'organizer_id' => 7, 'form_version' => 1, 'status' => $status,
            'primary_name' => 'Private Person', 'primary_email' => 'private@example.com',
            'primary_email_hash' => hash('sha256', 'private@example.com'), 'attendee_count' => 1,
            'consent_version' => 'test-v1', 'consented_at' => now()->subDays($ageDays),
            'source_ip_hash' => hash('sha256', '192.0.2.1'), 'submitted_at' => now()->subDays($ageDays),
            'review_note' => 'Private review note',
        ]);
    }

    private function schema(): void
    {
        Schema::create('events', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('organizer_id'); $table->boolean('status');
            $table->string('lifecycle_status'); $table->string('event_type'); $table->string('registration_mode');
            $table->date('start_date')->nullable(); $table->time('start_time')->nullable(); $table->date('end_date')->nullable();
            $table->timestamp('end_date_time')->nullable(); $table->timestamps();
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
        Schema::create('event_registration_answers', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('submission_id'); $table->unsignedBigInteger('attendee_id')->nullable();
            $table->unsignedBigInteger('question_id'); $table->string('question_key'); $table->string('question_label');
            $table->string('question_type'); $table->text('value')->nullable(); $table->timestamps();
        });
        Schema::create('event_registration_files', function (Blueprint $table): void {
            $table->id(); $table->string('public_id')->unique(); $table->unsignedBigInteger('answer_id')->unique();
            $table->unsignedBigInteger('submission_id'); $table->unsignedBigInteger('attendee_id')->nullable();
            $table->unsignedBigInteger('question_id'); $table->string('disk'); $table->string('path')->unique();
            $table->text('original_name'); $table->string('mime_type'); $table->unsignedBigInteger('size');
            $table->char('sha256', 64); $table->timestamps();
        });
        Schema::create('event_registration_notifications', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('submission_id'); $table->unsignedBigInteger('event_id');
            $table->string('kind'); $table->string('status'); $table->integer('attempts')->default(0);
            $table->unsignedSmallInteger('dispatch_attempts')->default(0);
            $table->timestamp('dispatch_last_attempt_at')->nullable(); $table->text('dispatch_last_error')->nullable();
            $table->timestamp('processing_at')->nullable(); $table->timestamp('sent_at')->nullable();
            $table->timestamp('alerted_at')->nullable(); $table->text('last_error')->nullable(); $table->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id(); $table->string('actor_type'); $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action'); $table->string('subject_type')->nullable(); $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('before')->nullable(); $table->json('after')->nullable(); $table->json('metadata')->nullable();
            $table->string('request_id')->nullable(); $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable(); $table->timestamp('created_at')->nullable();
        });
    }
}
