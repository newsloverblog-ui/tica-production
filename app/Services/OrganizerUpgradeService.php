<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Organizer;
use App\Models\OrganizerInfo;
use App\Models\OrganizerUpgradeRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Turns a customer account into an organiser account.
 *
 * The customer row is never deleted: its bookings, tickets and wishlist keep
 * pointing at it, and the organiser reads them through
 * customers.migrated_to_organizer_id. After the move the email counts as an
 * organiser email everywhere (see AccountTypeGuard) and the customer log-in
 * is switched off.
 */
class OrganizerUpgradeService
{
    public function __construct(private readonly AccountTypeGuard $guard)
    {
    }

    public static function available(): bool
    {
        return AccountTypeGuard::upgradeEnabled() && Schema::hasTable('organizer_upgrade_requests');
    }

    public function pending(Customer $customer): ?OrganizerUpgradeRequest
    {
        if (!Schema::hasTable('organizer_upgrade_requests')) {
            return null;
        }

        return OrganizerUpgradeRequest::query()
            ->where('customer_id', $customer->id)
            ->where('status', OrganizerUpgradeRequest::PENDING)
            ->latest('id')
            ->first();
    }

    /** What the customer header shows: an upgrade button, a "under review" note, or nothing. */
    public function headerState(Customer $customer): ?array
    {
        if (!self::available() || $customer->migrated_to_organizer_id) {
            return null;
        }
        if ($this->pending($customer)) {
            return ['state' => 'pending', 'label' => __('Your organizer request is under review')];
        }

        return ['state' => 'available', 'label' => __('Become an Organizer'), 'url' => route('customer.organizer_upgrade.show')];
    }

    /**
     * @param array{organisation:string,contact_name:string,phone:string} $data
     * @return array{status:string,request:OrganizerUpgradeRequest,organizer:?Organizer}
     */
    public function submit(Customer $customer, array $data): array
    {
        if (!self::available()) {
            throw new RuntimeException(__('Organizer upgrades are not available right now.'));
        }
        if ($customer->migrated_to_organizer_id) {
            throw new RuntimeException(AccountTypeGuard::upgradedMessage());
        }
        if ($this->pending($customer)) {
            throw new RuntimeException(__('Your organizer request is already under review.'));
        }
        if ($this->guard->organizer($customer->email)) {
            throw new RuntimeException(__('An organizer account already uses this email. Contact support so we can link your accounts.'));
        }

        $request = OrganizerUpgradeRequest::create([
            'customer_id' => $customer->id,
            'organisation' => $data['organisation'],
            'contact_name' => $data['contact_name'],
            'phone' => $data['phone'],
            'status' => OrganizerUpgradeRequest::PENDING,
        ]);
        $this->audit('organizer_upgrade.requested', $request, ['customer_id' => $customer->id]);

        if (!AccountTypeGuard::upgradeNeedsApproval()) {
            $organizer = $this->migrate($request, null);
            $this->mail('organizer_upgrade_approved', $request, $customer);

            return ['status' => 'upgraded', 'request' => $request->fresh(), 'organizer' => $organizer];
        }

        $this->mail('organizer_upgrade_received', $request, $customer);
        rescue(fn () => app(MailTemplateService::class)->queueAdminSafely('admin_organizer_upgrade_request', [
            'customer_name' => $this->name($customer),
            'customer_email' => $customer->email,
            'organisation' => $request->organisation,
            'phone' => $request->phone,
            'admin_link' => route('admin.organizer_upgrades.index'),
        ], $request, 'admin-organizer-upgrade:' . $request->id), null, false);
        \App\Support\AdminQueues::forget();

        return ['status' => 'pending', 'request' => $request, 'organizer' => null];
    }

    public function approve(OrganizerUpgradeRequest $request, int $adminId): Organizer
    {
        if ($request->status !== OrganizerUpgradeRequest::PENDING) {
            throw new RuntimeException(__('This request has already been decided.'));
        }
        $organizer = $this->migrate($request, $adminId);
        $this->mail('organizer_upgrade_approved', $request->fresh(), $request->customer);
        \App\Support\AdminQueues::forget();

        return $organizer;
    }

