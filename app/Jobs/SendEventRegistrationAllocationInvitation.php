<?php

namespace App\Jobs;

use App\Mail\EventRegistrationAllocationInvitationMail;
use App\Models\EventRegistrationAllocationClaim;
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

class SendEventRegistrationAllocationInvitation implements ShouldQueue, ShouldBeEncrypted, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $uniqueFor = 3600;

    public function __construct(public int $claimId, public string $token) { $this->onQueue('default'); }
    public function uniqueId(): string { return 'registration-allocation-claim:'.$this->claimId.':'.hash('sha256', $this->token); }
    public function backoff(): array { return [60, 300, 900, 1800]; }
    public function middleware(): array { return [(new WithoutOverlapping('registration-allocation-claim:'.$this->claimId))->expireAfter(900)]; }

    public function handle(): void
    {
        $claim = EventRegistrationAllocationClaim::with(['allocation', 'event.information'])->findOrFail($this->claimId);
        if (!hash_equals($claim->token_hash, hash('sha256', $this->token))) return;
        if ($claim->status !== 'pending') {
            $claim->forceFill(['dispatch_token' => null])->save();
            return;
        }
        if (!$claim->allocation || $claim->allocation->status !== 'active' || $claim->expires_at->isPast()) {
            $claim->forceFill(['status' => $claim->expires_at->isPast() ? 'expired' : 'cancelled', 'delivery_status' => 'suppressed', 'dispatch_token' => null])->save();
            return;
        }
        $claim->forceFill(['delivery_status' => 'processing', 'processing_at' => now(),
            'delivery_attempts' => $claim->delivery_attempts + 1, 'last_error' => null, 'alerted_at' => null])->save();
        try {
            if (!filter_var($claim->email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('The allocation invitation does not have a deliverable email address.');
            $url = URL::temporarySignedRoute('event.registration-allocation.claim.show', $claim->expires_at, ['claim' => $claim, 'token' => $this->token]);
            Mail::to($claim->email)->send(new EventRegistrationAllocationInvitationMail(
                $claim->name, $claim->allocation->sponsor_name, (string) ($claim->event?->information?->title ?: 'Your event'),
                $url, $claim->expires_at->timezone(config('app.timezone'))->format('j M Y, g:i A T')
            ));
            $claim->forceFill(['delivery_status' => 'sent', 'invited_at' => now(), 'processing_at' => null, 'last_error' => null, 'dispatch_token' => null, 'dispatch_last_error' => null])->save();
        } catch (Throwable $exception) {
            $claim->forceFill(['delivery_status' => 'failed', 'processing_at' => null, 'last_error' => mb_substr($exception->getMessage(), 0, 2000)])->save();
            throw $exception;
        }
    }
}
