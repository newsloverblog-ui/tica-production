<?php

namespace Tests\Unit;

use App\Http\Controllers\FrontEnd\EventController;
use App\Models\Event\Coupon;
use App\Services\EventCouponService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EventCouponServiceTest extends TestCase
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
        session()->flush();

        Schema::create('coupons', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('code')->unique();
            $table->string('type');
            $table->decimal('value', 12, 2);
            $table->text('events')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->timestamps();
        });
    }

    public function test_coupon_is_event_scoped_date_bounded_and_server_calculated(): void
    {
        $coupon = $this->coupon([
            'type' => 'percentage',
            'value' => 25,
            'events' => json_encode([42]),
        ]);
        $service = app(EventCouponService::class);
        $today = CarbonImmutable::now();

        $this->assertSame(2250.0, $service->discount($coupon, 42, 10000, 1000, $today));
        $this->assertNull($service->discount($coupon, 43, 10000, 1000, $today));
        $this->assertNull($service->discount($coupon, 42, 10000, 1000, $today->addMonth()));
    }

    public function test_invalid_configuration_fails_closed_and_fixed_value_cannot_make_total_negative(): void
    {
        $service = app(EventCouponService::class);

        $fixed = $this->coupon(['code' => 'FIXED', 'type' => 'fixed', 'value' => 999999, 'events' => null]);
        $this->assertSame(4500.0, $service->discount($fixed, 42, 5000, 500));

        $overPercentage = $this->coupon(['code' => 'OVER', 'type' => 'percentage', 'value' => 101, 'events' => '[]']);
        $this->assertNull($service->discount($overPercentage, 42, 5000, 0));

        $malformed = $this->coupon(['code' => 'BADJSON', 'type' => 'fixed', 'value' => 100, 'events' => '{broken']);
        $this->assertNull($service->discount($malformed, 42, 5000, 0));
    }

    public function test_controller_clears_stale_discount_and_rejects_expired_checkout_session(): void
    {
        session()->put('discount', 500);
        session()->put('event_coupon_id', 99);
        $request = Request::create('/event-booking/apply-coupon', 'POST', ['coupon_code' => 'MISSING']);
        $request->setLaravelSession(app('session')->driver());

        $response = app(EventController::class)->applyCoupon($request);

        $this->assertSame('error', $response->getData(true)['status']);
        $this->assertSame('Your checkout session has expired.', $response->getData(true)['message']);
        $this->assertFalse(session()->has('discount'));
        $this->assertFalse(session()->has('event_coupon_id'));
    }

    public function test_controller_uses_only_the_server_held_event_and_subtotal(): void
    {
        $this->coupon([
            'code' => 'SAVE20',
            'type' => 'percentage',
            'value' => 20,
            'events' => json_encode([42]),
        ]);
        session()->put('event', (object) ['id' => 42]);
        session()->put('selTickets', [['ticket_id' => 9, 'qty' => 1, 'price' => 5000]]);
        session()->put('sub_total', 5000);
        session()->put('total_early_bird_dicount', 500);

        $request = Request::create('/event-booking/apply-coupon', 'POST', [
            'coupon_code' => 'SAVE20',
            'event_id' => 999,
            'subtotal' => 1,
        ]);
        $request->setLaravelSession(app('session')->driver());

        $response = app(EventController::class)->applyCoupon($request);

        $this->assertSame('success', $response->getData(true)['status']);
        $this->assertSame(900.0, (float) session('discount'));
    }

    private function coupon(array $attributes): Coupon
    {
        return Coupon::query()->create(array_merge([
            'name' => 'Test coupon',
            'code' => 'SAVE',
            'type' => 'fixed',
            'value' => 500,
            'events' => null,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
        ], $attributes));
    }
}
