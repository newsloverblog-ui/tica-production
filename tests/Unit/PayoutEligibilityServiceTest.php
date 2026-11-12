<?php

namespace Tests\Unit;

use App\Jobs\SubmitPaystackPayout;
use App\Models\Organizer;
use App\Models\OrganizerPayoutAccount;
use App\Models\PayoutDispute;
use App\Models\PayoutTransfer;
use App\Models\Transaction;
use App\Models\Withdraw;
use App\Services\AutomaticPayoutReleaseService;
use App\Services\PayoutEligibilityService;
use App\Services\PayoutTransferService;
use App\Services\OrganizerPayoutScheduleService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class PayoutEligibilityServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        config()->set('app.key', 'base64:' . base64_encode(str_repeat('e', 32)));
        config()->set('app.timezone', 'UTC');
        config()->set('cache.default', 'array');
        config()->set('payouts.paystack_transfers_enabled', true);
        config()->set('payouts.automatic_release_enabled', true);
        config()->set('payouts.eligibility_policy', $this->policy());
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->createSchema();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-04 11:00:00', 'Africa/Lagos'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_next_business_day_skips_weekends_and_configured_holidays(): void
    {
        config()->set('payouts.eligibility_policy.holiday_dates', ['2026-11-09']);

        $release = (new PayoutEligibilityService())->scheduledReleaseAt(
            CarbonImmutable::parse('2026-11-06 15:00:00', 'Africa/Lagos')
        );

        $this->assertSame('2026-11-10 10:00:00', $release->format('Y-m-d H:i:s'));
        $this->assertSame('Africa/Lagos', $release->timezoneName);
    }

    public function test_eligible_withdrawal_is_queued_with_system_policy_evidence(): void
    {
        Queue::fake();
        [, , $withdrawal] = $this->fixture();
        $eligibility = new PayoutEligibilityService();

        $decision = $eligibility->evaluate($withdrawal);
        $transfer = (new PayoutTransferService())->queueAutomatically($withdrawal->id, $eligibility);

        $this->assertTrue($decision['eligible']);
        $this->assertSame([], $decision['reasons']);
        $this->assertSame('automatic', $transfer->release_source);
        $this->assertSame('2026-11-v1', $transfer->policy_version);
        $this->assertNull($transfer->requested_by_admin_id);
        $this->assertSame(Withdraw::STATUS_PROCESSING, (int) $withdrawal->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payout.transfer_queued', 'actor_type' => 'system']);
        Queue::assertPushed(SubmitPaystackPayout::class, 1);
    }

    public function test_risk_conditions_block_automatic_release_with_specific_reasons(): void
    {
        [$organizer, $account, $withdrawal] = $this->fixture();
        config()->set('payouts.eligibility_policy.minimum_successful_payouts', 2);
        $account->forceFill(['verified_at' => now()->subDay()])->save();
        $held = $this->withdrawal($organizer, $account, Withdraw::STATUS_HELD, 5000);
        PayoutDispute::create([
            'public_id' => 'dispute-risk',
            'withdrawal_id' => $held->id,
            'organizer_id' => $organizer->id,
            'category' => 'delayed',
            'description' => 'Held payout still requires review.',
            'status' => PayoutDispute::STATUS_OPEN,
            'withdrawal_status' => Withdraw::STATUS_HELD,
            'amount' => 5000,
            'opened_at' => now(),
        ]);

        $decision = (new PayoutEligibilityService())->evaluate($withdrawal->fresh());

        $this->assertFalse($decision['eligible']);
        $this->assertContains('manual_payout_history_required', $decision['reasons']);
        $this->assertContains('payout_account_cooling_period', $decision['reasons']);
        $this->assertContains('unresolved_payout_dispute', $decision['reasons']);
        $this->assertContains('held_withdrawal_requires_review', $decision['reasons']);
    }

    public function test_daily_limit_counts_non_failed_transfers_and_blocks_candidate(): void
    {
        [$organizer, $account, $withdrawal, $history] = $this->fixture();
        $history->forceFill([
            'amount' => 900000,
            'created_at' => now()->startOfDay()->addHour(),
            'updated_at' => now()->startOfDay()->addHour(),
        ])->save();
        $withdrawal->forceFill(['payable_amount' => 200000])->save();

        $decision = (new PayoutEligibilityService())->evaluate($withdrawal->fresh());

        $this->assertFalse($decision['eligible']);
        $this->assertContains('daily_organizer_limit_exceeded', $decision['reasons']);
    }

    public function test_release_runner_is_dry_run_by_default_and_apply_requires_enabled_flag(): void
    {
        Queue::fake();
        [, , $withdrawal] = $this->fixture();
        $runner = new AutomaticPayoutReleaseService(
            new PayoutEligibilityService(),
            new PayoutTransferService()
        );

        $summary = $runner->run(false, 10);

        $this->assertSame('dry-run', $summary['mode']);
        $this->assertSame(1, $summary['eligible']);
        $this->assertSame(0, $summary['queued']);
        $this->assertSame(Withdraw::STATUS_PENDING, (int) $withdrawal->fresh()->status);
        $this->assertDatabaseCount('payout_transfers', 1);

        config()->set('payouts.automatic_release_enabled', false);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Automatic payout release is disabled');
        $runner->run(true, 10);
    }

    public function test_organizer_payout_schedule_shows_real_release_window_when_eligible(): void
    {
        [$organizer, , $withdrawal] = $this->fixture();

        $summary = (new OrganizerPayoutScheduleService(new PayoutEligibilityService()))
            ->summary($organizer->id);

        $this->assertSame('ready', $summary['status']);
        $this->assertSame($withdrawal->id, $summary['withdrawal_id']);
        $this->assertSame('2026-11-03T10:00:00+01:00', $summary['scheduled_at']);
    }

    public function test_organizer_payout_schedule_does_not_promise_date_when_automatic_release_is_disabled(): void
    {
        [$organizer] = $this->fixture();
        config()->set('payouts.automatic_release_enabled', false);

        $summary = (new OrganizerPayoutScheduleService(new PayoutEligibilityService()))
            ->summary($organizer->id);

        $this->assertSame('manual_review', $summary['status']);
        $this->assertNull($summary['scheduled_at']);
        $this->assertStringContainsString('finance team', $summary['message']);
    }

    private function policy(): array
    {
        return [
            'version' => '2026-11-v1',
            'approved' => true,
            'approval_reference' => 'FIN-RISK-2026-11',
            'settlement_owner' => 'platform',
            'timezone' => 'Africa/Lagos',
            'release_hour' => 10,
            'business_days' => [1, 2, 3, 4, 5],
            'holiday_dates' => [],
            'minimum_organizer_age_days' => 14,
            'minimum_kyc_age_days' => 7,
            'minimum_account_age_days' => 3,
            'minimum_successful_payouts' => 1,
            'minimum_amount' => 1000,
            'maximum_amount' => 500000,
            'daily_organizer_limit' => 1000000,
            'batch_limit' => 100,
            'scan_limit' => 5000,
        ];
    }

    private function fixture(): array
    {
        $organizer = Organizer::create([
            'email' => 'eligible-organizer@example.com',
            'username' => 'eligible-organizer',
            'password' => 'not-used',
            'status' => 1,
            'amount' => 1000000,
            'kyc_status' => 'verified',
            'verified_legal_name' => 'Ada Lovelace',
            'kyc_verified_at' => now()->subDays(30),
        ]);
        $organizer->forceFill(['created_at' => now()->subDays(60), 'updated_at' => now()->subDays(30)])->save();
        $account = OrganizerPayoutAccount::create([
            'public_id' => 'account-eligible',
            'organizer_id' => $organizer->id,
            'provider' => 'paystack',
            'country_code' => 'NG',
            'currency' => 'NGN',
            'bank_code' => '058',
            'bank_name' => 'Guarantee Trust Bank',
            'account_number' => '0123456789',
            'account_number_hash' => hash('sha256', '0123456789'),
            'account_last4' => '6789',
            'resolved_account_name' => 'ADA LOVELACE',
            'name_match_status' => 'matched',
            'status' => 'verified',
            'is_default' => true,
            'verified_at' => now()->subDays(20),
        ]);
        $paid = $this->withdrawal($organizer, $account, Withdraw::STATUS_PAID, 5000);
        $history = PayoutTransfer::create([
            'public_id' => 'transfer-history',
            'withdrawal_id' => $paid->id,
            'payout_account_id' => $account->id,
            'gateway' => 'paystack',
            'reference' => 'tica-history',
            'status' => 'success',
            'amount' => 5000,
            'currency' => 'NGN',
            'requested_by_admin_id' => 7,
            'release_source' => 'manual',
            'completed_at' => now()->subDays(10),
        ]);
        $history->forceFill(['created_at' => now()->subDays(10), 'updated_at' => now()->subDays(10)])->save();
        $withdrawal = $this->withdrawal($organizer, $account, Withdraw::STATUS_PENDING, 10000);
        $withdrawal->forceFill(['created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2)])->save();

        return [$organizer, $account, $withdrawal, $history];
    }

    private function withdrawal(
        Organizer $organizer,
        OrganizerPayoutAccount $account,
        int $status,
        float $amount
    ): Withdraw {
        $withdrawal = new Withdraw();
        $withdrawal->withdraw_id = uniqid('withdrawal-', true);
        $withdrawal->organizer_id = $organizer->id;
        $withdrawal->method_id = 1;
        $withdrawal->payout_account_id = $account->id;
        $withdrawal->amount = $amount;
        $withdrawal->payable_amount = $amount;
        $withdrawal->total_charge = 0;
        $withdrawal->status = $status;
        $withdrawal->save();
        Transaction::create([
            'transcation_id' => uniqid('transaction-', true),
            'booking_id' => $withdrawal->id,
            'transcation_type' => 3,
            'organizer_id' => $organizer->id,
            'payment_status' => $status === Withdraw::STATUS_PAID ? 1 : 0,
            'payment_method' => $withdrawal->method_id,
            'grand_total' => $amount,
            'pre_balance' => $amount,
            'after_balance' => 0,
        ]);
        return $withdrawal;
    }

    private function createSchema(): void
    {
        Schema::create('organizers', function (Blueprint $table): void {
            $table->id(); $table->string('email')->nullable(); $table->string('username')->nullable();
            $table->string('password')->nullable(); $table->integer('status')->default(1);
            $table->decimal('amount', 18, 2)->default(0); $table->string('kyc_status', 24)->default('unverified');
            $table->string('verified_legal_name')->nullable(); $table->timestamp('kyc_verified_at')->nullable(); $table->timestamps();
        });
        Schema::create('organizer_payout_accounts', function (Blueprint $table): void {
            $table->id(); $table->uuid('public_id')->unique(); $table->unsignedBigInteger('organizer_id');
            $table->string('provider', 30); $table->text('provider_recipient_code')->nullable();
            $table->timestamp('recipient_created_at')->nullable(); $table->string('country_code', 2);
            $table->string('currency', 8); $table->string('bank_code', 30); $table->string('bank_name');
            $table->text('account_number'); $table->char('account_number_hash', 64); $table->string('account_last4', 4);
            $table->string('resolved_account_name'); $table->string('name_match_status', 24); $table->string('status', 24);
            $table->boolean('is_default')->default(true); $table->timestamp('verified_at')->nullable(); $table->timestamps();
        });
        Schema::create('withdraws', function (Blueprint $table): void {
            $table->id(); $table->string('withdraw_id'); $table->unsignedBigInteger('organizer_id');
            $table->unsignedBigInteger('method_id'); $table->unsignedBigInteger('payout_account_id')->nullable();
            $table->decimal('amount', 18, 2); $table->decimal('payable_amount', 18, 2); $table->decimal('total_charge', 18, 2);
            $table->text('additional_reference')->nullable(); $table->string('payout_reference')->nullable()->unique();
            $table->text('decision_reason')->nullable(); $table->unsignedBigInteger('reviewed_by_admin_id')->nullable();
            $table->timestamp('reviewed_at')->nullable(); $table->timestamp('paid_at')->nullable(); $table->text('feilds')->nullable();
            $table->integer('status'); $table->timestamps();
        });
        Schema::create('transactions', function (Blueprint $table): void {
            $table->id(); $table->string('transcation_id'); $table->string('booking_id')->nullable();
            $table->integer('transcation_type'); $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('organizer_id')->nullable(); $table->string('payment_status');
            $table->string('payment_method')->nullable(); $table->decimal('grand_total', 18, 2);
            $table->decimal('commission', 18, 2)->default(0); $table->decimal('tax', 18, 2)->default(0);
            $table->decimal('pre_balance', 18, 2)->default(0); $table->decimal('after_balance', 18, 2)->default(0);
            $table->string('gateway_type')->nullable(); $table->string('currency_symbol')->nullable();
            $table->string('currency_symbol_position')->nullable(); $table->timestamps();
        });
        Schema::create('payout_transfers', function (Blueprint $table): void {
            $table->id(); $table->uuid('public_id')->unique(); $table->unsignedBigInteger('withdrawal_id');
            $table->unsignedBigInteger('payout_account_id'); $table->string('gateway', 30); $table->string('reference', 191)->unique();
            $table->text('recipient_code')->nullable(); $table->string('gateway_transfer_code')->nullable();
            $table->string('status', 32); $table->decimal('amount', 18, 2); $table->string('currency', 8);
            $table->unsignedBigInteger('requested_by_admin_id')->nullable(); $table->string('release_source', 24)->default('manual');
            $table->string('policy_version', 64)->nullable(); $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('verification_attempts')->default(0); $table->string('gateway_status', 40)->nullable();
            $table->longText('gateway_response')->nullable(); $table->text('last_error')->nullable();
            $table->timestamp('submitted_at')->nullable(); $table->timestamp('last_verified_at')->nullable();
            $table->timestamp('webhook_received_at')->nullable(); $table->timestamp('completed_at')->nullable();
            $table->string('organizer_notified_status', 32)->nullable(); $table->timestamp('organizer_notified_at')->nullable();
            $table->string('finance_notified_status', 32)->nullable(); $table->timestamp('finance_notified_at')->nullable(); $table->timestamps();
        });
        Schema::create('payout_disputes', function (Blueprint $table): void {
            $table->id(); $table->uuid('public_id')->unique(); $table->unsignedBigInteger('withdrawal_id');
            $table->unsignedBigInteger('organizer_id'); $table->unsignedBigInteger('resolved_by_admin_id')->nullable();
            $table->string('category', 40); $table->text('description'); $table->string('status', 24);
            $table->unsignedTinyInteger('withdrawal_status'); $table->decimal('amount', 18, 2);
            $table->text('resolution')->nullable(); $table->timestamp('opened_at'); $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('closed_at')->nullable(); $table->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id(); $table->string('actor_type'); $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action'); $table->string('subject_type')->nullable(); $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('before')->nullable(); $table->json('after')->nullable(); $table->json('metadata')->nullable();
            $table->string('request_id')->nullable(); $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable(); $table->timestamp('created_at')->nullable();
        });
    }
}
