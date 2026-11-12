<?php

namespace Tests\Unit;

use App\Models\Event\Ticket;
use App\Models\TicketReservation;
use App\Services\TicketReservationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class TicketReservationServiceTest extends TestCase
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
        config()->set('tickets.reservation_minutes', 15);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->string('pricing_type')->default('normal');
            $table->string('ticket_available_type')->default('limited');
            $table->unsignedInteger('ticket_available')->default(0);
            $table->string('max_ticket_buy_type')->default('unlimited');
            $table->unsignedInteger('max_buy_ticket')->nullable();
            $table->longText('variations')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('ticket_reservations', function (Blueprint $table) {
            $table->id();
            $table->uuid('token');
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('ticket_id');
            $table->string('variation_name')->nullable();
            $table->unsignedInteger('quantity');
            $table->string('status', 24)->default('active');
            $table->string('session_hash', 64)->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->char('buyer_email_hash', 64)->nullable();
            $table->unsignedSmallInteger('buyer_email_hash_version')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('ticket_id')->nullable();
            $table->char('buyer_email_hash', 64)->nullable();
            $table->unsignedSmallInteger('buyer_email_hash_version')->nullable();
            $table->longText('variation')->nullable();
            $table->unsignedInteger('quantity')->default(0);
            $table->string('paymentStatus')->default('completed');
            $table->timestamps();
        });
    }

    public function test_reservation_deducts_stock_and_consumption_does_not_deduct_twice(): void
    {
        $ticket = $this->ticket(10);
        $service = new TicketReservationService();

        $token = $service->reserve(41, [$this->selection($ticket, 3)], 'session-a', 7);

        $this->assertSame(7, (int) $ticket->fresh()->ticket_available);
        $service->consume($token, 41);

        $this->assertSame(7, (int) $ticket->fresh()->ticket_available);
        $this->assertSame('consumed', TicketReservation::where('token', $token)->value('status'));
    }

    public function test_release_restores_stock_once(): void
    {
        $ticket = $this->ticket(5);
        $service = new TicketReservationService();
        $token = $service->reserve(41, [$this->selection($ticket, 2)], null, null);

        $this->assertSame(1, $service->release($token, 'cancelled'));
        $this->assertSame(0, $service->release($token, 'cancelled'));
        $this->assertSame(5, (int) $ticket->fresh()->ticket_available);
    }

    public function test_verified_late_payment_reacquires_stock_after_expiry_release(): void
    {
        $ticket = $this->ticket(6);
        $service = new TicketReservationService();
        $token = $service->reserve(41, [$this->selection($ticket, 2)], null, null);

        TicketReservation::where('token', $token)->update(['expires_at' => now()->subSecond()]);
        $this->assertSame(1, $service->releaseExpired());
        $this->assertSame(6, (int) $ticket->fresh()->ticket_available);

        $service->consume($token, 41);

        $this->assertSame(4, (int) $ticket->fresh()->ticket_available);
        $this->assertSame('consumed', TicketReservation::where('token', $token)->value('status'));
    }

    public function test_insufficient_stock_rolls_back_the_entire_reservation(): void
    {
        $ticket = $this->ticket(1);

        try {
            (new TicketReservationService())->reserve(41, [$this->selection($ticket, 2)], null, null);
            $this->fail('Expected insufficient stock to reject the reservation.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('no longer available', $exception->getMessage());
        }

        $this->assertSame(1, (int) $ticket->fresh()->ticket_available);
        $this->assertSame(0, TicketReservation::count());
    }

    public function test_parallel_customer_holds_cannot_exceed_purchase_limit(): void
    {
        $ticket = $this->ticket(10);
        $ticket->update(['max_ticket_buy_type' => 'limited', 'max_buy_ticket' => 3]);
        $service = new TicketReservationService();

        $service->reserve(41, [$this->selection($ticket, 2)], 'session-a', 7);

        try {
            $service->reserve(41, [$this->selection($ticket, 2)], 'session-b', 7);
            $this->fail('Expected active holds to count toward the customer purchase limit.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('purchase limit', $exception->getMessage());
        }

        $this->assertSame(8, (int) $ticket->fresh()->ticket_available);
        $this->assertSame(1, TicketReservation::where('status', 'active')->count());
    }

    public function test_late_payment_cannot_reacquire_stock_beyond_customer_limit(): void
    {
        $ticket = $this->ticket(10);
        $ticket->update(['max_ticket_buy_type' => 'limited', 'max_buy_ticket' => 3]);
        $service = new TicketReservationService();
        $lateToken = $service->reserve(41, [$this->selection($ticket, 2)], 'old-session', 7);
        TicketReservation::where('token', $lateToken)->update(['expires_at' => now()->subSecond()]);
        $service->releaseExpired();
        $service->reserve(41, [$this->selection($ticket, 2)], 'current-session', 7);

        try {
            $service->consume($lateToken, 41);
            $this->fail('Expected late payment recovery to preserve the customer purchase limit.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('purchase limit', $exception->getMessage());
        }

        $this->assertSame(8, (int) $ticket->fresh()->ticket_available);
        $this->assertSame('expired', TicketReservation::where('token', $lateToken)->value('status'));
    }

    public function test_parallel_guest_holds_with_the_same_email_cannot_exceed_purchase_limit(): void
    {
        $ticket = $this->ticket(10);
        $ticket->update(['max_ticket_buy_type' => 'limited', 'max_buy_ticket' => 3]);
        $limits = app(\App\Services\EventTicketPurchaseLimitService::class);
        $buyerHash = $limits->buyerIdentityHash(' Guest@Example.COM ');
        $service = new TicketReservationService();

        $service->reserve(
            41,
            [$this->selection($ticket, 2)],
            'guest-session-a',
            null,
            buyerEmailHash: $buyerHash,
        );

        try {
            $service->reserve(
                41,
                [$this->selection($ticket, 2)],
                'guest-session-b',
                null,
                buyerEmailHash: $limits->buyerIdentityHash('guest@example.com'),
            );
            $this->fail('Expected active guest holds to count toward the email purchase limit.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('purchase limit', $exception->getMessage());
        }

        $this->assertSame(8, (int) $ticket->fresh()->ticket_available);
        $this->assertSame(1, TicketReservation::where('status', 'active')->count());
        $this->assertSame($buyerHash, TicketReservation::where('status', 'active')->value('buyer_email_hash'));
        $this->assertSame(1, (int) TicketReservation::where('status', 'active')->value('buyer_email_hash_version'));
    }

    public function test_guest_booking_history_is_counted_without_storing_another_plaintext_email(): void
    {
        $ticket = $this->ticket(10);
        $ticket->update(['max_ticket_buy_type' => 'limited', 'max_buy_ticket' => 3]);
        $limits = app(\App\Services\EventTicketPurchaseLimitService::class);
        $buyerHash = $limits->buyerIdentityHash('guest@example.com');

        DB::table('bookings')->insert([
            'event_id' => 41,
            'ticket_id' => $ticket->id,
            'buyer_email_hash' => $buyerHash,
            'quantity' => 2,
            'paymentStatus' => 'completed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            (new TicketReservationService())->reserve(
                41,
                [$this->selection($ticket, 2)],
                'guest-session',
                null,
                buyerEmailHash: $buyerHash,
            );
            $this->fail('Expected prior guest bookings to count toward the email purchase limit.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('purchase limit', $exception->getMessage());
        }

        $this->assertSame(10, (int) $ticket->fresh()->ticket_available);
        $this->assertSame(0, TicketReservation::count());
    }

    public function test_guest_late_payment_cannot_reacquire_beyond_email_limit(): void
    {
        $ticket = $this->ticket(10);
        $ticket->update(['max_ticket_buy_type' => 'limited', 'max_buy_ticket' => 3]);
        $buyerHash = app(\App\Services\EventTicketPurchaseLimitService::class)
            ->buyerIdentityHash('guest@example.com');
        $service = new TicketReservationService();
        $lateToken = $service->reserve(
            41,
            [$this->selection($ticket, 2)],
            'old-guest-session',
            null,
            buyerEmailHash: $buyerHash,
        );
        TicketReservation::where('token', $lateToken)->update(['expires_at' => now()->subSecond()]);
        $service->releaseExpired();
        $service->reserve(
            41,
            [$this->selection($ticket, 2)],
            'current-guest-session',
            null,
            buyerEmailHash: $buyerHash,
        );

        try {
            $service->consume($lateToken, 41);
            $this->fail('Expected late guest payment recovery to preserve the email purchase limit.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('purchase limit', $exception->getMessage());
        }

        $this->assertSame(8, (int) $ticket->fresh()->ticket_available);
        $this->assertSame('expired', TicketReservation::where('token', $lateToken)->value('status'));
    }

    public function test_reservation_consumption_is_bound_to_the_guest_identity(): void
    {
        $ticket = $this->ticket(5);
        $limits = app(\App\Services\EventTicketPurchaseLimitService::class);
        $ownerHash = $limits->buyerIdentityHash('owner@example.com');
        $token = (new TicketReservationService())->reserve(
            41,
            [$this->selection($ticket, 1)],
            'owner-session',
            null,
            buyerEmailHash: $ownerHash,
        );

        try {
            (new TicketReservationService())->consume(
                $token,
                41,
                false,
                null,
                $limits->buyerIdentityHash('other@example.com'),
            );
            $this->fail('Expected a different guest identity to be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('does not belong', $exception->getMessage());
        }

        $this->assertSame('active', TicketReservation::where('token', $token)->value('status'));
        (new TicketReservationService())->consume($token, 41, false, null, $ownerHash);
        $this->assertSame('consumed', TicketReservation::where('token', $token)->value('status'));
    }

    public function test_replacement_is_atomic_and_replay_safe(): void
    {
        $ticket = $this->ticket(5);
        $service = new TicketReservationService();
        $oldToken = $service->reserve(41, [$this->selection($ticket, 2)], 'session-a', 7);

        $newToken = $service->replace(41, [$this->selection($ticket, 3)], $oldToken, 'session-a', 7);

        $this->assertNotSame($oldToken, $newToken);
        $this->assertSame(2, (int) $ticket->fresh()->ticket_available);
        $this->assertSame('replaced', TicketReservation::where('token', $oldToken)->value('status'));
        $this->assertSame('active', TicketReservation::where('token', $newToken)->value('status'));

        try {
            $service->replace(41, [$this->selection($ticket, 1)], $oldToken, 'session-a', 7);
            $this->fail('Expected a replaced token to be single use.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('no longer active', $exception->getMessage());
        }

        $this->assertSame(2, (int) $ticket->fresh()->ticket_available);
        $this->assertSame(1, TicketReservation::where('status', 'active')->count());
    }

    public function test_failed_replacement_preserves_the_original_hold(): void
    {
        $heldTicket = $this->ticket(5);
        $unavailableTicket = $this->ticket(0);
        $service = new TicketReservationService();
        $oldToken = $service->reserve(41, [$this->selection($heldTicket, 2)], 'session-a', 7);

        try {
            $service->replace(
                41,
                [$this->selection($unavailableTicket, 1)],
                $oldToken,
                'session-a',
                7,
            );
            $this->fail('Expected an unavailable replacement to fail.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('no longer available', $exception->getMessage());
        }

        $this->assertSame(3, (int) $heldTicket->fresh()->ticket_available);
        $this->assertSame(0, (int) $unavailableTicket->fresh()->ticket_available);
        $this->assertSame('active', TicketReservation::where('token', $oldToken)->value('status'));
        $this->assertSame(1, TicketReservation::count());
    }

    public function test_replacement_requires_session_or_customer_ownership(): void
    {
        $ticket = $this->ticket(5);
        $service = new TicketReservationService();
        $oldToken = $service->reserve(41, [$this->selection($ticket, 2)], 'session-a', 7);

        try {
            $service->replace(41, [$this->selection($ticket, 1)], $oldToken, 'session-b', 8);
            $this->fail('Expected cross-checkout replacement to be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('does not belong', $exception->getMessage());
        }

        $this->assertSame(3, (int) $ticket->fresh()->ticket_available);
        $this->assertSame('active', TicketReservation::where('token', $oldToken)->value('status'));
    }

    private function ticket(int $available): Ticket
    {
        return Ticket::create([
            'event_id' => 41,
            'pricing_type' => 'normal',
            'ticket_available_type' => 'limited',
            'ticket_available' => $available,
        ]);
    }

    private function selection(Ticket $ticket, int $quantity): array
    {
        return [
            'ticket_id' => $ticket->id,
            'name' => 'General admission',
            'qty' => $quantity,
            'price' => 10000,
            'early_bird_dicount' => 0,
        ];
    }
}
