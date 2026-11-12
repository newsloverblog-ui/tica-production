<?php

namespace Tests\Unit;

use App\Models\Organizer;
use App\Models\OrganizerPayoutAccount;
use App\Models\WithdrawPaymentMethod;
use App\Services\WithdrawalService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WithdrawalServiceTest extends TestCase
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
        $this->createSchema();
    }

    public function test_withdrawal_is_reserved_atomically_for_the_verified_destination(): void
    {
        $organizer = $this->organizer('verified', 50000);
        $account = $this->payoutAccount($organizer);
        $method = $this->method();

        $withdrawal = (new WithdrawalService())->request(
            $organizer->id, $method->id, 10000, 'November payout', '₦', 'left'
        );

        $this->assertSame($account->id, $withdrawal->payout_account_id);
        $this->assertSame(300.0, (float) $withdrawal->total_charge);
        $this->assertSame(9700.0, (float) $withdrawal->payable_amount);
        $this->assertSame(40000.0, (float) $organizer->fresh()->amount);
        $this->assertStringContainsString('******6789', $withdrawal->feilds);
        $this->assertStringNotContainsString('0123456789', $withdrawal->feilds);
        $this->assertDatabaseHas('transactions', [
            'booking_id' => (string) $withdrawal->id,
            'transcation_type' => 3,
            'payment_status' => '0',
            'pre_balance' => 50000,
            'after_balance' => 40000,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'withdrawal.requested',
            'subject_id' => $withdrawal->id,
        ]);
    }

    public function test_unverified_organizer_cannot_reserve_money_for_withdrawal(): void
    {
        $organizer = $this->organizer('pending', 50000);
        $method = $this->method();

        try {
            (new WithdrawalService())->request($organizer->id, $method->id, 10000, null, '₦', 'left');
            $this->fail('Unverified organizers must not withdraw.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('withdraw_method', $exception->errors());
        }

        $this->assertSame(50000.0, (float) $organizer->fresh()->amount);
        $this->assertDatabaseCount('withdraws', 0);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_verified_organizer_without_verified_bank_account_cannot_withdraw(): void
    {
        $organizer = $this->organizer('verified', 50000);
        $method = $this->method();

        $this->expectException(ValidationException::class);
        try {
            (new WithdrawalService())->request($organizer->id, $method->id, 10000, null, '₦', 'left');
        } finally {
            $this->assertSame(50000.0, (float) $organizer->fresh()->amount);
            $this->assertDatabaseCount('withdraws', 0);
        }
    }

    public function test_invalid_or_overdrawn_requests_leave_all_financial_state_unchanged(): void
    {
        $organizer = $this->organizer('verified', 5000);
        $this->payoutAccount($organizer);
        $method = $this->method();

        try {
            (new WithdrawalService())->request($organizer->id, $method->id, 10000, null, '₦', 'left');
            $this->fail('The request exceeds the balance.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('withdraw_amount', $exception->errors());
        }

        $this->assertSame(5000.0, (float) $organizer->fresh()->amount);
        $this->assertDatabaseCount('withdraws', 0);
        $this->assertDatabaseCount('transactions', 0);
    }

    private function organizer(string $kycStatus, float $amount): Organizer
    {
        return Organizer::create([
            'email' => uniqid('withdraw-', true) . '@example.com',
            'username' => uniqid('withdraw-', true),
            'password' => 'not-used',
            'status' => 1,
            'amount' => $amount,
            'kyc_status' => $kycStatus,
            'verified_legal_name' => $kycStatus === 'verified' ? 'Ada Lovelace' : null,
            'kyc_verified_at' => $kycStatus === 'verified' ? now() : null,
        ]);
    }

    private function payoutAccount(Organizer $organizer): OrganizerPayoutAccount
    {
        return OrganizerPayoutAccount::create([
            'public_id' => (string) \Illuminate\Support\Str::uuid(),
            'organizer_id' => $organizer->id,
            'provider' => 'paystack',
            'country_code' => 'NG',
            'currency' => 'NGN',
            'bank_code' => '058',
            'bank_name' => 'Guarantee Trust Bank',
            'account_number' => '0123456789',
            'account_number_hash' => hash('sha256', uniqid('', true)),
            'account_last4' => '6789',
            'resolved_account_name' => 'ADA LOVELACE',
            'name_match_status' => 'matched',
            'status' => 'verified',
            'is_default' => true,
            'verified_at' => now(),
        ]);
    }

    private function method(): WithdrawPaymentMethod
    {
        return WithdrawPaymentMethod::create([
            'language_id' => 1,
            'fixed_charge' => 100,
            'percentage_charge' => 2,
            'max_limit' => 1000000,
            'min_limit' => 1000,
            'name' => 'Verified bank payout',
            'status' => 1,
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('organizers', function (Blueprint $table) {
            $table->id(); $table->string('email')->nullable(); $table->string('username')->nullable();
            $table->string('password')->nullable(); $table->integer('status')->default(1);
            $table->decimal('amount', 18, 2)->default(0); $table->string('kyc_status', 24)->default('unverified');
            $table->string('verified_legal_name')->nullable(); $table->timestamp('kyc_verified_at')->nullable();
            $table->timestamps();
        });
        Schema::create('withdraw_payment_methods', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('language_id')->nullable(); $table->decimal('fixed_charge', 18, 2);
            $table->decimal('percentage_charge', 8, 2); $table->decimal('max_limit', 18, 2);
            $table->decimal('min_limit', 18, 2); $table->string('name'); $table->integer('status'); $table->timestamps();
        });
        Schema::create('organizer_payout_accounts', function (Blueprint $table) {
            $table->id(); $table->uuid('public_id'); $table->unsignedBigInteger('organizer_id');
            $table->string('provider'); $table->string('country_code'); $table->string('currency');
            $table->string('bank_code'); $table->string('bank_name'); $table->text('account_number');
            $table->char('account_number_hash', 64); $table->string('account_last4', 4);
            $table->string('resolved_account_name'); $table->string('name_match_status'); $table->string('status');
            $table->boolean('is_default'); $table->timestamp('verified_at')->nullable(); $table->timestamps();
        });
        Schema::create('withdraws', function (Blueprint $table) {
            $table->id(); $table->uuid('withdraw_id'); $table->unsignedBigInteger('organizer_id');
            $table->unsignedBigInteger('method_id'); $table->unsignedBigInteger('payout_account_id')->nullable();
            $table->decimal('amount', 18, 2); $table->decimal('payable_amount', 18, 2);
            $table->decimal('total_charge', 18, 2); $table->text('additional_reference')->nullable();
            $table->text('feilds')->nullable(); $table->integer('status'); $table->timestamps();
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
