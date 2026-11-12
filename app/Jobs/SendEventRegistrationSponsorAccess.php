<?php

namespace App\Jobs;

use App\Mail\EventRegistrationSponsorAccessMail;
use App\Models\EventRegistrationAllocation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use RuntimeException;
use Throwable;

class SendEventRegistrationSponsorAccess implements ShouldQueue, ShouldBeEncrypted, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $uniqueFor = 3600;

    public function __construct(public int $allocationId, public string $token) { $this->onQueue('default'); }
    public function uniqueId(): string { return 'registration-sponsor-access:'.$this->allocationId.':'.hash('sha256', $this->token); }
    public function backoff(): array { return [60, 300, 900, 1800]; }
    public function middleware(): array { return [(new WithoutOverlapping('registration-sponsor-access:'.$this->allocationId))->expireAfter(900)]; }

    public function handle(): void
    {
        $allocation = EventRegistrationAllocation::with('event.information')->findOrFail($this->allocationId);
        if (!hash_equals($allocation->access_token_hash, hash('sha256', $this->token))) return;
        if ($allocation->status !== 'active') {
            $allocation->forceFill(['dispatch_token' => null])->save();
            return;
        }
        if (!$allocation->event || (int) $allocation->event->status !== 1
            || ($allocation->event->lifecycle_status ?: 'scheduled') !== 'scheduled'
            || $allocation->event->cancelled_at || $allocation->expires_at->isPast()) {
            $allocation->forceFill(['status' => $allocation->expires_at->isPast() ? 'expired' : 'cancelled', 'delivery_status' => 'suppressed', 'dispatch_token' => null])->save();
            return;
        }
        $allocation->forceFill(['delivery_status' => 'processing', 'processing_at' => now(),
            'delivery_attempts' => $allocation->delivery_attempts + 1, 'last_error' => null, 'alerted_at' => null])->save();
        try {
            if (!filter_var($allocation->sponsor_email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('The sponsor allocation does not have a deliverable email address.');
            $url = URL::temporarySignedRoute('event.registration-allocation.show', $allocation->expires_at, ['allocation' => $allocation, 'token' => $this->token]);
            Mail::to($allocation->sponsor_email)->send(new EventRegistrationSponsorAccessMail(
                $allocation->sponsor_name, (string) ($allocation->event?->information?->title ?: 'Your event'),
                $allocation->capacity, $url, $allocation->expires_at->timezone(config('app.timezone'))->format('j M Y, g:i A T')
            ));
            $allocation->forceFill(['delivery_status' => 'sent', 'invited_at' => now(), 'processing_at' => null, 'last_error' => null, 'dispatch_token' => null, 'dispatch_last_error' => null])->save();
        } catch (Throwable $exception) {
            $allocation->forceFill(['delivery_status' => 'failed', 'processing_at' => null, 'last_error' => mb_substr($exception->getMessage(), 0, 2000)])->save();
            throw $exception;
        }
    }
}
