<?php

namespace Tests\Unit;

use App\Models\PaymentIntent;
use App\Models\PaymentReconciliationEntry;
use App\Models\Transaction;
use App\Services\PaystackGatewayClient;
use App\Services\PaystackReconciliationService;
use App\Services\IncidentAlertService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class PaystackReconciliationServiceTest extends TestCase
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

    public function test_reconciliation_records_matches_and_gateway_only_charges(): void
    {
        $this->intent('matched-ref', 100, 'completed', 41);
        $client = Mockery::mock(PaystackGatewayClient::class);
        $client->shouldReceive('listTransactions')->once()->andReturn([
            'status' => true,
            'data' => [
                $this->gatewayTransaction('matched-ref', 10000),
                $this->gatewayTransaction('gateway-only-ref', 5000),
            ],
            'meta' => ['pageCount' => 1],
        ]);

        $run = (new PaystackReconciliationService())->reconcile(CarbonImmutable::today(), $client);

        $this->assertSame('mismatched', $run->status);
        $this->assertSame(1, $run->matched_count);
        $this->assertSame(1, $run->mismatch_count);
        $this->assertDatabaseHas('payment_reconciliation_entries', [
            'reference' => 'matched-ref', 'result' => 'matched',
        ]);
        $this->assertDatabaseHas('payment_reconciliation_entries', [
            'reference' => 'gateway-only-ref', 'result' => 'gateway_only',
        ]);
    }

    public function test_reconciliation_pages_without_accumulating_the_provider_day(): void
    {
        $this->intent('page-one-ref', 100, 'completed', 51);
        $this->intent('page-two-ref', 75, 'completed', 52);
        $client = Mockery::mock(PaystackGatewayClient::class);
        $client->shouldReceive('listTransactions')->once()
            ->withArgs(fn ($from, $to, $page, $perPage) => $page === 1 && $perPage === 100)
            ->andReturn([
                'status' => true,
                'data' => [$this->gatewayTransaction('page-one-ref', 10000)],
                'meta' => ['pageCount' => 2],
            ]);
        $client->shouldReceive('listTransactions')->once()
            ->withArgs(fn ($from, $to, $page, $perPage) => $page === 2 && $perPage === 100)
            ->andReturn([
                'status' => true,
                'data' => [$this->gatewayTransaction('page-two-ref', 7500)],
                'meta' => ['pageCount' => 2],
            ]);

        $run = (new PaystackReconciliationService())->reconcile(CarbonImmutable::today(), $client);

        $this->assertSame('matched', $run->status);
        $this->assertSame(2, $run->gateway_count);
        $this->assertSame(2, $run->local_count);
        $this->assertSame(2, $run->matched_count);
        $this->assertSame(0, $run->mismatch_count);
    }

    public function test_excessive_provider_page_count_fails_without_false_local_only_entries(): void
    {
        $this->intent('unscanned-local-ref', 100, 'completed', 53);
        $client = Mockery::mock(PaystackGatewayClient::class);
        $client->shouldReceive('listTransactions')->once()->andReturn([
            'status' => true,
            'data' => [],
            'meta' => ['pageCount' => 1001],
        ]);

        $run = (new PaystackReconciliationService())->reconcile(CarbonImmutable::today(), $client);

        $this->assertSame('failed', $run->status);
        $this->assertSame(1, $run->local_count);
        $this->assertSame(0, PaymentReconciliationEntry::where('result', 'local_only')->count());
    }

    public function test_provider_failure_is_encrypted_and_incident_context_is_safe(): void
    {
        $client = Mockery::mock(PaystackGatewayClient::class);
        $client->shouldReceive('listTransactions')->once()
            ->andThrow(new RuntimeException('sensitive Paystack provider response', 503));
        $alerts = Mockery::mock(IncidentAlertService::class);
        $alerts->shouldReceive('alert')->once()->withArgs(function ($key, $title, $context): bool {
            return str_starts_with($key, 'paystack-reconciliation-failed:')
                && $title === 'Paystack reconciliation failed.'
                && !array_key_exists('error', $context)
                && $context['exception_type'] === RuntimeException::class
                && $context['exception_code'] === 503;
        })->andReturn([]);
        $this->app->instance(IncidentAlertService::class, $alerts);

        $run = (new PaystackReconciliationService())->reconcile(CarbonImmutable::today(), $client);

        $this->assertSame('failed', $run->status);
        $this->assertStringContainsString('sensitive Paystack provider response', (string) $run->last_error);
        $this->assertStringNotContainsString(
            'sensitive Paystack provider response',
            (string) DB::table('payment_reconciliation_runs')->where('id', $run->id)->value('last_error')
        );
    }

    public function test_reconciliation_flags_paid_without_booking_amount_drift_and_local_only(): void
    {
        $this->intent('bad-ref', 100, 'initialized', null);
        $this->intent('local-only-ref', 80, 'completed', 42);
        $client = Mockery::mock(PaystackGatewayClient::class);
        $client->shouldReceive('listTransactions')->once()->andReturn([
            'status' => true,
            'data' => [$this->gatewayTransaction('bad-ref', 12000)],
            'meta' => ['pageCount' => 1],
        ]);

        $run = (new PaystackReconciliationService())->reconcile(CarbonImmutable::today(), $client);

        $this->assertSame(0, $run->matched_count);
        $this->assertSame(2, $run->mismatch_count);
        $bad = PaymentReconciliationEntry::where('reference', 'bad-ref')->firstOrFail();
        $this->assertSame('paid_without_booking', $bad->result);
        $this->assertContains('amount_mismatch', $bad->details['issues']);
        $this->assertContains('local_status_not_completed', $bad->details['issues']);
        $this->assertDatabaseHas('payment_reconciliation_entries', [
            'reference' => 'local-only-ref', 'result' => 'local_only',
        ]);
    }

    public function test_completed_payment_without_gateway_ledger_identity_is_not_matched(): void
    {
        $this->intent('missing-ledger-ref', 100, 'completed', 43, false);
        $client = Mockery::mock(PaystackGatewayClient::class);
        $client->shouldReceive('listTransactions')->once()->andReturn([
            'status' => true,
            'data' => [$this->gatewayTransaction('missing-ledger-ref', 10000)],
            'meta' => ['pageCount' => 1],
        ]);

        $run = (new PaystackReconciliationService())->reconcile(CarbonImmutable::today(), $client);

        $this->assertSame(0, $run->matched_count);
        $entry = PaymentReconciliationEntry::firstOrFail();
        $this->assertSame('ledger_identity_missing', $entry->result);
        $this->assertContains('ledger_identity_missing', $entry->details['issues']);
    }

    private function intent(
        string $reference,
        float $amount,
        string $status,
        ?int $bookingId,
        bool $withLedger = true
    ): PaymentIntent
    {
        $intent = PaymentIntent::create([
            'gateway' => 'paystack', 'reference' => $reference, 'event_id' => 5,
            'booking_id' => $bookingId, 'status' => $status, 'amount' => $amount,
            'currency' => 'NGN', 'email' => 'buyer@example.com',
            'payload' => ['event_id' => 5],
            'processed_at' => $status === 'completed' ? now() : null,
        ]);
        if ($bookingId !== null && $withLedger) {
            Transaction::create([
                'transcation_id' => $reference,
                'gateway' => 'paystack',
                'gateway_reference' => $reference,
                'booking_id' => $bookingId,
                'transcation_type' => 1,
                'payment_status' => $status === 'completed' ? 1 : 0,
                'grand_total' => $amount,
                'tax' => 0,
            ]);
        }
        return $intent;
    }

    private function gatewayTransaction(string $reference, int $amount): array
    {
        return [
            'id' => random_int(1000, 9999), 'reference' => $reference,
            'amount' => $amount, 'currency' => 'NGN', 'status' => 'success',
        ];
    }

    private function createSchema(): void
    {
        Schema::create('payment_intents', function (Blueprint $table) {
            $table->id(); $table->string('gateway'); $table->string('reference');
            $table->unsignedBigInteger('event_id'); $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('booking_id')->nullable(); $table->string('status');
            $table->decimal('amount', 18, 2); $table->string('currency', 8); $table->string('email');
            $table->longText('payload'); $table->text('last_error')->nullable();
            $table->timestamp('expires_at')->nullable(); $table->timestamp('processed_at')->nullable(); $table->timestamps();
        });
        Schema::create('payment_reconciliation_runs', function (Blueprint $table) {
            $table->id(); $table->uuid('public_id'); $table->string('gateway'); $table->date('reconciliation_date');
            $table->string('status'); $table->unsignedInteger('gateway_count')->default(0);
            $table->unsignedInteger('local_count')->default(0); $table->unsignedInteger('matched_count')->default(0);
            $table->unsignedInteger('mismatch_count')->default(0); $table->text('last_error')->nullable();
            $table->timestamp('started_at'); $table->timestamp('completed_at')->nullable(); $table->timestamps();
        });
        Schema::create('payment_reconciliation_entries', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('run_id'); $table->unsignedBigInteger('payment_intent_id')->nullable();
            $table->unsignedBigInteger('booking_id')->nullable(); $table->string('gateway'); $table->string('reference');
            $table->string('result'); $table->decimal('expected_amount', 18, 2)->nullable();
            $table->decimal('gateway_amount', 18, 2)->nullable(); $table->string('expected_currency')->nullable();
            $table->string('gateway_currency')->nullable(); $table->string('local_status')->nullable();
            $table->string('gateway_status')->nullable(); $table->longText('details')->nullable();
            $table->timestamp('checked_at'); $table->timestamps();
        });
        Schema::create('transactions', function (Blueprint $table) {
            $table->id(); $table->string('transcation_id')->nullable(); $table->string('gateway', 40)->nullable();
            $table->string('gateway_reference', 191)->nullable(); $table->string('booking_id')->nullable();
            $table->integer('transcation_type'); $table->string('payment_status')->nullable();
            $table->decimal('grand_total', 18, 2)->default(0); $table->decimal('tax', 18, 2)->default(0);
            $table->timestamps(); $table->unique(['gateway', 'gateway_reference']);
        });
    }
}
