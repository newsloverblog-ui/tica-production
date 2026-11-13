<?php

namespace App\Services;

use App\Contracts\PayoutProvider;
use App\Exceptions\GatewayOutcomeUnknownException;
use App\Exceptions\PayoutProviderUnavailable;
use App\Models\Organizer;
use App\Models\OrganizerPayoutAccount;
use App\Services\Payouts\BankDirectory;
use App\Services\Payouts\PaystackPayoutProvider;
use App\Services\Payouts\PayoutProviders;
use App\Support\LifecycleMailCatalog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class OrganizerPayoutAccountService
{
    /**
     * Save a payout account after the organiser confirmed it. The bank's name
     * must match the verified identity; if it doesn't, the account is kept as
     * "needs_review" for an admin to approve or reject, and isn't used.
     */
    public function verify(
        int $organizerId,
        string $bankCode,
        string $accountNumber,
        mixed $client = null
    ): OrganizerPayoutAccount {
        $organizer = Organizer::findOrFail($organizerId);
        if ($organizer->kyc_status !== 'verified' || trim((string) $organizer->verified_legal_name) === '') {
            throw new RuntimeException('Complete organizer verification before adding a payout account.');
        }

        $provider = $this->provider($client);
        $looked = $this->lookup($bankCode, $accountNumber, $provider);
        $matched = $this->namesMatch($organizer->verified_legal_name, $looked['name']);
        if (!$matched) {
            AuditLogger::record('organizer.payout_account_name_mismatch', $organizer, [], [], [
                'bank_code' => $bankCode,
                'account_last4' => substr($accountNumber, -4),
                'resolved_name' => $looked['name'],
            ]);
        }

        try {
            $account = DB::transaction(function () use ($organizerId, $looked, $bankCode, $accountNumber, $matched, $provider) {
                $organizer = Organizer::whereKey($organizerId)->lockForUpdate()->firstOrFail();
                if ($organizer->kyc_status !== 'verified') {
                    throw new RuntimeException('Organizer verification changed. Verify the organizer again before continuing.');
                }

                // A mismatched account waits for review and leaves the current account in place.
                if ($matched) {
                    OrganizerPayoutAccount::where('organizer_id', $organizerId)
                        ->where('is_default', true)
                        ->update(['is_default' => false, 'status' => 'replaced']);
                }
                OrganizerPayoutAccount::where('organizer_id', $organizerId)->where('status', 'needs_review')
                    ->update(['status' => 'replaced']);

                $account = OrganizerPayoutAccount::create([
                    'public_id' => (string) Str::uuid(),
                    'organizer_id' => $organizerId,
                    'provider' => $provider->name(),
                    'country_code' => 'NG',
                    'currency' => 'NGN',
                    'bank_code' => $bankCode,
                    'bank_name' => $looked['bank'],
                    'account_number' => $accountNumber,
                    'account_number_hash' => $this->accountHash($bankCode, $accountNumber),
                    'account_last4' => substr($accountNumber, -4),
                    'resolved_account_name' => $looked['name'],
                    'name_match_status' => $matched ? 'matched' : 'mismatch',
                    'status' => $matched ? 'verified' : 'needs_review',
                    'is_default' => $matched,
                    'verified_at' => $matched ? now() : null,
                    'hold_until' => now()->addHours(24),
                ]);

                AuditLogger::record($matched ? 'organizer.payout_account_verified' : 'organizer.payout_account_needs_review', $account, [], [
                    'status' => $account->status,
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

        if ($account->status === 'verified') {
            $this->registerRecipient($account, $provider);
        }

        return $account;
    }

    /**
     * Create the provider's transfer recipient now, so the first payout doesn't
     * wait on it. If the provider is down this is retried at transfer time.
     */
    public function registerRecipient(OrganizerPayoutAccount $account, ?PayoutProvider $provider = null): void
    {
        if ($account->provider_recipient_code) {
            return;
        }
        try {
            $provider ??= app(PayoutProviders::class)->for((string) $account->provider);
            $code = $provider->createRecipient($account->resolved_account_name, $account->account_number, $account->bank_code, $account->currency);
            $account->forceFill(['provider_recipient_code' => $code, 'recipient_created_at' => now()])->save();
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /** Admin decision on an account whose bank name didn't match. */
    public function review(string $publicId, bool $approve, int $adminId, ?string $reason = null): OrganizerPayoutAccount
    {
        $account = DB::transaction(function () use ($publicId, $approve, $adminId, $reason) {
            $account = OrganizerPayoutAccount::where('public_id', $publicId)->lockForUpdate()->firstOrFail();
            if ($account->status !== 'needs_review') {
                throw new RuntimeException('This account is no longer waiting for review.');
            }
            if (!$approve && trim((string) $reason) === '') {
                throw new RuntimeException('Give a reason so the organiser knows what to fix.');
            }
            if ($approve) {
                OrganizerPayoutAccount::where('organizer_id', $account->organizer_id)->where('is_default', true)
                    ->update(['is_default' => false, 'status' => 'replaced']);
            }
            $account->forceFill([
                'status' => $approve ? 'verified' : 'rejected',
                'is_default' => $approve,
                'verified_at' => $approve ? now() : null,
                // A fresh 24-hour hold starts when the account becomes usable.
                'hold_until' => $approve ? now()->addHours(24) : $account->hold_until,
                'review_reason' => $reason,
                'reviewed_by_admin_id' => $adminId,
                'reviewed_at' => now(),
            ])->save();
            AuditLogger::record($approve ? 'organizer.payout_account_review_approved' : 'organizer.payout_account_review_rejected',
                $account, ['status' => 'needs_review'], ['status' => $account->status, 'reason' => $reason]);

            return $account;
        }, 3);

        if ($approve) {
            $this->registerRecipient($account);
        }
        $organizer = Organizer::find($account->organizer_id);
        if ($organizer) {
            app(LifecycleMailService::class)->queueSafely(
                LifecycleMailCatalog::ORGANIZER_PAYOUT_ACCOUNT_CHANGED,
                $organizer->email,
                [
                    'bank_name' => $account->bank_name,
                    'account_last4' => $account->maskedNumber(),
                    'changed_at' => now()->format('D, j M Y g:i A'),
                    'review_outcome' => $approve ? 'approved' : 'rejected',
                    'review_reason' => $reason,
                    'account_url' => url('/dashboard/payouts'),
                ],
                $account
            );
        }

        return $account;
    }

    /** Ask the bank again for the name on an existing account (admin "re-verify"). */
    public function reverify(string $publicId): array
    {
        $account = OrganizerPayoutAccount::where('public_id', $publicId)->firstOrFail();
        $looked = $this->lookup($account->bank_code, $account->account_number, app(PayoutProviders::class)->for((string) $account->provider));
        $organizer = Organizer::find($account->organizer_id);
        $matches = $organizer && $this->namesMatch((string) $organizer->verified_legal_name, $looked['name']);
        AuditLogger::record('organizer.payout_account_reverified', $account, [], [
            'resolved_name' => $looked['name'], 'matches' => $matches,
        ]);

        return ['name' => $looked['name'], 'matches' => $matches, 'changed' => $looked['name'] !== $account->resolved_account_name];
    }

    /**
     * Ask the bank for the name on an account. A slow or unavailable provider
     * is reported as such, never as "account not found".
     */
    public function lookup(string $bankCode, string $accountNumber, mixed $client = null): array
    {
        $provider = $this->provider($client);
        $bank = app(BankDirectory::class)->find($bankCode, null, $provider);
        try {
            $resolved = $provider->resolveAccount($accountNumber, $bankCode);
        } catch (ConnectionException|GatewayOutcomeUnknownException $exception) {
            report($exception);
            throw new PayoutProviderUnavailable('Your bank is taking too long to answer. Try again in a minute.', 0, $exception);
        } catch (Throwable $exception) {
            throw new RuntimeException('We couldn\'t find this account at that bank. Check the number and the bank.', 0, $exception);
        }
        $number = $resolved['account_number'];
        $name = $resolved['account_name'];
        if ($name === '' || ($number !== '' && $number !== $accountNumber)) {
            throw new RuntimeException('We couldn\'t find this account at that bank. Check the number and the bank.');
        }

        return ['bank' => $bank['name'], 'name' => $name];
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

    /** @return array<int, array{code: string, name: string}> */
    public function banks(): array
    {
        return app(BankDirectory::class)->list()['banks'];
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

    /** Accepts a provider, a legacy Paystack client (tests), or nothing for the admin's choice. */
    private function provider(mixed $client): PayoutProvider
    {
        if ($client instanceof PayoutProvider) {
            return $client;
        }
        if ($client instanceof PaystackGatewayClient) {
            return new PaystackPayoutProvider($client);
        }

        return app(PayoutProviders::class)->current();
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
