<?php

namespace Tests\Unit;

use App\Jobs\GenerateOrganizerFinanceExport;
use App\Models\Organizer;
use App\Models\Transaction;
use App\Models\Withdraw;
use App\Services\DataExportService;
use App\Services\OrganizerFinanceStatementService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrganizerFinanceStatementServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        config()->set('app.key', 'base64:' . base64_encode(str_repeat('f', 32)));
        config()->set('exports.disk', 'local');
        config()->set('exports.retention_days', 7);
        config()->set('exports.max_rows', 250000);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->createSchema();
    }

    public function test_statement_totals_reconcile_sales_refunds_adjustments_and_payouts(): void
    {
        $organizer = $this->organizer();
        $this->transaction($organizer->id, 1, 10000, 1000, 1, 'sale-1');
        $refund = $this->transaction($organizer->id, 6, 2000, 200, 'processed', 'refund-1');
        $this->transaction($organizer->id, 4, 500, 0, 1, 'credit-1');
        $this->transaction($organizer->id, 5, 100, 0, 1, 'debit-1');
        $this->withdrawal($organizer->id, Withdraw::STATUS_PENDING, 2000, 1900, 100, null, now()->subMonths(2));
        $this->withdrawal($organizer->id, Withdraw::STATUS_PAID, 3000, 2900, 100, now());

        $statement = (new OrganizerFinanceStatementService())->statement(
            $organizer->id, CarbonImmutable::now()->startOfMonth(), CarbonImmutable::now()
        );

        $this->assertSame(10000.0, $statement['gross_sales']);
        $this->assertSame(1000.0, $statement['platform_fees']);
        $this->assertSame(2000.0, $statement['refunds']);
        $this->assertSame(200.0, $statement['refunded_platform_fees']);
        $this->assertSame(500.0, $statement['manual_credits']);
        $this->assertSame(100.0, $statement['manual_debits']);
        $this->assertSame(7600.0, $statement['net_sales']);
        $this->assertSame(12345.0, $statement['available_balance']);
        $this->assertSame(2000.0, $statement['pending_payouts']);
        $this->assertSame(2900.0, $statement['paid_out']);
        $this->assertSame(100.0, $statement['payout_fees']);
        $this->assertNull($statement['gateway_fees']);
        $refundLine = $statement['transactions']->firstWhere('reference', $refund->transcation_id);
        $this->assertSame(-1800.0, $refundLine['net_amount']);
    }

    public function test_statement_strictly_respects_the_requested_date_range(): void
    {
        $organizer = $this->organizer();
        $old = $this->transaction($organizer->id, 1, 9000, 900, 1, 'old-sale');
        $old->forceFill(['created_at' => now()->subMonths(2), 'updated_at' => now()->subMonths(2)])->save();
        $this->transaction($organizer->id, 1, 1000, 100, 1, 'current-sale');

        $statement = (new OrganizerFinanceStatementService())->statement(
            $organizer->id, CarbonImmutable::now()->startOfMonth(), CarbonImmutable::now()
        );

        $this->assertSame(1000.0, $statement['gross_sales']);
        $this->assertSame(100.0, $statement['platform_fees']);
        $this->assertSame(1, $statement['transaction_count']);
        $this->assertSame('current-sale', $statement['transactions']->first()['reference']);
    }

    public function test_summary_mode_reconciles_without_materializing_ledger_rows(): void
    {
        $organizer = $this->organizer();
        $this->transaction($organizer->id, 1, 5000, 500, 1, 'summary-sale');

        $statement = (new OrganizerFinanceStatementService())->statement(
            $organizer->id,
            CarbonImmutable::now()->startOfMonth(),
            CarbonImmutable::now(),
            null,
            false
        );

        $this->assertSame(5000.0, $statement['gross_sales']);
        $this->assertSame(500.0, $statement['platform_fees']);
        $this->assertSame(1, $statement['transaction_count']);
        $this->assertTrue($statement['transactions']->isEmpty());
    }

    public function test_background_finance_export_is_private_encrypted_snapshotted_and_idempotent(): void
    {
        Storage::fake('local');
        Queue::fake();
        $organizer = $this->organizer();
        $included = $this->transaction($organizer->id, 1, 5000, 500, 1, '=unsafe-reference');
        $from = CarbonImmutable::now()->startOfMonth();
        $to = CarbonImmutable::now();

        $export = app(DataExportService::class)->queueOrganizerFinance(
            $organizer->id,
            $from,
            $to,
            'csv'
        );

        $this->assertSame('organizer_finance', $export->export_type);
        $this->assertSame($organizer->id, $export->requested_by_id);
        $this->assertSame($organizer->id, $export->organizer_id);
        $this->assertSame($included->id, $export->filters['max_transaction_id']);
        $rawFilters = (string) DB::table('data_exports')->where('id', $export->id)->value('filters');
        $this->assertStringNotContainsString($from->format('Y-m-d'), $rawFilters);
        Queue::assertPushed(GenerateOrganizerFinanceExport::class, fn ($job) => $job->exportId === $export->id);

        $this->transaction($organizer->id, 1, 7000, 700, 1, 'late-reference');
        $job = new GenerateOrganizerFinanceExport($export->id);
        $job->handle(app(OrganizerFinanceStatementService::class));
        $ready = $export->fresh();

        $this->assertSame('ready', $ready->status);
        $this->assertSame(1, $ready->attempts);
        Storage::disk('local')->assertExists($ready->path);
        $csv = Storage::disk('local')->get($ready->path);
        $this->assertStringContainsString("'=unsafe-reference", $csv);
        $this->assertStringNotContainsString('late-reference', $csv);

        $job->handle(app(OrganizerFinanceStatementService::class));
        $this->assertSame(1, $ready->fresh()->attempts);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'data_export.ready')->count());
    }

    private function organizer(): Organizer
    {
        return Organizer::create([
            'email' => uniqid('statement-', true) . '@example.com',
            'username' => uniqid('statement-', true),
            'password' => 'not-used',
            'status' => 1,
            'amount' => 12345,
        ]);
    }

    private function transaction(
        int $organizerId, int $type, float $amount, float $commission, $status, string $reference
    ): Transaction {
        return Transaction::create([
            'transcation_id' => $reference,
            'booking_id' => null,
            'transcation_type' => $type,
            'organizer_id' => $organizerId,
            'payment_status' => $status,
            'payment_method' => 'Paystack',
            'grand_total' => $amount,
            'commission' => $commission,
            'pre_balance' => 0,
            'after_balance' => 0,
            'currency_symbol' => '₦',
            'currency_symbol_position' => 'left',
        ]);
    }

    private function withdrawal(
        int $organizerId, int $status, float $amount, float $payable, float $fee, $paidAt, $createdAt = null
    ): void {
        $withdrawal = new Withdraw();
        $withdrawal->withdraw_id = uniqid('payout-', true);
        $withdrawal->organizer_id = $organizerId;
        $withdrawal->method_id = 1;
        $withdrawal->amount = $amount;
        $withdrawal->payable_amount = $payable;
        $withdrawal->total_charge = $fee;
        $withdrawal->status = $status;
        $withdrawal->paid_at = $paidAt;
        $withdrawal->save();
        if ($createdAt) {
            $withdrawal->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();
        }
    }

    private function createSchema(): void
    {
        Schema::create('organizers', function (Blueprint $table) {
            $table->id(); $table->string('email')->nullable(); $table->string('username')->nullable();
            $table->string('password')->nullable(); $table->integer('status')->default(1);
            $table->decimal('amount', 18, 2)->default(0); $table->timestamps();
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
        Schema::create('withdraws', function (Blueprint $table) {
            $table->id(); $table->string('withdraw_id'); $table->unsignedBigInteger('organizer_id');
            $table->unsignedBigInteger('method_id'); $table->unsignedBigInteger('payout_account_id')->nullable();
            $table->decimal('amount', 18, 2); $table->decimal('payable_amount', 18, 2);
            $table->decimal('total_charge', 18, 2); $table->text('additional_reference')->nullable();
            $table->string('payout_reference')->nullable(); $table->text('decision_reason')->nullable();
            $table->unsignedBigInteger('reviewed_by_admin_id')->nullable(); $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('paid_at')->nullable(); $table->text('feilds')->nullable();
            $table->integer('status'); $table->timestamps();
        });
        Schema::create('data_exports', function (Blueprint $table) {
            $table->id(); $table->uuid('public_id')->unique(); $table->string('requested_by_type', 20);
            $table->unsignedBigInteger('requested_by_id'); $table->unsignedBigInteger('organizer_id')->nullable();
            $table->unsignedBigInteger('schedule_id')->nullable();
            $table->string('export_type', 40); $table->string('format', 8); $table->string('status', 20);
            $table->longText('filters')->nullable(); $table->string('disk', 40); $table->string('path', 500)->nullable();
            $table->string('filename', 191); $table->unsignedBigInteger('row_count')->default(0);
            $table->unsignedSmallInteger('attempts')->default(0); $table->text('last_error')->nullable();
            $table->timestamp('started_at')->nullable(); $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable(); $table->timestamp('downloaded_at')->nullable(); $table->timestamps();
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
