<?php

namespace Tests\Unit;

use App\Http\Controllers\FrontEnd\Event\BookingController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EventCheckoutBoundaryTest extends TestCase
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
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        Schema::create('basic_settings', function (Blueprint $table): void {
            $table->id();
            $table->integer('event_guest_checkout_status')->default(0);
            $table->timestamps();
        });

        session()->flush();
    }

    public function test_guest_query_parameter_cannot_bypass_disabled_guest_checkout(): void
    {
        DB::table('basic_settings')->insert(['event_guest_checkout_status' => 0]);
        $request = $this->request(['type' => 'guest']);

        $response = app(BookingController::class)->index($request, 42);

        $this->assertSame(route('customer.login', ['redirectPath' => 'event_checkout']), $response->getTargetUrl());
    }

    public function test_enabled_guest_checkout_enforces_shared_attendee_contract(): void
    {
        DB::table('basic_settings')->insert(['event_guest_checkout_status' => 1]);
        $request = $this->request([
            'fname' => 'Ada',
            'lname' => 'Okafor',
            'email' => 'not-an-email',
            'phone' => '08000000000',
        ]);

        try {
            app(BookingController::class)->index($request, 42);
            $this->fail('Expected invalid attendee details to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('email', $exception->errors());
        }
    }

    public function test_request_total_cannot_downgrade_paid_session_to_free_booking(): void
    {
        DB::table('basic_settings')->insert(['event_guest_checkout_status' => 1]);
        session()->put('sub_total', 5000);
        $request = $this->request([
            'fname' => 'Ada',
            'lname' => 'Okafor',
            'email' => 'ada@example.com',
            'phone' => '08000000000',
            'total' => 0,
        ]);

        $response = app(BookingController::class)->index($request, 42);

        $this->assertTrue($response->isRedirect());
        $this->assertSame('Please select a payment method.', session()->get('error'));
    }

    private function request(array $input): Request
    {
        $request = Request::create('/ticket-booking/42', 'POST', $input);
        $request->setLaravelSession(app('session')->driver());
        $request->headers->set('referer', '/checkout');

        return $request;
    }
}
