<?php

namespace Tests\Unit;

use App\Models\PaymentIntent;
use App\Models\Transaction;
use App\Services\GatewayLedgerBackfillService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GatewayLedgerBackfillServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        config()->set('payments.production_ready_gateways', ['paystack', 'flutterwave']);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->createSchema();
    }

    public function test_dry_run_reports_verified_mapping_without_writing(): void
    {
        $intent = $this->intent('paystack', 'ref-dry', 71, 10500);
        $transaction = $this->transaction('ref-dry', 71, 10000, 500);

        $result = (new GatewayLedgerBackfillService())->run(false, ['paystack']);

        $this->assertTrue($result['healthy']);
        $this->assertSame(1, $result['would_update']);
        $this->assertNull($transaction->fresh()->gateway);
        $this->assertNull($transaction->fresh()->gateway_reference);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertSame($intent->id, PaymentIntent::first()->id);
    }

    public function test_apply_is_audited_and_idempotent(): void
    {
        $this->intent('flutterwave', 'ref-apply', 72, 25250);
        $transaction = $this->transaction('ref-apply', 72, 25000, 250, 'completed');
        $service = new GatewayLedgerBackfillService();

        $first = $service->run(true, ['flutterwave']);
        $second = $service->run(true, ['flutterwave']);

        $this->assertSame(1, $first['updated']);
        $this->assertSame(1, $second['already_linked']);
        $this->assertSame('flutterwave', $transaction->fresh()->gateway);
        $this->assertSame('ref-apply', $transaction->fresh()->gateway_reference);
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'transaction.gateway_identity_backfilled',
            'subject_id' => $transaction->id,
        ]);
    }

    public function test_amount_mismatch_is_reported_without_mutation(): void
    {
        $this->intent('paystack', 'ref-conflict', 73, 12000);
        $transaction = $this->transaction('ref-conflict', 73, 10000, 500);

        $result = (new GatewayLedgerBackfillService())->run(true, ['paystack']);

        $this->assertFalse($result['healthy']);
        $this->assertSame(1, $result['conflicts']);
        $this->assertSame('sale_values_do_not_reconcile', $result['issues'][0]['reason']);
        $this->assertNull($transaction->fresh()->gateway);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_reference_mismatch_and_missing_ledger_are_not_guessed(): void
    {
        $this->intent('paystack', 'expected-reference', 74, 10500);
        $this->transaction('different-reference', 74, 10000, 500);
        $this->intent('paystack', 'missing-reference', 75, 10500);

        $result = (new GatewayLedgerBackfillService())->run(true, ['paystack']);

        $this->assertFalse($result['healthy']);
        $this->assertSame(1, $result['conflicts']);
        $this->assertSame(1, $result['missing']);
        $this->assertSame(
            ['legacy_reference_mismatch', 'sale_ledger_row_not_found'],
            collect($result['issues'])->pluck('reason')->sort()->values()->all()
        );
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_command_rejects_gateway_outside_hardened_allowlist(): void
    {
        $this->artisan('ledger:backfill-gateway-identities', ['--gateway' => ['paypal']])
            ->expectsOutputToContain('not on the hardened production allowlist')
            ->assertExitCode(2);
    }

    private function intent(string $gateway, string $reference, int $bookingId, float $amount): PaymentIntent
    {
        return PaymentIntent::create([
            'gateway' => $gateway,
            'reference' => $reference,
            'event_id' => 9,
            'booking_id' => $bookingId,
            'status' => 'completed',
            'amount' => $amount,
            'currency' => 'NGN',
            'email' => 'buyer@example.com',
            'payload' => [],
            'processed_at' => now(),
        ]);
    }

    private function transaction(
        string $reference,
        int $bookingId,
        float $grandTotal,
        float $tax,
        $paymentStatus = 1
    ): Transaction {
        return Transaction::create([
            'transcation_id' => $reference,
            'booking_id' => $bookingId,
            'transcation_type' => 1,
            'payment_status' => $paymentStatus,
            'grand_total' => $grandTotal,
            'tax' => $tax,
            'commission' => 500,
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('payment_intents', function (Blueprint $table) {
            $table->id();
            $table->string('gateway', 40);
            $table->string('reference', 191);
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('booking_id')->nullable();
            $table->string('status', 32);
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
            $table->unique(['gateway', 'reference']);
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transcation_id')->nullable();
            $table->string('gateway', 40)->nullable();
            $table->string('gateway_reference', 191)->nullable();
            $table->string('booking_id')->nullable();
            $table->integer('transcation_type');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('organizer_id')->nullable();
            $table->string('payment_status');
            $table->string('payment_method')->nullable();
            $table->decimal('grand_total', 18, 2);
            $table->decimal('commission', 18, 2)->default(0);
            $table->decimal('tax', 18, 2)->default(0);
            $table->decimal('pre_balance', 18, 2)->default(0);
            $table->decimal('after_balance', 18, 2)->nullable();
            $table->string('gateway_type')->nullable();
            $table->string('currency_symbol')->nullable();
            $table->string('currency_symbol_position')->nullable();
            $table->timestamps();
            $table->unique(['gateway', 'gateway_reference']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('actor_type', 32)->default('system');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action', 120);
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
