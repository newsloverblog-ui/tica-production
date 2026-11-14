<?php

namespace Tests\Unit;

use App\Console\Commands\ReconcilePaystackPayouts;
use App\Jobs\SubmitPaystackPayout;
use App\Jobs\SendPayoutStatusNotification;
use App\Mail\PayoutStatusMail;
use App\Models\Organizer;
use App\Models\OrganizerPayoutAccount;
use App\Models\PayoutTransfer;
use App\Models\Transaction;
use App\Models\Withdraw;
use App\Services\PaystackGatewayClient;
use App\Services\PayoutTransferService;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Console\Isolatable;
use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Mockery;
use ReflectionProperty;
use RuntimeException;
use Tests\TestCase;

class PayoutTransferServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        config()->set('app.key', 'base64:' . base64_encode(str_repeat('p', 32)));
        config()->set('cache.default', 'array');
        config()->set('payouts.paystack_transfers_enabled', true);
        config()->set('payouts.reconcile_after_minutes', 1);
        config()->set('payouts.verification_max_attempts', 2);
        config()->set('payouts.finance_email', 'finance@tica.example');
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->createSchema();
    }

    public function test_queue_is_idempotent_moves_withdrawal_to_processing_and_dispatches_encrypted_job(): void
    {
        Queue::fake();
        [, , $withdrawal] = $this->payoutFixture();
        $service = new PayoutTransferService();

        $first = $service->queue($withdrawal->id, 19);
        $second = $service->queue($withdrawal->id, 19);

        $this->assertSame($first->id, $second->id);
        $this->assertSame('queued', $first->status);
        $this->assertSame('manual', $first->release_source);
        $this->assertNull($first->policy_version);
        $this->assertSame(Withdraw::STATUS_PROCESSING, (int) $withdrawal->fresh()->status);
        $this->assertDatabaseCount('payout_transfers', 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payout.transfer_queued']);
        Queue::assertPushed(SubmitPaystackPayout::class, 1);
        Queue::assertPushed(SubmitPaystackPayout::class, function ($job) use ($first) {
            return $job->transferId === $first->id
                && $job instanceof ShouldBeEncrypted
                && $job instanceof ShouldBeUnique
                && $job->queue === 'payments';
        });
        Queue::assertPushed(SendPayoutStatusNotification::class, fn ($job) =>
            $job->transferId === $first->id && $job->expectedStatus === 'queued'
        );
    }

    public function test_queue_fails_closed_when_withdrawal_financial_evidence_is_missing_or_duplicated(): void
    {
        Queue::fake();
        [, , $missing] = $this->payoutFixture();
        Transaction::query()->delete();

        try {
            (new PayoutTransferService())->queue($missing->id, 19);
            $this->fail('Missing withdrawal evidence must block payout queueing.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('incomplete or duplicated', $exception->getMessage());
        }
        $this->assertSame(Withdraw::STATUS_PENDING, (int) $missing->fresh()->status);
        $this->assertDatabaseCount('payout_transfers', 0);

        [, , $duplicate] = $this->payoutFixture();
        $transaction = Transaction::where('booking_id', $duplicate->id)->firstOrFail();
        $copy = $transaction->replicate();
        $copy->transcation_id = 'duplicated-payout-evidence';
        $copy->save();

        try {
            (new PayoutTransferService())->queue($duplicate->id, 19);
            $this->fail('Duplicate withdrawal evidence must block payout queueing.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('incomplete or duplicated', $exception->getMessage());
        }
        $this->assertSame(Withdraw::STATUS_PENDING, (int) $duplicate->fresh()->status);
        $this->assertDatabaseCount('payout_transfers', 0);
    }

    public function test_lost_submission_handoff_is_durable_and_recovered_without_duplicate_transfer(): void
    {
        Queue::fake();
        [, , $withdrawal] = $this->payoutFixture();
        $failing = new class extends PayoutTransferService {
            protected function pushSubmissionJob(int $transferId): void
            {
                throw new RuntimeException('payments queue unavailable');
            }
        };

        $transfer = $failing->queue($withdrawal->id, 19);
        $failed = $transfer->fresh();
        $this->assertSame('queued', $failed->status);
        $this->assertSame(1, $failed->dispatch_attempts);
        $this->assertStringContainsString('payments queue unavailable', $failed->dispatch_last_error);
        $this->assertStringNotContainsString('payments queue unavailable', (string) DB::table('payout_transfers')
            ->where('id', $transfer->id)->value('dispatch_last_error'));
        Queue::assertNotPushed(SubmitPaystackPayout::class);

        $this->artisan('payouts:dispatch-pending')->assertSuccessful();

        Queue::assertPushed(SubmitPaystackPayout::class,
            fn (SubmitPaystackPayout $job): bool => $job->transferId === $transfer->id);
        $recovered = $transfer->fresh();
        $this->assertSame(2, $recovered->dispatch_attempts);
        $this->assertNull($recovered->dispatch_last_error);
        $this->assertSame(Withdraw::STATUS_PROCESSING, (int) $withdrawal->fresh()->status);
        $this->assertDatabaseCount('payout_transfers', 1);
    }

    public function test_lost_status_notification_handoff_is_durable_and_recovered(): void
    {
        Queue::fake();
        [, , $withdrawal] = $this->payoutFixture();
        $failing = new class extends PayoutTransferService {
            protected function pushStatusNotificationJob(int $transferId, string $expectedStatus): void
            {
                throw new RuntimeException('default queue unavailable');
            }
        };

        $transfer = $failing->queue($withdrawal->id, 19);
        $failed = $transfer->fresh();
        $this->assertSame('queued', $failed->status);
        $this->assertSame(1, $failed->notification_dispatch_attempts);
        $this->assertStringContainsString(
            'default queue unavailable',
            $failed->notification_dispatch_last_error
        );
        $this->assertStringNotContainsString(
            'default queue unavailable',
            (string) DB::table('payout_transfers')
                ->where('id', $transfer->id)
                ->value('notification_dispatch_last_error')
        );
        Queue::assertNotPushed(SendPayoutStatusNotification::class);

        $this->artisan('payouts:dispatch-pending')->assertSuccessful();

        Queue::assertPushed(SendPayoutStatusNotification::class, fn ($job): bool =>
            $job->transferId === $transfer->id && $job->expectedStatus === 'queued'
        );
        $recovered = $transfer->fresh();
        $this->assertSame(2, $recovered->notification_dispatch_attempts);
        $this->assertNull($recovered->notification_dispatch_last_error);
        $this->assertNull($recovered->organizer_notified_status);
        $this->assertDatabaseCount('payout_transfers', 1);
    }

    public function test_submission_reuses_encrypted_recipient_and_keeps_money_pending_until_confirmation(): void
    {
        Queue::fake();
        [, $account, $withdrawal] = $this->payoutFixture('RCP_existing');
        $transfer = (new PayoutTransferService())->queue($withdrawal->id, 7);
        $client = Mockery::mock(PaystackGatewayClient::class);
        $client->shouldNotReceive('createTransferRecipient');
        $client->shouldReceive('initiateTransfer')->once()->withArgs(function (
            $recipient, $amount, $reference, $reason, $currency
        ) use ($transfer) {
            return $recipient === 'RCP_existing'
                && $amount === 9800.0
                && $reference === $transfer->reference
                && str_contains($reason, (string) $transfer->withdrawal_id)
                && $currency === 'NGN';
        })->andReturn([
            'status' => true,
            'data' => [
                'reference' => $transfer->reference,
                'transfer_code' => 'TRF_pending_1',
                'amount' => 980000,
                'currency' => 'NGN',
                'status' => 'pending',
            ],
        ]);

        $result = (new PayoutTransferService())->submit($transfer->id, $client);

        $this->assertSame('pending', $result->status);
        $this->assertSame('TRF_pending_1', $result->gateway_transfer_code);
        $this->assertSame('RCP_existing', $result->recipient_code);
        $this->assertNotSame('RCP_existing', DB::table('payout_transfers')->value('recipient_code'));
        $this->assertNotSame('RCP_existing', DB::table('organizer_payout_accounts')->value('provider_recipient_code'));
        $this->assertSame(Withdraw::STATUS_PROCESSING, (int) $withdrawal->fresh()->status);
        $this->assertSame('0', (string) Transaction::firstOrFail()->payment_status);
        $this->assertSame('RCP_existing', $account->fresh()->provider_recipient_code);
    }

    public function test_success_webhook_marks_withdrawal_and_ledger_paid_exactly_once(): void
    {
        [$transfer, $withdrawal] = $this->submittedTransfer();
        $service = new PayoutTransferService();
        $payload = [
            'reference' => $transfer->reference,
            'transfer_code' => $transfer->gateway_transfer_code,
            'amount' => 980000,
            'currency' => 'NGN',
        ];

        $service->handleWebhook('transfer.success', $payload);
        $service->handleWebhook('transfer.success', $payload);

        $this->assertSame('success', $transfer->fresh()->status);
        $this->assertSame(Withdraw::STATUS_PAID, (int) $withdrawal->fresh()->status);
        $this->assertSame($transfer->reference, $withdrawal->fresh()->payout_reference);
        $this->assertNotNull($withdrawal->fresh()->paid_at);
        $this->assertSame('1', (string) Transaction::firstOrFail()->payment_status);
        // One audit records the initial pending submission and one records the
        // confirmed success; replaying the success webhook adds no third row.
        $this->assertSame(2, DB::table('audit_logs')
            ->where('action', 'payout.transfer_status_changed')->count());
    }

    public function test_mismatched_gateway_amount_never_marks_withdrawal_paid(): void
    {
        [$transfer, $withdrawal] = $this->submittedTransfer();

        (new PayoutTransferService())->handleWebhook('transfer.success', [
            'reference' => $transfer->reference,
            'transfer_code' => $transfer->gateway_transfer_code,
            'amount' => 970000,
            'currency' => 'NGN',
        ]);

        $this->assertSame('needs_attention', $transfer->fresh()->status);
        $this->assertStringContainsString('amount_mismatch', $transfer->fresh()->last_error);
        $this->assertSame(Withdraw::STATUS_PROCESSING, (int) $withdrawal->fresh()->status);
        $this->assertSame('0', (string) Transaction::firstOrFail()->payment_status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payout.transfer_verification_failed']);
    }

    public function test_success_confirmation_never_synthesizes_or_updates_missing_financial_evidence(): void
    {
        [$transfer, $withdrawal] = $this->submittedTransfer();
        Transaction::query()->delete();

        (new PayoutTransferService())->handleWebhook('transfer.success', [
            'reference' => $transfer->reference,
            'transfer_code' => $transfer->gateway_transfer_code,
            'amount' => 980000,
            'currency' => 'NGN',
        ]);

        $this->assertSame('needs_attention', $transfer->fresh()->status);
        $this->assertStringContainsString('incomplete or duplicated', $transfer->fresh()->last_error);
        $this->assertSame(Withdraw::STATUS_PROCESSING, (int) $withdrawal->fresh()->status);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'payout.transfer_financial_evidence_failed',
            'subject_id' => $transfer->id,
        ]);
        $this->assertStringNotContainsString(
            'incomplete or duplicated',
            (string) DB::table('audit_logs')
                ->where('action', 'payout.transfer_financial_evidence_failed')
                ->value('metadata')
        );
    }

    public function test_failed_webhook_holds_withdrawal_without_restoring_reserved_balance(): void
    {
        [$transfer, $withdrawal, $organizer] = $this->submittedTransfer();

        (new PayoutTransferService())->handleWebhook('transfer.failed', [
            'reference' => $transfer->reference,
            'transfer_code' => $transfer->gateway_transfer_code,
            'amount' => 980000,
            'currency' => 'NGN',
        ]);

        $this->assertSame('failed', $transfer->fresh()->status);
        $this->assertSame(Withdraw::STATUS_HELD, (int) $withdrawal->fresh()->status);
        $this->assertSame(1000.0, (float) $organizer->fresh()->amount);
        $this->assertSame('0', (string) Transaction::firstOrFail()->payment_status);
    }

    public function test_unknown_submission_outcome_is_audited_and_reconciled_not_marked_failed(): void
    {
        Queue::fake();
        [, , $withdrawal] = $this->payoutFixture('RCP_timeout');
        $transfer = (new PayoutTransferService())->queue($withdrawal->id, 7);
        $client = Mockery::mock(PaystackGatewayClient::class);
        $client->shouldReceive('initiateTransfer')->once()->andThrow(new ConnectionException('network timeout'));

        $result = (new PayoutTransferService())->submit($transfer->id, $client);

        $this->assertSame('verification_required', $result->status);
        $this->assertSame(Withdraw::STATUS_PROCESSING, (int) $withdrawal->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payout.transfer_verification_required']);
        $this->assertStringNotContainsString(
            'network timeout',
            (string) DB::table('payout_transfers')->where('id', $transfer->id)->value('last_error')
        );
        $this->assertStringNotContainsString(
            'network timeout',
            (string) DB::table('audit_logs')
                ->where('action', 'payout.transfer_verification_required')
                ->value('metadata')
        );
    }

    public function test_terminal_submission_failure_keeps_raw_diagnostic_out_of_audit_evidence(): void
    {
        Queue::fake();
        [, , $withdrawal] = $this->payoutFixture('RCP_terminal_failure');
        $service = new PayoutTransferService();
        $transfer = $service->queue($withdrawal->id, 7);
        $client = Mockery::mock(PaystackGatewayClient::class);
        $client->shouldReceive('initiateTransfer')->once()
            ->andThrow(new RuntimeException('sensitive terminal provider response', 422));

        $result = $service->submit($transfer->id, $client);

        $this->assertSame('failed', $result->status);
        $this->assertSame(Withdraw::STATUS_HELD, (int) $withdrawal->fresh()->status);
        $this->assertStringContainsString('sensitive terminal provider response', (string) $result->last_error);
        $this->assertStringNotContainsString(
            'sensitive terminal provider response',
            (string) DB::table('payout_transfers')->where('id', $transfer->id)->value('last_error')
        );
        $this->assertStringNotContainsString(
            'sensitive terminal provider response',
            (string) DB::table('audit_logs')->where('action', 'payout.transfer_failed')->value('metadata')
        );
    }

    public function test_reconciliation_exhaustion_escalates_for_manual_review(): void
    {
        [$transfer, $withdrawal] = $this->submittedTransfer();
        DB::table('payout_transfers')->where('id', $transfer->id)
            ->update(['updated_at' => now()->subMinutes(10)]);
        $client = Mockery::mock(PaystackGatewayClient::class);
        $client->shouldReceive('verifyTransfer')->twice()->andThrow(new RuntimeException('Paystack unavailable'));
        $service = new PayoutTransferService();

        $first = $service->reconcileDue($client);
        DB::table('payout_transfers')->where('id', $transfer->id)
            ->update(['updated_at' => now()->subMinutes(10)]);
        $second = $service->reconcileDue($client);

        $this->assertSame(['checked' => 1, 'updated' => 0, 'errors' => 1], $first);
        $this->assertSame(['checked' => 1, 'updated' => 0, 'errors' => 1], $second);
        $this->assertSame('needs_attention', $transfer->fresh()->status);
        $this->assertSame(2, (int) $transfer->fresh()->verification_attempts);
        $this->assertSame(Withdraw::STATUS_PROCESSING, (int) $withdrawal->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payout.transfer_reconciliation_exhausted']);
        $this->assertStringNotContainsString(
            'Paystack unavailable',
            (string) DB::table('payout_transfers')->where('id', $transfer->id)->value('last_error')
        );
        $this->assertStringNotContainsString(
            'Paystack unavailable',
            (string) DB::table('audit_logs')
                ->where('action', 'payout.transfer_reconciliation_exhausted')
                ->value('metadata')
        );
    }

    public function test_reconciliation_is_bounded_and_failed_records_rotate_behind_untouched_work(): void
    {
        $transfers = collect(range(1, 3))->map(fn () => $this->submittedTransfer()[0]);
        DB::table('payout_transfers')->whereIn('id', $transfers->pluck('id'))
            ->update(['updated_at' => now()->subMinutes(10)]);

        $client = Mockery::mock(PaystackGatewayClient::class);
        $client->shouldReceive('verifyTransfer')->times(3)
            ->andThrow(new RuntimeException('provider response contains confidential detail'));
        $service = new PayoutTransferService();

        $first = $service->reconcileDue($client, 2);
        $second = $service->reconcileDue($client, 2);

        $this->assertSame(['checked' => 2, 'updated' => 0, 'errors' => 2], $first);
        $this->assertSame(['checked' => 1, 'updated' => 0, 'errors' => 1], $second);
        $this->assertSame(
            [1, 1, 1],
            PayoutTransfer::query()->whereIn('id', $transfers->pluck('id'))
                ->orderBy('id')->pluck('verification_attempts')->all()
        );
        foreach ($transfers as $transfer) {
            $this->assertStringNotContainsString(
                'confidential detail',
                (string) DB::table('payout_transfers')->where('id', $transfer->id)->value('last_error')
            );
        }
    }

    public function test_payout_reconciliation_command_is_isolated_and_rejects_unbounded_limits(): void
    {
        $command = new ReconcilePaystackPayouts();

        $this->assertInstanceOf(Isolatable::class, $command);
        $this->assertTrue((new ReflectionProperty($command, 'isolated'))->getValue($command));
        $this->assertSame(
            Command::FAILURE,
            (new ReflectionProperty($command, 'isolatedExitCode'))->getValue($command)
        );
        $this->assertTrue($command->isolationLockExpiresAt()->greaterThan(now()->addHour()));
        $this->artisan('payouts:reconcile-paystack', ['--limit' => 501])
            ->assertExitCode(Command::INVALID);
    }

    public function test_webhook_without_a_controlled_identifier_is_ignored(): void
    {
        $this->payoutFixture();
        $result = (new PayoutTransferService())->handleWebhook('transfer.success', [
            'amount' => 980000,
            'currency' => 'NGN',
        ]);

        $this->assertNull($result);
        $this->assertDatabaseCount('payout_transfers', 0);
    }

    public function test_success_notification_is_encrypted_and_sent_to_organizer_once(): void
    {
        [$transfer] = $this->submittedTransfer();
        (new PayoutTransferService())->handleWebhook('transfer.success', [
            'reference' => $transfer->reference,
            'transfer_code' => $transfer->gateway_transfer_code,
            'amount' => 980000,
            'currency' => 'NGN',
        ]);
        Mail::fake();
        $job = new SendPayoutStatusNotification($transfer->id, 'success');

        $job->handle();
        $job->handle();

        $this->assertInstanceOf(ShouldBeEncrypted::class, $job);
        $this->assertInstanceOf(ShouldBeUnique::class, $job);
        Mail::assertSent(PayoutStatusMail::class, 1);
        Mail::assertSent(PayoutStatusMail::class, fn ($mail) =>
            $mail->audience === 'organizer' && $mail->hasTo($transfer->withdrawal->organizer->email)
        );
        $this->assertSame('success', $transfer->fresh()->organizer_notified_status);
        $this->assertNotNull($transfer->fresh()->organizer_notified_at);
    }

    public function test_initiated_notification_is_sent_to_organizer_once(): void
    {
        Queue::fake();
        [, , $withdrawal] = $this->payoutFixture('RCP_notify');
        $transfer = (new PayoutTransferService())->queue($withdrawal->id, 7);
        Mail::fake();
        $job = new SendPayoutStatusNotification($transfer->id, 'queued');

        $job->handle();
        $job->handle();

        Mail::assertSent(PayoutStatusMail::class, 1);
        Mail::assertSent(PayoutStatusMail::class, fn ($mail) =>
            $mail->status === 'queued' && $mail->hasTo($withdrawal->organizer->email)
        );
        $this->assertSame('queued', $transfer->fresh()->organizer_notified_status);
    }

    public function test_attention_notification_goes_to_configured_finance_recipient(): void
    {
        [$transfer] = $this->submittedTransfer();
        (new PayoutTransferService())->handleWebhook('transfer.success', [
            'reference' => $transfer->reference,
            'transfer_code' => $transfer->gateway_transfer_code,
            'amount' => 970000,
            'currency' => 'NGN',
        ]);
        Mail::fake();

        (new SendPayoutStatusNotification($transfer->id, 'needs_attention'))->handle();

        Mail::assertSent(PayoutStatusMail::class, fn ($mail) =>
            $mail->audience === 'finance' && $mail->hasTo('finance@tica.example')
        );
        $this->assertSame('needs_attention', $transfer->fresh()->finance_notified_status);
        $this->assertNotNull($transfer->fresh()->finance_notified_at);
    }

    public function test_monnify_account_is_paid_through_monnify_and_confirmed_by_its_webhook(): void
    {
        Queue::fake();
        [, , $withdrawal] = $this->payoutFixture(null, 'monnify');
        $transfer = (new PayoutTransferService())->queue($withdrawal->id, 7);
        $this->assertSame('monnify', $transfer->gateway);

        $sent = [];
        $provider = new class($sent) implements \App\Contracts\PayoutProvider {
          public function __construct(public array &$sent) {}
          public function name(): string { return 'monnify'; }
          public function banks(): array { return []; }
          public function resolveAccount(string $a, string $b): array { return []; }
          public function createRecipient(string $n, string $a, string $b, string $c): string { return 'monnify:' . $b . ':' . $a; }
          public function initiateTransfer(string $r, float $amount, string $ref, string $reason, string $cur): array {
            $this->sent[] = compact('r', 'amount', 'ref');
            return ['data' => \App\Services\Payouts\MonnifyPayoutProvider::normalise(['status' => 'PENDING', 'amount' => $amount, 'reference' => $ref, 'currency' => 'NGN'], $ref), 'raw' => []];
          }
          public function verifyTransfer(string $ref): array { return ['data' => ['status' => 'pending', 'reference' => $ref], 'raw' => []]; }
          public function testConnection(): void {}
        };

        $result = (new PayoutTransferService())->submit($transfer->id, $provider);

        $this->assertSame('pending', $result->status);
        $this->assertSame('monnify:058:0123456789', $sent[0]['r']);
        $this->assertSame(9800.0, $sent[0]['amount']);

        $done = (new PayoutTransferService())->handleMonnifyWebhook([
          'reference' => $transfer->reference, 'status' => 'SUCCESS', 'amount' => 9800, 'currency' => 'NGN',
        ]);

        $this->assertSame('success', $done->status);
        $this->assertSame(Withdraw::STATUS_PAID, (int) $withdrawal->fresh()->status);
    }

    public function test_monnify_webhook_for_an_unknown_reference_changes_nothing(): void
    {
        $this->assertNull((new PayoutTransferService())->handleMonnifyWebhook(['reference' => 'not-ours', 'status' => 'SUCCESS']));
    }

    private function submittedTransfer(): array
    {
        Queue::fake();
        [$organizer, , $withdrawal] = $this->payoutFixture('RCP_submitted');
        $service = new PayoutTransferService();
        $transfer = $service->queue($withdrawal->id, 7);
        $client = Mockery::mock(PaystackGatewayClient::class);
        $client->shouldReceive('initiateTransfer')->once()->andReturn([
            'status' => true,
            'data' => [
                'reference' => $transfer->reference,
                'transfer_code' => 'TRF_controlled_1',
                'amount' => 980000,
                'currency' => 'NGN',
                'status' => 'pending',
            ],
        ]);
        $service->submit($transfer->id, $client);
        return [$transfer->fresh(), $withdrawal->fresh(), $organizer->fresh()];
    }

    private function payoutFixture(?string $recipientCode = null, string $provider = 'paystack'): array
    {
        $organizer = Organizer::create([
            'email' => uniqid('payout-', true) . '@example.com',
            'username' => uniqid('payout-', true),
            'password' => 'not-used',
            'status' => 1,
            'amount' => 1000,
            'kyc_status' => 'verified',
            'verified_legal_name' => 'Ada Lovelace',
            'kyc_verified_at' => now(),
        ]);
        $account = OrganizerPayoutAccount::create([
            'public_id' => uniqid('account-', true),
            'organizer_id' => $organizer->id,
            'provider' => $provider,
            'provider_recipient_code' => $recipientCode,
            'recipient_created_at' => $recipientCode ? now() : null,
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
            'verified_at' => now(),
        ]);
        $withdrawal = new Withdraw();
        $withdrawal->withdraw_id = uniqid('withdrawal-', true);
        $withdrawal->organizer_id = $organizer->id;
        $withdrawal->method_id = 1;
        $withdrawal->payout_account_id = $account->id;
        $withdrawal->amount = 10000;
        $withdrawal->payable_amount = 9800;
        $withdrawal->total_charge = 200;
        $withdrawal->status = Withdraw::STATUS_PENDING;
        $withdrawal->save();
        Transaction::create([
            'transcation_id' => uniqid('transaction-', true),
            'booking_id' => $withdrawal->id,
            'transcation_type' => 3,
            'organizer_id' => $organizer->id,
            'payment_status' => 0,
            'payment_method' => 1,
            'grand_total' => 10000,
            'pre_balance' => 11000,
            'after_balance' => 1000,
        ]);
        return [$organizer, $account, $withdrawal];
    }

    private function createSchema(): void
    {
        Schema::create('organizers', function (Blueprint $table) {
            $table->id(); $table->string('email')->nullable(); $table->string('username')->nullable();
            $table->string('password')->nullable(); $table->integer('status')->default(1);
            $table->decimal('amount', 18, 2)->default(0); $table->string('kyc_status', 24)->default('unverified');
            $table->string('verified_legal_name')->nullable(); $table->timestamp('kyc_verified_at')->nullable(); $table->timestamps();
        });
        Schema::create('organizer_payout_accounts', function (Blueprint $table) {
            $table->id(); $table->uuid('public_id')->unique(); $table->unsignedBigInteger('organizer_id');
            $table->string('provider', 30); $table->text('provider_recipient_code')->nullable();
            $table->timestamp('recipient_created_at')->nullable(); $table->string('country_code', 2);
            $table->string('currency', 8); $table->string('bank_code', 30); $table->string('bank_name');
            $table->text('account_number'); $table->char('account_number_hash', 64); $table->string('account_last4', 4);
            $table->string('resolved_account_name'); $table->string('name_match_status', 24); $table->string('status', 24);
            $table->boolean('is_default')->default(true); $table->timestamp('verified_at')->nullable(); $table->timestamps();
        });
        Schema::create('withdraws', function (Blueprint $table) {
            $table->id(); $table->string('withdraw_id'); $table->unsignedBigInteger('organizer_id');
            $table->unsignedBigInteger('method_id'); $table->unsignedBigInteger('payout_account_id')->nullable();
            $table->decimal('amount', 18, 2); $table->decimal('payable_amount', 18, 2); $table->decimal('total_charge', 18, 2);
            $table->text('additional_reference')->nullable(); $table->string('payout_reference')->nullable()->unique();
            $table->text('decision_reason')->nullable(); $table->unsignedBigInteger('reviewed_by_admin_id')->nullable();
            $table->timestamp('reviewed_at')->nullable(); $table->timestamp('paid_at')->nullable(); $table->text('feilds')->nullable();
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
        Schema::create('payout_transfers', function (Blueprint $table) {
            $table->id(); $table->uuid('public_id')->unique(); $table->unsignedBigInteger('withdrawal_id');
            $table->unsignedBigInteger('payout_account_id'); $table->string('gateway', 30); $table->string('reference', 191)->unique();
            $table->text('recipient_code')->nullable(); $table->string('gateway_transfer_code')->nullable();
            $table->string('status', 32); $table->decimal('amount', 18, 2); $table->string('currency', 8);
            $table->unsignedBigInteger('requested_by_admin_id')->nullable();
            $table->string('release_source', 24)->default('manual'); $table->string('policy_version', 64)->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedSmallInteger('dispatch_attempts')->default(0);
            $table->timestamp('dispatch_last_attempt_at')->nullable();
            $table->text('dispatch_last_error')->nullable();
            $table->timestamp('dispatch_alerted_at')->nullable();
            $table->unsignedSmallInteger('notification_dispatch_attempts')->default(0);
            $table->timestamp('notification_dispatch_last_attempt_at')->nullable();
            $table->text('notification_dispatch_last_error')->nullable();
            $table->timestamp('notification_dispatch_alerted_at')->nullable();
            $table->unsignedInteger('verification_attempts')->default(0); $table->string('gateway_status', 40)->nullable();
            $table->longText('gateway_response')->nullable(); $table->text('last_error')->nullable();
            $table->timestamp('submitted_at')->nullable(); $table->timestamp('last_verified_at')->nullable();
            $table->timestamp('webhook_received_at')->nullable(); $table->timestamp('completed_at')->nullable(); $table->timestamps();
            $table->string('organizer_notified_status', 32)->nullable(); $table->timestamp('organizer_notified_at')->nullable();
            $table->string('finance_notified_status', 32)->nullable(); $table->timestamp('finance_notified_at')->nullable();
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
