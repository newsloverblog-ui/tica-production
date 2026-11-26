<?php

namespace App\Services;

use App\Contracts\PayoutProvider;
use App\Services\Payouts\PayoutProviders;
use App\Services\Payouts\PaystackPayoutProvider;
use App\Jobs\SendPayoutStatusNotification;
use App\Jobs\SubmitPaystackPayout;
use App\Models\OrganizerPayoutAccount;
use App\Models\Organizer;
use App\Models\PayoutTransfer;
use App\Models\Withdraw;
use App\Models\Admin;
use App\Support\Money;
use App\Support\TicaPlatform;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class PayoutTransferService
{
    private const ACTIVE = ['queued', 'submitting', 'pending', 'processing', 'verification_required', 'needs_attention'];
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

    public function approveRelease(int $withdrawalId, int $adminId, string $expectedFingerprint): void
    {
        if (!Schema::hasColumn('withdraws', 'release_approval')) {
            throw new RuntimeException('Payout approvals will be available after the database update.');
        }
        DB::transaction(function () use ($withdrawalId, $adminId, $expectedFingerprint): void {
            $withdrawal = Withdraw::whereKey($withdrawalId)->lockForUpdate()->firstOrFail();
            $this->authorizeAdmin($adminId, approval: true);
            if (!in_array((int) $withdrawal->status, [Withdraw::STATUS_PENDING, Withdraw::STATUS_HELD], true)) {
                throw new RuntimeException('Only pending or held withdrawals can be approved for release.');
            }
            $this->withdrawalEvidence->lockAndValidate($withdrawal, [0]);
            $account = $this->verifiedAccount($withdrawal);
            $fingerprint = $this->approvalFingerprint($withdrawal, $account);
            if (!hash_equals($fingerprint, $expectedFingerprint)) {
                throw new RuntimeException('This payout changed. Review the current amount and beneficiary.');
            }
            $approval = [
                'id' => (string) Str::uuid(),
                'admin_id' => $adminId,
                'fingerprint' => $fingerprint,
                'expires_at' => now()->addMinutes(max(1, (int) config('payouts.approval_minutes', 30)))->getTimestamp(),
            ];
            $withdrawal->forceFill(['release_approval' => $approval])->save();
            AuditLogger::record('payout.release_approved', $withdrawal, [], [
                'approval_id' => $approval['id'], 'approver_id' => $adminId, 'expires_at' => $approval['expires_at'],
                'amount_minor' => Money::toMinor($withdrawal->payable_amount), 'currency' => $account->currency,
                'payout_account_id' => $account->id,
            ]);
        }, 3);
    }

    private function authorizeAdmin(int $id, bool $approval = false): void
    {
        $admin = Admin::find($id);
        if (!$admin || (int) $admin->status !== 1 || !$admin->hasPermission('Withdraw Method')
            || ($approval && !$admin->hasPermission('Transaction'))) {
            throw new RuntimeException('An active admin with the correct payout permissions is required.');
        }
    }

    private function verifiedAccount(Withdraw $withdrawal): OrganizerPayoutAccount
    {
        if (!Organizer::whereKey($withdrawal->organizer_id)->where('kyc_status', 'verified')->lockForUpdate()->first()) {
            throw new RuntimeException('Organizer verification is required to release this payout.');
        }
        if (\App\Models\PaystackDispute::holdsOrganizer((int) $withdrawal->organizer_id)) {
            throw new RuntimeException('An unresolved payment dispute requires finance review before releasing this payout.');
        }
        $account = OrganizerPayoutAccount::whereKey($withdrawal->payout_account_id)
            ->where('organizer_id', $withdrawal->organizer_id)
            ->whereIn('provider', ['paystack', 'monnify'])
            ->where('status', 'verified')->where('is_default', true)->lockForUpdate()->first();
        if (!$account || strtoupper((string) $account->currency) !== 'NGN') {
            throw new RuntimeException('The original verified NGN payout account must still be active and selected.');
        }
        if ($account->hold_until && now()->lt($account->hold_until)) {
            throw new RuntimeException('This payout account is still within its security hold.');
        }
        return $account;
    }

    public function approvalFingerprint(Withdraw $withdrawal, OrganizerPayoutAccount $account): string
    {
        return hash('sha256', json_encode([
            (int) $withdrawal->id, (int) $withdrawal->organizer_id, Money::toMinor($withdrawal->amount),
            Money::toMinor($withdrawal->payable_amount), (int) $account->id, $account->provider,
            strtoupper((string) $account->currency), $account->bank_code, $account->account_number_hash,
            $account->verified_at?->getTimestamp(), $account->hold_until?->getTimestamp(),
        ], JSON_THROW_ON_ERROR));
    }

    private function requireApproval(Withdraw $withdrawal, OrganizerPayoutAccount $account, int $adminId): array
    {
        $this->authorizeAdmin($adminId);
        $approval = $withdrawal->release_approval;
        if (!$approval || (int) $approval['expires_at'] <= now()->getTimestamp()
            || !hash_equals($approval['fingerprint'], $this->approvalFingerprint($withdrawal, $account))) {
            throw new RuntimeException('A fresh approval is required for the current payout amount and beneficiary.');
        }
        if ((int) $approval['admin_id'] === $adminId) {
            throw new RuntimeException('A different admin must release this payout.');
        }
        $this->authorizeAdmin((int) $approval['admin_id'], approval: true);
        return $approval;
    }

    public function recordExternalPayment(int $withdrawalId, string $reference, int $adminId): Withdraw
    {
        return DB::transaction(function () use ($withdrawalId, $reference, $adminId): Withdraw {
            $withdrawal = Withdraw::whereKey($withdrawalId)->lockForUpdate()->firstOrFail();
            $this->authorizeAdmin($adminId);
            if ((int) $withdrawal->status !== Withdraw::STATUS_PAID) {
                $this->requireApproval($withdrawal, $this->verifiedAccount($withdrawal), $adminId);
                if (PayoutTransfer::where('withdrawal_id', $withdrawalId)->exists()) {
                    throw new RuntimeException('Reconcile the existing provider transfer before recording an external payout.');
                }
            }
            return app(WithdrawalDecisionService::class)->markPaid($withdrawalId, $reference, $adminId);
        }, 3);
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
        if ($releaseSource === 'automatic' && !TicaPlatform::automaticPayoutReleaseEnabled()) {
            throw new RuntimeException('Automatic payout release is disabled by configuration.');
        }

        return DB::transaction(function () use ($withdrawalId, $adminId, $releaseSource, $eligibility) {
            $withdrawal = Withdraw::whereKey($withdrawalId)->lockForUpdate()->firstOrFail();
            $organizer = Organizer::whereKey($withdrawal->organizer_id)->lockForUpdate()->firstOrFail();
            $withdrawal->setRelation('organizer', $organizer);
            if ($adminId !== null) {
                $this->authorizeAdmin($adminId);
            }
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
            if ($withdrawal->paid_at || PayoutTransfer::where('withdrawal_id', $withdrawal->id)->where('status', 'reversed')->exists()) {
                throw new RuntimeException('A reversed payout must be resolved by finance before any new withdrawal.');
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

            $account = $this->verifiedAccount($withdrawal);
            $approval = $adminId === null ? null : $this->requireApproval($withdrawal, $account, $adminId);

            $transfer = PayoutTransfer::create([
                'public_id' => (string) Str::uuid(),
                'withdrawal_id' => $withdrawal->id,
                'payout_account_id' => $account->id,
                'gateway' => (string) ($account->provider ?: 'paystack'),
                'reference' => 'tica-payout-' . Str::uuid(),
                'status' => 'queued',
                'amount' => $withdrawal->payable_amount,
                'currency' => strtoupper((string) $account->currency),
                'requested_by_admin_id' => $adminId,
                'release_source' => $releaseSource,
                'policy_version' => $policyDecision['policy_version'] ?? null,
            ]);
            if ($approval) {
                $transfer->forceFill(['release_approval_id' => $approval['id']])->save();
            }

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
        if (!$this->submissionsEnabled($transfer)) {
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

    public function submit(int $transferId, mixed $client = null): PayoutTransfer
    {
        $transfer = PayoutTransfer::findOrFail($transferId);
        try {
            $claimed = DB::transaction(function () use ($transfer) {
                $withdrawal = Withdraw::whereKey($transfer->withdrawal_id)->lockForUpdate()->firstOrFail();
                $transfer = PayoutTransfer::whereKey($transfer->id)->lockForUpdate()->firstOrFail();
                if ($transfer->status !== 'queued' || !$this->submissionsEnabled($transfer)) {
                    return false;
                }
                $this->assertSubmissionTerms($withdrawal, $transfer);
                $transfer->forceFill([
                    'status' => 'submitting',
                    'attempts' => (int) $transfer->attempts + 1,
                    'last_error' => null,
                ])->save();
                return true;
            }, 3);
        } catch (RuntimeException $exception) {
            $this->markFailed($transferId, $exception, 'queued');
            return PayoutTransfer::findOrFail($transferId);
        }

        if (!$claimed) {
            return PayoutTransfer::findOrFail($transferId);
        }

        $transfer = PayoutTransfer::with('payoutAccount')->findOrFail($transferId);

        try {
            $provider = $this->providerFor((string) $transfer->gateway, $client);
            $account = $this->recipient($transfer->payoutAccount, $provider);
            $transfer->forceFill(['recipient_code' => $account->provider_recipient_code])->save();
        } catch (Throwable $exception) {
            $this->markFailed($transfer->id, $exception);
            return PayoutTransfer::findOrFail($transferId);
        }

        try {
            if ((bool) config('payouts.verify_provider_balance', true)
                && method_exists($provider, 'availableBalanceMinor')) {
                $requiredMinor = (int) round((float) $transfer->amount * 100);
                if ($provider->availableBalanceMinor($transfer->currency) < $requiredMinor) {
                    throw new RuntimeException('The Paystack payout balance is too low for this transfer. Add funds before retrying.');
                }
            }
            DB::transaction(function () use ($transfer, $account): void {
                $withdrawal = Withdraw::whereKey($transfer->withdrawal_id)->lockForUpdate()->firstOrFail();
                $current = $this->assertSubmissionTerms($withdrawal, $transfer);
                if ($current->provider_recipient_code !== $account->provider_recipient_code) {
                    throw new RuntimeException('The provider recipient changed while the payout was queued.');
                }
            }, 3);
        } catch (Throwable $exception) {
            $this->markFailed($transferId, $exception);
            return PayoutTransfer::findOrFail($transferId);
        }

        if (!$this->submissionsEnabled($transfer)) {
            PayoutTransfer::whereKey($transferId)->where('status', 'submitting')->update(['status' => 'queued']);
            return PayoutTransfer::findOrFail($transferId);
        }

        try {
            $result = $provider->initiateTransfer(
                $account->provider_recipient_code,
                (float) $transfer->amount,
                $transfer->reference,
                'Tica organizer payout ' . $transfer->withdrawal_id,
                $transfer->currency
            );
            $data = (array) $result['data'];
            $data['status'] = $data['status'] ?? 'pending';
            $this->applyGatewayUpdate($transfer->id, $data, 'submission', (array) $result['raw']);
        } catch (Throwable $exception) {
            $this->markVerificationRequired($transfer->id, $exception);
        }

        return PayoutTransfer::findOrFail($transferId);
    }

    private function assertSubmissionTerms(Withdraw $withdrawal, PayoutTransfer $transfer): OrganizerPayoutAccount
    {
        $account = $this->verifiedAccount($withdrawal);
        if ((int) $withdrawal->status !== Withdraw::STATUS_PROCESSING || (int) $transfer->payout_account_id !== (int) $account->id
            || Money::toMinor($transfer->amount) !== Money::toMinor($withdrawal->payable_amount)
            || $transfer->gateway !== $account->provider || $transfer->currency !== strtoupper($account->currency)) {
            throw new RuntimeException('The queued payout does not match the approved withdrawal.');
        }
        if ($transfer->release_source !== 'automatic') {
            $approval = $this->requireApproval($withdrawal, $account, (int) $transfer->requested_by_admin_id);
            if (($transfer->release_approval_id ?? '') !== $approval['id']) {
                throw new RuntimeException('This queued payout requires a new approval.');
            }
        }
        return $account;
    }

    private function submissionsEnabled(PayoutTransfer $transfer): bool
    {
        return (bool) config('payouts.paystack_transfers_enabled', false)
            && ($transfer->release_source !== 'automatic' || (
                TicaPlatform::automaticPayoutReleaseEnabled()
                && app(PayoutEligibilityService::class)->automaticReleaseConfigurationIssues() === []
            ));
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

    /** Monnify disbursement webhook (signature already checked by the controller). */
    public function handleMonnifyWebhook(array $data): ?PayoutTransfer
    {
        $reference = trim((string) ($data['reference'] ?? ''));
        if ($reference === '') {
            return null;
        }
        $transfer = PayoutTransfer::query()->where('gateway', 'monnify')->where('reference', $reference)->first();
        if (!$transfer) {
            return null;
        }
        $normalised = \App\Services\Payouts\MonnifyPayoutProvider::normalise($data, $reference);
        $this->applyGatewayUpdate($transfer->id, $normalised, 'webhook', $data);

        return PayoutTransfer::findOrFail($transfer->id);
    }

    public function reconcileDue(mixed $client = null, ?int $limit = null): array
    {
        $limit = max(1, min(500, $limit ?? (int) config('payouts.reconcile_batch_limit', 100)));
        $threshold = now()->subMinutes(max(1, (int) config('payouts.reconcile_after_minutes', 5)));
        $summary = ['checked' => 0, 'updated' => 0, 'errors' => 0];

        $transfers = PayoutTransfer::query()
            ->whereIn('status', ['submitting', 'pending', 'processing', 'verification_required'])
            ->where('updated_at', '<=', $threshold)
            ->oldest('updated_at')
            ->oldest('id')
            ->limit($limit)
            ->get();

        foreach ($transfers as $transfer) {
            $summary['checked']++;
            try {
                $result = $this->providerFor((string) $transfer->gateway, $client)->verifyTransfer($transfer->reference);
                $this->applyGatewayUpdate(
                    $transfer->id,
                    (array) $result['data'],
                    'reconciliation',
                    (array) $result['raw']
                );
                $summary['updated']++;
            } catch (Throwable $exception) {
                $summary['errors']++;
                $this->recordReconciliationFailure($transfer->id, $exception);
            }
        }

        return $summary;
    }

    private function recipient(OrganizerPayoutAccount $account, PayoutProvider $provider): OrganizerPayoutAccount
    {
        if ($account->provider_recipient_code) {
            return $account;
        }

        return Cache::lock('payout-recipient-account:' . $account->id, 30)->block(5, function () use ($account, $provider) {
            $account = OrganizerPayoutAccount::findOrFail($account->id);
            if ($account->provider_recipient_code) {
                return $account;
            }
            $recipientCode = $provider->createRecipient(
                $account->resolved_account_name,
                $account->account_number,
                $account->bank_code,
                $account->currency
            );
            $account->forceFill([
                'provider_recipient_code' => $recipientCode,
                'recipient_created_at' => now(),
            ])->save();
            return $account;
        });
    }

    /** The provider a transfer was created with. Tests may pass a Paystack client or provider. */
    private function providerFor(string $gateway, mixed $client = null): PayoutProvider
    {
        if ($client instanceof PayoutProvider) {
            return $client;
        }
        if ($client instanceof PaystackGatewayClient) {
            return new PaystackPayoutProvider($client);
        }

        return app(PayoutProviders::class)->for($gateway ?: 'paystack');
    }

    private function applyGatewayUpdate(int $transferId, array $data, string $source, array $response): void
    {
        DB::transaction(function () use ($transferId, $data, $source, $response) {
            $withdrawal = Withdraw::whereKey(PayoutTransfer::findOrFail($transferId)->withdrawal_id)->lockForUpdate()->firstOrFail();
            $transfer = PayoutTransfer::whereKey($transferId)->lockForUpdate()->firstOrFail();
            $issues = $this->verificationIssues($transfer, $data);
            if ($issues !== []) {
                if (in_array($transfer->status, ['success', 'failed', 'reversed'], true)) {
                    DB::afterCommit(fn () => app(IncidentAlertService::class)->alert(
                        'payout-terminal-mismatch:' . $transfer->id,
                        'A provider update did not match a completed payout.',
                        ['payout_transfer_id' => $transfer->id, 'issues' => $issues]
                    ));
                    return;
                }
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
                'otp' => 'verification_required',
                'pending', 'received' => 'pending',
                default => 'processing',
            };
            if (in_array($transfer->status, ['success', 'failed', 'reversed'], true)
                && !($transfer->status === 'success' && $status === 'reversed')) {
                if ($status !== $transfer->status && in_array($status, ['success', 'failed', 'reversed'], true)) {
                    DB::afterCommit(fn () => app(IncidentAlertService::class)->alert(
                        'payout-terminal-conflict:' . $transfer->id,
                        'Payout received conflicting terminal outcomes. Finance review is required.',
                        ['payout_transfer_id' => $transfer->id]
                    ));
                }
                return;
            }
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
                if (Schema::hasTable('financial_ledger_accounts')) {
                    app(FinancialLedgerService::class)->settleWithdrawal($withdrawal, $transfer->currency);
                }
                $withdrawalTransaction->payment_status = 1;
                $withdrawalTransaction->save();
            } elseif ($status === 'reversed' && (int) $withdrawal->status === Withdraw::STATUS_PAID) {
                if (Schema::hasTable('financial_ledger_accounts')) {
                    app(FinancialLedgerService::class)->reversePaidWithdrawal($withdrawal, $transfer->currency);
                }
                $withdrawal->forceFill([
                    'status' => Withdraw::STATUS_HELD,
                    'decision_reason' => 'The provider reversed this payout. Funds are reserved for finance review.',
                    'paid_at' => null,
                ] + $this->clearedReleaseApproval($withdrawal))->save();
                $withdrawalTransaction->payment_status = 0;
                $withdrawalTransaction->save();
            } elseif (in_array($status, ['failed', 'reversed'], true)
                && (int) $withdrawal->status === Withdraw::STATUS_PROCESSING) {
                $withdrawal->forceFill([
                    'status' => Withdraw::STATUS_HELD,
                    'decision_reason' => 'Payout ' . $status . '. Funds are reserved for finance review.',
                ] + $this->clearedReleaseApproval($withdrawal))->save();
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
        if (in_array(strtolower((string) ($data['status'] ?? '')), ['success', 'failed', 'reversed'], true)
            && (!isset($data['reference'], $data['amount'], $data['currency']) || !is_numeric($data['amount']))) {
            $issues[] = 'incomplete_terminal_evidence';
        }
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
        $updated = PayoutTransfer::whereKey($transferId)->where('status', 'submitting')->update([
            'status' => 'verification_required',
        ]);
        if ($updated !== 1) {
            return;
        }
        $transfer = PayoutTransfer::findOrFail($transferId);
        AuditLogger::record('payout.transfer_verification_required', $transfer, ['status' => 'submitting'], [
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
            if (!in_array($transfer->status, ['submitting', 'pending', 'processing', 'verification_required'], true)) {
                return;
            }
            $attempts = (int) $transfer->verification_attempts + 1;
            $maxAttempts = max(1, (int) config('payouts.verification_max_attempts', 5));
            $exhausted = $attempts >= $maxAttempts;
            $before = ['status' => $transfer->status, 'verification_attempts' => (int) $transfer->verification_attempts];

            $transfer->forceFill([
                'status' => $exhausted ? 'needs_attention' : $transfer->status,
                'verification_attempts' => $attempts,
                'last_verified_at' => now(),
                'last_error' => 'The provider did not confirm the original transfer. Funds remain reserved.',
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

    private function markFailed(int $transferId, Throwable $exception, string $expectedStatus = 'submitting'): void
    {
        DB::transaction(function () use ($transferId, $exception, $expectedStatus) {
            $withdrawal = Withdraw::whereKey(PayoutTransfer::findOrFail($transferId)->withdrawal_id)->lockForUpdate()->firstOrFail();
            $transfer = PayoutTransfer::whereKey($transferId)->lockForUpdate()->firstOrFail();
            if ($transfer->status !== $expectedStatus) {
                return;
            }
            $transfer->forceFill([
                'status' => 'failed',
                'last_error' => 'The transfer could not start. Review the payout approval and provider configuration.',
                'completed_at' => now(),
            ])->save();
            if ((int) $withdrawal->status === Withdraw::STATUS_PROCESSING) {
                $withdrawal->forceFill([
                    'status' => Withdraw::STATUS_HELD,
                    'decision_reason' => 'Transfer submission failed; finance review is required.',
                ] + $this->clearedReleaseApproval($withdrawal))->save();
            }
            AuditLogger::record('payout.transfer_failed', $transfer, [], [
                'status' => 'failed',
            ], [
                'exception_type' => $exception::class,
                'exception_code' => $exception->getCode(),
            ]);
            DB::afterCommit(fn () => $this->dispatchStatusNotification($transfer->id, 'failed'));
        }, 3);
    }

    private function clearedReleaseApproval(Withdraw $withdrawal): array
    {
        return array_intersect_key(['release_approval' => null], $withdrawal->getAttributes());
    }
}
