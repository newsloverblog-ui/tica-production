<?php

namespace Tests\Unit;

use App\Http\Controllers\BackEnd\Organizer\CustomerMessageController;
use App\Http\Controllers\FrontEnd\SupportTicketController;
use App\Models\Customer;
use App\Models\Organizer;
use App\Models\SupportTicket;
use App\Services\SupportTicketMailService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class CustomerOrganizerMessagingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->string('fname')->nullable();
            $table->string('lname')->nullable();
            $table->string('email');
            $table->timestamps();
        });
        Schema::create('event_contents', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->string('title')->nullable();
            $table->timestamps();
        });
        Schema::create('bookings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('booking_id')->nullable();
            $table->unsignedBigInteger('event_id')->nullable();
            $table->unsignedBigInteger('organizer_id')->nullable();
            $table->string('paymentStatus')->nullable();
            $table->timestamps();
        });
        Schema::create('support_tickets', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('user_type');
            $table->string('recipient_type')->default('tica');
            $table->unsignedBigInteger('recipient_organizer_id')->nullable();
            $table->unsignedBigInteger('event_id')->nullable();
            $table->unsignedBigInteger('booking_id')->nullable();
            $table->string('email');
            $table->string('subject');
            $table->text('description');
            $table->string('attachment')->nullable();
            $table->unsignedTinyInteger('status')->default(1);
            $table->timestamp('last_message')->nullable();
            $table->timestamps();
        });
        Schema::create('conversations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedTinyInteger('type');
            $table->unsignedBigInteger('support_ticket_id');
            $table->text('reply');
            $table->string('file')->nullable();
            $table->timestamps();
        });
    }

    public function test_only_the_responsible_organizer_can_open_a_customer_conversation(): void
    {
        DB::table('customers')->insert(['id' => 3, 'fname' => 'Ada', 'email' => 'ada@example.com']);
        DB::table('event_contents')->insert(['event_id' => 11, 'title' => 'Test Night']);
        $ticket = SupportTicket::create([
            'user_id' => 3,
            'user_type' => 'customer',
            'recipient_type' => 'organizer',
            'recipient_organizer_id' => 7,
            'event_id' => 11,
            'email' => 'ada@example.com',
            'subject' => 'Venue access',
            'description' => 'Where is the entrance?',
            'status' => 1,
        ]);

        Auth::guard('organizer')->setUser($this->organizer(7));
        $response = app(CustomerMessageController::class)->show($ticket);
        $this->assertSame('organizer.customer_messages.show', $response->name());

        Auth::guard('organizer')->setUser($this->organizer(8));
        $this->expectException(NotFoundHttpException::class);
        app(CustomerMessageController::class)->show($ticket);
    }

    public function test_customer_cannot_message_an_organizer_through_someone_elses_booking(): void
    {
        DB::table('customers')->insert(['id' => 3, 'fname' => 'Ada', 'email' => 'ada@example.com']);
        DB::table('event_contents')->insert(['event_id' => 11, 'title' => 'Test Night']);
        DB::table('bookings')->insert([
            'id' => 21,
            'customer_id' => 99,
            'booking_id' => 'ORDER-21',
            'event_id' => 11,
            'organizer_id' => 7,
            'paymentStatus' => 'completed',
        ]);
        $customer = Customer::findOrFail(3);
        Auth::guard('customer')->setUser($customer);

        $request = Request::create('/customer/support-ticket/store', 'POST', [
            'recipient_type' => 'organizer',
            'booking_id' => 21,
            'email' => 'ada@example.com',
            'subject' => 'Venue access',
            'description' => 'Where is the entrance?',
        ]);

        $this->expectException(ModelNotFoundException::class);
        app(SupportTicketController::class)->store($request);
    }

    public function test_completed_booking_routes_the_message_to_its_organizer(): void
    {
        DB::table('customers')->insert(['id' => 3, 'fname' => 'Ada', 'email' => 'ada@example.com']);
        DB::table('event_contents')->insert(['event_id' => 11, 'title' => 'Test Night']);
        DB::table('bookings')->insert([
            'id' => 21,
            'customer_id' => 3,
            'booking_id' => 'ORDER-21',
            'event_id' => 11,
            'organizer_id' => 7,
            'paymentStatus' => 'completed',
        ]);
        Auth::guard('customer')->setUser(Customer::findOrFail(3));
        $mailer = Mockery::mock(SupportTicketMailService::class);
        $mailer->shouldReceive('created')->once();
        $this->app->instance(SupportTicketMailService::class, $mailer);

        $response = app(SupportTicketController::class)->store(Request::create('/customer/support-ticket/store', 'POST', [
            'recipient_type' => 'organizer',
            'booking_id' => 21,
            'email' => 'ada@example.com',
            'subject' => 'Venue access',
            'description' => 'Where is the entrance?',
        ]));

        $ticket = SupportTicket::firstOrFail();
        $this->assertSame('organizer', $ticket->recipient_type);
        $this->assertSame(7, (int) $ticket->recipient_organizer_id);
        $this->assertSame(11, (int) $ticket->event_id);
        $this->assertSame(21, (int) $ticket->booking_id);
        $this->assertStringContainsString('/customer/support-ticket/message/' . $ticket->id, $response->getTargetUrl());
    }

    private function organizer(int $id): Organizer
    {
        $organizer = new Organizer();
        $organizer->forceFill(['id' => $id, 'email' => 'organizer@example.com', 'username' => 'events']);
        $organizer->exists = true;
        return $organizer;
    }
}
