<?php

namespace Tests\Unit;

use App\Http\Middleware\TrackWebhookReceipt;
use App\Models\WebhookReceipt;
use App\Services\IncidentAlertService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class WebhookObservabilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        config()->set('app.key', 'base64:' . base64_encode(str_repeat('w', 32)));
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        Schema::create('webhook_receipts', function (Blueprint $table) {
            $table->id(); $table->uuid('public_id')->unique(); $table->string('gateway', 32);
            $table->string('event_type', 100)->nullable(); $table->char('body_hash', 64);
            $table->char('reference_hash', 64)->nullable(); $table->string('request_id', 100)->nullable();
            $table->boolean('signature_present')->default(false); $table->boolean('signature_valid')->nullable();
            $table->string('outcome', 32)->default('received'); $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('processing_ms')->nullable(); $table->string('error_code', 100)->nullable();
            $table->timestamp('received_at'); $table->timestamp('completed_at')->nullable(); $table->timestamps();
        });
    }

    public function test_middleware_records_hashed_metadata_without_raw_payload(): void
    {
        $body = json_encode([
            'event' => 'charge.success',
            'data' => ['reference' => 'private-reference-123', 'email' => 'buyer@example.test'],
        ]);
        $request = Request::create('/paystack/webhook', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => 'signature-present',
        ], $body);
        $request->attributes->set('request_id', 'request-abc');

        $response = (new TrackWebhookReceipt())->handle($request, function ($request) {
            $request->attributes->set('webhook_signature_valid', true);
            return response()->json(['status' => 'accepted']);
        }, 'paystack');

        $this->assertSame(200, $response->getStatusCode());
        $receipt = WebhookReceipt::firstOrFail();
        $this->assertSame('charge.success', $receipt->event_type);
        $this->assertSame('processed', $receipt->outcome);
        $this->assertTrue($receipt->signature_present);
        $this->assertTrue($receipt->signature_valid);
        $this->assertSame(hash('sha256', $body), $receipt->body_hash);
        $this->assertSame(hash_hmac('sha256', 'private-reference-123', config('app.key')), $receipt->reference_hash);
        $serialized = json_encode(DB::table('webhook_receipts')->first());
        $this->assertStringNotContainsString('private-reference-123', $serialized);
        $this->assertStringNotContainsString('buyer@example.test', $serialized);
    }

    public function test_invalid_signature_response_is_classified_as_rejected(): void
    {
        $request = Request::create('/flutterwave/webhook', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_VERIF_HASH' => 'invalid',
        ], json_encode(['event' => 'charge.completed', 'data' => ['tx_ref' => 'tx-ref']]));

        (new TrackWebhookReceipt())->handle($request, function ($request) {
            $request->attributes->set('webhook_signature_valid', false);
            return response()->json(['status' => 'invalid signature'], 401);
        }, 'flutterwave');

        $this->assertDatabaseHas('webhook_receipts', [
            'gateway' => 'flutterwave', 'outcome' => 'rejected', 'http_status' => 401,
            'signature_present' => 1, 'signature_valid' => 0,
        ]);
    }

    public function test_monitor_alerts_when_gateway_failure_limit_is_reached(): void
    {
        config()->set('operations.webhook_monitor_window_minutes', 15);
        config()->set('operations.webhook_failure_limit', 2);
        config()->set('operations.webhook_rejection_limit', 20);
        config()->set('operations.webhook_stuck_minutes', 5);
        foreach (range(1, 2) as $index) {
            WebhookReceipt::create([
                'gateway' => 'paystack', 'event_type' => 'charge.success',
                'body_hash' => hash('sha256', 'failure-' . $index),
                'outcome' => 'failed', 'http_status' => 500,
                'received_at' => now(), 'completed_at' => now(),
            ]);
        }
        $alerts = Mockery::mock(IncidentAlertService::class);
        $alerts->shouldReceive('alert')->once()->withArgs(fn ($key, $title, $context) =>
            $key === 'webhook-health:paystack' && $context['counts']['failed'] === 2
        )->andReturn(['sent' => true]);
        $this->app->instance(IncidentAlertService::class, $alerts);

        $this->artisan('ops:monitor-webhooks')->assertExitCode(1);
    }

    public function test_pruner_deletes_only_receipts_older_than_the_retention_window(): void
    {
        config()->set('operations.webhook_receipt_retention_days', 90);
        foreach ([
            ['body' => 'expired', 'received_at' => now()->subDays(91)],
            ['body' => 'boundary', 'received_at' => now()->subDays(90)],
            ['body' => 'recent', 'received_at' => now()->subDay()],
        ] as $receipt) {
            WebhookReceipt::create([
                'gateway' => 'paystack',
                'event_type' => 'charge.success',
                'body_hash' => hash('sha256', $receipt['body']),
                'outcome' => 'processed',
                'http_status' => 200,
                'received_at' => $receipt['received_at'],
                'completed_at' => $receipt['received_at'],
            ]);
        }

        $this->artisan('ops:prune-webhook-receipts --json')
            ->expectsOutputToContain('"deleted":1')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('webhook_receipts', ['body_hash' => hash('sha256', 'expired')]);
        $this->assertDatabaseHas('webhook_receipts', ['body_hash' => hash('sha256', 'boundary')]);
        $this->assertDatabaseHas('webhook_receipts', ['body_hash' => hash('sha256', 'recent')]);
    }

    public function test_admin_webhook_ledger_is_inside_the_transaction_permission_boundary(): void
    {
        $route = app('router')->getRoutes()->getByName('admin.webhook_operations.index');

        $this->assertNotNull($route);
        $this->assertContains('auth:admin', $route->gatherMiddleware());
        $this->assertContains('Deactive:admin', $route->gatherMiddleware());
        $this->assertContains('permission:Transaction', $route->gatherMiddleware());
    }
}
