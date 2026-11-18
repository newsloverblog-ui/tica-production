<?php

namespace App\Mail;

use App\Services\LifecycleMailService;
use App\Support\LifecycleMailCatalog;
use App\Services\MailTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PayoutStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $audience,
        public string $status,
        public string $reference,
        public string $withdrawalId,
        public string $amount,
        public string $currency,
        public ?string $error = null,
        public string $organizerName = 'there',
        public string $bankName = '',
        public string $maskedAccountNumber = ''
    ) {
    }

    public function build()
    {
        if ($this->audience === 'organizer' && $this->status === 'success') {
            $rendered = app(LifecycleMailService::class)->render(LifecycleMailCatalog::ORGANIZER_PAYOUT_SENT, [
                'organizer_name' => $this->organizerName,
                'payout_amount' => $this->currency . ' ' . $this->amount,
                'payout_reference' => $this->reference,
                'bank_name' => $this->bankName,
                'masked_account_number' => $this->maskedAccountNumber,
                'finance_url' => route('organizer.finance.index'),
            ]);

            return $this->subject($rendered['subject'])->html($rendered['html']);
        }

        if ($this->audience === 'organizer' && in_array($this->status, ['failed', 'reversed'], true)) {
            $rendered = app(MailTemplateService::class)->render('payout_failed', [
                'organizer_name' => $this->organizerName,
                'amount' => $this->currency.' '.$this->amount,
                'reference' => $this->reference,
                'reason' => $this->error ?: ($this->status === 'reversed'
                    ? 'The receiving bank reversed the transfer.'
                    : 'The transfer provider could not complete the payout.'),
                'dashboard_link' => route('organizer.finance.index'),
            ]);
            return $this->subject($rendered['subject'])->html($rendered['html']);
        }

        $subject = $this->audience === 'finance'
            ? 'Payout requires finance attention: ' . $this->reference
            : match ($this->status) {
                'queued' => 'Your Tica payout is being processed',
                'success' => 'Your Tica payout has been completed',
                default => 'Update on your Tica payout',
            };

        return $this->subject($subject)->markdown('emails.payout-status');
    }
}
