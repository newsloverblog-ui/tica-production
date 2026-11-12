<?php

namespace App\Console\Commands;

use App\Models\PayoutTransfer;
use App\Services\IncidentAlertService;
use App\Services\PayoutTransferService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class DispatchPendingPayouts extends Command
{
    protected $signature = 'payouts:dispatch-pending {--limit=100} {--stale-minutes=10}';
    protected $description = 'Recover payout submission and status-notification queue handoffs';

    public function handle(PayoutTransferService $payouts, IncidentAlertService $alerts): int
    {
        if (!Schema::hasTable('payout_transfers') || !Schema::hasColumn('payout_transfers', 'dispatch_attempts')) {
            $this->error('The payout dispatch metadata migration is not applied.');
            return self::FAILURE;
        }
        $limit = max(1, min(500, (int) $this->option('limit')));
        $staleMinutes = max(5, (int) $this->option('stale-minutes'));
        $attempted = 0;
        $failed = 0;
        $alerted = 0;
        $notificationAttempted = 0;
        $notificationFailed = 0;
        $notificationAlerted = 0;

        $transfers = PayoutTransfer::query()
            ->where('status', 'queued')
            ->orderBy('id')
            ->limit($limit)
            ->get();
        foreach ($transfers as $transfer) {
            $attempted++;
            if (!$payouts->dispatchQueued($transfer->id)) {
                $failed++;
            }
            $transfer->refresh();
            if ($transfer->status === 'queued'
                && $transfer->created_at->lt(now()->subMinutes($staleMinutes))
                && $transfer->dispatch_alerted_at === null) {
                $transfer->forceFill(['dispatch_alerted_at' => now()])->save();
                $alerts->alert('payout-dispatch-pending:'.$transfer->id,
                    'A committed payout transfer did not start on the payments queue.', [
                        'payout_transfer_id' => $transfer->id,
                        'withdrawal_id' => $transfer->withdrawal_id,
                        'gateway' => $transfer->gateway,
                        'dispatch_attempts' => $transfer->dispatch_attempts,
                    ], 'critical');
                $alerted++;
            }
        }

        if (!Schema::hasColumn('payout_transfers', 'notification_dispatch_attempts')) {
            $this->error('The payout notification dispatch metadata migration is not applied.');
            return self::FAILURE;
        }

        $notificationTransfers = PayoutTransfer::query()
            ->whereIn('status', [
                'queued', 'success', 'failed', 'reversed',
                'verification_required', 'needs_attention',
            ])
            ->where(function ($query): void {
                $query->where(function ($organizer): void {
                    $organizer->whereIn('status', ['queued', 'success', 'failed', 'reversed'])
                        ->where(function ($missing): void {
                            $missing->whereNull('organizer_notified_status')
                                ->orWhereColumn('organizer_notified_status', '!=', 'status');
                        });
                })->orWhere(function ($finance): void {
                    $finance->whereIn('status', ['failed', 'reversed', 'verification_required', 'needs_attention'])
                        ->where(function ($missing): void {
                            $missing->whereNull('finance_notified_status')
                                ->orWhereColumn('finance_notified_status', '!=', 'status');
                        });
                });
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($notificationTransfers as $transfer) {
            $notificationAttempted++;
            $status = $transfer->status;
            $statusRecordedAt = $transfer->completed_at
                ?? $transfer->submitted_at
                ?? $transfer->created_at;
            if (!$payouts->dispatchStatusNotification($transfer->id, $status)) {
                $notificationFailed++;
            }
            $transfer->refresh();
            if ($transfer->status === $status
                && $payouts->statusNotificationRequired($transfer)
                && $statusRecordedAt->lt(now()->subMinutes($staleMinutes))
                && $transfer->notification_dispatch_alerted_at === null) {
                $transfer->forceFill(['notification_dispatch_alerted_at' => now()])->save();
                $alerts->alert('payout-notification-dispatch-pending:'.$transfer->id.':'.$status,
                    'A committed payout status did not start its notification delivery.', [
                        'payout_transfer_id' => $transfer->id,
                        'withdrawal_id' => $transfer->withdrawal_id,
                        'status' => $status,
                        'notification_dispatch_attempts' => $transfer->notification_dispatch_attempts,
                    ], 'critical');
                $notificationAlerted++;
            }
        }

        $this->line(sprintf('Payout dispatch attempted: %d | failed: %d | alerted: %d', $attempted, $failed, $alerted));
        $this->line(sprintf(
            'Payout notification dispatch attempted: %d | failed: %d | alerted: %d',
            $notificationAttempted,
            $notificationFailed,
            $notificationAlerted
        ));
        return $failed === 0 && $notificationFailed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
