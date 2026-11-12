<?php

namespace Tests\Unit;

use App\Models\PayoutDispute;
use App\Models\Withdraw;
use App\Services\PayoutDisputeService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class PayoutDisputeServiceTest extends TestCase
{
    private PayoutDisputeService $service;

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
        $this->service = new PayoutDisputeService();
    }

    public function test_organizer_can_open_only_one_active_dispute_for_owned_payout(): void
    {
        $withdrawal = $this->withdrawal(7, Withdraw::STATUS_HELD);

        $dispute = $this->service->open(
            $withdrawal->id,
            7,
            'delayed',
            'The payout has remained on hold beyond the advised review time.'
        );

        $this->assertSame(PayoutDispute::STATUS_OPEN, $dispute->status);
        $this->assertSame(9500.0, (float) $dispute->amount);
        $this->assertSame(Withdraw::STATUS_HELD, (int) $dispute->withdrawal_status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payout.dispute_opened', 'subject_id' => $dispute->id]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('active dispute');
        $this->service->open(
            $withdrawal->id,
            7,
            'other',
            'This second case should be rejected while the first remains active.'
        );
    }

    public function test_organizer_cannot_open_dispute_for_another_organizer(): void
    {
        $withdrawal = $this->withdrawal(7, Withdraw::STATUS_PAID);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('does not belong');
        $this->service->open(
            $withdrawal->id,
            8,
            'incorrect_amount',
            'The received amount does not match the payout statement total.'
        );
    }

    public function test_finance_review_is_audited_and_never_mutates_the_withdrawal(): void
    {
        $withdrawal = $this->withdrawal(7, Withdraw::STATUS_REJECTED);
        $dispute = $this->service->open(
            $withdrawal->id,
            7,
            'rejected_payout',
            'The rejection reason does not match the verified payout account.'
        );

        $reviewing = $this->service->review($dispute->id, 99, 'under_review', 'Checking the provider record.');
        $this->assertSame(PayoutDispute::STATUS_UNDER_REVIEW, $reviewing->status);
        $this->assertNull($reviewing->closed_at);

        $resolved = $this->service->review(
            $dispute->id,
            99,
            'resolved',
            'The payout destination was corrected and a new controlled withdrawal may be submitted.'
        );
        $this->assertSame(PayoutDispute::STATUS_RESOLVED, $resolved->status);
        $this->assertNotNull($resolved->closed_at);
        $this->assertSame(99, (int) $resolved->resolved_by_admin_id);
        $this->assertSame(Withdraw::STATUS_REJECTED, (int) $withdrawal->fresh()->status);
        $this->assertSame(9500.0, (float) $withdrawal->fresh()->payable_amount);
        $this->assertDatabaseCount('audit_logs', 3);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('closed payout dispute');
        $this->service->review($dispute->id, 99, 'rejected', 'Trying to overwrite a closed decision.');
    }

    public function test_closing_a_dispute_requires_a_meaningful_resolution(): void
    {
        $withdrawal = $this->withdrawal(7, Withdraw::STATUS_PROCESSING);
        $dispute = $this->service->open(
            $withdrawal->id,
            7,
            'missing_payout',
            'The provider says complete but the expected bank credit is missing.'
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('resolution is required');
        $this->service->review($dispute->id, 99, 'resolved', 'done');
    }

    private function withdrawal(int $organizerId, int $status): Withdraw
    {
        $withdrawal = new Withdraw();
        $withdrawal->forceFill([
            'withdraw_id' => 'WD-' . uniqid(),
            'organizer_id' => $organizerId,
            'amount' => 10000,
            'payable_amount' => 9500,
            'total_charge' => 500,
            'status' => $status,
        ])->save();

        return $withdrawal->fresh();
    }

    private function createSchema(): void
    {
        Schema::create('withdraws', function (Blueprint $table): void {
            $table->id();
            $table->string('withdraw_id');
            $table->unsignedBigInteger('organizer_id');
            $table->decimal('amount', 18, 2);
            $table->decimal('payable_amount', 18, 2);
            $table->decimal('total_charge', 18, 2);
            $table->integer('status');
            $table->timestamps();
        });
        Schema::create('payout_disputes', function (Blueprint $table): void {
            $table->id(); $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('withdrawal_id'); $table->unsignedBigInteger('organizer_id');
            $table->unsignedBigInteger('resolved_by_admin_id')->nullable();
            $table->string('category'); $table->text('description'); $table->string('status');
            $table->unsignedTinyInteger('withdrawal_status'); $table->decimal('amount', 18, 2);
            $table->text('resolution')->nullable(); $table->timestamp('opened_at');
            $table->timestamp('reviewed_at')->nullable(); $table->timestamp('closed_at')->nullable();
            $table->timestamps();
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
