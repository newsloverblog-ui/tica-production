<?php

namespace App\Services;

use App\Jobs\SendCustomerEmailVerification;
use App\Models\Customer;
use App\Models\CustomerEmailVerificationDelivery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Throwable;

class CustomerEmailVerificationService
{
    public function request(Customer $customer): ?CustomerEmailVerificationDelivery
    {
        if ($customer->email_verified_at) {
            return null;
        }

        $delivery = DB::transaction(function () use ($customer): ?CustomerEmailVerificationDelivery {
            $locked = Customer::query()->lockForUpdate()->find($customer->id);
            if (!$locked || $locked->email_verified_at) {
                return null;
            }

            $recent = CustomerEmailVerificationDelivery::query()
                ->where('customer_id', $locked->id)
                ->where('created_at', '>=', now()->subMinutes(2))
                ->latest('id')
                ->first();
            if ($recent) {
                return $recent;
            }

            CustomerEmailVerificationDelivery::query()
                ->where('customer_id', $locked->id)
                ->whereIn('status', ['pending', 'processing'])
                ->update(['status' => 'superseded', 'updated_at' => now()]);

            $delivery = CustomerEmailVerificationDelivery::create([
                'public_id' => (string) Str::uuid(),
                'customer_id' => $locked->id,
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
        $delivery = CustomerEmailVerificationDelivery::query()->find($deliveryId);
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
        SendCustomerEmailVerification::dispatch($deliveryId);
    }

    public function verificationUrl(Customer $customer, CustomerEmailVerificationDelivery $delivery): string
    {
        return URL::temporarySignedRoute('customer.email.verify', $delivery->expires_at, [
            'customer' => $customer->id,
            'delivery' => $delivery->public_id,
            'email' => $delivery->email_hash,
        ]);
    }

    public function verify(int $customerId, string $deliveryPublicId, string $emailHash): Customer
    {
        return DB::transaction(function () use ($customerId, $deliveryPublicId, $emailHash): Customer {
            $customer = Customer::query()->lockForUpdate()->findOrFail($customerId);
            $delivery = CustomerEmailVerificationDelivery::query()
                ->where('public_id', $deliveryPublicId)
                ->where('customer_id', $customer->id)
                ->lockForUpdate()
                ->firstOrFail();
            $expectedHash = $this->emailHash((string) $customer->email);

            abort_unless(hash_equals($expectedHash, $emailHash), 403);
            abort_unless(hash_equals($delivery->email_hash, $expectedHash), 403);
            abort_if($delivery->expires_at->isPast(), 410, 'This verification link has expired.');

            if (!$customer->email_verified_at) {
                $customer->forceFill(['email_verified_at' => now(), 'status' => 1, 'verification_token' => null])->save();
            }

            $delivery->forceFill(['status' => 'verified', 'verified_at' => now()])->save();

            return $customer->fresh();
        }, 3);
    }
}
