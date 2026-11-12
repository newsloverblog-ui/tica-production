<?php

namespace App\Jobs;

use App\Mail\OrganizerEmailVerificationMail;
use App\Models\OrganizerEmailVerificationDelivery;
use App\Services\LifecycleMailService;
use App\Services\OrganizerEmailVerificationService;
use App\Support\LifecycleMailCatalog;
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
use Throwable;

class SendOrganizerEmailVerification implements ShouldQueue, ShouldBeEncrypted, ShouldBeUnique
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
        return 'organizer-email-verification:'.$this->deliveryId;
    }

    public function backoff(): array
    {
        return [60, 300, 900, 1800];
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->uniqueId()))->expireAfter(900)];
    }

    public function handle(OrganizerEmailVerificationService $verifications): void
    {
        $delivery = OrganizerEmailVerificationDelivery::query()
            ->with('organizer')
            ->findOrFail($this->deliveryId);

        if ($delivery->status === 'sent') {
            return;
        }

        $organizer = $delivery->organizer;
        if (!$organizer
            || $organizer->email_verified_at
            || $delivery->expires_at->isPast()
            || !hash_equals($delivery->email_hash, $verifications->emailHash((string) $organizer->email))) {
            $delivery->forceFill([
                'status' => 'suppressed', 'processing_at' => null, 'last_error' => null,
            ])->save();
            return;
        }

        $delivery->forceFill([
            'status' => 'processing',
            'attempts' => (int) $delivery->attempts + 1,
            'processing_at' => now(),
            'last_error' => null,
            'alerted_at' => null,
        ])->save();

        try {
            if (!filter_var($organizer->email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('The organizer does not have a deliverable email address.');
            }

            $url = $verifications->verificationUrl($organizer, $delivery);
            $rendered = app(LifecycleMailService::class)->render(LifecycleMailCatalog::ORGANIZER_WELCOME, [
                'first_name' => (string) ($organizer->username ?: 'there'),
                'verification_url' => $url,
            ]);

            Mail::to($organizer->email)->send(new OrganizerEmailVerificationMail(
                $rendered['subject'],
                $rendered['html']
            ));

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
