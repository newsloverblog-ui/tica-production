<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\Event;
use App\Models\Organizer;
use App\Services\OperationalStatusService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OperationalStatusServiceTest extends TestCase
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

    public function test_organizer_deactivation_also_stops_all_event_sales_and_is_audited(): void
    {
        $organizer = $this->organizer(1);
        $event = $this->event($organizer->id, 1);

        $changed = (new OperationalStatusService())->setOrganizerStatus($organizer->id, 0);

        $this->assertTrue($changed);
        $this->assertSame(0, (int) $organizer->fresh()->status);
        $this->assertSame(0, (int) $event->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'organizer.status_changed', 'subject_id' => $organizer->id]);
    }

    public function test_status_transitions_are_idempotent_and_reactivation_is_audited_once(): void
    {
        $customer = Customer::create(['email' => 'status@example.com', 'password' => 'x', 'status' => 0]);
        $service = new OperationalStatusService();

        $this->assertTrue($service->setCustomerStatus($customer->id, 1));
        $this->assertFalse($service->setCustomerStatus($customer->id, 1));
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'customer.status_changed')->count());
    }

    public function test_event_status_enforces_organizer_ownership(): void
    {
        $owner = $this->organizer(1);
        $other = $this->organizer(1);
        $event = $this->event($owner->id, 0);

        try {
            (new OperationalStatusService())->setEventStatus($event->id, 1, $other->id);
            $this->fail('Cross-organizer status changes must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $this->assertSame(0, (int) $event->fresh()->status);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_email_verification_changes_are_validated_idempotent_and_audited(): void
    {
        $organizer = $this->organizer(1);
        $customer = Customer::create(['email' => 'verify@example.com', 'password' => 'x', 'status' => 1]);
        $service = new OperationalStatusService();

        $this->assertTrue($service->setOrganizerEmailVerification($organizer->id, 1));
        $this->assertFalse($service->setOrganizerEmailVerification($organizer->id, 1));
        $this->assertTrue($service->setCustomerEmailVerification($customer->id, 1));
        $this->assertNotNull($organizer->fresh()->email_verified_at);
        $this->assertNotNull($customer->fresh()->email_verified_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'organizer.email_verification_changed']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'customer.email_verification_changed']);
    }

    public function test_featured_event_changes_enforce_ownership_and_are_idempotently_audited(): void
    {
        $owner = $this->organizer(1);
        $other = $this->organizer(1);
        $event = $this->event($owner->id, 1);
        $service = new OperationalStatusService();

        $this->assertTrue($service->setEventFeatured($event->id, 'yes', $owner->id));
        $this->assertFalse($service->setEventFeatured($event->id, 'yes', $owner->id));
        try {
            $service->setEventFeatured($event->id, 'no', $other->id);
            $this->fail('Cross-organizer featured changes must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('is_featured', $exception->errors());
        }

        $this->assertSame('yes', $event->fresh()->is_featured);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'event.featured_status_changed')->count());
    }

    private function organizer(int $status): Organizer
    {
        return Organizer::create([
            'email' => uniqid('status-', true) . '@example.com', 'username' => uniqid('status-', true),
            'password' => 'x', 'status' => $status, 'amount' => 0,
        ]);
    }

    private function event(int $organizerId, int $status): Event
    {
        return Event::create(['organizer_id' => $organizerId, 'status' => $status, 'event_type' => 'venue']);
    }

    private function createSchema(): void
    {
        Schema::create('organizers', function (Blueprint $table) {
            $table->id(); $table->string('email')->nullable(); $table->string('username')->nullable();
            $table->string('password')->nullable(); $table->integer('status'); $table->decimal('amount', 18, 2);
            $table->timestamp('email_verified_at')->nullable(); $table->timestamps();
        });
        Schema::create('customers', function (Blueprint $table) {
            $table->id(); $table->string('email')->nullable(); $table->string('password')->nullable();
            $table->integer('status'); $table->timestamp('email_verified_at')->nullable(); $table->timestamps();
        });
        Schema::create('events', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('organizer_id'); $table->integer('status');
            $table->string('event_type')->nullable(); $table->string('is_featured')->default('no'); $table->timestamps();
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
