<?php

namespace Tests\Unit;

use App\Jobs\SendEventWaitlistAvailability;
use App\Models\Event;
use App\Models\Event\Ticket;
use App\Models\Event\Booking;
use App\Models\EventWaitlistEntry;
use App\Services\EventWaitlistService;
use App\Services\WaitlistNotificationDispatchService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class EventWaitlistServiceTest extends TestCase
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
        config()->set('tickets.waitlist_notification_minutes', 30);
        config()->set('tickets.waitlist_dispatch_limit', 200);
        config()->set('tickets.waitlist_consent_version', 'test-v1');
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->createSchema();
    }

    public function test_registration_is_consented_encrypted_idempotent_and_only_for_sold_out_stock(): void
    {
        [$event, $ticket] = $this->fixture(0);
        $service = app(EventWaitlistService::class);

        $entry = $service->register($event, $ticket->id, null, 'Ada Guest', 'ADA@Example.com');
        $again = $service->register($event, $ticket->id, null, 'Ada Guest', 'ada@example.com');

        $this->assertSame($entry->id, $again->id);
        $this->assertSame(1, EventWaitlistEntry::count());
        $this->assertSame('ada@example.com', $entry->fresh()->email);
        $this->assertNotSame('ada@example.com', DB::table('event_waitlist_entries')->value('email'));
        $this->assertSame('test-v1', $entry->consent_version);
        $this->assertNotNull($entry->consented_at);

        $ticket->update(['ticket_available' => 1]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('available now');
        $service->register($event, $ticket->id, null, 'Another Guest', 'other@example.com');
    }

    public function test_waitlist_dispatch_is_fifo_and_never_invites_more_people_than_available_stock(): void
    {
        Queue::fake();
        [$event, $ticket] = $this->fixture(0);
        $service = app(EventWaitlistService::class);
        $first = $service->register($event, $ticket->id, null, 'First', 'first@example.com');
        $second = $service->register($event, $ticket->id, null, 'Second', 'second@example.com');
        $third = $service->register($event, $ticket->id, null, 'Third', 'third@example.com');

        $ticket->update(['ticket_available' => 2]);
        $this->assertSame(2, $service->notifyAvailable($ticket->id));

        $this->assertSame('notification_pending', $first->fresh()->status);
        $this->assertSame('notification_pending', $second->fresh()->status);
        $this->assertSame('active', $third->fresh()->status);
        Queue::assertPushed(SendEventWaitlistAvailability::class, 2);
        $this->assertSame(0, $service->notifyAvailable($ticket->id));
    }

    public function test_notification_is_durable_and_contains_a_signed_unsubscribe_link(): void
    {
        Queue::fake();
        Mail::fake();
        [$event, $ticket] = $this->fixture(0);
        $service = app(EventWaitlistService::class);
        $entry = $service->register($event, $ticket->id, null, 'Ada Guest', 'ada@example.com');
        $ticket->update(['ticket_available' => 1]);
        $service->notifyAvailable($ticket->id);

        app()->call([new SendEventWaitlistAvailability($entry->id), 'handle']);

        $this->assertSame('notified', $entry->fresh()->status);
        $this->assertSame(1, $entry->fresh()->attempts);
        $this->assertNull($entry->fresh()->processing_at);
        $this->assertNotNull($entry->fresh()->notified_at);
        Mail::assertQueued(\App\Mail\EventWaitlistAvailableMail::class, 0);
        Mail::assertSent(\App\Mail\EventWaitlistAvailableMail::class, function ($mail): bool {
            return str_contains($mail->unsubscribeUrl, 'signature=')
                && str_contains($mail->eventUrl, '/event/test-event/');
        });
    }

    public function test_failed_waitlist_handoff_preserves_the_window_and_is_recovered_without_inviting_another_entry(): void
    {
        Queue::fake();
        [$event, $ticket] = $this->fixture(0);
        $service = app(EventWaitlistService::class);
        $entry = $service->register($event, $ticket->id, null, 'First', 'first@example.com');
        $second = $service->register($event, $ticket->id, null, 'Second', 'second@example.com');
        $failing = new class extends WaitlistNotificationDispatchService {
            protected function pushNotificationJob(int $entryId): void
            {
                throw new \RuntimeException('queue unavailable');
            }
        };
        app()->instance(WaitlistNotificationDispatchService::class, $failing);

        $ticket->update(['ticket_available' => 1]);
        $this->assertSame(1, $service->notifyAvailable($ticket->id));

        $entry->refresh();
        $this->assertSame('notification_pending', $entry->status);
        $this->assertSame(1, $entry->dispatch_attempts);
        $this->assertSame('queue unavailable', $entry->dispatch_last_error);
        $this->assertSame('active', $second->fresh()->status);
        $entry->forceFill(['dispatch_last_attempt_at' => now()->subMinutes(2)])->save();

        app()->instance(
            WaitlistNotificationDispatchService::class,
            new WaitlistNotificationDispatchService()
        );
        $this->artisan('events:dispatch-waitlist --ticket='.$ticket->id)
            ->expectsOutputToContain('Waitlist notifications queued: 0 | recovery attempted: 1 | failed: 0 | alerted: 0')
            ->assertSuccessful();

        $entry->refresh();
        $this->assertSame(2, $entry->dispatch_attempts);
        $this->assertNull($entry->dispatch_last_error);
        $this->assertSame('active', $second->fresh()->status);
        Queue::assertPushed(SendEventWaitlistAvailability::class, function ($job) use ($entry): bool {
            return $job->entryId === $entry->id;
        });
    }

    public function test_dispatch_limit_is_a_global_safety_cap(): void
    {
        Queue::fake();
        config()->set('tickets.waitlist_dispatch_limit', 1);
        [$event, $ticket] = $this->fixture(0);
        $service = app(EventWaitlistService::class);
        $first = $service->register($event, $ticket->id, null, 'First', 'first@example.com');
        $second = $service->register($event, $ticket->id, null, 'Second', 'second@example.com');
        $ticket->update(['ticket_available' => 2]);

        $this->assertSame(1, $service->notifyAvailable());
        $this->assertSame('notification_pending', $first->fresh()->status);
        $this->assertSame('active', $second->fresh()->status);
        Queue::assertPushed(SendEventWaitlistAvailability::class, 1);
    }

    public function test_unsubscribe_is_idempotent_and_rejoining_requires_new_consent(): void
    {
        [$event, $ticket] = $this->fixture(0);
        $service = app(EventWaitlistService::class);
        $entry = $service->register($event, $ticket->id, null, 'Ada Guest', 'ada@example.com');
        $service->unsubscribe($entry);
        $service->unsubscribe($entry->fresh());
        $this->assertSame('unsubscribed', $entry->fresh()->status);

        $rejoined = $service->register($event, $ticket->id, null, 'Ada Guest', 'ada@example.com');
        $this->assertSame($entry->id, $rejoined->id);
        $this->assertSame('active', $rejoined->status);
        $this->assertNull($rejoined->unsubscribed_at);
    }

    public function test_completed_booking_marks_the_matching_waitlist_entry_converted(): void
    {
        [$event, $ticket] = $this->fixture(0);
        $service = app(EventWaitlistService::class);
        $entry = $service->register($event, $ticket->id, null, 'Ada Guest', 'ada@example.com');
        $booking = Booking::create([
            'booking_id' => 'booking-1',
            'event_id' => $event->id,
            'ticket_id' => $ticket->id,
            'email' => 'ADA@example.com',
            'paymentStatus' => 'completed',
            'quantity' => 1,
        ]);

        $this->assertSame(1, $service->markConvertedForBooking($booking));
        $this->assertSame('converted', $entry->fresh()->status);
        $this->assertNotNull($entry->fresh()->converted_at);
    }

    private function fixture(int $available): array
    {
        $event = Event::create([
            'status' => 1,
            'lifecycle_status' => 'scheduled',
            'end_date_time' => now()->addDay(),
        ]);
        DB::table('event_contents')->insert([
            'event_id' => $event->id,
            'language_id' => 1,
            'title' => 'Test Event',
            'slug' => 'test-event',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $ticket = Ticket::create([
            'event_id' => $event->id,
            'pricing_type' => 'normal',
            'ticket_available_type' => 'limited',
            'ticket_available' => $available,
        ]);
        DB::table('ticket_contents')->insert([
            'ticket_id' => $ticket->id,
            'language_id' => 1,
            'title' => 'General admission',
            'description' => '',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return [$event, $ticket];
    }

    private function createSchema(): void
    {
        Schema::create('events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organizer_id')->nullable();
            $table->boolean('status')->default(true);
            $table->string('lifecycle_status')->default('scheduled');
            $table->timestamp('sales_closed_at')->nullable();
            $table->timestamp('end_date_time')->nullable();
            $table->timestamps();
        });
        Schema::create('tickets', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->string('pricing_type')->default('normal');
            $table->string('ticket_available_type')->default('limited');
            $table->unsignedInteger('ticket_available')->default(0);
            $table->longText('variations')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('event_contents', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('language_id');
            $table->string('title');
            $table->string('slug');
            $table->timestamps();
        });
        Schema::create('bookings', function (Blueprint $table): void {
            $table->id();
            $table->string('booking_id');
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('ticket_id')->nullable();
            $table->string('email')->nullable();
            $table->string('paymentStatus')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->longText('variation')->nullable();
            $table->timestamps();
        });
        Schema::create('ticket_contents', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('ticket_id');
            $table->unsignedBigInteger('language_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamps();
        });
        Schema::create('event_waitlist_entries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('ticket_id');
            $table->string('variation_name')->nullable();
            $table->string('selection_key', 64)->unique();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->text('name');
            $table->text('email');
            $table->string('email_hash', 64);
            $table->string('status', 32);
            $table->string('consent_version', 32);
            $table->timestamp('consented_at');
            $table->timestamp('joined_at');
            $table->timestamp('notification_pending_at')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('notification_expires_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamp('processing_at')->nullable();
            $table->timestamp('alerted_at')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedSmallInteger('dispatch_attempts')->default(0);
            $table->timestamp('dispatch_last_attempt_at')->nullable();
            $table->text('dispatch_last_error')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
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
