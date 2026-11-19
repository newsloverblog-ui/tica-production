<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Organizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * One email, one account type.
 *
 * TICA keeps attendees (customers) and organisers in separate tables with
 * separate log-ins. An email may belong to only one of them, so every place
 * that creates an account or changes an email asks this class first. Emails
 * are compared trimmed and case-insensitive; Gmail dots and +aliases are not
 * folded because the rest of TICA treats them as different addresses.
 *
 * A customer who was upgraded to an organiser keeps its row (for order
 * history) but no longer counts as a customer: its email is the organiser's.
 */
class AccountTypeGuard
{
    public const CUSTOMER = 'customer';
    public const ORGANIZER = 'organizer';

    public static function normalize(?string $email): string
    {
        return mb_strtolower(trim((string) $email));
    }

    /** An active (not upgraded) customer with this email. */
    public function customer(?string $email, ?int $ignoreId = null): ?Customer
    {
        $email = self::normalize($email);
        if ($email === '' || !Schema::hasTable('customers')) {
            return null;
        }

        return Customer::query()
            ->whereRaw('LOWER(TRIM(email)) = ?', [$email])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->when(self::hasMigrationColumns(), fn ($q) => $q->whereNull('migrated_to_organizer_id'))
            ->first();
    }

    public function organizer(?string $email, ?int $ignoreId = null): ?Organizer
    {
        $email = self::normalize($email);
        if ($email === '' || !Schema::hasTable('organizers')) {
            return null;
        }

        return Organizer::query()
            ->whereRaw('LOWER(TRIM(email)) = ?', [$email])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->first();
    }

    /** A customer whose account was upgraded to an organiser. */
    public function migratedCustomer(?string $email): ?Customer
    {
        $email = self::normalize($email);
        if ($email === '' || !self::hasMigrationColumns()) {
            return null;
        }

        return Customer::query()
            ->whereRaw('LOWER(TRIM(email)) = ?', [$email])
            ->whereNotNull('migrated_to_organizer_id')
            ->first();
    }

    /**
     * Why $email cannot be used for a $as account (customer or organizer),
     * or null when it is free for that type. Same-type uniqueness stays with
     * each form's own unique rule.
     *
     * @return array{type:string,message:string,login_url:string,login_label:string}|null
     */
    public function conflict(string $as, ?string $email): ?array
    {
        if ($as === self::ORGANIZER) {
            if (!$this->customer($email)) {
                return null;
            }
            $message = __('This email is registered as a customer account. Please log in as a customer.');
            if (self::upgradeEnabled()) {
                $message .= ' ' . __('You can upgrade to an organizer account from your customer dashboard.');
            }

            return [
                'type' => self::CUSTOMER,
                'message' => $message,
                'login_url' => self::loginUrl(self::CUSTOMER),
                'login_label' => __('Log in as a customer'),
            ];
        }

        if ($this->organizer($email)) {
            return [
                'type' => self::ORGANIZER,
                'message' => __('This email belongs to an organizer account. Please log in as an organizer.'),
                'login_url' => self::loginUrl(self::ORGANIZER),
                'login_label' => __('Log in as an organizer'),
            ];
        }

        return null;
    }

    public function message(string $as, ?string $email): ?string
    {
        return $this->conflict($as, $email)['message'] ?? null;
    }

    /** What a customer sees when they try to log in to an upgraded account. */
    public static function upgradedMessage(): string
    {
        return __('Your account has been upgraded to an organizer account. Log in as an organizer with the same email.');
    }

    public static function loginUrl(string $type): string
    {
        return url('/login?role=' . ($type === self::ORGANIZER ? 'org' : 'att'));
    }

    public static function upgradeEnabled(): bool
    {
        return (int) self::setting('customer_organizer_upgrade', 0) === 1;
    }

    public static function upgradeNeedsApproval(): bool
    {
        return (int) self::setting('customer_organizer_upgrade_approval', 1) === 1;
    }

    public static function hasMigrationColumns(): bool
    {
        static $has = null;
        if (app()->runningUnitTests()) {
            return Schema::hasTable('customers') && Schema::hasColumn('customers', 'migrated_to_organizer_id');
        }

        return $has ??= Schema::hasTable('customers') && Schema::hasColumn('customers', 'migrated_to_organizer_id');
    }

    private static function setting(string $column, int $default): int
    {
        try {
            if (!Schema::hasColumn('basic_settings', $column)) {
                return $default;
            }
            $value = DB::table('basic_settings')->where('uniqid', 12345)->value($column);
            if ($value === null) {
                $value = DB::table('basic_settings')->value($column);
            }

            return $value === null ? $default : (int) $value;
        } catch (Throwable) {
            return $default;
        }
    }
}
