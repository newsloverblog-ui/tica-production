<?php

namespace Tests\Unit;

use App\Models\PaymentIntent;
use App\Services\PaystackGatewayClient;
use App\Services\PaystackPaymentRecoveryService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class PaystackPaymentRecoveryServiceTest extends TestCase
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

    public function test_completed_intent_does_not_call_gateway_or_fulfillment_again(): void
    {
        $intent = $this->intent('completed-ref', 'completed', 91);
        $client = Mockery::mock(PaystackGatewayClient::class);
        $client->shouldNotReceive('verifyTransaction');
        $fulfilled = false;

        $outcome = (new PaystackPaymentRecoveryService())->attempt(
            $intent,
            function () use (&$fulfilled) { $fulfilled = true; },
            $client
        );

        $this->assertSame('completed', $outcome['status']);
        $this->assertSame(91, $outcome['booking_id']);
        $this->assertFalse($fulfilled);
        $this->assertSame(0, $intent->fresh()->verification_attempts);
    }

    public function test_pending_gateway_status_preserves_checkout_without_fulfilling(): void
    {
        $intent = $this->intent('pending-ref');
        $client = $this->clientReturning('pending-ref', ['status' => 'pending']);
        $fulfilled = false;

        $outcome = (new PaystackPaymentRecoveryService())->attempt(
            $intent,
            function () use (&$fulfilled) { $fulfilled = true; },
            $client
        );

        $this->assertSame('pending', $outcome['status']);
        $this->assertFalse($fulfilled);
        $intent->refresh();
        $this->assertSame('initialized', $intent->status);
        $this->assertSame(1, $intent->verification_attempts);
        $this->assertNotNull($intent->last_verified_at);
    }

    public function test_verified_payment_is_fulfilled_once_and_returns_booking(): void
    {
        $intent = $this->intent('success-ref');
        $client = $this->clientReturning('success-ref', $this->successfulGatewayPayment());
        $fulfillments = 0;

        $outcome = (new PaystackPaymentRecoveryService())->attempt(
            $intent,
            function () use ($intent, &$fulfillments) {
                $fulfillments++;
                $intent->update([
                    'status' => 'completed',
                    'booking_id' => 77,
                    'processed_at' => now(),
                ]);
                return ['status' => 'success'];
            },
            $client
        );

        $this->assertSame('completed', $outcome['status']);
        $this->assertSame(77, $outcome['booking_id']);
        $this->assertSame(1, $fulfillments);
        $this->assertSame(1, $intent->fresh()->verification_attempts);
    }

    public function test_verification_mismatch_fails_closed_and_is_audited(): void
    {
        $intent = $this->intent('mismatch-ref');
        $payment = $this->successfulGatewayPayment();
        $payment['amount'] = 1;
        $client = $this->clientReturning('mismatch-ref', $payment);
        $fulfilled = false;

        $outcome = (new PaystackPaymentRecoveryService())->attempt(
            $intent,
            function () use (&$fulfilled) { $fulfilled = true; },
            $client
        );

        $this->assertSame('failed', $outcome['status']);
        $this->assertFalse($fulfilled);
        $this->assertSame('failed', $intent->fresh()->status);
        $this->assertStringContainsString('amount_mismatch', (string) $intent->fresh()->last_error);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'payment.recovery_mismatch',
            'subject_id' => $intent->id,
        ]);
    }

    public function test_terminal_gateway_failure_is_idempotently_recorded_without_fulfillment(): void
    {
        $intent = $this->intent('failed-ref');
        $client = $this->clientReturning('failed-ref', ['status' => 'abandoned']);
        $fulfilled = false;

        $outcome = (new PaystackPaymentRecoveryService())->attempt(
            $intent,
            function () use (&$fulfilled) { $fulfilled = true; },
            $client
        );

        $this->assertSame('failed', $outcome['status']);
        $this->assertFalse($fulfilled);
        $this->assertSame('failed', $intent->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'payment.recovery_failed',
            'subject_id' => $intent->id,
        ]);
    }

    private function intent(string $reference, string $status = 'initialized', ?int $bookingId = null): PaymentIntent
    {
        return PaymentIntent::create([
            'gateway' => 'paystack',
            'reference' => $reference,
            'event_id' => 5,
            'booking_id' => $bookingId,
            'status' => $status,
            'amount' => 100,
            'currency' => 'NGN',
            'email' => 'buyer@example.com',
            'payload' => [
                'price' => 90,
                'tax' => 10,
                'currencyText' => 'NGN',
                'email' => 'buyer@example.com',
            ],
            'processed_at' => $status === 'completed' ? now() : null,
        ]);
    }

    private function clientReturning(string $reference, array $data): PaystackGatewayClient
    {
        $client = Mockery::mock(PaystackGatewayClient::class);
        $client->shouldReceive('verifyTransaction')->once()->with($reference)->andReturn([
            'status' => true,
            'data' => $data,
        ]);
        return $client;
    }

    private function successfulGatewayPayment(): array
    {
        return [
            'status' => 'success',
            'amount' => 10000,
            'currency' => 'NGN',
            'customer' => ['email' => 'buyer@example.com'],
        ];
    }

    private function createSchema(): void
    {
        Schema::create('payment_intents', function (Blueprint $table) {
            $table->id();
            $table->string('gateway');
            $table->string('reference');
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('booking_id')->nullable();
            $table->string('status');
            $table->decimal('amount', 18, 2);
            $table->string('currency', 8);
            $table->string('email');
            $table->longText('payload');
            $table->text('authorization_url')->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedInteger('verification_attempts')->default(0);
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('actor_type')->default('system');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->json('metadata')->nullable();
            $table->string('request_id')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }
}
