<?php

namespace App\Jobs;

use App\Mail\PayoutStatusMail;
use App\Models\PayoutTransfer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class SendPayoutStatusNotification implements ShouldQueue, ShouldBeEncrypted, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $uniqueFor = 3600;

    public function __construct(public int $transferId, public string $expectedStatus)
    {
        $this->onQueue('default');
    }

    public function uniqueId(): string
    {
        return 'payout-status:' . $this->transferId . ':' . $this->expectedStatus;
    }

    public function backoff(): array
    {
        return [60, 300, 900, 1800];
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->uniqueId()))->expireAfter(900)];
    }

    public function handle(): void
    {
        $transfer = PayoutTransfer::with('withdrawal.organizer')->findOrFail($this->transferId);
        if ($transfer->status !== $this->expectedStatus) {
            return;
        }

        $withdrawal = $transfer->withdrawal;
        $organizer = $withdrawal?->organizer;
        $organizerStatuses = ['queued', 'success', 'failed', 'reversed'];
        $financeStatuses = ['failed', 'reversed', 'verification_required', 'needs_attention'];

        if (in_array($transfer->status, $organizerStatuses, true)
            && $transfer->organizer_notified_status !== $transfer->status) {
            if (!$organizer || !filter_var($organizer->email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('The payout organizer does not have a deliverable email address.');
            }
            Mail::to($organizer->email)->send($this->mail($transfer, 'organizer'));
            $transfer->forceFill([
                'organizer_notified_status' => $transfer->status,
                'organizer_notified_at' => now(),
            ])->save();
        }

        if (in_array($transfer->status, $financeStatuses, true)
            && $transfer->finance_notified_status !== $transfer->status) {
            $financeEmail = (string) config('payouts.finance_email', '');
            if (!filter_var($financeEmail, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('PAYOUT_FINANCE_EMAIL is not configured.');
            }
            Mail::to($financeEmail)->send($this->mail($transfer, 'finance'));
            $transfer->forceFill([
                'finance_notified_status' => $transfer->status,
                'finance_notified_at' => now(),
            ])->save();
        }
    }

    private function mail(PayoutTransfer $transfer, string $audience): PayoutStatusMail
    {
        return new PayoutStatusMail(
            $audience,
            $transfer->status,
            $transfer->reference,
            (string) $transfer->withdrawal->withdraw_id,
            number_format((float) $transfer->amount, 2, '.', ','),
            strtoupper($transfer->currency),
            $audience === 'finance' ? $transfer->last_error : null
        );
    }
}
