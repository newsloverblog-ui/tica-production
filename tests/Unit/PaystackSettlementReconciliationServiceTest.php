<?php

namespace Tests\Unit;

use App\Models\GatewaySettlementEntry;
use App\Models\PaymentIntent;
use App\Models\Transaction;
use App\Services\PaystackGatewayClient;
use App\Services\PaystackSettlementReconciliationService;
use App\Services\IncidentAlertService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class PaystackSettlementReconciliationServiceTest extends TestCase
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

    public function test_settlement_reconciliation_matches_local_payments_and_flags_gateway_only_rows(): void
    {
        $this->intent('matched-ref', 100, 71);
        $client = Mockery::mock(PaystackGatewayClient::class);
        $client->shouldReceive('listSettlements')->once()->andReturn($this->settlementsPayload(15000, 150));
        $client->shouldReceive('listSettlementTransactions')->once()->with('3001', 1, 100)->andReturn([
            'status' => true,
            'data' => [
                $this->transaction('matched-ref', 10000, 100),
                $this->transaction('gateway-only-ref', 5000, 50),
            ],
            'meta' => ['pageCount' => 1],
        ]);

        $settlements = (new PaystackSettlementReconciliationService())->reconcile(
            CarbonImmutable::today()->subDay(), CarbonImmutable::today(), $client
        );
        $settlement = $settlements[0];

        $this->assertSame('mismatched', $settlement->reconciliation_status);
        $this->assertSame(2, $settlement->transaction_count);
        $this->assertSame(1, $settlement->matched_count);
        $this->assertSame(1, $settlement->mismatch_count);
        $this->assertDatabaseHas('gateway_settlement_entries', [
            'reference' => 'matched-ref', 'result' => 'matched',
        ]);
        $this->assertDatabaseHas('gateway_settlement_entries', [
            'reference' => 'gateway-only-ref', 'result' => 'settled_charge_missing_locally',
        ]);
    }

    public function test_settlement_transactions_are_reconciled_across_provider_pages(): void
    {
        $this->intent('page-one-ref', 100, 71);
        $this->intent('page-two-ref', 50, 72);
        $client = Mockery::mock(PaystackGatewayClient::class);
        $client->shouldReceive('listSettlements')->once()->andReturn($this->settlementsPayload(15000, 150));
        $client->shouldReceive('listSettlementTransactions')
            ->once()
            ->with('3001', 1, 100)
            ->andReturn([
                'status' => true,
                'data' => [$this->transaction('page-one-ref', 10000, 100)],
                'meta' => ['pageCount' => 2],
            ]);
        $client->shouldReceive('listSettlementTransactions')
            ->once()
            ->with('3001', 2, 100)
            ->andReturn([
                'status' => true,
                'data' => [$this->transaction('page-two-ref', 5000, 50)],
                'meta' => ['pageCount' => 2],
            ]);

        $settlement = (new PaystackSettlementReconciliationService())->reconcile(
            CarbonImmutable::today()->subDay(), CarbonImmutable::today(), $client
        )[0];

        $this->assertSame('matched', $settlement->reconciliation_status);
        $this->assertSame(2, $settlement->transaction_count);
        $this->assertSame(2, $settlement->matched_count);
        $this->assertSame(0, $settlement->mismatch_count);
        $this->assertDatabaseCount('gateway_settlement_entries', 2);
    }

    public function test_excessive_transaction_page_count_fails_without_partial_settlement_evidence(): void
    {
        $this->intent('would-match-ref', 100, 71);
        $client = Mockery::mock(PaystackGatewayClient::class);
        $client->shouldReceive('listSettlements')->once()->andReturn($this->settlementsPayload(10000, 100));
        $client->shouldReceive('listSettlementTransactions')
            ->once()
            ->with('3001', 1, 100)
            ->andReturn([
                'status' => true,
                'data' => [$this->transaction('would-match-ref', 10000, 100)],
                'meta' => ['pageCount' => 1001],
            ]);

        $settlement = (new PaystackSettlementReconciliationService())->reconcile(
            CarbonImmutable::today()->subDay(), CarbonImmutable::today(), $client
        )[0];

        $this->assertSame('failed', $settlement->reconciliation_status);
        $this->assertSame(0, $settlement->transaction_count);
        $this->assertSame(0, $settlement->matched_count);
        $this->assertSame(0, $settlement->mismatch_count);
        $this->assertStringContainsString('page safety limit', $settlement->last_error);
        $this->assertDatabaseCount('gateway_settlement_entries', 0);
    }

    public function test_excessive_settlement_list_page_count_aborts_before_persisting_partial_range(): void
    {
        $client = Mockery::mock(PaystackGatewayClient::class);
        $payload = $this->settlementsPayload(10000, 100);
        $payload['meta']['pageCount'] = 1001;
        $client->shouldReceive('listSettlements')->once()->andReturn($payload);
        $client->shouldNotReceive('listSettlementTransactions');

        try {
            (new PaystackSettlementReconciliationService())->reconcile(
                CarbonImmutable::today()->subDay(), CarbonImmutable::today(), $client
            );
            $this->fail('An oversized provider range must fail closed.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('page safety limit', $exception->getMessage());
        }

        $this->assertDatabaseCount('gateway_settlements', 0);
        $this->assertDatabaseCount('gateway_settlement_entries', 0);
    }

    public function test_transaction_failure_is_encrypted_and_incident_context_is_safe(): void
    {
        $client = Mockery::mock(PaystackGatewayClient::class);
        $client->shouldReceive('listSettlements')->once()->andReturn($this->settlementsPayload(10000, 100));
        $client->shouldReceive('listSettlementTransactions')->once()
            ->andThrow(new RuntimeException('sensitive settlement transaction response', 502));
        $alerts = Mockery::mock(IncidentAlertService::class);
        $alerts->shouldReceive('alert')->once()->withArgs(function ($key, $title, $context): bool {
            return $key === 'paystack-settlement-failed:3001'
                && $title === 'Paystack settlement reconciliation failed.'
                && !array_key_exists('error', $context)
                && $context['exception_type'] === RuntimeException::class
                && $context['exception_code'] === 502;
        })->andReturn([]);
        $this->app->instance(IncidentAlertService::class, $alerts);

        $settlement = (new PaystackSettlementReconciliationService())->reconcile(
            CarbonImmutable::today()->subDay(), CarbonImmutable::today(), $client
        )[0];

        $this->assertSame('failed', $settlement->reconciliation_status);
        $this->assertStringContainsString('sensitive settlement transaction response', (string) $settlement->last_error);
        $this->assertStringNotContainsString(
            'sensitive settlement transaction response',
            (string) DB::table('gateway_settlements')->where('id', $settlement->id)->value('last_error')
        );
    }

    public function test_settlement_list_failure_emits_only_safe_exception_metadata(): void
    {
        $client = Mockery::mock(PaystackGatewayClient::class);
        $client->shouldReceive('listSettlements')->once()
            ->andThrow(new RuntimeException('sensitive settlement list response', 503));
        $alerts = Mockery::mock(IncidentAlertService::class);
        $alerts->shouldReceive('alert')->once()->withArgs(function ($key, $title, $context): bool {
            return str_starts_with($key, 'paystack-settlement-list-failed:')
                && $title === 'Paystack settlement listing failed.'
                && !array_key_exists('error', $context)
                && $context['exception_type'] === RuntimeException::class
                && $context['exception_code'] === 503;
        })->andReturn([]);
        $this->app->instance(IncidentAlertService::class, $alerts);

        try {
            (new PaystackSettlementReconciliationService())->reconcile(
                CarbonImmutable::today()->subDay(), CarbonImmutable::today(), $client
            );
            $this->fail('A provider settlement-list failure must fail the command.');
        } catch (RuntimeException $exception) {
            $this->assertSame(503, $exception->getCode());
        }

        $this->assertDatabaseCount('gateway_settlements', 0);
    }

    public function test_settlement_totals_and_fees_must_equal_the_transaction_breakdown(): void
    {
        $this->intent('matched-ref', 100, 71);
        $client = Mockery::mock(PaystackGatewayClient::class);
        $client->shouldReceive('listSettlements')->once()->andReturn($this->settlementsPayload(12000, 200));
        $client->shouldReceive('listSettlementTransactions')->once()->andReturn([
            'status' => true,
            'data' => [$this->transaction('matched-ref', 10000, 100)],
            'meta' => ['pageCount' => 1],
        ]);

        $settlement = (new PaystackSettlementReconciliationService())->reconcile(
            CarbonImmutable::today()->subDay(), CarbonImmutable::today(), $client
        )[0];

        $this->assertSame('mismatched', $settlement->reconciliation_status);
        $this->assertSame(2, $settlement->mismatch_count);
        $this->assertStringContainsString('gross', $settlement->last_error);
        $this->assertStringContainsString('fees', $settlement->last_error);
        $this->assertSame('matched', GatewaySettlementEntry::firstOrFail()->result);
    }

    public function test_reconciliation_can_be_rerun_without_duplicate_settlements_or_entries(): void
    {
        $this->intent('matched-ref', 100, 71);
        $client = Mockery::mock(PaystackGatewayClient::class);
        $client->shouldReceive('listSettlements')->twice()->andReturn($this->settlementsPayload(10000, 100));
        $client->shouldReceive('listSettlementTransactions')->twice()->andReturn([
            'status' => true,
            'data' => [$this->transaction('matched-ref', 10000, 100)],
            'meta' => ['pageCount' => 1],
        ]);
        $service = new PaystackSettlementReconciliationService();

        $service->reconcile(CarbonImmutable::today()->subDay(), CarbonImmutable::today(), $client);
        $service->reconcile(CarbonImmutable::today()->subDay(), CarbonImmutable::today(), $client);

        $this->assertDatabaseCount('gateway_settlements', 1);
        $this->assertDatabaseCount('gateway_settlement_entries', 1);
        $this->assertSame('matched', GatewaySettlementEntry::firstOrFail()->result);
    }

    public function test_settled_payment_without_gateway_ledger_identity_is_not_matched(): void
    {
        $this->intent('missing-ledger-ref', 100, 72, false);
        $client = Mockery::mock(PaystackGatewayClient::class);
        $client->shouldReceive('listSettlements')->once()->andReturn($this->settlementsPayload(10000, 100));
        $client->shouldReceive('listSettlementTransactions')->once()->andReturn([
            'status' => true,
            'data' => [$this->transaction('missing-ledger-ref', 10000, 100)],
            'meta' => ['pageCount' => 1],
        ]);

        $settlement = (new PaystackSettlementReconciliationService())->reconcile(
            CarbonImmutable::today()->subDay(), CarbonImmutable::today(), $client
        )[0];

        $this->assertSame('mismatched', $settlement->reconciliation_status);
        $entry = GatewaySettlementEntry::firstOrFail();
        $this->assertSame('ledger_identity_missing', $entry->result);
        $this->assertContains('ledger_identity_missing', $entry->details['issues']);
    }

    private function intent(string $reference, float $amount, int $bookingId, bool $withLedger = true): PaymentIntent
    {
        $intent = PaymentIntent::create([
            'gateway' => 'paystack', 'reference' => $reference, 'event_id' => 5,
            'booking_id' => $bookingId, 'status' => 'completed', 'amount' => $amount,
            'currency' => 'NGN', 'email' => 'buyer@example.com',
            'payload' => ['event_id' => 5], 'processed_at' => now(),
        ]);
        if ($withLedger) {
            Transaction::create([
                'transcation_id' => $reference,
                'gateway' => 'paystack',
                'gateway_reference' => $reference,
                'booking_id' => $bookingId,
                'transcation_type' => 1,
                'payment_status' => 1,
                'grand_total' => $amount,
                'tax' => 0,
            ]);
        }
        return $intent;
    }

    private function settlementsPayload(int $processed, int $fees): array
    {
        return [
            'status' => true,
            'data' => [[
                'id' => 3001, 'status' => 'success', 'currency' => 'NGN',
                'total_processed' => $processed, 'total_fees' => $fees,
                'total_amount' => $processed - $fees, 'effective_amount' => $processed - $fees,
                'settlement_date' => now()->toISOString(),
            ]],
            'meta' => ['pageCount' => 1],
        ];
    }

    private function transaction(string $reference, int $amount, int $fees): array
    {
        return [
            'id' => random_int(1000, 9999), 'reference' => $reference,
            'amount' => $amount, 'fees' => $fees, 'currency' => 'NGN', 'status' => 'success',
        ];
    }

    private function createSchema(): void
    {
        Schema::create('payment_intents', function (Blueprint $table) {
            $table->id(); $table->string('gateway'); $table->string('reference'); $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('customer_id')->nullable(); $table->unsignedBigInteger('booking_id')->nullable();
            $table->string('status'); $table->decimal('amount', 18, 2); $table->string('currency', 8);
            $table->string('email'); $table->longText('payload'); $table->text('last_error')->nullable();
            $table->timestamp('expires_at')->nullable(); $table->timestamp('processed_at')->nullable(); $table->timestamps();
        });
        Schema::create('gateway_settlements', function (Blueprint $table) {
            $table->id(); $table->string('gateway'); $table->string('gateway_settlement_id'); $table->string('status');
            $table->string('currency')->nullable(); $table->decimal('total_processed', 18, 2)->default(0);
            $table->decimal('total_fees', 18, 2)->default(0); $table->decimal('total_amount', 18, 2)->default(0);
            $table->decimal('effective_amount', 18, 2)->default(0); $table->unsignedInteger('transaction_count')->default(0);
            $table->unsignedInteger('matched_count')->default(0); $table->unsignedInteger('mismatch_count')->default(0);
            $table->string('reconciliation_status'); $table->longText('gateway_payload')->nullable();
            $table->text('last_error')->nullable(); $table->timestamp('settlement_date')->nullable();
            $table->timestamp('fetched_at'); $table->timestamp('reconciled_at')->nullable(); $table->timestamps();
        });
        Schema::create('gateway_settlement_entries', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('gateway_settlement_id'); $table->unsignedBigInteger('payment_intent_id')->nullable();
            $table->unsignedBigInteger('booking_id')->nullable(); $table->string('reference'); $table->string('result');
            $table->decimal('expected_amount', 18, 2)->nullable(); $table->decimal('gateway_amount', 18, 2);
            $table->decimal('gateway_fee', 18, 2)->default(0); $table->string('expected_currency')->nullable();
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
