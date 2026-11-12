<?php

namespace Tests\Unit;

use App\Http\Controllers\BackEnd\FunnelAnalyticsController;
use App\Models\FunnelAnalyticsEvent;
use App\Models\PaymentIntent;
use App\Services\FunnelAnalyticsService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

class FunnelAnalyticsServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        config()->set('app.key', 'base64:' . base64_encode(str_repeat('a', 32)));
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->createSchema();
    }

    public function test_browser_search_is_data_minimised_and_session_is_hashed(): void
    {
        $request = Request::create('/analytics/events', 'POST');
        $request->setLaravelSession(app('session')->driver());
        $request->session()->setId('raw-session-identifier');
        $sessionId = $request->session()->getId();

        $event = app(FunnelAnalyticsService::class)->recordBrowser($request, [
            'event' => 'search',
            'occurred_at' => now()->toIso8601String(),
            'properties' => [
                'search_term' => 'private birthday plan',
                'location' => 'Lagos',
                'category' => 'Parties',
                'email' => 'person@example.com',
            ],
        ]);

        $this->assertSame('browser', $event->source);
        $this->assertSame(hash_hmac('sha256', $sessionId, config('app.key')), $event->session_hash);
        $this->assertNotSame($sessionId, $event->session_hash);
        $this->assertTrue($event->properties['has_query']);
        $this->assertSame(21, $event->properties['query_length']);
        $this->assertSame('Lagos', $event->properties['location']);
        $this->assertArrayNotHasKey('search_term', $event->properties);
        $this->assertArrayNotHasKey('email', $event->properties);
        $this->assertStringNotContainsString('private birthday plan', (string) DB::table('funnel_analytics_events')->value('properties'));
    }

    public function test_browser_cannot_claim_a_payment_or_delivery_outcome(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(FunnelAnalyticsService::class)->recordBrowser(Request::create('/analytics/events', 'POST'), [
            'event' => 'payment_success',
            'properties' => ['amount' => 1],
        ]);
    }

    public function test_server_events_are_encrypted_and_deduplicated(): void
    {
        $service = app(FunnelAnalyticsService::class);
        $first = $service->recordServer('payment_success', [
            'event_id' => 42,
            'booking_id' => 9,
            'gateway' => 'paystack',
            'amount' => 5000,
            'email' => 'must-not-persist@example.com',
        ], 'payment:success:77');
        $second = $service->recordServer('payment_success', [
            'event_id' => 999,
            'amount' => 1,
        ], 'payment:success:77');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, FunnelAnalyticsEvent::count());
        $this->assertSame('paystack', $first->properties['gateway']);
        $this->assertArrayNotHasKey('email', $first->properties);
        $this->assertStringNotContainsString('paystack', (string) DB::table('funnel_analytics_events')->value('properties'));
    }

    public function test_payment_intent_status_transitions_emit_authoritative_events_once(): void
    {
        $intent = PaymentIntent::create([
            'gateway' => 'paystack',
            'reference' => 'ref-analytics-1',
            'event_id' => 44,
            'status' => 'initialized',
            'amount' => 2500,
            'currency' => 'NGN',
            'email' => 'buyer@example.com',
            'payload' => [],
        ]);
        $intent->update(['status' => 'completed', 'processed_at' => now()]);
        $intent->update(['status' => 'completed']);

        $this->assertDatabaseHas('funnel_analytics_events', [
            'event_name' => 'payment_attempt',
            'payment_intent_id' => $intent->id,
            'event_id' => 44,
            'source' => 'server',
        ]);
        $this->assertDatabaseHas('funnel_analytics_events', [
            'event_name' => 'payment_success',
            'payment_intent_id' => $intent->id,
            'event_id' => 44,
            'source' => 'server',
        ]);
        $this->assertSame(2, FunnelAnalyticsEvent::count());
    }

    public function test_http_endpoint_accepts_only_non_financial_browser_events(): void
    {
        $accepted = $this->postJson(route('analytics.events.store'), [
            'event' => 'view_event',
            'occurred_at' => now()->toIso8601String(),
            'properties' => ['event_id' => 52, 'category' => 'Music', 'email' => 'drop@example.com'],
        ]);
        $accepted->assertStatus(202);
        $this->assertDatabaseHas('funnel_analytics_events', ['event_name' => 'view_event', 'event_id' => 52]);

        $this->postJson(route('analytics.events.store'), [
            'event' => 'purchase',
            'properties' => ['event_id' => 52, 'value' => 100],
        ])->assertStatus(422);
        $this->assertSame(1, FunnelAnalyticsEvent::count());
    }

    public function test_admin_dashboard_builds_period_and_event_conversion_metrics(): void
    {
        $service = app(FunnelAnalyticsService::class);
        $request = Request::create('/analytics/events', 'POST');
        $service->recordBrowser($request, ['event' => 'view_event', 'properties' => ['event_id' => 52]]);
        $service->recordBrowser($request, ['event' => 'view_event', 'properties' => ['event_id' => 52]]);
        $service->recordBrowser($request, ['event' => 'begin_checkout', 'properties' => ['event_id' => 52, 'value' => 5000]]);
        $service->recordServer('payment_success', ['event_id' => 52, 'amount' => 5000], 'dashboard-payment-1');
        DB::table('event_contents')->insert([
            'event_id' => 52, 'title' => 'Analytics concert', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $dashboard = (new FunnelAnalyticsController())->index(Request::create('/admin/analytics/funnel', 'GET', [
            'from' => now()->toDateString(),
            'to' => now()->toDateString(),
        ]));
        $data = $dashboard->getData();

        $this->assertSame(2, $data['counts']['view_event']);
        $this->assertSame(1, $data['counts']['begin_checkout']);
        $this->assertSame(1, $data['counts']['payment_success']);
        $this->assertSame('Analytics concert', $data['titles'][52]);
        $this->assertSame(50.0, (float) $data['topEvents']->first()->purchases / (float) $data['topEvents']->first()->views * 100);
    }

    private function createSchema(): void
    {
        Schema::create('funnel_analytics_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('event_name', 64)->index();
            $table->string('source', 16)->index();
            $table->unsignedBigInteger('event_id')->nullable()->index();
            $table->unsignedBigInteger('booking_id')->nullable()->index();
            $table->unsignedBigInteger('payment_intent_id')->nullable()->index();
            $table->unsignedBigInteger('organizer_id')->nullable()->index();
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->char('session_hash', 64)->nullable()->index();
            $table->string('dedupe_key', 191)->nullable()->unique();
            $table->longText('properties')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();
        });

        Schema::create('payment_intents', function (Blueprint $table) {
            $table->id();
            $table->string('gateway');
            $table->string('reference');
            $table->unsignedBigInteger('event_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('booking_id')->nullable();
            $table->string('status');
            $table->decimal('amount', 12, 2);
            $table->string('currency');
            $table->string('email')->nullable();
            $table->longText('payload')->nullable();
            $table->text('authorization_url')->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedInteger('verification_attempts')->default(0);
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('event_contents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->string('title');
            $table->timestamps();
        });
    }
}
