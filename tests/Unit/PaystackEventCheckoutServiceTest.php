<?php

namespace Tests\Unit;

use App\Models\PaymentIntent;
use App\Services\PaystackEventCheckoutService;
use App\Services\PaystackGatewayClient;
use App\Services\IncidentAlertService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Mockery;
use Tests\TestCase;

class PaystackEventCheckoutServiceTest extends TestCase
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

        Schema::create('payment_intents', function (Blueprint $table): void {
            $table->id();
            $table->string('gateway', 40); $table->string('reference', 191);
            $table->unsignedBigInteger('event_id'); $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('booking_id')->nullable(); $table->string('status', 32);
            $table->decimal('amount', 18, 2); $table->string('currency', 8); $table->string('email');
            $table->longText('payload'); $table->text('authorization_url')->nullable();
            $table->text('last_error')->nullable(); $table->string('gateway_transaction_id')->nullable();
            $table->unsignedInteger('verification_attempts')->default(0);
            $table->timestamp('last_verified_at')->nullable(); $table->timestamp('expires_at')->nullable();
            $table->timestamp('processed_at')->nullable(); $table->timestamps();
            $table->unique(['gateway', 'reference']);
        });
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id(); $table->string('actor_type'); $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action'); $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable(); $table->json('before')->nullable();
            $table->json('after')->nullable(); $table->json('metadata')->nullable();
            $table->string('request_id')->nullable(); $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable(); $table->timestamp('created_at')->nullable();
        });
    }

    public function test_it_persists_intent_before_calling_paystack_and_uses_same_reference(): void
    {
        Http::fake([
            'https://api.paystack.co/transaction/initialize' => function ($request) {
                $stored = PaymentIntent::where('reference', $request['reference'])->first();
                $this->assertNotNull($stored, 'The durable intent must exist before the provider call.');
                $this->assertSame('initializing', $stored->status);

                return Http::response([
                    'status' => true,
                    'data' => [
                        'reference' => $request['reference'],
                        'authorization_url' => 'https://checkout.paystack.com/secure/event',
                    ],
                ]);
            },
        ]);

        $intent = (new PaystackEventCheckoutService())->initialize(
            $this->checkout(),
            new PaystackGatewayClient('test-secret')
        );

        $this->assertSame('initialized', $intent->status);
        $this->assertStringStartsWith('tica-pay-', $intent->reference);
        $this->assertSame('https://checkout.paystack.com/secure/event', $intent->authorization_url);
        $this->assertSame('buyer@example.com', $intent->email);
        $this->assertSame('payment.created', DB::table('audit_logs')->value('action'));
    }

    public function test_definitive_provider_rejection_marks_durable_intent_failed(): void
    {
        Http::fake([
            'https://api.paystack.co/transaction/initialize' => Http::response([
                'status' => false, 'message' => 'Transaction rejected',
            ], 400),
        ]);

        try {
            (new PaystackEventCheckoutService())->initialize(
                $this->checkout(),
                new PaystackGatewayClient('test-secret')
            );
            $this->fail('A definitive provider rejection must throw.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Transaction rejected', $exception->getMessage());
        }

        $intent = PaymentIntent::firstOrFail();
        $this->assertSame('failed', $intent->status);
        $this->assertSame('Transaction rejected', $intent->last_error);
        $this->assertSame(
            ['payment.created', 'payment.initialization_failed'],
            DB::table('audit_logs')->orderBy('id')->pluck('action')->all()
        );
    }

    public function test_ambiguous_provider_outcome_is_recoverable_and_does_not_discard_intent(): void
    {
        Http::fake([
            'https://api.paystack.co/transaction/initialize' => Http::response([
                'message' => 'Upstream timed out after accepting the request',
            ], 503),
        ]);
        $alerts = Mockery::mock(IncidentAlertService::class);
        $alerts->shouldReceive('alert')->once()->andReturn(['sent' => true]);
        app()->instance(IncidentAlertService::class, $alerts);

        $intent = (new PaystackEventCheckoutService())->initialize(
            $this->checkout(),
            new PaystackGatewayClient('test-secret')
        );

        $this->assertSame('verification_required', $intent->status);
        $this->assertStringContainsString('timed out', $intent->last_error);
        $this->assertNotNull($intent->payload);
        $this->assertNull($intent->authorization_url);
    }

    public function test_event_paystack_controller_has_no_session_cache_or_blocking_http_fallback(): void
    {
        $source = (string) file_get_contents(app_path(
            'Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php'
        ));

        $this->assertStringNotContainsString('curl_init(', $source);
        $this->assertStringNotContainsString('sleep(', $source);
        $this->assertStringNotContainsString('paystack_data_', $source);
        $this->assertStringNotContainsString('paystack_event_', $source);
        $this->assertStringNotContainsString('paystack_done_', $source);
        $this->assertStringNotContainsString('Recovered a legacy in-flight payment intent', $source);
    }

    private function checkout(): array
    {
        return [
            'event_id' => 42,
            'customer_id' => 7,
            'amount' => 12500.50,
            'currency' => 'ngn',
            'email' => 'Buyer@Example.com',
            'payload' => [
                'price' => 12000.50,
                'tax' => 500,
                'currencyText' => 'NGN',
                'email' => 'buyer@example.com',
                'reservation_token' => 'reservation-1',
            ],
        ];
    }
}
