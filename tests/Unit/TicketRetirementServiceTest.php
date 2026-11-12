<?php

namespace Tests\Unit;

use App\Models\Event;
use App\Models\Event\Ticket;
use App\Services\TicketRetirementService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TicketRetirementServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->createSchema();
    }

    public function test_retirement_soft_deletes_ticket_and_preserves_labels_and_bookings(): void
    {
        [$event, $ticket] = $this->ticket(7);
        DB::table('ticket_contents')->insert(['ticket_id' => $ticket->id, 'title' => 'General admission']);
        DB::table('bookings')->insert([
            'event_id' => $event->id,
            'variation' => json_encode([['ticket_id' => $ticket->id, 'name' => 'General admission', 'qty' => 1]]),
        ]);

        $changed = (new TicketRetirementService())->retire([$ticket->id], 7);

        $this->assertSame(1, $changed);
        $this->assertNull(Ticket::find($ticket->id));
        $this->assertNotNull(Ticket::withTrashed()->find($ticket->id));
        $this->assertDatabaseCount('ticket_contents', 1);
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ticket.retired', 'subject_id' => $ticket->id]);
    }

    public function test_active_reservation_blocks_retirement_without_partial_changes(): void
    {
        [, $ticket] = $this->ticket(7);
        DB::table('ticket_reservations')->insert([
            'ticket_id' => $ticket->id, 'event_id' => $ticket->event_id, 'status' => 'active',
        ]);

        try {
            (new TicketRetirementService())->retire([$ticket->id], 7);
            $this->fail('An in-flight checkout must block retirement.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('tickets', $exception->errors());
        }

        $this->assertNotNull(Ticket::find($ticket->id));
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_organizer_cannot_retire_another_organizers_ticket(): void
    {
        [, $ticket] = $this->ticket(7);
        $this->expectException(ValidationException::class);
        (new TicketRetirementService())->retire([$ticket->id], 8);
    }

    private function ticket(int $organizerId): array
    {
        $event = Event::create(['organizer_id' => $organizerId, 'status' => 1, 'event_type' => 'venue']);
        $ticket = Ticket::create([
            'event_id' => $event->id, 'event_type' => 'venue', 'pricing_type' => 'normal',
            'price' => '5000', 'f_price' => 5000,
        ]);
        return [$event, $ticket];
    }

    private function createSchema(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('organizer_id'); $table->integer('status');
            $table->string('event_type')->nullable(); $table->timestamps();
        });
        Schema::create('tickets', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('event_id'); $table->string('event_type')->nullable();
            $table->string('pricing_type')->nullable(); $table->string('price')->nullable();
            $table->decimal('f_price', 18, 2)->nullable(); $table->timestamps(); $table->softDeletes();
        });
        Schema::create('ticket_contents', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('ticket_id'); $table->string('title');
        });
        Schema::create('bookings', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('event_id'); $table->text('variation')->nullable();
        });
        Schema::create('ticket_reservations', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('event_id'); $table->unsignedBigInteger('ticket_id');
            $table->string('status');
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
