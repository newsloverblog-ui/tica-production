<?php

namespace App\Mail;

use App\Services\LifecycleMailService;
use App\Support\LifecycleMailCatalog;
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
        public ?string $error = null
    ) {
    }

    public function build()
    {
        if ($this->audience === 'organizer' && $this->status === 'success') {
            $rendered = app(LifecycleMailService::class)->render(LifecycleMailCatalog::ORGANIZER_PAYOUT_SENT, [
                'first_name' => 'there',
                'payout_amount' => $this->currency . ' ' . $this->amount,
                'payout_reference' => $this->reference,
                'finance_url' => route('organizer.finance.index'),
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
