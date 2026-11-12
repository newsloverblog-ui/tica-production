<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerPrivacyRequest;
use DomainException;
use Illuminate\Support\Facades\DB;

class CustomerPrivacyRequestService
{
    public const OPEN_STATUSES = ['pending', 'under_review'];
    public const STATUSES = ['pending', 'under_review', 'rejected', 'fulfilled', 'cancelled'];

    public function requestDeletion(int $customerId, ?string $reason): CustomerPrivacyRequest
    {
        $reason = trim((string) $reason);
        if ($customerId < 1 || ($reason !== '' && mb_strlen($reason) > 2000)) {
            throw new DomainException('The privacy request is invalid.');
        }

        [$request, $created] = DB::transaction(function () use ($customerId, $reason): array {
            $customer = Customer::query()->whereKey($customerId)->lockForUpdate()->firstOrFail();
            $existing = CustomerPrivacyRequest::query()
                ->where('customer_id', $customerId)
                ->where('request_type', 'deletion')
                ->whereIn('status', self::OPEN_STATUSES)
                ->latest('id')
                ->first();
            if ($existing) {
                return [$existing, false];
            }

            $request = CustomerPrivacyRequest::create([
                'customer_id' => $customer->id,
                'request_type' => 'deletion',
                'status' => 'pending',
                'customer_email' => (string) $customer->email,
                'customer_name' => trim((string) $customer->fname.' '.(string) $customer->lname),
                'reason' => $reason !== '' ? $reason : null,
                'policy_version' => trim((string) config('privacy.policy_version')) ?: 'not-configured',
                'submitted_at' => now(),
            ]);
            AuditLogger::record('customer_privacy_request.submitted', $request, [], [
                'request_type' => 'deletion', 'status' => 'pending', 'policy_version' => $request->policy_version,
            ]);

            return [$request, true];
        }, 3);

        if ($created) {
            $this->notify($request, 'Your Tica privacy request was received',
                'We received your account deletion request. It will be reviewed against financial, security and legal retention requirements. Your account and records have not been deleted yet.');
        }

        return $request;
    }

    public function cancel(int $requestId, int $customerId): CustomerPrivacyRequest
    {
        return DB::transaction(function () use ($requestId, $customerId): CustomerPrivacyRequest {
            $request = CustomerPrivacyRequest::query()->whereKey($requestId)->lockForUpdate()->firstOrFail();
            if ((int) $request->customer_id !== $customerId) {
                throw new DomainException('You cannot change this privacy request.');
            }
            if ($request->status !== 'pending') {
                throw new DomainException('Only a pending privacy request can be cancelled.');
            }
            $before = ['status' => $request->status];
            $request->forceFill(['status' => 'cancelled', 'completed_at' => now()])->save();
            AuditLogger::record('customer_privacy_request.cancelled', $request, $before, [
                'status' => 'cancelled',
            ]);

            return $request;
        }, 3);
    }

    public function review(
        int $requestId,
        int $adminId,
        string $status,
        string $resolution,
        ?string $evidenceReference
    ): CustomerPrivacyRequest {
        $status = trim($status);
        $resolution = trim($resolution);
        $evidenceReference = trim((string) $evidenceReference);
        if ($adminId < 1 || !in_array($status, ['under_review', 'rejected', 'fulfilled'], true)) {
            throw new DomainException('The privacy review decision is invalid.');
        }
        if (mb_strlen($resolution) < 20 || mb_strlen($resolution) > 4000) {
            throw new DomainException('Record a clear review resolution between 20 and 4,000 characters.');
        }
        if ($status === 'fulfilled' && ($evidenceReference === '' || mb_strlen($evidenceReference) > 500)) {
            throw new DomainException('A non-secret completion evidence reference is required before fulfilment.');
        }

        $request = DB::transaction(function () use (
            $requestId, $adminId, $status, $resolution, $evidenceReference
        ): CustomerPrivacyRequest {
            $request = CustomerPrivacyRequest::query()->whereKey($requestId)->lockForUpdate()->firstOrFail();
            $allowed = match ($request->status) {
                'pending' => ['under_review', 'rejected'],
                'under_review' => ['rejected', 'fulfilled'],
                default => [],
            };
            if (!in_array($status, $allowed, true)) {
                throw new DomainException('This privacy request cannot move to the selected status.');
            }

            $before = $request->only(['status', 'reviewed_by_admin_id', 'reviewed_at', 'completed_at']);
            $request->forceFill([
                'status' => $status,
                'reviewed_by_admin_id' => $adminId,
                'resolution' => $resolution,
                'evidence_reference' => $evidenceReference !== '' ? $evidenceReference : null,
                'reviewed_at' => now(),
                'completed_at' => in_array($status, ['rejected', 'fulfilled'], true) ? now() : null,
            ])->save();

            if ($status === 'fulfilled') {
                app(OperationalArchiveService::class)->archiveCustomers([(int) $request->customer_id]);
            }

            AuditLogger::record('customer_privacy_request.reviewed', $request, $before, [
                'status' => $status,
                'reviewed_by_admin_id' => $adminId,
                'evidence_reference' => $request->evidence_reference,
                'account_archived' => $status === 'fulfilled',
            ]);

            return $request;
        }, 3);

        $message = match ($request->status) {
            'under_review' => 'Your Tica privacy request is under review. No data has been deleted yet.',
            'rejected' => 'Your Tica privacy request review is complete. Sign in or contact support for the recorded outcome.',
            'fulfilled' => 'Your Tica privacy request has been completed and your account has been deactivated. Records that must be retained for financial, security or legal purposes remain protected under the applicable retention policy.',
        };
        $this->notify($request, 'Update on your Tica privacy request', $message);

        return $request;
    }

    private function notify(CustomerPrivacyRequest $request, string $subject, string $message): void
    {
        try {
            app(ApplicationMailService::class)->queueHtml(
                'customer_privacy_request',
                (string) $request->customer_email,
                $subject,
                '<p>'.e($message).'</p><p>Reference: '.e($request->public_id).'</p>',
                source: $request
            );
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
