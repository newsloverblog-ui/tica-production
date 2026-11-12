<?php

namespace App\Services;

use App\Models\Organizer;
use App\Models\OrganizerPayoutAccount;
use App\Support\LifecycleMailCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class OrganizerPayoutAccountService
{
    public function verify(
        int $organizerId,
        string $bankCode,
        string $accountNumber,
        ?PaystackGatewayClient $client = null
    ): OrganizerPayoutAccount {
        $organizer = Organizer::findOrFail($organizerId);
        if ($organizer->kyc_status !== 'verified' || trim((string) $organizer->verified_legal_name) === '') {
            throw new RuntimeException('Complete organizer verification before adding a payout account.');
        }

        $client = $client ?: new PaystackGatewayClient();
        $bank = $this->bankByCode($client, $bankCode);
        $resolved = $client->resolveAccount($accountNumber, $bankCode);
        $resolvedData = (array) ($resolved['data'] ?? []);
        $resolvedNumber = preg_replace('/\D+/', '', (string) ($resolvedData['account_number'] ?? ''));
        $resolvedName = trim((string) ($resolvedData['account_name'] ?? ''));
        if ($resolvedNumber !== $accountNumber || $resolvedName === '') {
            throw new RuntimeException('The bank returned incomplete or inconsistent account details.');
        }
        if (!$this->namesMatch($organizer->verified_legal_name, $resolvedName)) {
            AuditLogger::record('organizer.payout_account_name_mismatch', $organizer, [], [], [
                'bank_code' => $bankCode,
                'account_last4' => substr($accountNumber, -4),
                'resolved_name' => $resolvedName,
            ]);
            throw new RuntimeException(
                'The bank account name does not match the verified organizer name. Contact support if this is a legitimate business account.'
            );
        }

        try {
            return DB::transaction(function () use ($organizerId, $bank, $bankCode, $accountNumber, $resolvedName) {
                $organizer = Organizer::whereKey($organizerId)->lockForUpdate()->firstOrFail();
                if ($organizer->kyc_status !== 'verified') {
                    throw new RuntimeException('Organizer verification changed. Verify the organizer again before continuing.');
                }

                OrganizerPayoutAccount::where('organizer_id', $organizerId)
                    ->where('is_default', true)
                    ->update(['is_default' => false, 'status' => 'replaced']);

                $account = OrganizerPayoutAccount::create([
                    'public_id' => (string) Str::uuid(),
                    'organizer_id' => $organizerId,
                    'provider' => 'paystack',
                    'country_code' => 'NG',
                    'currency' => 'NGN',
                    'bank_code' => $bankCode,
                    'bank_name' => (string) ($bank['name'] ?? $bankCode),
                    'account_number' => $accountNumber,
                    'account_number_hash' => $this->accountHash($bankCode, $accountNumber),
                    'account_last4' => substr($accountNumber, -4),
                    'resolved_account_name' => $resolvedName,
                    'name_match_status' => 'matched',
                    'status' => 'verified',
                    'is_default' => true,
                    'verified_at' => now(),
                    'hold_until' => now()->addHours(24),
                ]);

                AuditLogger::record('organizer.payout_account_verified', $account, [], [
                    'status' => 'verified',
                    'bank_name' => $account->bank_name,
                    'account_last4' => $account->account_last4,
                ]);

                DB::afterCommit(fn () => $this->notifyChanged($account, $organizer));

                return $account;
            }, 3);
        } catch (Throwable $exception) {
            if ($this->isDuplicateDestination($exception)) {
                throw new RuntimeException('This payout account is already registered. Contact support if you own it.');
            }
            throw $exception;
        }
    }

    /** Ask the bank for the name on an account. Returns the bank and the account name. */
    public function lookup(string $bankCode, string $accountNumber, ?PaystackGatewayClient $client = null): array
    {
        $client = $client ?: new PaystackGatewayClient();
        $bank = $this->bankByCode($client, $bankCode);
        try {
            $resolved = (array) ($client->resolveAccount($accountNumber, $bankCode)['data'] ?? []);
        } catch (Throwable $exception) {
            throw new RuntimeException('We couldn\'t find this account at that bank. Check the number and the bank.');
        }
        $number = preg_replace('/\D+/', '', (string) ($resolved['account_number'] ?? ''));
        $name = trim((string) ($resolved['account_name'] ?? ''));
        if ($name === '' || ($number !== '' && $number !== $accountNumber)) {
            throw new RuntimeException('We couldn\'t find this account at that bank. Check the number and the bank.');
        }

        return ['bank' => (string) ($bank['name'] ?? $bankCode), 'name' => $name];
    }

    /** True when the bank's account name matches any of the names the organiser gave. */
    public function matchesAny(array $names, string $bankName): bool
    {
        foreach ($names as $name) {
            if (trim((string) $name) !== '' && $this->namesMatch((string) $name, $bankName)) return true;
        }
        return false;
    }

    /**
     * Keep an account given during identity verification. It is not used for
     * payouts until the identity check is approved and the name still matches.
     */
    public function stage(int $organizerId, array $names, string $bankCode, string $accountNumber, ?array $looked = null): OrganizerPayoutAccount
    {
        $looked = $looked ?: $this->lookup($bankCode, $accountNumber);
        if (!$this->matchesAny($names, $looked['name'])) {
            throw new RuntimeException('The bank says this account belongs to ' . $looked['name'] . '. Use an account in the name you entered.');
        }
        $hash = $this->accountHash($bankCode, $accountNumber);

        try {
            return DB::transaction(function () use ($organizerId, $bankCode, $accountNumber, $looked, $hash) {
                Organizer::whereKey($organizerId)->lockForUpdate()->firstOrFail();
                OrganizerPayoutAccount::where('organizer_id', $organizerId)->where('status', 'pending_kyc')
                    ->where('account_number_hash', '!=', $hash)->delete();
                $values = [
                    'organizer_id' => $organizerId,
                    'provider' => 'paystack',
                    'country_code' => 'NG',
                    'currency' => 'NGN',
                    'bank_code' => $bankCode,
                    'bank_name' => $looked['bank'],
                    'account_number' => $accountNumber,
                    'account_number_hash' => $hash,
                    'account_last4' => substr($accountNumber, -4),
                    'resolved_account_name' => $looked['name'],
                    'name_match_status' => 'matched',
                    'status' => 'pending_kyc',
                    'is_default' => false,
                    'verified_at' => null,
                ];
                $own = OrganizerPayoutAccount::where('organizer_id', $organizerId)->where('provider', 'paystack')
                    ->where('bank_code', $bankCode)->where('account_number_hash', $hash)->lockForUpdate()->first();
                if ($own && in_array($own->status, ['verified', 'locked'], true)) {
                    throw new RuntimeException('This account is already your payout account.');
                }
                $account = $own ? tap($own)->update($values) : OrganizerPayoutAccount::create($values + ['public_id' => (string) Str::uuid()]);
                AuditLogger::record('organizer.payout_account_staged', $account, [], [
                    'status' => 'pending_kyc', 'bank_name' => $account->bank_name, 'account_last4' => $account->account_last4,
                ]);

                return $account;
            }, 3);
        } catch (Throwable $exception) {
            if ($this->isDuplicateDestination($exception)) {
                throw new RuntimeException('This payout account is already registered. Contact support if you own it.');
            }
            throw $exception;
        }
    }

    /**
     * Called inside the identity approval. The waiting account becomes the payout
     * account only if its bank name matches the approved names.
     */
    public function activateStaged(Organizer $organizer, array $names): ?OrganizerPayoutAccount
    {
        $account = OrganizerPayoutAccount::where('organizer_id', $organizer->id)->where('status', 'pending_kyc')
            ->latest('id')->lockForUpdate()->first();
        if (!$account) return null;

        if (!$this->matchesAny($names, (string) $account->resolved_account_name)) {
            $account->forceFill(['status' => 'name_mismatch', 'name_match_status' => 'mismatch'])->save();
            AuditLogger::record('organizer.payout_account_name_mismatch', $account, ['status' => 'pending_kyc'], ['status' => 'name_mismatch']);
            return null;
        }

        OrganizerPayoutAccount::where('organizer_id', $organizer->id)->where('is_default', true)
            ->update(['is_default' => false, 'status' => 'replaced']);
        $account->forceFill([
            'status' => 'verified',
            'is_default' => true,
            'name_match_status' => 'matched',
            'verified_at' => now(),
            'hold_until' => now()->addHours(24),
        ])->save();
        AuditLogger::record('organizer.payout_account_verified', $account, ['status' => 'pending_kyc'], [
            'status' => 'verified', 'bank_name' => $account->bank_name, 'account_last4' => $account->account_last4,
        ]);
        DB::afterCommit(fn () => $this->notifyChanged($account, $organizer));

        return $account;
    }

    public function lock(string $publicId): OrganizerPayoutAccount
    {
        return DB::transaction(function () use ($publicId): OrganizerPayoutAccount {
            $account = OrganizerPayoutAccount::where('public_id', $publicId)->lockForUpdate()->firstOrFail();
            if ($account->locked_at) return $account;

            $before = ['status' => $account->status, 'is_default' => (bool) $account->is_default];
            $account->forceFill([
                'status' => 'locked',
                'is_default' => false,
                'locked_at' => now(),
            ])->save();
            AuditLogger::record('organizer.payout_account_locked', $account, $before, [
                'status' => 'locked', 'is_default' => false,
            ]);

            return $account;
        }, 3);
    }

    public function banks(?PaystackGatewayClient $client = null): array
    {
        $payload = ($client ?: new PaystackGatewayClient())->listBanks('nigeria', 'NGN');
        return collect((array) ($payload['data'] ?? []))
            ->filter(fn ($bank) => !empty($bank['code']) && !empty($bank['name']))
            ->sortBy(fn ($bank) => strtolower((string) $bank['name']))
            ->values()->all();
    }

    public function namesMatch(string $verifiedName, string $bankName): bool
    {
        $left = $this->nameTokens($verifiedName);
        $right = $this->nameTokens($bankName);
        if ($left === [] || $right === []) return false;
        if ($left === $right) return true;

        $shorter = count($left) <= count($right) ? $left : $right;
        $longer = count($left) <= count($right) ? $right : $left;
        $shared = array_values(array_intersect($shorter, $longer));
        if (count($shorter) === 1) return count($shared) === 1;

        return count($shared) >= 2 && count($shared) / count($shorter) >= 0.75;
    }

    private function bankByCode(PaystackGatewayClient $client, string $bankCode): array
    {
        foreach ($this->banks($client) as $bank) {
            if (hash_equals((string) $bank['code'], $bankCode)) return $bank;
        }
        throw new RuntimeException('Select a bank returned by the payout provider.');
    }

    private function accountHash(string $bankCode, string $accountNumber): string
    {
        return hash_hmac('sha256', $bankCode . ':' . $accountNumber, (string) config('app.key'));
    }

    private function nameTokens(string $name): array
    {
        $normalized = strtoupper((string) preg_replace('/[^A-Z0-9]+/i', ' ', $name));
        $ignored = ['LIMITED', 'LTD', 'PLC', 'LLC', 'INC', 'ENTERPRISES', 'ENTERPRISE', 'COMPANY', 'CO'];
        $tokens = array_filter(explode(' ', trim($normalized)), function ($token) use ($ignored) {
            return strlen($token) > 1 && !in_array($token, $ignored, true);
        });
        $tokens = array_values(array_unique($tokens));
        sort($tokens);
        return $tokens;
    }

    private function isDuplicateDestination(Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());
        return str_contains($message, 'payout_account_destination_unique')
            || str_contains($message, 'unique constraint failed');
    }

    private function notifyChanged(OrganizerPayoutAccount $account, Organizer $organizer): void
    {
        $lockUrl = Route::has('organizer.payout-account.lock.confirm')
            ? URL::temporarySignedRoute(
                'organizer.payout-account.lock.confirm',
                now()->addHours(48),
                ['account' => $account->public_id]
            )
            : url('/organizer/payout-account/' . $account->public_id . '/lock');
        app(LifecycleMailService::class)->queueSafely(
            LifecycleMailCatalog::ORGANIZER_PAYOUT_ACCOUNT_CHANGED,
            $organizer->email,
            [
                'bank_name' => $account->bank_name,
                'account_last4' => $account->maskedNumber(),
                'changed_at' => $account->created_at?->format('D, j M Y g:i A'),
                'lock_url' => $lockUrl,
                'account_url' => url('/organizer/kyc'),
            ],
            $account
        );
    }
}
