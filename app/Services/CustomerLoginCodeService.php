<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerLoginCode;
use App\Support\LifecycleMailCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerLoginCodeService
{
    public function request(string $email): void
    {
        $delivery = DB::transaction(function () use ($email): ?array {
            $customer = $this->lockEligibleCustomer($email);
            if (!$customer) {
                return null;
            }
            $recent = CustomerLoginCode::where('customer_id', $customer->id)->where('created_at', '>', now()->subMinutes(10));
            if ((clone $recent)->count() >= 3 || (int) $recent->sum('attempts') >= 5) {
                return null;
            }
            $recentHashes = $recent->pluck('code_hash')->all();
            do {
                $plain = (string) random_int(100000, 999999);
                $hash = hash_hmac('sha256', $plain, (string) config('app.key'));
            } while (in_array($hash, $recentHashes, true));
            CustomerLoginCode::where('customer_id', $customer->id)->whereNull('consumed_at')
                ->update(['consumed_at' => now()]);
            $model = CustomerLoginCode::create([
                'public_id' => (string) Str::uuid(),
                'customer_id' => $customer->id,
                'code_hash' => $hash,
                'expires_at' => now()->addMinutes(10),
            ]);
            AuditLogger::record('customer.login_code_requested', $model, [], ['expires_at' => $model->expires_at]);
            return [$model, $customer->email, $plain];
        }, 3);
        if (!$delivery) {
            return;
        }

        [$loginCode, $recipient, $plain] = $delivery;

        app(LifecycleMailService::class)->queueSafely(
            LifecycleMailCatalog::BUYER_LOGIN_CODE,
            $recipient,
            ['code' => $plain, 'expires_in' => '10 minutes'],
            $loginCode
        );
    }

    public function consume(string $email, string $plain): Customer
    {
        $customer = DB::transaction(function () use ($email, $plain): ?Customer {
            $customer = $this->lockEligibleCustomer($email);
            if (!$customer) return null;
            $loginCode = CustomerLoginCode::where('customer_id', $customer->id)
                ->whereNull('consumed_at')->latest('id')->lockForUpdate()->first();
            if (!$loginCode || now()->gte($loginCode->expires_at)) return null;
            $attempts = CustomerLoginCode::where('customer_id', $customer->id)
                ->where('created_at', '>', now()->subMinutes(10))->sum('attempts');
            if ($attempts >= 5) return null;

            $loginCode->increment('attempts');
            if (!hash_equals($loginCode->code_hash, hash_hmac('sha256', trim($plain), (string) config('app.key')))) {
                return null;
            }
            $loginCode->forceFill(['consumed_at' => now()])->save();
            AuditLogger::record('customer.login_code_consumed', $loginCode, [], ['customer_id' => $customer->id]);
            return $customer;
        }, 3);
        if (!$customer) throw $this->invalid();
        return $customer;
    }

    private function lockEligibleCustomer(string $email): ?Customer
    {
        $customer = Customer::query()->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($email))])
            ->where('status', 1)->whereNotNull('email_verified_at')->lockForUpdate()->first();
        return $customer && $customer->two_factor_enabled_at === null ? $customer : null;
    }

    private function invalid(): ValidationException
    {
        return ValidationException::withMessages(['code' => 'The code is invalid or has expired. Request a new one.']);
    }
}
