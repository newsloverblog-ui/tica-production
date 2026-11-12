<?php

namespace App\Jobs;

use App\Mail\CustomerEmailVerificationMail;
use App\Models\BasicSettings\MailTemplate;
use App\Models\CustomerEmailVerificationDelivery;
use App\Services\CustomerEmailVerificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class SendCustomerEmailVerification implements ShouldQueue, ShouldBeEncrypted, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $uniqueFor = 3600;

    public function __construct(public int $deliveryId)
    {
        $this->onQueue('default');
    }

    public function uniqueId(): string
    {
        return 'customer-email-verification:'.$this->deliveryId;
    }

    public function backoff(): array
    {
        return [60, 300, 900, 1800];
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->uniqueId()))->expireAfter(900)];
    }

    public function handle(CustomerEmailVerificationService $verifications): void
    {
        $delivery = CustomerEmailVerificationDelivery::query()->with('customer')->findOrFail($this->deliveryId);

        if ($delivery->status === 'sent') {
            return;
        }

        $customer = $delivery->customer;
        if (!$customer || $customer->email_verified_at || $delivery->expires_at->isPast()
            || !hash_equals($delivery->email_hash, $verifications->emailHash((string) $customer->email))) {
            $delivery->forceFill([
                'status' => 'suppressed', 'processing_at' => null, 'last_error' => null,
            ])->save();
            return;
        }

        $delivery->forceFill([
            'status' => 'processing', 'attempts' => (int) $delivery->attempts + 1,
            'processing_at' => now(), 'last_error' => null, 'alerted_at' => null,
        ])->save();

        try {
            if (!filter_var($customer->email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('The customer does not have a deliverable email address.');
            }

            $template = MailTemplate::query()->where('mail_type', 'verify_email')->first();
            $settings = DB::table('basic_settings')->select('website_title')->first();
            $subject = (string) ($template?->mail_subject ?: 'Verify your Tica email');
            $body = (string) ($template?->mail_body
                ?: 'Hello {username},<br><br>Please verify your email address: {verification_link}<br><br>{website_title}');
            $url = $verifications->verificationUrl($customer, $delivery);
            $body = str_replace('{username}', e(trim((string) $customer->fname.' '.(string) $customer->lname)), $body);
            $body = str_replace('{verification_link}', '<a href="'.e($url).'">Verify email address</a>', $body);
            $body = str_replace('{website_title}', e((string) ($settings?->website_title ?: config('app.name'))), $body);

            Mail::to($customer->email)->send(new CustomerEmailVerificationMail($subject, $body));

            $delivery->forceFill([
                'status' => 'sent', 'sent_at' => now(), 'processing_at' => null, 'last_error' => null,
            ])->save();
        } catch (Throwable $exception) {
            $delivery->forceFill([
                'status' => 'failed', 'processing_at' => null,
                'last_error' => mb_substr($exception->getMessage(), 0, 2000),
            ])->save();
            throw $exception;
        }
    }
}
