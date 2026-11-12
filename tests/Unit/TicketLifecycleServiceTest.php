<?php

namespace Tests\Unit;

use App\Models\Event\Booking;
use App\Models\Event\BookingTicket;
use App\Models\Event\BookingTicketTransfer;
use App\Models\Customer;
use App\Jobs\SendTicketTransferInvitation;
use App\Mail\TicketTransferInvitationMail;
use App\Services\TicketLifecycleService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class TicketLifecycleServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]);
        config()->set('app.key', 'base64:'.base64_encode(str_repeat('t', 32)));
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->createSchema();
        Queue::fake();
    }

    public function test_customer_transfer_rotates_qr_only_after_verified_recipient_accepts(): void
    {
        [$booking, $ticket] = $this->ticket();
        $oldPublicId = $ticket->public_id;

        $request = (new TicketLifecycleService())->requestTransferForCustomer(
            $ticket->id, $booking->customer_id, 'Chidi Okafor', 'CHIDI@example.com'
        );

        $this->assertSame($oldPublicId, $ticket->fresh()->public_id);
        $this->assertSame('pending', $request['transfer']->status);
        $this->assertNotSame($request['token'], $request['transfer']->token_hash);
        $this->assertDatabaseHas('booking_ticket_events', [
            'booking_ticket_id' => $ticket->id, 'action' => 'transfer_requested', 'actor_type' => 'customer',
        ]);
        Queue::assertPushed(SendTicketTransferInvitation::class, function ($job) use ($request) {
            return $job->transferId === $request['transfer']->id
                && $job instanceof \Illuminate\Contracts\Queue\ShouldBeEncrypted;
        });

        $recipient = Customer::create([
            'fname' => 'Chidi', 'lname' => 'Okafor', 'email' => 'chidi@example.com',
            'status' => 1, 'email_verified_at' => now(), 'password' => 'x',
        ]);
        $updated = (new TicketLifecycleService())->acceptTransfer(
            $request['transfer']->id, $request['token'], $recipient->id
        );

        $this->assertNotSame($oldPublicId, $updated->public_id);
        $this->assertSame('Chidi Okafor', $updated->holder_name);
        $this->assertSame('chidi@example.com', $updated->holder_email);
        $this->assertSame($recipient->id, $updated->holder_customer_id);
        $this->assertSame('accepted', $request['transfer']->fresh()->status);
        $this->assertDatabaseHas('booking_ticket_events', [
            'booking_ticket_id' => $ticket->id, 'action' => 'transferred', 'actor_type' => 'customer',
        ]);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'ticket_unit.transferred')->count());
    }

    public function test_customer_cannot_change_another_customers_ticket(): void
    {
        [, $ticket] = $this->ticket();
        $this->expectException(AuthorizationException::class);
        (new TicketLifecycleService())->requestTransferForCustomer($ticket->id, 999, 'New Holder', 'new@example.com');
    }

    public function test_transfer_invitation_job_records_delivery_and_does_not_send_twice(): void
    {
        [$booking, $ticket] = $this->ticket();
        $request = (new TicketLifecycleService())->requestTransferForCustomer(
            $ticket->id, $booking->customer_id, 'Chidi Okafor', 'chidi@example.com'
        );
        Mail::fake();

        $job = new SendTicketTransferInvitation($request['transfer']->id, $request['token']);
        $job->handle();
        $job->handle();

        $transfer = $request['transfer']->fresh();
        $this->assertSame('sent', $transfer->delivery_status);
        $this->assertSame(1, $transfer->delivery_attempts);
        $this->assertNotNull($transfer->sent_at);
        Mail::assertSent(TicketTransferInvitationMail::class, 1);
        $this->assertNull($transfer->dispatch_token);
    }

    public function test_lost_transfer_invitation_handoff_is_encrypted_and_recovered(): void
    {
        [$booking, $ticket] = $this->ticket();
        $failing = new class extends TicketLifecycleService {
            protected function pushTransferInvitationJob(int $transferId, string $token): void
            {
                throw new RuntimeException('transfer queue unavailable');
            }
        };

        $request = $failing->requestTransferForCustomer(
            $ticket->id,
            $booking->customer_id,
            'Chidi Okafor',
            'chidi@example.com'
        );
        $failed = $request['transfer']->fresh();
        $this->assertSame(1, $failed->dispatch_attempts);
        $this->assertStringContainsString('transfer queue unavailable', $failed->dispatch_last_error);
        $this->assertSame($request['token'], $failed->dispatch_token);
        $this->assertNotSame(
            $request['token'],
            DB::table('booking_ticket_transfers')->where('id', $failed->id)->value('dispatch_token')
        );
        Queue::assertNotPushed(SendTicketTransferInvitation::class);

        $this->artisan('tickets:dispatch-transfers')->assertSuccessful();

        Queue::assertPushed(SendTicketTransferInvitation::class, fn ($job): bool =>
            $job->transferId === $failed->id && $job->plainToken === $request['token']
        );
        $recovered = $failed->fresh();
        $this->assertSame(2, $recovered->dispatch_attempts);
        $this->assertNull($recovered->dispatch_last_error);
        $this->assertDatabaseCount('booking_ticket_transfers', 1);
    }

    public function test_cancelled_transfer_suppresses_pending_delivery(): void
    {
        [$booking, $ticket] = $this->ticket();
        $request = (new TicketLifecycleService())->requestTransferForCustomer(
            $ticket->id, $booking->customer_id, 'Chidi Okafor', 'chidi@example.com'
        );
        (new TicketLifecycleService())->cancelTransferForCustomer(
            $request['transfer']->id, $booking->customer_id
        );
        Mail::fake();

        (new SendTicketTransferInvitation($request['transfer']->id, $request['token']))->handle();

        $this->assertSame('suppressed', $request['transfer']->fresh()->delivery_status);
        Mail::assertNothingSent();
    }

    public function test_cancelled_event_cannot_create_or_accept_a_transfer(): void
    {
        [$booking, $ticket] = $this->ticket();
        $request = (new TicketLifecycleService())->requestTransferForCustomer(
            $ticket->id, $booking->customer_id, 'Chidi Okafor', 'chidi@example.com'
        );
        DB::table('events')->where('id', 50)->update([
            'lifecycle_status' => 'cancelled', 'cancelled_at' => now(),
        ]);

        try {
            (new TicketLifecycleService())->requestTransferForCustomer(
                $ticket->id, $booking->customer_id, 'Another Holder', 'another@example.com'
            );
            $this->fail('A cancelled event must not create a transfer invitation.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('inactive or cancelled', $exception->getMessage());
        }

        $recipient = Customer::create([
            'fname' => 'Chidi', 'lname' => 'Okafor', 'email' => 'chidi@example.com',
            'status' => 1, 'email_verified_at' => now(), 'password' => 'x',
        ]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('inactive or cancelled');
        (new TicketLifecycleService())->acceptTransfer(
            $request['transfer']->id, $request['token'], $recipient->id
        );
    }

    public function test_used_ticket_cannot_be_transferred_reissued_or_revoked(): void
    {
        [$booking, $ticket] = $this->ticket('checked_in');
        $service = new TicketLifecycleService();

        foreach (['transfer', 'reissue', 'revoke'] as $operation) {
            try {
                if ($operation === 'transfer') {
                    $service->requestTransferForCustomer($ticket->id, $booking->customer_id, 'New Holder', 'new@example.com');
                } elseif ($operation === 'reissue') {
                    $service->reissueForCustomer($ticket->id, $booking->customer_id, 'Lost QR.');
                } else {
                    $service->revokeForOrganizer($ticket->id, $ticket->organizer_id, 'Fraud review.');
                }
                $this->fail('Used ticket lifecycle change must fail.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('unused issued', $exception->getMessage());
            }
        }

        $this->assertDatabaseCount('booking_ticket_events', 0);
    }

    public function test_organizer_revoke_invalidates_ticket_and_records_reason(): void
    {
        [, $ticket] = $this->ticket();
        $updated = (new TicketLifecycleService())->revokeForOrganizer(
            $ticket->id, $ticket->organizer_id, 'Duplicate chargeback ticket.'
        );

        $this->assertSame('revoked', $updated->status);
        $this->assertNotNull($updated->revoked_at);
        $this->assertDatabaseHas('booking_ticket_events', [
            'action' => 'revoked', 'reason' => 'Duplicate chargeback ticket.',
        ]);
    }

    public function test_transfer_cannot_be_claimed_by_wrong_email_or_tampered_token(): void
    {
        [$booking, $ticket] = $this->ticket();
        $request = (new TicketLifecycleService())->requestTransferForCustomer(
            $ticket->id, $booking->customer_id, 'Chidi Okafor', 'chidi@example.com'
        );
        $wrongCustomer = Customer::create([
            'fname' => 'Wrong', 'lname' => 'User', 'email' => 'wrong@example.com',
            'status' => 1, 'email_verified_at' => now(), 'password' => 'x',
        ]);

        foreach (['tampered-token', $request['token']] as $index => $token) {
            try {
                (new TicketLifecycleService())->acceptTransfer($request['transfer']->id, $token, $wrongCustomer->id);
                $this->fail('An invalid transfer claim must fail.');
            } catch (AuthorizationException $exception) {
                $this->assertNotEmpty($exception->getMessage(), 'Failure ' . $index . ' must be explicit.');
            }
        }

        $this->assertSame('pending', $request['transfer']->fresh()->status);
        $this->assertSame($ticket->public_id, $ticket->fresh()->public_id);
    }

    public function test_accepted_invitation_is_single_use_and_old_owner_loses_control(): void
    {
        [$booking, $ticket] = $this->ticket();
        $request = (new TicketLifecycleService())->requestTransferForCustomer(
            $ticket->id, $booking->customer_id, 'Chidi Okafor', 'chidi@example.com'
        );
        $recipient = Customer::create([
            'fname' => 'Chidi', 'lname' => 'Okafor', 'email' => 'chidi@example.com',
            'status' => 1, 'email_verified_at' => now(), 'password' => 'x',
        ]);
        $service = new TicketLifecycleService();
        $service->acceptTransfer($request['transfer']->id, $request['token'], $recipient->id);

        try {
            $service->acceptTransfer($request['transfer']->id, $request['token'], $recipient->id);
            $this->fail('A transfer invitation must be single use.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('no longer available', $exception->getMessage());
        }

        $this->expectException(AuthorizationException::class);
        $service->reissueForCustomer($ticket->id, $booking->customer_id, 'Original purchaser retry.');
    }

    private function ticket(string $status = 'issued'): array
    {
        DB::table('events')->insertOrIgnore([
            'id' => 50, 'status' => 1, 'lifecycle_status' => 'scheduled',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('event_contents')->insertOrIgnore([
            'event_id' => 50, 'language_id' => 1, 'title' => 'Live in Lagos',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        Customer::create([
            'id' => 12, 'fname' => 'Ada', 'lname' => 'Okafor', 'email' => 'ada@example.com',
            'status' => 1, 'email_verified_at' => now(), 'password' => 'x',
        ]);
        $booking = Booking::create([
            'booking_id' => (string) Str::uuid(), 'customer_id' => 12, 'event_id' => 50,
            'organizer_id' => 8, 'quantity' => 1, 'paymentStatus' => 'completed', 'scan_status' => 0,
        ]);
        $ticket = BookingTicket::create([
            'public_id' => (string) Str::uuid(), 'booking_id' => $booking->id,
            'event_id' => 50, 'organizer_id' => 8, 'sequence' => 1,
            'holder_name' => 'Ada Okafor', 'holder_email' => 'ada@example.com', 'status' => $status,
        ]);
        return [$booking, $ticket];
    }

    private function createSchema(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('organizer_id')->nullable();
            $table->unsignedTinyInteger('status')->default(1); $table->string('lifecycle_status')->default('scheduled');
            $table->timestamp('cancelled_at')->nullable(); $table->timestamps();
        });
        Schema::create('event_contents', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('event_id'); $table->unsignedBigInteger('language_id');
            $table->string('title'); $table->timestamps(); $table->unique(['event_id', 'language_id']);
        });
        Schema::create('bookings', function (Blueprint $table) {
            $table->id(); $table->string('booking_id'); $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('event_id'); $table->unsignedBigInteger('organizer_id')->nullable();
            $table->unsignedInteger('quantity'); $table->integer('scan_status')->default(0);
            $table->string('paymentStatus'); $table->string('refund_status')->nullable(); $table->timestamps();
        });
        Schema::create('customers', function (Blueprint $table) {
            $table->id(); $table->string('fname')->nullable(); $table->string('lname')->nullable();
            $table->string('email')->unique(); $table->string('password')->nullable();
            $table->unsignedTinyInteger('status')->default(1); $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();
        });
        Schema::create('booking_tickets', function (Blueprint $table) {
            $table->id(); $table->uuid('public_id')->unique(); $table->unsignedBigInteger('booking_id');
            $table->unsignedBigInteger('event_id'); $table->unsignedBigInteger('organizer_id')->nullable();
            $table->unsignedBigInteger('ticket_id')->nullable(); $table->unsignedInteger('sequence');
            $table->string('variation_name')->nullable(); $table->string('holder_name')->nullable();
            $table->string('holder_email')->nullable(); $table->unsignedBigInteger('holder_customer_id')->nullable();
            $table->string('status')->default('issued');
            $table->timestamp('checked_in_at')->nullable(); $table->unsignedBigInteger('checked_in_by_organizer_id')->nullable();
            $table->timestamp('revoked_at')->nullable(); $table->string('revoked_reason')->nullable(); $table->timestamps();
        });
        Schema::create('booking_ticket_events', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('booking_ticket_id'); $table->string('action');
            $table->string('actor_type'); $table->unsignedBigInteger('actor_id')->nullable();
            $table->json('before')->nullable(); $table->json('after')->nullable();
            $table->string('reason')->nullable(); $table->timestamp('created_at')->nullable();
        });
        Schema::create('booking_ticket_transfers', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('booking_ticket_id'); $table->unsignedBigInteger('from_customer_id');
            $table->string('recipient_name'); $table->string('recipient_email'); $table->char('token_hash', 64)->unique();
            $table->text('dispatch_token')->nullable(); $table->unsignedSmallInteger('dispatch_attempts')->default(0);
            $table->timestamp('dispatch_last_attempt_at')->nullable(); $table->text('dispatch_last_error')->nullable();
            $table->string('status')->default('pending'); $table->string('delivery_status')->default('pending');
            $table->unsignedSmallInteger('delivery_attempts')->default(0); $table->text('last_error')->nullable();
            $table->timestamp('processing_at')->nullable(); $table->timestamp('sent_at')->nullable();
            $table->timestamp('alerted_at')->nullable(); $table->timestamp('expires_at');
            $table->unsignedBigInteger('accepted_by_customer_id')->nullable(); $table->timestamp('accepted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable(); $table->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id(); $table->string('actor_type'); $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action'); $table->string('subject_type')->nullable(); $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('before')->nullable(); $table->json('after')->nullable(); $table->json('metadata')->nullable();
            $table->string('request_id')->nullable(); $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable(); $table->timestamp('created_at')->nullable();
        });
    }
}