    public function reject(OrganizerUpgradeRequest $request, string $reason, int $adminId): void
    {
        $updated = OrganizerUpgradeRequest::query()
            ->whereKey($request->id)
            ->where('status', OrganizerUpgradeRequest::PENDING)
            ->update([
                'status' => OrganizerUpgradeRequest::REJECTED,
                'reason' => $reason,
                'reviewed_by' => $adminId,
                'reviewed_at' => now(),
                'updated_at' => now(),
            ]);
        if (!$updated) {
            throw new RuntimeException(__('This request has already been decided.'));
        }
        $request->refresh();
        $this->audit('organizer_upgrade.rejected', $request, ['reason' => $reason]);
        $this->mail('organizer_upgrade_rejected', $request, $request->customer);
        \App\Support\AdminQueues::forget();
    }

    /**
     * Creates the organiser from the customer and switches the customer
     * log-in off, all in one transaction.
     */
    public function migrate(OrganizerUpgradeRequest $request, ?int $adminId): Organizer
    {
        return DB::transaction(function () use ($request, $adminId) {
            $locked = OrganizerUpgradeRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== OrganizerUpgradeRequest::PENDING) {
                throw new RuntimeException(__('This request has already been decided.'));
            }
            $customer = Customer::query()->whereKey($locked->customer_id)->lockForUpdate()->firstOrFail();
            if ($customer->migrated_to_organizer_id) {
                throw new RuntimeException(AccountTypeGuard::upgradedMessage());
            }
            $email = AccountTypeGuard::normalize($customer->email);
            if ($this->guard->organizer($email)) {
                throw new RuntimeException(__('An organizer account already uses this email. Resolve it from Duplicate accounts.'));
            }

            $organizer = new Organizer();
            $organizer->forceFill([
                'username' => $this->uniqueUsername($locked->organisation),
                'email' => $email,
                'phone' => $locked->phone,
                // The same password hash: the person keeps the password they know.
                'password' => (string) $customer->getAuthPassword(),
                'status' => 1,
                'email_verified_at' => $customer->email_verified_at ?: now(),
                'provider' => $customer->provider ?: null,
                'provider_id' => $customer->provider_id ?: null,
                'migrated_from_customer_id' => $customer->id,
            ])->save();

            OrganizerInfo::create([
                'organizer_id' => $organizer->id,
                'language_id' => (int) DB::table('languages')->where('is_default', 1)->value('id') ?: null,
                'name' => mb_substr($locked->organisation, 0, 100),
                'designation' => mb_substr($locked->contact_name, 0, 255),
                'country' => $customer->country ?: null,
                'city' => $customer->city ?: null,
                'state' => $customer->state ?: null,
            ]);

            $this->linkCustomer($customer, $organizer);

            $locked->forceFill([
                'status' => OrganizerUpgradeRequest::APPROVED,
                'organizer_id' => $organizer->id,
                'reviewed_by' => $adminId,
                'reviewed_at' => now(),
            ])->save();

            $this->audit('organizer_upgrade.migrated', $locked, [
                'customer_id' => $customer->id,
                'organizer_id' => $organizer->id,
                'approved_by' => $adminId,
            ]);

            return $organizer;
        });
    }

    /**
     * Marks a customer as moved to an organiser and switches its log-in off.
     * Saved quietly: this is not a suspension, so no "account suspended" email.
     */
    public function linkCustomer(Customer $customer, Organizer $organizer): void
    {
        $customer->forceFill([
            'migrated_to_organizer_id' => $organizer->id,
            'migrated_at' => now(),
            'status' => 0,
        ])->saveQuietly();
    }

    private function uniqueUsername(string $seed): string
    {
        $base = Str::limit(Str::slug($seed, '_') ?: 'organiser', 40, '');
        $reserved = (string) DB::table('admins')->value('username');
        $username = $base;
        for ($i = 2; DB::table('organizers')->where('username', $username)->exists() || $username === $reserved; $i++) {
            $username = $base . '_' . $i;
        }

        return $username;
    }

    private function mail(string $type, OrganizerUpgradeRequest $request, ?Customer $customer): void
    {
        if (!$customer) {
            return;
        }
        rescue(fn () => app(MailTemplateService::class)->queueSafely($type, $customer->email, [
            'customer_name' => $this->name($customer),
            'organisation' => $request->organisation,
            'reason' => $request->reason,
            'dashboard_link' => url('/account'),
            'login_link' => AccountTypeGuard::loginUrl(AccountTypeGuard::ORGANIZER),
        ], $request, $type . ':' . $request->id), null, false);
    }

    private function name(Customer $customer): string
    {
        return trim((string) $customer->fname . ' ' . (string) $customer->lname) ?: ((string) $customer->username ?: 'there');
    }

    private function audit(string $action, OrganizerUpgradeRequest $request, array $metadata): void
    {
        try {
            AuditLogger::record($action, $request, [], ['status' => $request->status], $metadata);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
