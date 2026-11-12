<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Services\EventCheckoutQuoteService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class EventCheckoutQuoteServiceTest extends TestCase
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
        $this->createSchema();

        DB::table('basic_settings')->insert(['tax' => 100, 'commission' => 10]);
        DB::table('events')->insert([
            'id' => 42,
            'status' => 1,
            'lifecycle_status' => 'scheduled',
            'registration_mode' => 'ticketing',
        ]);
    }

    public function test_quote_reprices_current_catalogue_and_revalidates_coupon(): void
    {
        $ticketId = DB::table('tickets')->insertGetId($this->ticket([
            'pricing_type' => 'normal',
            'price' => 7000,
        ]));
        $couponId = DB::table('coupons')->insertGetId([
            'code' => 'CURRENT',
            'type' => 'fixed',
            'value' => 1000,
            'events' => json_encode([42]),
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
        ]);
        session()->put('selTickets', [[
            'ticket_id' => $ticketId,
            'name' => 'General admission',
            'qty' => 2,
            'price' => 1,
            'early_bird_dicount' => 999,
        ]]);
        session()->put('event_coupon_id', $couponId);
        session()->put('grand_total', 1);

        $quote = app(EventCheckoutQuoteService::class)->refreshSession(42);

        $this->assertSame(7000.0, $quote['selTickets'][0]['price']);
        $this->assertSame(14000.0, $quote['sub_total']);
        $this->assertSame(1000.0, $quote['discount']);
        $this->assertSame(780.0, $quote['tax']);
        $this->assertSame(13780.0, $quote['grand_total']);
        $this->assertSame($quote['grand_total'], (float) session('grand_total'));
    }

    public function test_expired_coupon_is_removed_instead_of_reusing_stale_amount(): void
    {
        $ticketId = DB::table('tickets')->insertGetId($this->ticket([
            'pricing_type' => 'normal',
            'price' => 5000,
        ]));
        $couponId = DB::table('coupons')->insertGetId([
            'code' => 'EXPIRED',
            'type' => 'fixed',
            'value' => 4000,
            'events' => null,
            'start_date' => now()->subDays(3)->toDateString(),
            'end_date' => now()->subDay()->toDateString(),
        ]);
        session()->put('selTickets', [['ticket_id' => $ticketId, 'name' => 'Entry', 'qty' => 1, 'price' => 5000]]);
        session()->put('event_coupon_id', $couponId);
        session()->put('discount', 4000);

        $quote = app(EventCheckoutQuoteService::class)->refreshSession(42);

        $this->assertSame(0.0, $quote['discount']);
        $this->assertFalse(session()->has('event_coupon_id'));
        $this->assertSame(5300.0, $quote['grand_total']);
    }

    public function test_variation_name_and_duplicate_identity_fail_closed(): void
    {
        $ticketId = DB::table('tickets')->insertGetId($this->ticket([
            'pricing_type' => 'variation',
            'variations' => json_encode([
                ['name' => 'VIP', 'price' => 20000, 'ticket_available_type' => 'limited', 'ticket_available' => 5],
            ]),
        ]));
        session()->put('selTickets', [['ticket_id' => $ticketId, 'name' => 'Forged', 'qty' => 1, 'price' => 1]]);

        try {
            app(EventCheckoutQuoteService::class)->refreshSession(42);
            $this->fail('Expected a forged variation to be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('option', $exception->getMessage());
        }

        session()->put('selTickets', [
            ['ticket_id' => $ticketId, 'name' => 'VIP', 'qty' => 1, 'price' => 1],
            ['ticket_id' => $ticketId, 'name' => 'VIP', 'qty' => 1, 'price' => 1],
        ]);
        $this->expectException(RuntimeException::class);
        app(EventCheckoutQuoteService::class)->refreshSession(42);
    }

    public function test_full_discount_produces_a_zero_balance_quote_without_a_gateway_charge(): void
    {
        DB::table('basic_settings')->update(['tax' => 0]);
        $ticketId = DB::table('tickets')->insertGetId($this->ticket([
            'pricing_type' => 'normal',
            'price' => 5000,
        ]));
        $couponId = DB::table('coupons')->insertGetId([
            'code' => 'COMPED',
            'type' => 'percentage',
            'value' => 100,
            'events' => json_encode([42]),
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
        ]);
        session()->put('selTickets', [['ticket_id' => $ticketId, 'name' => 'Entry', 'qty' => 1, 'price' => 5000]]);
        session()->put('event_coupon_id', $couponId);

        $quote = app(EventCheckoutQuoteService::class)->refreshSession(42);

        $this->assertSame(5000.0, $quote['discount']);
        $this->assertSame(0.0, $quote['grand_total']);
        $this->assertSame(0.0, $quote['total']);
    }

    public function test_variation_and_cumulative_customer_limits_are_revalidated(): void
    {
        $ticketId = DB::table('tickets')->insertGetId($this->ticket([
            'pricing_type' => 'variation',
            'variations' => json_encode([[
                'name' => 'VIP',
                'price' => 20000,
                'ticket_available_type' => 'unlimited',
                'ticket_available' => 0,
                'max_ticket_buy_type' => 'limited',
                'v_max_ticket_buy' => 3,
            ]]),
        ]));
        $customer = Customer::query()->create([
            'email' => 'buyer@example.test',
            'password' => bcrypt('secret'),
            'status' => 1,
        ]);
        DB::table('bookings')->insert([
            'customer_id' => $customer->id,
            'event_id' => 42,
            'ticket_id' => null,
            'variation' => json_encode([['ticket_id' => $ticketId, 'name' => 'VIP', 'qty' => 2]]),
            'quantity' => 2,
            'paymentStatus' => 'completed',
        ]);
        Auth::guard('customer')->login($customer);
        session()->put('selTickets', [[
            'ticket_id' => $ticketId,
            'name' => 'VIP',
            'qty' => 2,
            'price' => 1,
        ]]);

        try {
            app(EventCheckoutQuoteService::class)->refreshSession(42);
            $this->fail('Expected the current variation limit to include prior purchases.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('purchase limit', $exception->getMessage());
        } finally {
            Auth::guard('customer')->logout();
        }
    }

    private function ticket(array $attributes): array
    {
        return array_merge([
            'event_id' => 42,
            'title' => 'Entry',
            'pricing_type' => 'normal',
            'price' => 0,
            'ticket_available_type' => 'unlimited',
            'ticket_available' => 0,
            'max_ticket_buy_type' => 'unlimited',
            'max_buy_ticket' => 0,
            'early_bird_discount' => 'disable',
            'variations' => null,
        ], $attributes);
    }

    private function createSchema(): void
    {
        Schema::create('basic_settings', function (Blueprint $table): void {
            $table->id();
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('commission', 8, 4)->default(0);
        });
        Schema::create('events', function (Blueprint $table): void {
            $table->id();
            $table->boolean('status')->default(true);
            $table->string('lifecycle_status')->nullable();
            $table->string('registration_mode')->nullable();
            $table->timestamp('sales_closed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('tickets', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->string('title')->nullable();
            $table->string('pricing_type');
            $table->decimal('price', 12, 2)->nullable();
            $table->string('ticket_available_type')->nullable();
            $table->integer('ticket_available')->default(0);
            $table->string('max_ticket_buy_type')->nullable();
            $table->integer('max_buy_ticket')->default(0);
            $table->string('early_bird_discount')->nullable();
            $table->decimal('early_bird_discount_amount', 12, 2)->nullable();
            $table->string('early_bird_discount_type')->nullable();
            $table->date('early_bird_discount_date')->nullable();
            $table->time('early_bird_discount_time')->nullable();
            $table->text('variations')->nullable();
            $table->timestamps();
        });
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('status')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('bookings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('ticket_id')->nullable();
            $table->text('variation')->nullable();
            $table->unsignedInteger('quantity')->default(0);
            $table->string('paymentStatus');
            $table->timestamps();
        });
        Schema::create('coupons', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('type');
            $table->decimal('value', 12, 2);
            $table->text('events')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->timestamps();
        });
    }
}
