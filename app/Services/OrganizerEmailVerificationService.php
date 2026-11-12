<?php

namespace App\Services;

use App\Jobs\SendOrganizerEmailVerification;
use App\Models\Organizer;
use App\Models\OrganizerEmailVerificationDelivery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Throwable;

class OrganizerEmailVerificationService
{
    public function request(Organizer $organizer): ?OrganizerEmailVerificationDelivery
    {
        if ($organizer->email_verified_at) {
            return null;
        }

        $delivery = DB::transaction(function () use ($organizer): ?OrganizerEmailVerificationDelivery {
            $locked = Organizer::query()->lockForUpdate()->find($organizer->id);
            if (!$locked || $locked->email_verified_at) {
                return null;
            }

            $recent = OrganizerEmailVerificationDelivery::query()
                ->where('organizer_id', $locked->id)
                ->where('created_at', '>=', now()->subMinutes(2))
                ->latest('id')
                ->first();
            if ($recent) {
                return $recent;
            }

            OrganizerEmailVerificationDelivery::query()
                ->where('organizer_id', $locked->id)
                ->whereIn('status', ['pending', 'processing'])
                ->update(['status' => 'superseded', 'updated_at' => now()]);

            $delivery = OrganizerEmailVerificationDelivery::create([
                'public_id' => (string) Str::uuid(),
                'organizer_id' => $locked->id,
                'email_hash' => $this->emailHash((string) $locked->email),
                'status' => 'pending',
                'expires_at' => now()->addMinutes(60),
            ]);

            return $delivery;
        }, 3);

        if ($delivery?->wasRecentlyCreated) {
            $this->dispatchDelivery((int) $delivery->id);
        }

        return $delivery;
    }

    public function emailHash(string $email): string
    {
        return hash_hmac('sha256', mb_strtolower(trim($email)), (string) config('app.key'));
    }

    public function dispatchDelivery(int $deliveryId): bool
    {
        $delivery = OrganizerEmailVerificationDelivery::query()->find($deliveryId);
        if (!$delivery || $delivery->status !== 'pending' || $delivery->expires_at->isPast()) {
            return true;
        }
        $delivery->forceFill([
            'dispatch_attempts' => (int) $delivery->dispatch_attempts + 1,
            'dispatch_last_attempt_at' => now(),
            'dispatch_last_error' => null,
        ])->save();
        try {
            $this->pushDeliveryJob($delivery->id);
            return true;
        } catch (Throwable $exception) {
            $delivery->forceFill([
                'dispatch_last_error' => mb_substr($exception->getMessage(), 0, 2000),
            ])->save();
            return false;
        }
    }

    protected function pushDeliveryJob(int $deliveryId): void
    {
        SendOrganizerEmailVerification::dispatch($deliveryId);
    }

    public function verificationUrl(
        Organizer $organizer,
        OrganizerEmailVerificationDelivery $delivery
    ): string {
        return URL::temporarySignedRoute('organizer.email.verify', $delivery->expires_at, [
            'organizer' => $organizer->id,
            'delivery' => $delivery->public_id,
            'email' => $delivery->email_hash,
        ]);
    }

    public function verify(int $organizerId, string $deliveryPublicId, string $emailHash): Organizer
    {
        return DB::transaction(function () use ($organizerId, $deliveryPublicId, $emailHash): Organizer {
            $organizer = Organizer::query()->lockForUpdate()->findOrFail($organizerId);
            $delivery = OrganizerEmailVerificationDelivery::query()
                ->where('public_id', $deliveryPublicId)
                ->where('organizer_id', $organizer->id)
                ->lockForUpdate()
                ->firstOrFail();
            $expectedHash = $this->emailHash((string) $organizer->email);

            abort_unless(hash_equals($expectedHash, $emailHash), 403);
            abort_unless(hash_equals($delivery->email_hash, $expectedHash), 403);
            abort_if($delivery->expires_at->isPast(), 410, 'This verification link has expired.');

            if (!$organizer->email_verified_at) {
                $organizer->email_verified_at = now();
                $setting = DB::table('basic_settings')->where('uniqid', 12345)
                    ->select('organizer_admin_approval')->first();
                if ((int) ($setting?->organizer_admin_approval ?? 0) !== 1) {
                    $organizer->status = 1;
                }
                $organizer->save();
            }

            $delivery->forceFill(['status' => 'verified', 'verified_at' => now()])->save();

            return $organizer->fresh();
        }, 3);
    }
}
