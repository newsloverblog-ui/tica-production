<?php

namespace Tests\Unit;

use App\Jobs\SendEventGuestInvitation;
use App\Mail\EventGuestInvitationMail;
use App\Models\Event;
use App\Models\EventGuestInvitation;
use App\Services\EventGuestListService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EventGuestListServiceTest extends TestCase
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
        config()->set('tickets.guest_invitation_days', 14);
        config()->set('tickets.guest_invitation_attestation_version', 'guest-test-v1');
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->createSchema();
    }

    public function test_invitation_is_owner_scoped_attested_encrypted_and_queued(): void
    {
        Queue::fake();
        $event = $this->event(41);

        $invitation = app(EventGuestListService::class)->invite($event, 41, [
            'name' => 'Ada Guest',
            'email' => 'ADA@example.com',
            'party_size' => 3,
            'source_attested' => true,
        ], '192.0.2.5');

        $fresh = $invitation->fresh();
        $raw = DB::table('event_guest_invitations')->where('id', $invitation->id)->first();
        $this->assertSame('ada@example.com', $fresh->email);
        $this->assertSame('Ada Guest', $fresh->name);
        $this->assertNotSame('ada@example.com', $raw->email);
        $this->assertNotSame('Ada Guest', $raw->name);
        $this->assertSame(hash_hmac('sha256', 'ada@example.com', (string) config('app.key')), $raw->email_hash);
        $this->assertSame('guest-test-v1', $fresh->source_attestation_version);
        $this->assertNotNull($fresh->source_attested_at);
        $this->assertNotNull($fresh->source_attested_ip_hash);
        $this->assertSame('pending', $fresh->delivery_status);
        $this->assertSame(1, $fresh->dispatch_attempts);
        $this->assertNotNull($fresh->dispatch_last_attempt_at);
        $this->assertNotSame($fresh->dispatch_token, $raw->dispatch_token);
        Queue::assertPushed(SendEventGuestInvitation::class, function ($job) use ($invitation): bool {
            return $job->invitationId === $invitation->id
                && $job instanceof \Illuminate\Contracts\Queue\ShouldBeEncrypted;
        });
        $this->assertDatabaseHas('audit_logs', ['action' => 'event_guest_invitation.created']);
    }

    public function test_an_organizer_cannot_invite_or_manage_another_organizers_guests(): void
    {
        Queue::fake();
        $event = $this->event(10);
        $service = app(EventGuestListService::class);

        try {
            $service->invite($event, 11, $this->payload());
            $this->fail('Cross-organizer invitation should have been rejected.');
        } catch (AuthorizationException) {
            $this->assertSame(0, EventGuestInvitation::count());
        }

        $invitation = $service->invite($event, 10, $this->payload());
        $this->expectException(AuthorizationException::class);
        $service->cancel($invitation, 11);
    }

    public function test_acceptance_requires_consent_and_records_marketing_separately(): void
    {
        Queue::fake();
        $invitation = app(EventGuestListService::class)->invite($this->event(9), 9, $this->payload());
        $job = Queue::pushed(SendEventGuestInvitation::class)->first();

        try {
            app(EventGuestListService::class)->respond($invitation, $job->token, 'accepted', false, true);
            $this->fail('Acceptance without RSVP consent should have failed.');
        } catch (ValidationException) {
            $this->assertSame('pending', $invitation->fresh()->status);
        }

        $accepted = app(EventGuestListService::class)->respond($invitation, $job->token, 'accepted', true, true);
        $this->assertSame('accepted', $accepted->status);
        $this->assertNotNull($accepted->rsvp_consent_at);
        $this->assertNotNull($accepted->marketing_consent_at);
        $this->assertNotNull($accepted->responded_at);

        $same = app(EventGuestListService::class)->respond($accepted, $job->token, 'accepted', true, true);
        $this->assertSame($accepted->responded_at->toISOString(), $same->responded_at->toISOString());

        $this->expectException(AuthorizationException::class);
        app(EventGuestListService::class)->respond($accepted, 'tampered-token', 'accepted', true, false);
    }

    public function test_decline_check_in_and_cancellation_rules_are_enforced(): void
    {
        Queue::fake();
        $service = app(EventGuestListService::class);
        $declined = $service->invite($this->event(8), 8, $this->payload());
        $declineJob = Queue::pushed(SendEventGuestInvitation::class)->first();
        $declined = $service->respond($declined, $declineJob->token, 'declined', false, true);
        $this->assertSame('declined', $declined->status);
        $this->assertNull($declined->rsvp_consent_at);
        $this->assertNull($declined->marketing_consent_at);

        try {
            $service->checkIn($declined, 8);
            $this->fail('A declined guest cannot be checked in.');
        } catch (ValidationException) {
            $this->assertNull($declined->fresh()->checked_in_at);
        }

        $accepted = $service->invite($this->event(12), 12, [
            ...$this->payload(),
            'email' => 'accepted@example.com',
        ]);
        $acceptJob = Queue::pushed(SendEventGuestInvitation::class)->last();
        $accepted = $service->respond($accepted, $acceptJob->token, 'accepted', true, false);
        $checkedIn = $service->checkIn($accepted, 12);
        $checkedInAgain = $service->checkIn($checkedIn, 12);
        $this->assertNotNull($checkedIn->checked_in_at);
        $this->assertSame($checkedIn->checked_in_at->toISOString(), $checkedInAgain->checked_in_at->toISOString());

        $this->expectException(ValidationException::class);
        $service->cancel($checkedInAgain, 12);
    }

    public function test_delivery_uses_a_signed_url_and_is_suppressed_after_event_cancellation(): void
    {
        Queue::fake();
        Mail::fake();
        $service = app(EventGuestListService::class);

        $sentInvitation = $service->invite($this->event(4, 'Launch Night'), 4, $this->payload());
        $sentJob = Queue::pushed(SendEventGuestInvitation::class)->first();
        app()->call([$sentJob, 'handle']);

        $this->assertSame('sent', $sentInvitation->fresh()->delivery_status);
        $this->assertNull($sentInvitation->fresh()->dispatch_token);
        $this->assertSame(1, $sentInvitation->fresh()->delivery_attempts);
        Mail::assertSent(EventGuestInvitationMail::class, function (EventGuestInvitationMail $mail): bool {
            return str_contains($mail->rsvpUrl, 'signature=')
                && str_contains($mail->rsvpUrl, '/event-rsvp/')
                && $mail->eventTitle === 'Launch Night';
        });

        Mail::fake();
        Queue::fake();
        $cancelledEvent = $this->event(5, 'Cancelled Event');
        $cancelledInvitation = $service->invite($cancelledEvent, 5, [
            ...$this->payload(),
            'email' => 'cancelled@example.com',
        ]);
        $cancelledJob = Queue::pushed(SendEventGuestInvitation::class)->first();
        $cancelledEvent->update(['status' => 0, 'lifecycle_status' => 'cancelled', 'cancelled_at' => now()]);
        app()->call([$cancelledJob, 'handle']);

        $this->assertSame('cancelled', $cancelledInvitation->fresh()->status);
        $this->assertSame('suppressed', $cancelledInvitation->fresh()->delivery_status);
        $this->assertNull($cancelledInvitation->fresh()->dispatch_token);
        Mail::assertNothingSent();
    }

    public function test_failed_queue_handoff_is_durable_and_recovered_by_the_poller(): void
    {
        Queue::fake();
        $service = new class extends EventGuestListService {
            public bool $failDispatch = true;

            protected function pushInvitationJob(int $invitationId, string $token): void
            {
                if ($this->failDispatch) {
                    throw new \RuntimeException('queue unavailable');
                }
                parent::pushInvitationJob($invitationId, $token);
            }
        };

        $invitation = $service->invite($this->event(23), 23, $this->payload());
        $failed = $invitation->fresh();
        $this->assertSame('pending', $failed->delivery_status);
        $this->assertSame(1, $failed->dispatch_attempts);
        $this->assertSame('queue unavailable', $failed->dispatch_last_error);
        $this->assertNotNull($failed->dispatch_token);
        Queue::assertNothingPushed();

        $service->failDispatch = false;
        $this->assertTrue($service->dispatchInvitation($invitation->id));
        $this->assertSame(2, $invitation->fresh()->dispatch_attempts);
        $this->assertNull($invitation->fresh()->dispatch_last_error);
        Queue::assertPushed(SendEventGuestInvitation::class, fn ($job): bool =>
            $job->invitationId === $invitation->id
        );

        Queue::fake();
        $this->artisan('guests:dispatch-invitations')
            ->expectsOutputToContain('Guest invitation dispatch attempted: 1')
            ->assertSuccessful();
        Queue::assertPushed(SendEventGuestInvitation::class, fn ($job): bool =>
            $job->invitationId === $invitation->id
        );
    }

    public function test_poller_retires_an_expired_pending_token_without_dispatching_it(): void
    {
        Queue::fake();
        $invitation = app(EventGuestListService::class)->invite(
            $this->event(24),
            24,
            [...$this->payload(), 'email' => 'expired@example.com']
        );
        $invitation->forceFill(['expires_at' => now()->subMinute()])->save();

        Queue::fake();
        $this->artisan('guests:dispatch-invitations')
            ->expectsOutputToContain('expired: 1')
            ->assertSuccessful();

        $expired = $invitation->fresh();
        $this->assertSame('expired', $expired->status);
        $this->assertSame('expired', $expired->delivery_status);
        $this->assertNull($expired->dispatch_token);
        Queue::assertNothingPushed();
    }

    private function payload(): array
    {
        return [
            'name' => 'Test Guest',
            'email' => 'guest@example.com',
            'party_size' => 2,
            'source_attested' => true,
        ];
    }

    private function event(int $organizerId, string $title = 'Test Event'): Event
    {
        $event = Event::create([
            'organizer_id' => $organizerId,
            'status' => 1,
            'lifecycle_status' => 'scheduled',
            'end_date_time' => now()->addWeek(),
        ]);
        DB::table('event_contents')->insert([
            'event_id' => $event->id,
            'language_id' => 1,
            'title' => $title,
            'slug' => 'event-'.$event->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $event;
    }

    private function createSchema(): void
    {
        Schema::create('events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organizer_id')->nullable();
            $table->boolean('status')->default(true);
            $table->string('lifecycle_status')->default('scheduled');
            $table->timestamp('sales_closed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('end_date_time')->nullable();
            $table->timestamps();
        });
        Schema::create('event_contents', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('language_id');
            $table->string('title');
            $table->string('slug');
            $table->timestamps();
        });
        Schema::create('event_guest_invitations', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id', 26)->unique();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('organizer_id');
            $table->text('name');
            $table->text('email');
            $table->char('email_hash', 64);
            $table->unsignedSmallInteger('party_size')->default(1);
            $table->string('status', 24)->default('pending');
            $table->string('delivery_status', 24)->default('pending');
            $table->char('token_hash', 64);
            $table->text('dispatch_token')->nullable();
            $table->unsignedSmallInteger('dispatch_attempts')->default(0);
            $table->timestamp('dispatch_last_attempt_at')->nullable();
            $table->text('dispatch_last_error')->nullable();
            $table->string('source_attestation_version', 32);
            $table->timestamp('source_attested_at');
            $table->string('source_attested_ip_hash', 64)->nullable();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('rsvp_consent_at')->nullable();
            $table->timestamp('marketing_consent_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->unsignedBigInteger('checked_in_by_organizer_id')->nullable();
            $table->unsignedSmallInteger('delivery_attempts')->default(0);
            $table->timestamp('processing_at')->nullable();
            $table->timestamp('alerted_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'email_hash']);
        });
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('actor_type');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->json('metadata')->nullable();
            $table->string('request_id')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }
}
