<?php

namespace Tests\Unit;

use App\Models\Event;
use App\Models\Organizer;
use App\Models\Transaction;
use App\Models\Withdraw;
use App\Services\FinancialLedgerService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FinancialLedgerServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]);
        config()->set('payouts.event_hold_days', 3);
        DB::purge('sqlite'); DB::setDefaultConnection('sqlite');
        $this->baseSchema();
        (include database_path('migrations/2026_11_17_120000_create_financial_ledger.php'))->up();
    }

    public function test_sale_is_balanced_idempotent_and_starts_pending(): void
    {
        [$booking, $legacy, $organizer] = $this->sale();
        $service = new FinancialLedgerService();

        $first = $service->recordSale($booking, $legacy);
        $second = $service->recordSale($booking, $legacy);

        $this->assertSame($first->id, $second->id);
        $this->assertSame('pending', $first->status);
        $this->assertSame(2350000, $first->organizer_amount_minor);
        $this->assertSame(['pending_minor' => 2350000, 'available_minor' => 0, 'on_hold_minor' => 0], $service->balances($organizer->id));
        $this->assertSame(
            DB::table('financial_ledger_entries')->where('direction', 'debit')->sum('amount_minor'),
            DB::table('financial_ledger_entries')->where('direction', 'credit')->sum('amount_minor')
        );
        $this->assertDatabaseCount('financial_ledger_transactions', 1);
    }

    public function test_eligible_earning_moves_from_pending_to_available_once(): void
    {
        [$booking, $legacy, $organizer] = $this->sale(now()->subDays(8));
        $service = new FinancialLedgerService();
        $release = $service->recordSale($booking, $legacy);
        $release->forceFill(['available_at' => now()->subMinute()])->save();

        $first = $service->releaseDue();
        $second = $service->releaseDue();

        $this->assertSame(1, $first['released']);
        $this->assertSame(0, $second['released']);
        $this->assertSame(['pending_minor' => 0, 'available_minor' => 2350000, 'on_hold_minor' => 0], $service->balances($organizer->id));
        $this->assertDatabaseCount('financial_ledger_transactions', 2);
    }

    public function test_cancelled_event_is_held_instead_of_released(): void
    {
        [$booking, $legacy] = $this->sale(now()->subDays(8), true);
        $service = new FinancialLedgerService();
        $release = $service->recordSale($booking, $legacy);
        $release->forceFill(['available_at' => now()->subMinute()])->save();

        $summary = $service->releaseDue();

        $this->assertSame(1, $summary['held']);
        $this->assertSame('held', $release->fresh()->status);
        $this->assertStringContainsString('cancelled', strtolower($release->fresh()->hold_reason));
    }

    public function test_verified_processor_fee_is_posted_once_from_settlement_evidence(): void
    {
        [$booking, $legacy] = $this->sale();
        $service = new FinancialLedgerService();
        $release = $service->recordSale($booking, $legacy);

        $first = $service->recordProcessorFee($booking->id, 'ref-1', 37500);
        $second = $service->recordProcessorFee($booking->id, 'ref-1', 37500);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(37500, $release->fresh()->processor_fee_minor);
        $this->assertDatabaseCount('financial_ledger_transactions', 2);
        $feeTransaction = $first->fresh('entries');
        $this->assertSame(37500, $feeTransaction->entries->where('direction', 'debit')->sum('amount_minor'));
        $this->assertSame(37500, $feeTransaction->entries->where('direction', 'credit')->sum('amount_minor'));
    }

    public function test_withdrawal_moves_available_money_through_hold_paid_and_reversed_states_once(): void
    {
        [$booking, $legacy, $organizer] = $this->sale(now()->subDays(8));
        $service = new FinancialLedgerService();
        $release = $service->recordSale($booking, $legacy);
        $release->forceFill(['available_at' => now()->subMinute()])->save();
        $service->releaseDue();
        $withdrawal = new Withdraw();
        $withdrawal->withdraw_id = 'withdraw-ledger-1';
        $withdrawal->organizer_id = $organizer->id;
        $withdrawal->method_id = 1;
        $withdrawal->amount = 10000;
        $withdrawal->payable_amount = 9900;
        $withdrawal->total_charge = 100;
        $withdrawal->status = Withdraw::STATUS_PROCESSING;
        $withdrawal->save();

        $service->reserveWithdrawal($withdrawal);
        $this->assertSame(
            ['pending_minor' => 0, 'available_minor' => 1350000, 'on_hold_minor' => 1000000],
            $service->balances($organizer->id)
        );

        $paid = $service->settleWithdrawal($withdrawal);
        $this->assertSame($paid->id, $service->settleWithdrawal($withdrawal)->id);
        $this->assertSame(
            ['pending_minor' => 0, 'available_minor' => 1350000, 'on_hold_minor' => 0],
            $service->balances($organizer->id)
        );

        $reversed = $service->reversePaidWithdrawal($withdrawal);
        $this->assertSame($reversed->id, $service->reversePaidWithdrawal($withdrawal)->id);
        $this->assertSame(
            ['pending_minor' => 0, 'available_minor' => 1350000, 'on_hold_minor' => 1000000],
            $service->balances($organizer->id)
        );
    }

    private function sale($end = null, bool $cancelled = false): array
    {
        $organizer = Organizer::create(['email' => uniqid() . '@test.tica', 'username' => uniqid(), 'password' => 'x', 'status' => 1, 'amount' => 0]);
        $end ??= now()->addDays(4);
        $event = Event::create([
            'organizer_id' => $organizer->id, 'status' => 1,
            'lifecycle_status' => $cancelled ? 'cancelled' : 'scheduled',
            'start_date' => $end->copy()->subHour()->format('Y-m-d'), 'start_time' => '18:00:00',
            'end_date' => $end->format('Y-m-d'), 'end_time' => '20:00:00',
            'timezone' => 'Africa/Lagos', 'cancelled_at' => $cancelled ? now() : null,
        ]);
        $legacy = Transaction::create([
            'transcation_id' => 'ref-1', 'gateway' => 'paystack', 'gateway_reference' => 'ref-1',
            'booking_id' => 81, 'transcation_type' => 1, 'organizer_id' => $organizer->id,
            'payment_status' => 1, 'payment_method' => 'paystack', 'grand_total' => 25000,
            'tax' => 250, 'commission' => 1500, 'pre_balance' => 0, 'after_balance' => 23500,
        ]);
        $booking = (object) [
            'id' => 81, 'event_id' => $event->id, 'organizer_id' => $organizer->id,
            'price' => 25000, 'tax' => 250, 'commission' => 1500, 'currencyText' => 'NGN',
        ];
        return [$booking, $legacy, $organizer];
    }

    private function baseSchema(): void
    {
        Schema::create('organizers', function (Blueprint $table) {
            $table->id(); $table->string('email')->nullable(); $table->string('username')->nullable();
            $table->string('password')->nullable(); $table->integer('status')->default(1);
            $table->decimal('amount', 18, 2)->default(0); $table->timestamps();
        });
        Schema::create('events', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('organizer_id')->nullable(); $table->integer('status')->default(1);
            $table->string('lifecycle_status')->nullable(); $table->date('start_date')->nullable(); $table->time('start_time')->nullable();
            $table->date('end_date')->nullable(); $table->time('end_time')->nullable(); $table->string('timezone')->nullable();
            $table->timestamp('cancelled_at')->nullable(); $table->timestamps();
        });
        Schema::create('transactions', function (Blueprint $table) {
            $table->id(); $table->string('transcation_id')->nullable(); $table->string('gateway')->nullable();
            $table->string('gateway_reference')->nullable(); $table->string('booking_id')->nullable(); $table->integer('transcation_type');
            $table->unsignedBigInteger('organizer_id')->nullable(); $table->string('payment_status'); $table->string('payment_method')->nullable();
            $table->decimal('grand_total', 18, 2); $table->decimal('commission', 18, 2)->default(0); $table->decimal('tax', 18, 2)->default(0);
            $table->decimal('pre_balance', 18, 2)->default(0); $table->decimal('after_balance', 18, 2)->nullable();
            $table->string('currency')->nullable(); $table->timestamps();
        });
        Schema::create('withdraws', function (Blueprint $table) {
            $table->id(); $table->string('withdraw_id')->unique(); $table->unsignedBigInteger('organizer_id');
            $table->unsignedBigInteger('method_id'); $table->unsignedBigInteger('payout_account_id')->nullable();
            $table->decimal('amount', 18, 2); $table->decimal('payable_amount', 18, 2); $table->decimal('total_charge', 18, 2);
            $table->text('additional_reference')->nullable(); $table->string('payout_reference')->nullable();
            $table->text('decision_reason')->nullable(); $table->unsignedBigInteger('reviewed_by_admin_id')->nullable();
            $table->timestamp('reviewed_at')->nullable(); $table->timestamp('paid_at')->nullable();
            $table->text('feilds')->nullable(); $table->integer('status'); $table->timestamps();
        });
    }
}
