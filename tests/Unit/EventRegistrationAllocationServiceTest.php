<?php

namespace Tests\Unit;

use App\Jobs\SendEventRegistrationAllocationInvitation;
use App\Jobs\SendEventRegistrationSponsorAccess;
use App\Mail\EventRegistrationAllocationInvitationMail;
use App\Mail\EventRegistrationSponsorAccessMail;
use App\Models\Event;
use App\Models\EventRegistrationAllocationClaim;
use App\Models\EventRegistrationForm;
use App\Models\Event\Ticket;
use App\Services\EventRegistrationAllocationService;
use App\Services\EventRegistrationService;
use App\Services\EventRegistrationRetentionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class EventRegistrationAllocationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]);
        config()->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        config()->set('registration.allocation_max_capacity', 10);
        config()->set('registration.max_attendees_per_submission', 10);
        DB::purge('sqlite'); DB::setDefaultConnection('sqlite');
        $this->schema();
    }

    public function test_sponsor_allocation_is_private_capacity_bounded_and_attendee_completed(): void
    {
        Queue::fake(); Mail::fake();
        [$event, $form] = $this->publishedForm();
        $service = app(EventRegistrationAllocationService::class);
        $allocation = $service->create($form, 10, [
            'sponsor_name' => 'Northwind', 'sponsor_email' => 'manager@northwind.test',
            'capacity' => 1, 'source_attested' => true, 'expires_at' => now()->addWeek()->toDateString(),
        ], '127.0.0.1');
        $this->assertSame('active', $allocation->status);
        $this->assertNotSame('manager@northwind.test', DB::table('event_registration_allocations')->value('sponsor_email'));

        $sponsorJob = null;
        Queue::assertPushed(SendEventRegistrationSponsorAccess::class, function ($job) use (&$sponsorJob): bool {
            $sponsorJob = $job; return true;
        });
        $sponsorJob->handle();
        Mail::assertSent(EventRegistrationSponsorAccessMail::class, fn ($mail) => str_contains($mail->accessUrl, $allocation->public_id));
        $this->assertSame('sent', $allocation->fresh()->delivery_status);

        $claim = $service->invite($allocation->fresh(), $sponsorJob->token, [
            'name' => 'Ada Attendee', 'email' => 'ada@example.test', 'source_attested' => true,
        ], '127.0.0.1');
        $claimJob = null;
        Queue::assertPushed(SendEventRegistrationAllocationInvitation::class, function ($job) use (&$claimJob): bool {
            $claimJob = $job; return true;
        });
        $claimJob->handle();
        Mail::assertSent(EventRegistrationAllocationInvitationMail::class, fn ($mail) => str_contains($mail->registrationUrl, $claim->public_id));

        try {
            $service->invite($allocation->fresh(), $sponsorJob->token, [
                'name' => 'Second Person', 'email' => 'second@example.test', 'source_attested' => true,
            ]);
            $this->fail('The allocation capacity must be enforced under the database lock.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('event_registration_allocation_claims', 1);
        }

        $submission = $service->submitClaim($claim->fresh(), $claimJob->token, [
            'registrant' => ['name' => 'Tampered', 'email' => 'wrong@example.test'],
            'attendees' => [
                ['name' => 'Tampered', 'email' => 'wrong@example.test'],
                ['name' => 'Injected second attendee', 'email' => 'second@example.test'],
            ],
            'consent' => true,
        ], null, '127.0.0.1', app(EventRegistrationService::class));
        $this->assertSame($claim->id, $submission->source_allocation_claim_id);
        $this->assertSame('Ada Attendee', $submission->primary_name);
        $this->assertSame('ada@example.test', $submission->primary_email);
        $this->assertSame(1, $submission->attendees()->count());
        $this->assertDatabaseHas('event_registration_allocation_claims', [
            'id' => $claim->id, 'status' => 'completed', 'submission_id' => $submission->id,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'event.registration_allocation.completed', 'subject_id' => $claim->id]);

        $this->expectException(ValidationException::class);
        $service->submitClaim($claim->fresh(), $claimJob->token, ['consent' => true], null, null, app(EventRegistrationService::class));
    }

    public function test_allocation_ownership_attestation_and_cancellation_are_enforced(): void
    {
        Queue::fake();
        [, $form] = $this->publishedForm();
        $service = app(EventRegistrationAllocationService::class);
        foreach ([
            [99, ['sponsor_name' => 'Sponsor', 'sponsor_email' => 'sponsor@example.test', 'capacity' => 2, 'source_attested' => true]],
            [10, ['sponsor_name' => 'Sponsor', 'sponsor_email' => 'sponsor@example.test', 'capacity' => 2]],
        ] as [$organizerId, $payload]) {
            try { $service->create($form, $organizerId, $payload); $this->fail('Boundary should reject request.'); }
            catch (\Throwable) { $this->assertDatabaseCount('event_registration_allocations', 0); }
        }
        $allocation = $service->create($form, 10, [
            'sponsor_name' => 'Sponsor', 'sponsor_email' => 'sponsor@example.test', 'capacity' => 2,
            'source_attested' => true,
        ]);
        $job = null; Queue::assertPushed(SendEventRegistrationSponsorAccess::class, function ($queued) use (&$job) { $job = $queued; return true; });
        $claim = $service->invite($allocation, $job->token, ['name' => 'Person', 'email' => 'person@example.test', 'source_attested' => true]);
        $service->cancel($allocation, 10);
        $this->assertSame('cancelled', $allocation->fresh()->status);
        $this->assertSame('cancelled', $claim->fresh()->status);
        $this->assertSame('suppressed', $claim->fresh()->delivery_status);
    }

    public function test_lost_allocation_handoffs_are_encrypted_and_recovered_without_new_records(): void
    {
        Queue::fake();
        [, $form] = $this->publishedForm();
        $failingSponsor = new class extends EventRegistrationAllocationService {
            protected function pushSponsorAccessJob(int $allocationId, string $token): void
            {
                throw new RuntimeException('sponsor queue unavailable');
            }
        };

        $allocation = $failingSponsor->create($form, 10, [
            'sponsor_name' => 'Recovery Sponsor',
            'sponsor_email' => 'recovery@sponsor.test',
            'capacity' => 2,
            'source_attested' => true,
        ]);
        $failedAllocation = $allocation->fresh();
        $this->assertSame(1, $failedAllocation->dispatch_attempts);
        $this->assertStringContainsString('sponsor queue unavailable', $failedAllocation->dispatch_last_error);
        $this->assertNotSame(
            $failedAllocation->dispatch_token,
            DB::table('event_registration_allocations')->where('id', $allocation->id)->value('dispatch_token')
        );
        Queue::assertNotPushed(SendEventRegistrationSponsorAccess::class);

        $this->artisan('registrations:dispatch-allocations')->assertSuccessful();

        Queue::assertPushed(SendEventRegistrationSponsorAccess::class, fn ($job): bool =>
            $job->allocationId === $allocation->id
        );
        $recoveredAllocation = $allocation->fresh();
        $this->assertSame(2, $recoveredAllocation->dispatch_attempts);
        $this->assertNull($recoveredAllocation->dispatch_last_error);

        $failingClaim = new class extends EventRegistrationAllocationService {
            protected function pushClaimInvitationJob(int $claimId, string $token): void
            {
                throw new RuntimeException('claim queue unavailable');
            }
        };
        $claim = $failingClaim->invite(
            $recoveredAllocation,
            $recoveredAllocation->dispatch_token,
            [
                'name' => 'Recovery Attendee',
                'email' => 'recovery@attendee.test',
                'source_attested' => true,
            ]
        );
        $failedClaim = $claim->fresh();
        $this->assertSame(1, $failedClaim->dispatch_attempts);
        $this->assertStringContainsString('claim queue unavailable', $failedClaim->dispatch_last_error);
        $this->assertNotSame(
            $failedClaim->dispatch_token,
            DB::table('event_registration_allocation_claims')->where('id', $claim->id)->value('dispatch_token')
        );
        Queue::assertNotPushed(SendEventRegistrationAllocationInvitation::class);

        $this->artisan('registrations:dispatch-allocations')->assertSuccessful();

        Queue::assertPushed(SendEventRegistrationAllocationInvitation::class, fn ($job): bool =>
            $job->claimId === $claim->id
        );
        $recoveredClaim = $claim->fresh();
        $this->assertSame(2, $recoveredClaim->dispatch_attempts);
        $this->assertNull($recoveredClaim->dispatch_last_error);
        $this->assertDatabaseCount('event_registration_allocations', 1);
        $this->assertDatabaseCount('event_registration_allocation_claims', 1);
    }

    public function test_allocated_attendee_can_choose_an_explicit_ticket_variation(): void
    {
        Queue::fake();
        [$event, $form] = $this->publishedForm();
        $ticket = Ticket::create([
            'event_id' => $event->id,
            'title' => 'Delegate',
            'pricing_type' => 'variation',
            'variations' => json_encode([
                ['name' => 'Standard', 'price' => 5000],
                ['name' => 'VIP', 'price' => 12000],
            ]),
        ]);
        $service = app(EventRegistrationAllocationService::class);
        $allocation = $service->create($form, 10, [
            'sponsor_name' => 'Variation Sponsor', 'sponsor_email' => 'sponsor@example.test',
            'capacity' => 1, 'source_attested' => true,
        ]);
        $sponsorJob = null;
        Queue::assertPushed(SendEventRegistrationSponsorAccess::class, function ($queued) use (&$sponsorJob): bool {
            $sponsorJob = $queued;
            return true;
        });
        $claim = $service->invite($allocation, $sponsorJob->token, [
            'name' => 'Allocated VIP', 'email' => 'vip@example.test', 'source_attested' => true,
        ]);
        $claimJob = null;
        Queue::assertPushed(SendEventRegistrationAllocationInvitation::class, function ($queued) use (&$claimJob): bool {
            $claimJob = $queued;
            return true;
        });

        $submission = $service->submitClaim($claim, $claimJob->token, [
            'attendees' => [[
                'ticket_id' => $ticket->id,
                'variation_name' => 'VIP',
            ]],
            'consent' => true,
        ], null, '127.0.0.1', app(EventRegistrationService::class));

        $this->assertDatabaseHas('event_registration_attendees', [
            'submission_id' => $submission->id,
            'ticket_id' => $ticket->id,
            'variation_name' => 'VIP',
        ]);
    }

    public function test_terminal_allocations_are_anonymized_only_after_the_retention_window(): void
    {
        Queue::fake();
        [, $form] = $this->publishedForm();
        $service = app(EventRegistrationAllocationService::class);
        $allocation = $service->create($form, 10, [
            'sponsor_name' => 'Private Sponsor', 'sponsor_email' => 'private@sponsor.test',
            'capacity' => 1, 'source_attested' => true,
        ]);
        $job = null; Queue::assertPushed(SendEventRegistrationSponsorAccess::class, function ($queued) use (&$job) { $job = $queued; return true; });
        $claim = $service->invite($allocation, $job->token, [
            'name' => 'Private Attendee', 'email' => 'private@attendee.test', 'source_attested' => true,
        ]);
        $service->cancel($allocation, 10);
        $allocation->forceFill(['delivery_status' => 'sent', 'created_at' => now()->subDays(100)])->save();
        $claim->forceFill(['created_at' => now()->subDays(100)])->save();

        $retention = app(EventRegistrationRetentionService::class);
        $this->assertSame(1, $retention->eligibleAllocationQuery(90)->count());
        $this->assertSame(1, $retention->anonymizeEligibleAllocations(90));
        $this->assertSame('Anonymized sponsor', $allocation->fresh()->sponsor_name);
        $this->assertSame('Anonymized attendee', $claim->fresh()->name);
        $this->assertNotNull($allocation->fresh()->anonymized_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'event.registration_allocation_anonymized', 'subject_id' => $allocation->id]);
    }

    private function publishedForm(): array
    {
        $event = Event::create([
            'organizer_id' => 10, 'status' => 1, 'lifecycle_status' => 'scheduled', 'event_type' => 'venue',
            'registration_mode' => 'corporate', 'start_date' => now()->addMonth()->toDateString(), 'start_time' => '09:00:00',
        ]);
        DB::table('event_contents')->insert(['event_id' => $event->id, 'title' => 'Partner summit', 'created_at' => now(), 'updated_at' => now()]);
        $form = EventRegistrationForm::create([
            'public_id' => (string) Str::ulid(), 'event_id' => $event->id, 'organizer_id' => 10,
            'name' => 'Partner summit', 'status' => 'published', 'approval_mode' => 'manual', 'version' => 1,
            'privacy_notice' => 'Used only to administer the summit.', 'consent_version' => 'test-v1', 'published_at' => now(),
        ]);
        return [$event, $form];
    }

    private function schema(): void
    {
        Schema::create('events', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('organizer_id'); $t->boolean('status'); $t->string('lifecycle_status'); $t->string('event_type'); $t->string('registration_mode'); $t->timestamp('cancelled_at')->nullable(); $t->date('start_date'); $t->time('start_time'); $t->date('end_date')->nullable(); $t->timestamp('end_date_time')->nullable(); $t->timestamps(); });
        Schema::create('event_contents', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('event_id'); $t->string('title'); $t->timestamps(); });
        Schema::create('tickets', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('event_id'); $t->string('title');
            $t->string('pricing_type')->nullable(); $t->json('variations')->nullable(); $t->timestamps();
        });
        Schema::create('event_registration_forms', function (Blueprint $t) { $t->id(); $t->string('public_id')->unique(); $t->unsignedBigInteger('event_id'); $t->unsignedBigInteger('organizer_id'); $t->string('name'); $t->string('status'); $t->string('approval_mode'); $t->unsignedInteger('version'); $t->text('intro')->nullable(); $t->text('privacy_notice'); $t->string('consent_version'); $t->timestamp('published_at')->nullable(); $t->timestamp('archived_at')->nullable(); $t->timestamps(); });
        Schema::create('event_registration_questions', function (Blueprint $t) { $t->id(); $t->string('public_id')->unique(); $t->unsignedBigInteger('form_id'); $t->unsignedBigInteger('ticket_id')->nullable(); $t->string('question_key'); $t->string('label'); $t->text('help_text')->nullable(); $t->string('type'); $t->string('scope'); $t->boolean('is_required'); $t->json('options')->nullable(); $t->json('condition')->nullable(); $t->unsignedSmallInteger('position'); $t->timestamps(); });
        Schema::create('event_registration_allocations', function (Blueprint $t) { $t->id(); $t->string('public_id')->unique(); $t->unsignedBigInteger('form_id'); $t->unsignedBigInteger('event_id'); $t->unsignedBigInteger('organizer_id'); $t->text('sponsor_name'); $t->text('sponsor_email'); $t->char('sponsor_email_hash', 64); $t->unsignedInteger('capacity'); $t->string('status'); $t->char('access_token_hash', 64); $t->text('dispatch_token')->nullable(); $t->unsignedSmallInteger('dispatch_attempts')->default(0); $t->timestamp('dispatch_last_attempt_at')->nullable(); $t->text('dispatch_last_error')->nullable(); $t->string('attestation_version'); $t->timestamp('attested_at'); $t->char('attested_ip_hash', 64)->nullable(); $t->timestamp('expires_at'); $t->timestamp('invited_at')->nullable(); $t->timestamp('cancelled_at')->nullable(); $t->string('delivery_status'); $t->unsignedSmallInteger('delivery_attempts'); $t->timestamp('processing_at')->nullable(); $t->timestamp('alerted_at')->nullable(); $t->text('last_error')->nullable(); $t->timestamp('anonymized_at')->nullable(); $t->string('anonymization_reason')->nullable(); $t->timestamps(); $t->unique(['event_id', 'sponsor_email_hash']); });
        Schema::create('event_registration_allocation_claims', function (Blueprint $t) { $t->id(); $t->string('public_id')->unique(); $t->unsignedBigInteger('allocation_id'); $t->unsignedBigInteger('event_id'); $t->unsignedBigInteger('form_id'); $t->text('name'); $t->text('email'); $t->char('email_hash', 64); $t->char('token_hash', 64); $t->text('dispatch_token')->nullable(); $t->unsignedSmallInteger('dispatch_attempts')->default(0); $t->timestamp('dispatch_last_attempt_at')->nullable(); $t->text('dispatch_last_error')->nullable(); $t->string('source_attestation_version'); $t->timestamp('source_attested_at'); $t->char('source_attested_ip_hash', 64)->nullable(); $t->string('status'); $t->unsignedBigInteger('submission_id')->nullable()->unique(); $t->timestamp('expires_at'); $t->timestamp('invited_at')->nullable(); $t->timestamp('completed_at')->nullable(); $t->timestamp('cancelled_at')->nullable(); $t->string('delivery_status'); $t->unsignedSmallInteger('delivery_attempts'); $t->timestamp('processing_at')->nullable(); $t->timestamp('alerted_at')->nullable(); $t->text('last_error')->nullable(); $t->timestamp('anonymized_at')->nullable(); $t->string('anonymization_reason')->nullable(); $t->timestamps(); $t->unique(['allocation_id', 'email_hash']); });
        Schema::create('event_registration_submissions', function (Blueprint $t) { $t->id(); $t->string('public_id')->unique(); $t->unsignedBigInteger('form_id'); $t->unsignedBigInteger('event_id'); $t->unsignedBigInteger('organizer_id'); $t->unsignedBigInteger('customer_id')->nullable(); $t->unsignedBigInteger('booking_id')->nullable(); $t->unsignedInteger('form_version'); $t->string('status'); $t->text('primary_name'); $t->text('primary_email'); $t->char('primary_email_hash', 64); $t->unsignedSmallInteger('attendee_count'); $t->string('consent_version'); $t->timestamp('consented_at'); $t->char('source_ip_hash', 64)->nullable(); $t->unsignedBigInteger('source_import_id')->nullable(); $t->string('source_import_key')->nullable(); $t->unsignedBigInteger('source_allocation_claim_id')->nullable()->unique(); $t->timestamp('submitted_at'); $t->unsignedBigInteger('reviewed_by_organizer_id')->nullable(); $t->timestamp('reviewed_at')->nullable(); $t->text('review_note')->nullable(); $t->timestamp('anonymized_at')->nullable(); $t->string('anonymization_reason')->nullable(); $t->timestamps(); });
        Schema::create('event_registration_attendees', function (Blueprint $t) { $t->id(); $t->string('public_id')->unique(); $t->unsignedBigInteger('submission_id'); $t->unsignedBigInteger('ticket_id')->nullable(); $t->string('variation_name')->nullable(); $t->unsignedBigInteger('booking_ticket_id')->nullable(); $t->unsignedSmallInteger('sequence'); $t->text('name'); $t->text('email')->nullable(); $t->char('email_hash', 64)->nullable(); $t->string('status'); $t->timestamps(); });
        Schema::create('event_registration_answers', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('submission_id'); $t->unsignedBigInteger('attendee_id')->nullable(); $t->unsignedBigInteger('question_id'); $t->string('question_key'); $t->string('question_label'); $t->string('question_type'); $t->text('value')->nullable(); $t->timestamps(); });
        Schema::create('event_registration_notifications', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('submission_id'); $t->unsignedBigInteger('event_id'); $t->string('kind'); $t->string('status'); $t->unsignedSmallInteger('attempts')->default(0); $t->unsignedSmallInteger('dispatch_attempts')->default(0); $t->timestamp('dispatch_last_attempt_at')->nullable(); $t->text('dispatch_last_error')->nullable(); $t->timestamp('processing_at')->nullable(); $t->timestamp('sent_at')->nullable(); $t->timestamp('alerted_at')->nullable(); $t->text('last_error')->nullable(); $t->timestamps(); $t->unique(['submission_id', 'kind']); });
        Schema::create('audit_logs', function (Blueprint $t) { $t->id(); $t->string('actor_type'); $t->unsignedBigInteger('actor_id')->nullable(); $t->string('action'); $t->string('subject_type')->nullable(); $t->unsignedBigInteger('subject_id')->nullable(); $t->json('before')->nullable(); $t->json('after')->nullable(); $t->json('metadata')->nullable(); $t->string('request_id')->nullable(); $t->string('ip_address')->nullable(); $t->text('user_agent')->nullable(); $t->timestamp('created_at')->nullable(); });
    }
}
