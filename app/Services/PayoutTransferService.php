<?php

namespace App\Services;

use App\Exceptions\GatewayOutcomeUnknownException;
use App\Jobs\SendPayoutStatusNotification;
use App\Jobs\SubmitPaystackPayout;
use App\Models\OrganizerPayoutAccount;
use App\Models\Organizer;
use App\Models\PayoutTransfer;
use App\Models\Withdraw;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class PayoutTransferService
{
    private const ACTIVE = ['queued', 'submitting', 'pending', 'processing', 'verification_required'];
    private const ORGANIZER_NOTIFICATION_STATUSES = ['queued', 'success', 'failed', 'reversed'];
    private const FINANCE_NOTIFICATION_STATUSES = ['failed', 'reversed', 'verification_required', 'needs_attention'];

    public function __construct(
        private readonly WithdrawalTransactionEvidenceService $withdrawalEvidence = new WithdrawalTransactionEvidenceService()
    ) {
    }

    public function queue(int $withdrawalId, int $adminId): PayoutTransfer
    {
        return $this->queueTransfer($withdrawalId, $adminId, 'manual');
    }

    public function queueAutomatically(
        int $withdrawalId,
        ?PayoutEligibilityService $eligibility = null
    ): PayoutTransfer {
        return $this->queueTransfer(
            $withdrawalId,
            null,
            'automatic',
            $eligibility ?: new PayoutEligibilityService()
        );
    }

    private function queueTransfer(
        int $withdrawalId,
        ?int $adminId,
        string $releaseSource,
        ?PayoutEligibilityService $eligibility = null
    ): PayoutTransfer
    {
        if (!(bool) config('payouts.paystack_transfers_enabled', false)) {
            throw new RuntimeException('Paystack payout transfers are disabled by configuration.');
        }
        if ($releaseSource === 'automatic' && !(bool) config('payouts.automatic_release_enabled', false)) {
            throw new RuntimeException('Automatic payout release is disabled by configuration.');
        }

        return DB::transaction(function () use ($withdrawalId, $adminId, $releaseSource, $eligibility) {
            $withdrawal = Withdraw::whereKey($withdrawalId)->lockForUpdate()->firstOrFail();
            $organizer = Organizer::whereKey($withdrawal->organizer_id)->lockForUpdate()->firstOrFail();
            $withdrawal->setRelation('organizer', $organizer);
            $existing = PayoutTransfer::where('withdrawal_id', $withdrawal->id)
                ->whereIn('status', self::ACTIVE)
                ->latest('id')
                ->lockForUpdate()
                ->first();
            if ($existing) {
                $this->withdrawalEvidence->lockAndValidate(
                    $withdrawal,
                    [(int) $withdrawal->status === Withdraw::STATUS_PAID ? 1 : 0]
                );
                return $existing;
            }
            $allowedStatuses = $releaseSource === 'automatic'
                ? [Withdraw::STATUS_PENDING]
                : [Withdraw::STATUS_PENDING, Withdraw::STATUS_HELD];
            if (!in_array((int) $withdrawal->status, $allowedStatuses, true)) {
                throw new RuntimeException($releaseSource === 'automatic'
                    ? 'Only pending withdrawals can be released automatically.'
                    : 'Only pending or held withdrawals can be submitted to Paystack.');
            }
            $this->withdrawalEvidence->lockAndValidate($withdrawal, [0]);

            $policyDecision = null;
            if ($releaseSource === 'automatic') {
                $policyDecision = ($eligibility ?: new PayoutEligibilityService())->assertEligible($withdrawal);
            }

            $account = OrganizerPayoutAccount::whereKey($withdrawal->payout_account_id)
                ->where('organizer_id', $withdrawal->organizer_id)
                ->where('provider', 'paystack')
                ->where('status', 'verified')
                ->lockForUpdate()
                ->first();
            if (!$account) {
                throw new RuntimeException('A verified Paystack payout account is required.');
            }
            if ($account->hold_until && now()->lt($account->hold_until)) {
                throw new RuntimeException('This payout account is still within its 24-hour security hold.');
            }

            $transfer = PayoutTransfer::create([
                'public_id' => (string) Str::uuid(),
                'withdrawal_id' => $withdrawal->id,
                'payout_account_id' => $account->id,
                'gateway' => 'paystack',
                'reference' => 'tica-payout-' . Str::uuid(),
                'status' => 'queued',
                'amount' => $withdrawal->payable_amount,
                'currency' => strtoupper((string) $account->currency),
                'requested_by_admin_id' => $adminId,
                'release_source' => $releaseSource,
                'policy_version' => $policyDecision['policy_version'] ?? null,
            ]);

            $before = (int) $withdrawal->status;
            $withdrawal->forceFill([
                'status' => Withdraw::STATUS_PROCESSING,
                'decision_reason' => 'Paystack transfer queued for processing.',
                'reviewed_by_admin_id' => $adminId,
                'reviewed_at' => now(),
            ])->save();

            AuditLogger::record('payout.transfer_queued', $transfer, [], [
                'status' => 'queued',
                'reference' => $transfer->reference,
                'amount' => (float) $transfer->amount,
                'currency' => $transfer->currency,
            ], [
                'withdrawal_id' => $withdrawal->id,
                'withdrawal_status_before' => $before,
                'payout_account_id' => $account->id,
                'release_source' => $releaseSource,
                'policy_version' => $policyDecision['policy_version'] ?? null,
                'scheduled_release_at' => $policyDecision['scheduled_release_at'] ?? null,
            ]);

            DB::afterCommit(fn () => $this->dispatchQueued($transfer->id));
            DB::afterCommit(fn () => $this->dispatchStatusNotification($transfer->id, 'queued'));
            return $transfer;
        }, 3);
    }

    public function dispatchQueued(int $transferId): bool
    {
        $transfer = PayoutTransfer::query()->find($transferId);
        if (!$transfer || $transfer->status !== 'queued') {
            return true;
        }
        $hasMetadata = Schema::hasColumn('payout_transfers', 'dispatch_attempts');
        if ($hasMetadata) {
            $transfer->forceFill([
                'dispatch_attempts' => (int) $transfer->dispatch_attempts + 1,
                'dispatch_last_attempt_at' => now(),
                'dispatch_last_error' => null,
            ])->save();
        }
        try {
            $this->pushSubmissionJob($transfer->id);
            return true;
        } catch (Throwable $exception) {
            if (!$hasMetadata) {
                throw $exception;
            }
            $transfer->forceFill([
                'dispatch_last_error' => mb_substr($exception->getMessage(), 0, 2000),
            ])->save();
            return false;
        }
    }

    protected function pushSubmissionJob(int $transferId): void
    {
        SubmitPaystackPayout::dispatch($transferId);
    }

    public function dispatchStatusNotification(int $transferId, string $expectedStatus): bool
    {
        $transfer = PayoutTransfer::query()->find($transferId);
        if (!$transfer || $transfer->status !== $expectedStatus || !$this->statusNotificationRequired($transfer)) {
            return true;
        }

        $hasMetadata = Schema::hasColumn('payout_transfers', 'notification_dispatch_attempts');
        if ($hasMetadata) {
            $transfer->forceFill([
                'notification_dispatch_attempts' => (int) $transfer->notification_dispatch_attempts + 1,
                'notification_dispatch_last_attempt_at' => now(),
                'notification_dispatch_last_error' => null,
            ])->save();
        }

        try {
            $this->pushStatusNotificationJob($transfer->id, $expectedStatus);
            return true;
        } catch (Throwable $exception) {
            if (!$hasMetadata) {
                throw $exception;
            }
            $transfer->forceFill([
                'notification_dispatch_last_error' => mb_substr($exception->getMessage(), 0, 2000),
            ])->save();
            return false;
        }
    }

    public function statusNotificationRequired(PayoutTransfer $transfer): bool
    {
        return (in_array($transfer->status, self::ORGANIZER_NOTIFICATION_STATUSES, true)
                && $transfer->organizer_notified_status !== $transfer->status)
            || (in_array($transfer->status, self::FINANCE_NOTIFICATION_STATUSES, true)
                && $transfer->finance_notified_status !== $transfer->status);
    }

    protected function pushStatusNotificationJob(int $transferId, string $expectedStatus): void
    {
        SendPayoutStatusNotification::dispatch($transferId, $expectedStatus);
    }

    public function submit(int $transferId, ?PaystackGatewayClient $client = null): PayoutTransfer
    {
        $claimed = DB::transaction(function () use ($transferId) {
            $transfer = PayoutTransfer::whereKey($transferId)->lockForUpdate()->firstOrFail();
            if ($transfer->status !== 'queued') {
                return false;
            }
            $transfer->forceFill([
                'status' => 'submitting',
                'attempts' => (int) $transfer->attempts + 1,
                'last_error' => null,
            ])->save();
            return true;
        }, 3);

        if (!$claimed) {
            return PayoutTransfer::findOrFail($transferId);
        }

        $client = $client ?: new PaystackGatewayClient();
        $transfer = PayoutTransfer::with('payoutAccount')->findOrFail($transferId);

        try {
            $account = $this->recipient($transfer->payoutAccount, $client);
            $transfer->forceFill(['recipient_code' => $account->provider_recipient_code])->save();
        } catch (Throwable $exception) {
            $this->markFailed($transfer->id, $exception);
            return PayoutTransfer::findOrFail($transferId);
        }

        try {
            $payload = $client->initiateTransfer(
                $account->provider_recipient_code,
                (float) $transfer->amount,
                $transfer->reference,
                'Tica organizer payout ' . $transfer->withdrawal_id,
                $transfer->currency
            );
            $data = (array) ($payload['data'] ?? []);
            $data['status'] = $data['status'] ?? 'pending';
            $this->applyGatewayUpdate($transfer->id, $data, 'submission', $payload);
        } catch (ConnectionException|GatewayOutcomeUnknownException $exception) {
            $this->markVerificationRequired($transfer->id, $exception);
        } catch (Throwable $exception) {
            $this->markFailed($transfer->id, $exception);
        }

        return PayoutTransfer::findOrFail($transferId);
    }

    public function handleWebhook(string $eventName, array $data): ?PayoutTransfer
    {
        if (!in_array($eventName, ['transfer.success', 'transfer.failed', 'transfer.reversed'], true)) {
            return null;
        }

        $reference = trim((string) ($data['reference'] ?? ''));
        $transferCode = trim((string) ($data['transfer_code'] ?? ''));
        if ($reference === '' && $transferCode === '') {
            return null;
        }
        $transfer = PayoutTransfer::query()
            ->where('gateway', 'paystack')
            ->where(function ($query) use ($reference, $transferCode) {
                if ($reference !== '') $query->where('reference', $reference);
                if ($transferCode !== '') {
                    $reference === '' ? $query->where('gateway_transfer_code', $transferCode)
                        : $query->orWhere('gateway_transfer_code', $transferCode);
                }
            })
            ->first();
        if (!$transfer) {
            return null;
        }

        $data['status'] = substr($eventName, strlen('transfer.'));
        $this->applyGatewayUpdate($transfer->id, $data, 'webhook', $data);
        return PayoutTransfer::findOrFail($transfer->id);
    }

    public function reconcileDue(?PaystackGatewayClient $client = null, ?int $limit = null): array
    {
        $client = $client ?: new PaystackGatewayClient();
        $limit = max(1, min(500, $limit ?? (int) config('payouts.reconcile_batch_limit', 100)));
        $threshold = now()->subMinutes(max(1, (int) config('payouts.reconcile_after_minutes', 5)));
        $summary = ['checked' => 0, 'updated' => 0, 'errors' => 0];

        $transfers = PayoutTransfer::query()
            ->whereIn('status', ['pending', 'processing', 'verification_required'])
            ->where('updated_at', '<=', $threshold)
            ->oldest('updated_at')
            ->oldest('id')
            ->limit($limit)
            ->get();

        foreach ($transfers as $transfer) {
            $summary['checked']++;
            try {
                $payload = $client->verifyTransfer($transfer->reference);
                $this->applyGatewayUpdate(
                    $transfer->id,
                    (array) $payload['data'],
                    'reconciliation',
                    $payload
                );
                $summary['updated']++;
            } catch (Throwable $exception) {
                $summary['errors']++;
                $this->recordReconciliationFailure($transfer->id, $exception);
            }
        }

        return $summary;
    }

    private function recipient(OrganizerPayoutAccount $account, PaystackGatewayClient $client): OrganizerPayoutAccount
    {
        if ($account->provider_recipient_code) {
            return $account;
        }

        return Cache::lock('payout-recipient-account:' . $account->id, 30)->block(5, function () use ($account, $client) {
            $account = OrganizerPayoutAccount::findOrFail($account->id);
            if ($account->provider_recipient_code) {
                return $account;
            }
            $payload = $client->createTransferRecipient(
                $account->resolved_account_name,
                $account->account_number,
                $account->bank_code,
                $account->currency
            );
            $recipientCode = trim((string) ($payload['data']['recipient_code'] ?? ''));
            if ($recipientCode === '') {
                throw new RuntimeException('Paystack did not return a transfer recipient code.');
            }
            $account->forceFill([
                'provider_recipient_code' => $recipientCode,
                'recipient_created_at' => now(),
            ])->save();
            return $account;
        });
    }

    private function applyGatewayUpdate(int $transferId, array $data, string $source, array $response): void
    {
        DB::transaction(function () use ($transferId, $data, $source, $response) {
            $transfer = PayoutTransfer::whereKey($transferId)->lockForUpdate()->firstOrFail();
            $withdrawal = Withdraw::whereKey($transfer->withdrawal_id)->lockForUpdate()->firstOrFail();
            $issues = $this->verificationIssues($transfer, $data);
            if ($issues !== []) {
                $transfer->forceFill([
                    'status' => 'needs_attention',
                    'gateway_response' => $response,
                    'last_error' => implode('; ', $issues),
                    'last_verified_at' => $source === 'reconciliation' ? now() : $transfer->last_verified_at,
                    'webhook_received_at' => $source === 'webhook' ? now() : $transfer->webhook_received_at,
                ])->save();
                AuditLogger::record('payout.transfer_verification_failed', $transfer, [], [
                    'status' => 'needs_attention',
                ], ['issues' => $issues, 'source' => $source]);
                $alertContext = [
                    'payout_transfer_id' => $transfer->id,
                    'issues' => $issues,
                    'source' => $source,
                ];
                DB::afterCommit(fn () => app(IncidentAlertService::class)->alert(
                    'paystack-payout-mismatch:' . $transfer->id,
                    'Paystack payout response did not match the controlled transfer.',
                    $alertContext
                ));
                DB::afterCommit(fn () => $this->dispatchStatusNotification($transfer->id, 'needs_attention'));
                return;
            }

            try {
                $withdrawalTransaction = $this->withdrawalEvidence->lockAndValidate(
                    $withdrawal,
                    [(int) $withdrawal->status === Withdraw::STATUS_PAID
                        ? 1
                        : ((int) $withdrawal->status === Withdraw::STATUS_REJECTED ? 2 : 0)]
                );
            } catch (RuntimeException $exception) {
                $transfer->forceFill([
                    'status' => 'needs_attention',
                    'gateway_response' => $response,
                    'last_error' => $exception->getMessage(),
                    'last_verified_at' => $source === 'reconciliation' ? now() : $transfer->last_verified_at,
                    'webhook_received_at' => $source === 'webhook' ? now() : $transfer->webhook_received_at,
                ])->save();
                AuditLogger::record('payout.transfer_financial_evidence_failed', $transfer, [], [
                    'status' => 'needs_attention',
                ], [
                    'source' => $source,
                    'withdrawal_id' => $withdrawal->id,
                    'exception_type' => $exception::class,
                    'exception_code' => $exception->getCode(),
                ]);
                $alertContext = [
                    'payout_transfer_id' => $transfer->id,
                    'withdrawal_id' => $withdrawal->id,
                    'source' => $source,
                ];
                DB::afterCommit(fn () => app(IncidentAlertService::class)->alert(
                    'paystack-payout-financial-evidence:' . $transfer->id,
                    'Paystack payout could not reconcile its withdrawal financial evidence.',
                    $alertContext
                ));
                DB::afterCommit(fn () => $this->dispatchStatusNotification($transfer->id, 'needs_attention'));
                return;
            }

            $gatewayStatus = strtolower((string) ($data['status'] ?? 'pending'));
            $status = match ($gatewayStatus) {
                'success' => 'success',
                'failed' => 'failed',
                'reversed' => 'reversed',
                'pending', 'received', 'otp' => 'pending',
                default => 'processing',
            };
            $transfer->forceFill([
                'status' => $status,
                'gateway_status' => $gatewayStatus,
                'gateway_transfer_code' => $data['transfer_code'] ?? $transfer->gateway_transfer_code,
                'gateway_response' => $response,
                'last_error' => null,
                'submitted_at' => $transfer->submitted_at ?: now(),
                'last_verified_at' => $source === 'reconciliation' ? now() : $transfer->last_verified_at,
                'verification_attempts' => $source === 'reconciliation'
                    ? (int) $transfer->verification_attempts + 1
                    : $transfer->verification_attempts,
                'webhook_received_at' => $source === 'webhook' ? now() : $transfer->webhook_received_at,
                'completed_at' => in_array($status, ['success', 'failed', 'reversed'], true) ? now() : null,
            ])->save();

            if ($status === 'success') {
                if ((int) $withdrawal->status === Withdraw::STATUS_PAID
                    && $withdrawal->payout_reference === $transfer->reference) {
                    // A replay can safely repair a lost notification dispatch;
                    // the notification job is unique and records delivery state.
                    DB::afterCommit(fn () => $this->dispatchStatusNotification($transfer->id, 'success'));
                    return;
                }
                if ((int) $withdrawal->status !== Withdraw::STATUS_PROCESSING) {
                    $transfer->forceFill([
                        'status' => 'needs_attention',
                        'last_error' => 'Withdrawal state changed before payout confirmation.',
                    ])->save();
                    return;
                }
                $withdrawal->forceFill([
                    'status' => Withdraw::STATUS_PAID,
                    'payout_reference' => $transfer->reference,
                    'decision_reason' => null,
                    'paid_at' => now(),
                ])->save();
                $withdrawalTransaction->payment_status = 1;
                $withdrawalTransaction->save();
            } elseif (in_array($status, ['failed', 'reversed'], true)
                && (int) $withdrawal->status === Withdraw::STATUS_PROCESSING) {
                $withdrawal->forceFill([
                    'status' => Withdraw::STATUS_HELD,
                    'decision_reason' => 'Paystack transfer ' . $status . '; finance review is required.',
                ])->save();
            }

            AuditLogger::record('payout.transfer_status_changed', $transfer, [], [
                'status' => $transfer->status,
                'gateway_status' => $gatewayStatus,
            ], [
                'source' => $source,
                'withdrawal_id' => $withdrawal->id,
                'withdrawal_status' => (int) $withdrawal->fresh()->status,
            ]);
            if (in_array($transfer->status, ['success', 'failed', 'reversed'], true)) {
                $status = $transfer->status;
                DB::afterCommit(fn () => $this->dispatchStatusNotification($transfer->id, $status));
            }
        }, 3);
    }

    private function verificationIssues(PayoutTransfer $transfer, array $data): array
    {
        $issues = [];
        if (($data['reference'] ?? $transfer->reference) !== $transfer->reference) {
            $issues[] = 'reference_mismatch';
        }
        if (isset($data['amount'])) {
            $amount = round((float) $data['amount'] / 100, 2);
            if (abs($amount - (float) $transfer->amount) > 0.009) $issues[] = 'amount_mismatch';
        }
        if (isset($data['currency']) && strtoupper((string) $data['currency']) !== strtoupper($transfer->currency)) {
            $issues[] = 'currency_mismatch';
        }
        if ($transfer->gateway_transfer_code && isset($data['transfer_code'])
            && (string) $data['transfer_code'] !== $transfer->gateway_transfer_code) {
            $issues[] = 'transfer_code_mismatch';
        }
        return $issues;
    }

    private function markVerificationRequired(int $transferId, Throwable $exception): void
    {
        $transfer = PayoutTransfer::findOrFail($transferId);
        $before = ['status' => $transfer->status];
        $transfer->forceFill([
            'status' => 'verification_required',
            'last_error' => mb_substr($exception->getMessage(), 0, 1000),
        ])->save();
        AuditLogger::record('payout.transfer_verification_required', $transfer, $before, [
            'status' => 'verification_required',
        ], [
            'exception_type' => $exception::class,
            'exception_code' => $exception->getCode(),
        ]);
        $this->dispatchStatusNotification($transfer->id, 'verification_required');
        app(IncidentAlertService::class)->alert(
            'paystack-payout-unknown:' . $transferId,
            'Paystack payout outcome is unknown and requires verification.', [
            'payout_transfer_id' => $transferId,
            'exception_type' => $exception::class,
            'exception_code' => $exception->getCode(),
        ]);
    }

    private function recordReconciliationFailure(int $transferId, Throwable $exception): void
    {
        DB::transaction(function () use ($transferId, $exception) {
            $transfer = PayoutTransfer::whereKey($transferId)->lockForUpdate()->firstOrFail();
            $attempts = (int) $transfer->verification_attempts + 1;
            $maxAttempts = max(1, (int) config('payouts.verification_max_attempts', 5));
            $exhausted = $attempts >= $maxAttempts;
            $before = ['status' => $transfer->status, 'verification_attempts' => (int) $transfer->verification_attempts];

            $transfer->forceFill([
                'status' => $exhausted ? 'needs_attention' : $transfer->status,
                'verification_attempts' => $attempts,
                'last_verified_at' => now(),
                'last_error' => mb_substr($exception->getMessage(), 0, 1000),
            ])->save();

            if ($exhausted) {
                AuditLogger::record('payout.transfer_reconciliation_exhausted', $transfer, $before, [
                    'status' => 'needs_attention',
                    'verification_attempts' => $attempts,
                ], [
                    'exception_type' => $exception::class,
                    'exception_code' => $exception->getCode(),
                ]);
                $alertContext = [
                    'payout_transfer_id' => $transfer->id,
                    'reference' => $transfer->reference,
                    'attempts' => $attempts,
                    'exception_type' => $exception::class,
                    'exception_code' => $exception->getCode(),
                ];
                DB::afterCommit(fn () => app(IncidentAlertService::class)->alert(
                    'paystack-payout-verification-exhausted:' . $transfer->id,
                    'Paystack payout verification attempts exhausted.',
                    $alertContext
                ));
                DB::afterCommit(fn () => $this->dispatchStatusNotification($transfer->id, 'needs_attention'));
                return;
            }

            Log::warning('Paystack payout verification failed.', [
                'payout_transfer_id' => $transfer->id,
                'reference' => $transfer->reference,
                'attempts' => $attempts,
                'exception_type' => $exception::class,
                'exception_code' => $exception->getCode(),
            ]);
        }, 3);
    }

    private function markFailed(int $transferId, Throwable $exception): void
    {
        DB::transaction(function () use ($transferId, $exception) {
            $transfer = PayoutTransfer::whereKey($transferId)->lockForUpdate()->firstOrFail();
            $transfer->forceFill([
                'status' => 'failed',
                'last_error' => mb_substr($exception->getMessage(), 0, 1000),
                'completed_at' => now(),
            ])->save();
            Withdraw::whereKey($transfer->withdrawal_id)
                ->where('status', Withdraw::STATUS_PROCESSING)
                ->update([
                    'status' => Withdraw::STATUS_HELD,
                    'decision_reason' => 'Paystack transfer submission failed; finance review is required.',
                    'updated_at' => now(),
                ]);
            AuditLogger::record('payout.transfer_failed', $transfer, [], [
                'status' => 'failed',
            ], [
                'exception_type' => $exception::class,
                'exception_code' => $exception->getCode(),
            ]);
            DB::afterCommit(fn () => $this->dispatchStatusNotification($transfer->id, 'failed'));
        }, 3);
    }
}
