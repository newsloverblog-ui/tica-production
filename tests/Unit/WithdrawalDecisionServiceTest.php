<?php

namespace Tests\Unit;

use App\Models\Organizer;
use App\Models\Transaction;
use App\Models\Withdraw;
use App\Services\WithdrawalDecisionService;
use App\Services\WithdrawalCancellationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class WithdrawalDecisionServiceTest extends TestCase
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

    public function test_hold_then_paid_requires_and_records_unique_external_reference(): void
    {
        [$organizer, $withdrawal] = $this->pendingWithdrawal();
        $service = new WithdrawalDecisionService();

        $held = $service->hold($withdrawal->id, 'Confirm source of funds.', 8);
        $this->assertSame(Withdraw::STATUS_HELD, (int) $held->status);
        $this->assertSame('Confirm source of funds.', $held->decision_reason);
        $this->assertSame(1000.0, (float) $organizer->fresh()->amount);

        $paid = $service->markPaid($withdrawal->id, 'PAYSTACK-TRF-1001', 8);
        $this->assertSame(Withdraw::STATUS_PAID, (int) $paid->status);
        $this->assertSame('PAYSTACK-TRF-1001', $paid->payout_reference);
        $this->assertNotNull($paid->paid_at);
        $this->assertSame('1', (string) Transaction::firstOrFail()->payment_status);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'withdrawal.held')->count());
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'withdrawal.paid')->count());
    }

    public function test_rejection_restores_reserved_balance_exactly_once(): void
    {
        [$organizer, $withdrawal] = $this->pendingWithdrawal();
        $service = new WithdrawalDecisionService();

        $rejected = $service->reject($withdrawal->id, 'Account is under compliance review.', 3);
        $this->assertSame(Withdraw::STATUS_REJECTED, (int) $rejected->status);
        $this->assertSame(11000.0, (float) $organizer->fresh()->amount);
        $this->assertSame('2', (string) Transaction::firstOrFail()->payment_status);

        $replayed = $service->reject($withdrawal->id, 'Duplicate rejection.', 3);
        $this->assertSame(Withdraw::STATUS_REJECTED, (int) $replayed->status);

        $this->assertSame(11000.0, (float) $organizer->fresh()->amount);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'withdrawal.rejected')->count());
    }

    public function test_paid_terminal_replay_verifies_the_same_reference_without_duplicate_audit(): void
    {
        [, $withdrawal] = $this->pendingWithdrawal();
        $service = new WithdrawalDecisionService();

        $service->markPaid($withdrawal->id, 'BANK-PAID-1', 5);
        $replayed = $service->markPaid($withdrawal->id, 'BANK-PAID-1', 5);

        $this->assertSame(Withdraw::STATUS_PAID, (int) $replayed->status);
        $this->assertSame('1', (string) Transaction::firstOrFail()->payment_status);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'withdrawal.paid')->count());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('different payout reference');
        $service->markPaid($withdrawal->id, 'BANK-PAID-2', 5);
    }

    public function test_missing_or_duplicate_transaction_evidence_blocks_balance_decisions(): void
    {
        [$organizer, $missing] = $this->pendingWithdrawal();
        Transaction::query()->delete();

        try {
            (new WithdrawalDecisionService())->reject($missing->id, 'Cannot reconcile evidence.', 3);
            $this->fail('Missing financial evidence must block the decision.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('incomplete or duplicated', $exception->getMessage());
        }
        $this->assertSame(1000.0, (float) $organizer->fresh()->amount);
        $this->assertSame(Withdraw::STATUS_PENDING, (int) $missing->fresh()->status);

        [$secondOrganizer, $duplicate] = $this->pendingWithdrawal('withdrawal-duplicate');
        $original = Transaction::where('booking_id', $duplicate->id)->firstOrFail();
        $copy = $original->replicate();
        $copy->transcation_id = 'duplicate-ledger-row';
        $copy->save();

        try {
            (new WithdrawalDecisionService())->markPaid($duplicate->id, 'BANK-DUPLICATE', 3);
            $this->fail('Duplicate financial evidence must block the decision.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('incomplete or duplicated', $exception->getMessage());
        }
        $this->assertSame(1000.0, (float) $secondOrganizer->fresh()->amount);
        $this->assertSame(Withdraw::STATUS_PENDING, (int) $duplicate->fresh()->status);
    }

    public function test_mismatched_transaction_identity_blocks_hold_and_leaves_state_unchanged(): void
    {
        [, $withdrawal] = $this->pendingWithdrawal();
        Transaction::where('booking_id', $withdrawal->id)->update(['grand_total' => 9999]);

        try {
            (new WithdrawalDecisionService())->hold($withdrawal->id, 'Review mismatch.', 9);
            $this->fail('Mismatched ledger evidence must block the hold.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('does not reconcile', $exception->getMessage());
        }

        $this->assertSame(Withdraw::STATUS_PENDING, (int) $withdrawal->fresh()->status);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'withdrawal.held']);
    }

    public function test_organizer_cancellation_restores_once_and_terminal_replay_is_verification_only(): void
    {
        [$organizer, $withdrawal] = $this->pendingWithdrawal();
        $service = new WithdrawalCancellationService();

        $this->assertTrue($service->cancel($withdrawal->id, $organizer->id));
        $this->assertSame(11000.0, (float) $organizer->fresh()->amount);
        $this->assertSame(Withdraw::STATUS_REJECTED, (int) $withdrawal->fresh()->status);
        $this->assertSame('2', (string) Transaction::firstOrFail()->payment_status);
        $this->assertSame(1000.0, (float) Transaction::firstOrFail()->after_balance);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'withdrawal.cancelled')->count());

        $this->assertFalse($service->cancel($withdrawal->id, $organizer->id));
        $this->assertSame(11000.0, (float) $organizer->fresh()->amount);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'withdrawal.cancelled')->count());
    }

    public function test_bulk_cancellation_is_exact_atomic_and_idempotent(): void
    {
        $organizer = $this->organizer(1000);
        $first = $this->withdrawalFor($organizer, 'bulk-1', 10000, 21000, 11000);
        $second = $this->withdrawalFor($organizer, 'bulk-2', 10000, 11000, 1000);
        $service = new WithdrawalCancellationService();

        $this->assertSame(2, $service->cancelMany([$second->id, $first->id], $organizer->id));
        $this->assertSame(21000.0, (float) $organizer->fresh()->amount);
        $this->assertSame(2, Transaction::where('payment_status', 2)->count());
        $this->assertSame(2, DB::table('audit_logs')->where('action', 'withdrawal.cancelled')->count());

        $this->assertSame(0, $service->cancelMany([$first->id, $second->id], $organizer->id));
        $this->assertSame(21000.0, (float) $organizer->fresh()->amount);

        $other = $this->organizer(500);
        try {
            $service->cancelMany([$first->id], $other->id);
            $this->fail('A withdrawal must never be cancellable by another organizer.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('unavailable', $exception->getMessage());
        }
        $this->assertSame(500.0, (float) $other->fresh()->amount);
    }

    public function test_cancellation_rolls_back_when_financial_evidence_is_missing_or_duplicated(): void
    {
        [$organizer, $withdrawal] = $this->pendingWithdrawal();
        $service = new WithdrawalCancellationService();
        Transaction::query()->delete();

        try {
            $service->cancel($withdrawal->id, $organizer->id);
            $this->fail('Missing financial evidence must block cancellation.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('incomplete or duplicated', $exception->getMessage());
        }
        $this->assertSame(1000.0, (float) $organizer->fresh()->amount);
        $this->assertSame(Withdraw::STATUS_PENDING, (int) $withdrawal->fresh()->status);

        $transaction = $this->transactionFor($withdrawal, 11000, 1000);
        $copy = $transaction->replicate();
        $copy->transcation_id = 'cancel-duplicate';
        $copy->save();

        try {
            $service->cancel($withdrawal->id, $organizer->id);
            $this->fail('Duplicate financial evidence must block cancellation.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('incomplete or duplicated', $exception->getMessage());
        }
        $this->assertSame(1000.0, (float) $organizer->fresh()->amount);
        $this->assertSame(Withdraw::STATUS_PENDING, (int) $withdrawal->fresh()->status);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'withdrawal.cancelled']);
    }

    public function test_a_payout_reference_cannot_be_reused(): void
    {
        [, $first] = $this->pendingWithdrawal();
        [, $second] = $this->pendingWithdrawal('withdrawal-2');
        $service = new WithdrawalDecisionService();
        $service->markPaid($first->id, 'BANK-REFERENCE-9', 1);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('already attached');
        $service->markPaid($second->id, 'BANK-REFERENCE-9', 1);
    }

    private function pendingWithdrawal(string $withdrawId = 'withdrawal-1'): array
    {
        $organizer = $this->organizer(1000);
        $withdrawal = $this->withdrawalFor($organizer, $withdrawId, 10000, 11000, 1000);
        return [$organizer, $withdrawal];
    }

    private function organizer(float $amount): Organizer
    {
        return Organizer::create([
            'email' => uniqid('finance-', true) . '@example.com',
            'username' => uniqid('finance-', true),
            'password' => 'not-used',
            'status' => 1,
            'amount' => $amount,
        ]);
    }

    private function withdrawalFor(
        Organizer $organizer,
        string $withdrawId,
        float $amount,
        float $preBalance,
        float $afterBalance
    ): Withdraw {
        $withdrawal = new Withdraw();
        $withdrawal->withdraw_id = $withdrawId;
        $withdrawal->organizer_id = $organizer->id;
        $withdrawal->method_id = 1;
        $withdrawal->amount = $amount;
        $withdrawal->payable_amount = $amount - 200;
        $withdrawal->total_charge = 200;
        $withdrawal->status = Withdraw::STATUS_PENDING;
        $withdrawal->save();
        $this->transactionFor($withdrawal, $preBalance, $afterBalance);
        return $withdrawal;
    }

    private function transactionFor(Withdraw $withdrawal, float $preBalance, float $afterBalance): Transaction
    {
        return Transaction::create([
            'transcation_id' => uniqid('transaction-', true),
            'booking_id' => $withdrawal->id,
            'transcation_type' => 3,
            'organizer_id' => $withdrawal->organizer_id,
            'payment_status' => 0,
            'payment_method' => $withdrawal->method_id,
            'grand_total' => $withdrawal->amount,
            'pre_balance' => $preBalance,
            'after_balance' => $afterBalance,
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('organizers', function (Blueprint $table) {
            $table->id(); $table->string('email')->nullable(); $table->string('username')->nullable();
            $table->string('password')->nullable(); $table->integer('status')->default(1);
            $table->decimal('amount', 18, 2)->default(0); $table->timestamps();
        });
        Schema::create('withdraws', function (Blueprint $table) {
            $table->id(); $table->string('withdraw_id'); $table->unsignedBigInteger('organizer_id');
            $table->unsignedBigInteger('method_id'); $table->unsignedBigInteger('payout_account_id')->nullable();
            $table->decimal('amount', 18, 2); $table->decimal('payable_amount', 18, 2);
            $table->decimal('total_charge', 18, 2); $table->text('additional_reference')->nullable();
            $table->string('payout_reference')->nullable()->unique(); $table->text('decision_reason')->nullable();
            $table->unsignedBigInteger('reviewed_by_admin_id')->nullable(); $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('paid_at')->nullable(); $table->text('feilds')->nullable();
            $table->integer('status'); $table->timestamps();
        });
        Schema::create('transactions', function (Blueprint $table) {
            $table->id(); $table->string('transcation_id'); $table->string('booking_id')->nullable();
            $table->integer('transcation_type'); $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('organizer_id')->nullable(); $table->string('payment_status');
            $table->string('payment_method')->nullable(); $table->decimal('grand_total', 18, 2);
            $table->decimal('commission', 18, 2)->default(0); $table->decimal('tax', 18, 2)->default(0);
            $table->decimal('pre_balance', 18, 2)->default(0); $table->decimal('after_balance', 18, 2)->default(0);
            $table->string('gateway_type')->nullable(); $table->string('currency_symbol')->nullable();
            $table->string('currency_symbol_position')->nullable(); $table->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id(); $table->string('actor_type'); $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action'); $table->string('subject_type')->nullable(); $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('before')->nullable(); $table->json('after')->nullable(); $table->json('metadata')->nullable();
            $table->string('request_id')->nullable(); $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable(); $table->timestamp('created_at')->nullable();
        });
    }
}
