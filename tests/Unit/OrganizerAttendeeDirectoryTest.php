<?php

namespace Tests\Unit;

use App\Services\TicaUi\OrganizerAttendeeDirectory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrganizerAttendeeDirectoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        Schema::create('bookings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organizer_id');
            $table->string('fname')->nullable();
            $table->string('lname')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('paymentStatus');
            $table->string('paymentMethod')->nullable();
            $table->decimal('price', 14, 2)->default(0);
            $table->decimal('tax', 14, 2)->default(0);
            $table->unsignedInteger('quantity')->default(1);
            $table->string('refund_status')->nullable();
            $table->string('currencyText')->nullable();
        });
        Schema::create('booking_tickets', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('booking_id');
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('organizer_id')->nullable();
            $table->unsignedBigInteger('ticket_id')->nullable();
            $table->unsignedInteger('sequence');
            $table->string('variation_name')->nullable();
            $table->string('holder_name')->nullable();
            $table->string('holder_email')->nullable();
            $table->string('status')->default('issued');
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->text('attendee_answer')->nullable();
        });
        Schema::create('event_contents', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('language_id')->nullable();
            $table->unsignedBigInteger('event_id');
            $table->string('title');
        });
        Schema::create('ticket_contents', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('language_id')->nullable();
            $table->unsignedBigInteger('ticket_id');
            $table->string('title');
        });
        Schema::create('payment_intents', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('booking_id')->nullable();
            $table->string('status');
        });
    }

    public function test_cursor_pages_are_stable_scoped_and_resolve_only_visible_metadata(): void
    {
        $older = $this->booking(1, 'Older Buyer');
        $newer = $this->booking(1, 'Newer Buyer');
        $other = $this->booking(2, 'Other Organizer');

        $this->ticket($older, 10, 100, 1, 'Older attendee');
        $this->ticket($newer, 20, 200, 1, 'Newer one');
        $this->ticket($newer, 20, 200, 2, 'Newer two');
        $this->ticket($other, 99, 999, 1, 'Private attendee');

        DB::table('event_contents')->insert([
            ['language_id' => 1, 'event_id' => 10, 'title' => 'Older event'],
            ['language_id' => 1, 'event_id' => 20, 'title' => 'Newer event'],
            ['language_id' => 1, 'event_id' => 99, 'title' => 'Other private event'],
        ]);
        DB::table('ticket_contents')->insert([
            ['language_id' => 1, 'ticket_id' => 100, 'title' => 'Standard'],
            ['language_id' => 1, 'ticket_id' => 200, 'title' => 'VIP'],
            ['language_id' => 1, 'ticket_id' => 999, 'title' => 'Private tier'],
        ]);
        DB::table('payment_intents')->insert([
            ['booking_id' => $older, 'status' => 'completed'],
            ['booking_id' => $newer, 'status' => 'completed'],
            ['booking_id' => $other, 'status' => 'completed'],
        ]);

        DB::enableQueryLog();
        $directory = new OrganizerAttendeeDirectory();
        $first = $directory->page(1, [], 2);

        $this->assertTrue($first['more']);
        $this->assertNotNull($first['nextCursor']);
        $this->assertSame(['Newer one', 'Newer two'], array_column($first['rows'], 'name'));
        $this->assertSame(['VIP', 'VIP'], array_column($first['rows'], 'tier'));

        $metadataQueries = collect(DB::getQueryLog())->filter(fn (array $query) => str_contains($query['query'], 'ticket_contents'));
        $this->assertCount(1, $metadataQueries);
        $this->assertSame([200], array_values($metadataQueries->first()['bindings']));

        $second = $directory->page(1, [], 2, $first['nextCursor']);

        $this->assertFalse($second['more']);
        $this->assertNull($second['nextCursor']);
        $this->assertSame(['Older attendee'], array_column($second['rows'], 'name'));
        $this->assertSame('Older event', $second['rows'][0]['event']);
        $this->assertTrue($second['rows'][0]['refundable']);
        $this->assertNotContains('Private attendee', array_merge(
            array_column($first['rows'], 'name'),
            array_column($second['rows'], 'name'),
        ));
    }

    public function test_complete_export_uses_bounded_cursor_batches(): void
    {
        for ($index = 0; $index < 5; $index++) {
            $booking = $this->booking(1, 'Buyer ' . $index);
            $this->ticket($booking, 10, 100, 1, 'Attendee ' . $index);
        }
        DB::table('event_contents')->insert(['language_id' => 1, 'event_id' => 10, 'title' => 'Event']);
        DB::table('ticket_contents')->insert(['language_id' => 1, 'ticket_id' => 100, 'title' => 'General']);

        $directory = new class extends OrganizerAttendeeDirectory
        {
            public const EXPORT_BATCH_SIZE = 2;
        };

        $rows = collect($directory->batches(1, []))->flatten(1);

        $this->assertCount(5, $rows);
        $this->assertSame(
            ['Attendee 4', 'Attendee 3', 'Attendee 2', 'Attendee 1', 'Attendee 0'],
            $rows->pluck('name')->all(),
        );
    }

    public function test_invalid_cursor_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        (new OrganizerAttendeeDirectory())->page(1, [], 50, 'not-a-cursor');
    }

    public function test_visible_ticket_reference_finds_the_exact_scoped_attendee(): void
    {
        $booking = $this->booking(1, 'Reference Buyer');
        $other = $this->booking(2, 'Private Reference');

        $this->ticket($booking, 10, 100, 1, 'First attendee');
        $this->ticket($booking, 10, 100, 2, 'Second attendee');
        $this->ticket($other, 99, 999, 1, 'Private attendee');
        DB::table('event_contents')->insert([
            ['language_id' => 1, 'event_id' => 10, 'title' => 'Visible event'],
            ['language_id' => 1, 'event_id' => 99, 'title' => 'Private event'],
        ]);
        DB::table('ticket_contents')->insert([
            ['language_id' => 1, 'ticket_id' => 100, 'title' => 'General'],
            ['language_id' => 1, 'ticket_id' => 999, 'title' => 'Private'],
        ]);

        $directory = new OrganizerAttendeeDirectory();
        $reference = '#TICA-' . str_pad((string) $booking, 6, '0', STR_PAD_LEFT) . '-2';
        $result = $directory->page(1, ['q' => $reference]);
        $privateReference = 'TICA-' . str_pad((string) $other, 6, '0', STR_PAD_LEFT) . '-1';
        $privateResult = $directory->page(1, ['q' => $privateReference]);

        $this->assertCount(1, $result['rows']);
        $this->assertSame('Second attendee', $result['rows'][0]['name']);
        $this->assertSame(ltrim($reference, '#'), $result['rows'][0]['ref']);
        $this->assertSame([], $privateResult['rows']);
    }

    private function booking(int $organizerId, string $name): int
    {
        [$first, $last] = array_pad(explode(' ', $name, 2), 2, '');

        return DB::table('bookings')->insertGetId([
            'organizer_id' => $organizerId,
            'fname' => $first,
            'lname' => $last,
            'email' => strtolower(str_replace(' ', '.', $name)) . '@example.test',
            'phone' => '08000000000',
            'paymentStatus' => 'completed',
            'paymentMethod' => 'Paystack',
            'price' => 100,
            'tax' => 6,
            'quantity' => 1,
            'refund_status' => null,
            'currencyText' => 'NGN',
        ]);
    }

    private function ticket(int $bookingId, int $eventId, int $ticketId, int $sequence, string $holder): void
    {
        DB::table('booking_tickets')->insert([
            'booking_id' => $bookingId,
            'event_id' => $eventId,
            'organizer_id' => DB::table('bookings')->where('id', $bookingId)->value('organizer_id'),
            'ticket_id' => $ticketId,
            'sequence' => $sequence,
            'variation_name' => null,
            'holder_name' => $holder,
            'holder_email' => strtolower(str_replace(' ', '.', $holder)) . '@example.test',
            'status' => 'issued',
            'checked_in_at' => null,
            'revoked_at' => null,
            'attendee_answer' => null,
        ]);
    }
}
