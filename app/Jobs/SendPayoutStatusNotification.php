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
            $this->sendSms($organizer, $transfer);
            $this->inbox($organizer, $transfer);
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

    /** The same news in the organiser's bell. */
    private function inbox($organizer, PayoutTransfer $transfer): void
    {
        $amount = strtoupper((string) $transfer->currency) . ' ' . number_format((float) $transfer->amount, 2);
        [$title, $body] = match ($transfer->status) {
            'queued' => ['Payout on its way', $amount . ' is being sent to your bank.'],
            'success' => ['Payout sent', $amount . ' has been paid to your bank account.'],
            'reversed' => ['Payout returned', 'Your bank returned ' . $amount . '. It is back in your balance and TICA will be in touch.'],
            default => ['Payout did not go through', 'We could not send ' . $amount . '. It is back in your balance and TICA will be in touch.'],
        };
        \App\Support\OrganizerInbox::send((int) $organizer->id, 'payout.' . $transfer->status, $title, $body, url('/dashboard/payouts'));
    }

    /**
     * Optional text for organisers who turned on "Text me about payouts".
     * Sent once per status, alongside the email; a failed SMS never blocks it.
     */
    private function sendSms($organizer, PayoutTransfer $transfer): void
    {
        if (!in_array($transfer->status, ['success', 'failed', 'reversed'], true)
            || !$organizer->preferenceEnabled('payout_sms', false)
            || trim((string) $organizer->phone) === '') {
            return;
        }
        $sms = app(\App\Contracts\SmsSender::class);
        if (!$sms->configured()) {
            return;
        }
        $amount = 'NGN ' . number_format((float) $transfer->amount, 2);
        $message = match ($transfer->status) {
            'success' => 'TICA: ' . $amount . ' has been paid to your bank account. Ref ' . substr((string) $transfer->reference, -8) . '.',
            default => 'TICA: your payout of ' . $amount . ' did not go through. We\'re looking into it and will email you.',
        };
        try {
            $sms->send((string) $organizer->phone, $message);
            \App\Services\AuditLogger::record('payout.sms_sent', $transfer, [], ['status' => $transfer->status]);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
