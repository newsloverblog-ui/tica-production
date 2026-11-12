<?php

namespace Tests\Unit;

use App\Models\Event;
use App\Models\Customer;
use App\Models\Organizer;
use App\Services\OperationalArchiveService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OperationalArchiveServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->createSchema();
    }

    public function test_event_archival_preserves_operational_and_financial_records(): void
    {
        $organizer = $this->organizer();
        $event = $this->event($organizer->id);
        DB::table('event_bookings')->insert(['event_id' => $event->id, 'booking_id' => 'BK-1']);
        DB::table('tickets')->insert(['event_id' => $event->id, 'name' => 'General']);
        DB::table('transactions')->insert(['organizer_id' => $organizer->id, 'event_id' => $event->id, 'amount' => 2500]);

        $changed = (new OperationalArchiveService())->archiveEvents([$event->id], $organizer->id);

        $this->assertSame(1, $changed);
        $this->assertSame(0, (int) $event->fresh()->status);
        $this->assertDatabaseCount('event_bookings', 1);
        $this->assertDatabaseCount('tickets', 1);
        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'event.archived', 'subject_id' => $event->id]);
        $this->assertSame(0, (new OperationalArchiveService())->archiveEvents([$event->id], $organizer->id));
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'event.archived')->count());
    }

    public function test_organizer_archival_preserves_withdrawals_and_events_and_deactivates_sales(): void
    {
        $organizer = $this->organizer();
        $event = $this->event($organizer->id);
        DB::table('withdraws')->insert(['organizer_id' => $organizer->id, 'amount' => 1000, 'status' => 0]);

        $changed = (new OperationalArchiveService())->archiveOrganizers([$organizer->id]);

        $this->assertSame(1, $changed);
        $this->assertSame(0, (int) $organizer->fresh()->status);
        $this->assertSame(0, (int) $event->fresh()->status);
        $this->assertDatabaseCount('organizers', 1);
        $this->assertDatabaseCount('events', 1);
        $this->assertDatabaseCount('withdraws', 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'organizer.archived', 'subject_id' => $organizer->id]);
    }

    public function test_organizer_cannot_archive_another_organizers_event(): void
    {
        $owner = $this->organizer();
        $other = $this->organizer();
        $event = $this->event($owner->id);

        try {
            (new OperationalArchiveService())->archiveEvents([$event->id], $other->id);
            $this->fail('Cross-organizer archival must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('events', $exception->errors());
        }

        $this->assertSame(1, (int) $event->fresh()->status);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_customer_archival_preserves_booking_and_order_history(): void
    {
        $customer = Customer::create([
            'email' => 'attendee@example.com', 'username' => 'attendee',
            'password' => 'not-used', 'status' => 1,
        ]);
        DB::table('customer_bookings')->insert(['customer_id' => $customer->id, 'booking_id' => 'BK-CUSTOMER']);
        DB::table('order_items')->insert(['user_id' => $customer->id, 'description' => 'Merchandise']);

        $changed = (new OperationalArchiveService())->archiveCustomers([$customer->id]);

        $this->assertSame(1, $changed);
        $this->assertSame(0, (int) $customer->fresh()->status);
        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseCount('customer_bookings', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'customer.archived', 'subject_id' => $customer->id]);
        $this->assertSame(0, (new OperationalArchiveService())->archiveCustomers([$customer->id]));
    }

    private function organizer(): Organizer
    {
        return Organizer::create([
            'email' => uniqid('archive-', true) . '@example.com',
            'username' => uniqid('archive-', true),
            'password' => 'not-used', 'status' => 1, 'amount' => 0,
        ]);
    }

    private function event(int $organizerId): Event
    {
        return Event::create(['organizer_id' => $organizerId, 'status' => 1, 'event_type' => 'venue']);
    }

    private function createSchema(): void
    {
        Schema::create('organizers', function (Blueprint $table) {
            $table->id(); $table->string('email')->nullable(); $table->string('username')->nullable();
            $table->string('password')->nullable(); $table->integer('status')->default(1);
            $table->decimal('amount', 18, 2)->default(0); $table->timestamps();
        });
        Schema::create('events', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('organizer_id'); $table->integer('status')->default(1);
            $table->string('event_type')->nullable(); $table->timestamps();
        });
        Schema::create('customers', function (Blueprint $table) {
            $table->id(); $table->string('email')->nullable(); $table->string('username')->nullable();
            $table->string('password')->nullable(); $table->integer('status')->default(1); $table->timestamps();
        });
        Schema::create('customer_bookings', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('customer_id'); $table->string('booking_id');
        });
        Schema::create('order_items', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('user_id'); $table->string('description');
        });
        Schema::create('event_bookings', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('event_id'); $table->string('booking_id');
        });
        Schema::create('tickets', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('event_id'); $table->string('name'); $table->softDeletes();
        });
        Schema::create('withdraws', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('organizer_id'); $table->decimal('amount', 18, 2); $table->integer('status');
        });
        Schema::create('transactions', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('organizer_id'); $table->unsignedBigInteger('event_id'); $table->decimal('amount', 18, 2);
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
