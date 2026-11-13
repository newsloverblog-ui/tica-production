<?php

namespace Tests\Unit;

use App\Http\Controllers\FrontEnd\CheckOutController;
use App\Models\Customer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CheckoutTicketPricingAuthorityTest extends TestCase
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

        DB::table('basic_settings')->insert(['event_guest_checkout_status' => 1]);
        DB::table('events')->insert([
            'id' => 10,
            'status' => 1,
            'lifecycle_status' => 'scheduled',
            'registration_mode' => 'ticketing',
            'event_type' => 'online',
            'date_type' => 'single',
            'start_date' => now()->addDay()->toDateString(),
            'start_time' => '18:00:00',
            'end_date' => now()->addDay()->toDateString(),
            'end_time' => '22:00:00',
            'end_date_time' => now()->addDay()->setTime(22, 0),
        ]);
        DB::table('event_contents')->insert([
            'event_id' => 10,
            'title' => 'Server-priced event',
            'slug' => 'server-priced-event',
            'city' => 'Lagos',
            'country' => 'Nigeria',
        ]);
        DB::table('tickets')->insert([
            'event_id' => 10,
            'pricing_type' => 'normal',
            'price' => 5000,
            'ticket_available_type' => 'unlimited',
            'ticket_available' => 0,
            'max_ticket_buy_type' => 'limited',
            'max_buy_ticket' => 5,
            'early_bird_discount' => 'disable',
        ]);
    }

    public function test_client_cannot_downgrade_a_paid_ticket_to_free(): void
    {
        $response = app(CheckOutController::class)->checkout2($this->request([
            'event_id' => 10,
            'pricing_type' => 'free',
            'quantity' => 2,
        ]));

        $this->assertSame(route('check-out'), $response->getTargetUrl());
        $this->assertSame(10000.0, (float) session('sub_total'));
        $this->assertSame(2, session('quantity'));
        $this->assertSame(5000.0, (float) session('selTickets.0.price'));
    }

    public function test_online_quantity_must_be_a_bounded_scalar(): void
    {
        try {
            app(CheckOutController::class)->checkout2($this->request([
                'event_id' => 10,
                'pricing_type' => 'normal',
                'quantity' => [1, 1],
            ]));
            $this->fail('Expected an array quantity to be rejected for an online ticket.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('quantity', $exception->errors());
        }

        try {
            app(CheckOutController::class)->checkout2($this->request([
                'event_id' => 10,
                'pricing_type' => 'normal',
                'quantity' => 6,
            ]));
            $this->fail('Expected the server-owned ticket limit to be enforced.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('quantity', $exception->errors());
        }
    }

    public function test_existing_admissions_reduce_the_authenticated_purchase_limit(): void
    {
        $customer = Customer::query()->create([
            'email' => 'buyer@example.com',
            'password' => bcrypt('secret'),
            'status' => 1,
        ]);
        DB::table('bookings')->insert([
            'customer_id' => $customer->id,
            'event_id' => 10,
            'paymentStatus' => 'completed',
            'quantity' => 4,
        ]);
        Auth::guard('customer')->login($customer);

        try {
            app(CheckOutController::class)->checkout2($this->request([
                'event_id' => 10,
                'pricing_type' => 'normal',
                'quantity' => 2,
            ]));
            $this->fail('Expected the cumulative server-owned purchase limit to be enforced.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('quantity', $exception->errors());
        } finally {
            Auth::guard('customer')->logout();
        }
    }

    public function test_event_date_is_derived_from_the_event_not_the_browser(): void
    {
        app(CheckOutController::class)->checkout2($this->request([
            'event_id' => 10,
            'pricing_type' => 'normal',
            'quantity' => 1,
            'event_date' => 'Fri, Jan 01, 2099 01:00am',
        ]));

        $expected = FullDateTime(
            DB::table('events')->where('id', 10)->value('start_date').' '.
            DB::table('events')->where('id', 10)->value('start_time')
        );
        $this->assertSame($expected, session('event_date'));
    }

    public function test_checkout_reuses_the_browser_submission_id_across_retries(): void
    {
        $submissionId = (string) Str::uuid();
        $selection = [
            'event_id' => 10,
            'quantity' => 1,
            'submission_id' => $submissionId,
        ];

        app(CheckOutController::class)->checkout2($this->request($selection));
        $this->assertSame($submissionId, session('checkout_submission_token'));

        app(CheckOutController::class)->checkout2($this->request($selection));
        $this->assertSame($submissionId, session('checkout_submission_token'));
    }

    public function test_multiple_date_selection_must_belong_to_the_event_and_remain_available(): void
    {
        DB::table('events')->where('id', 10)->update(['date_type' => 'multiple']);
        $validStart = now()->addDays(2)->setTime(18, 0);
        DB::table('event_dates')->insert([
            'event_id' => 10,
            'start_date_time' => $validStart,
            'end_date_time' => $validStart->copy()->addHours(4),
        ]);

        try {
            app(CheckOutController::class)->checkout2($this->request([
                'event_id' => 10,
                'pricing_type' => 'normal',
                'quantity' => 1,
                'event_date' => FullDateTime(now()->addYears(3)),
            ]));
            $this->fail('Expected a date outside the event schedule to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('event_date', $exception->errors());
        }

        app(CheckOutController::class)->checkout2($this->request([
            'event_id' => 10,
            'pricing_type' => 'free',
            'quantity' => 1,
            'event_date' => FullDateTime($validStart),
        ]));
        $this->assertSame(FullDateTime($validStart), session('event_date'));
        $this->assertSame(5000.0, (float) session('sub_total'));
    }

    private function request(array $input): Request
    {
        $request = Request::create('/check-out2', 'POST', $input);
        $request->setLaravelSession(app('session')->driver());
        $request->headers->set('referer', '/event/server-priced-event/10');

        return $request;
    }

    private function createSchema(): void
    {
        Schema::create('basic_settings', function (Blueprint $table): void {
            $table->id();
            $table->boolean('event_guest_checkout_status')->default(false);
        });
        Schema::create('events', function (Blueprint $table): void {
            $table->id();
            $table->boolean('status')->default(true);
            $table->string('lifecycle_status')->nullable();
            $table->string('registration_mode')->nullable();
            $table->string('event_type');
            $table->string('date_type');
            $table->date('start_date')->nullable();
            $table->time('start_time')->nullable();
            $table->date('end_date')->nullable();
            $table->time('end_time')->nullable();
            $table->timestamp('end_date_time')->nullable();
            $table->timestamp('sales_closed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('event_contents', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->string('title');
            $table->string('slug');
            $table->string('city')->nullable();
            $table->string('country')->nullable();
        });
        Schema::create('event_dates', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->timestamp('start_date_time');
            $table->timestamp('end_date_time');
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
            $table->string('paymentStatus');
            $table->integer('quantity')->default(0);
            $table->timestamps();
        });
        Schema::create('tickets', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('event_id');
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
        Schema::create('online_gateways', function (Blueprint $table): void {
            $table->id();
            $table->string('keyword');
            $table->boolean('status')->default(false);
        });
        Schema::create('offline_gateways', function (Blueprint $table): void {
            $table->id();
            $table->boolean('status')->default(false);
            $table->integer('serial_number')->default(0);
        });
    }
}
