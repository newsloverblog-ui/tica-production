<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Organizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Emails that exist as both a customer and an organiser, from before TICA
 * enforced one email = one account type. Nothing here merges or deletes on
 * its own: an admin decides per email, and every decision is audit-logged.
 */
class DuplicateAccountService
{
    public const KEEP_ORGANIZER = 'keep_organizer';
    public const KEEP_CUSTOMER = 'keep_customer';
    public const LEAVE = 'leave';

    /** @return array<int, array<string, mixed>> */
    public function find(): array
    {
        $query = DB::table('customers as c')
            ->join('organizers as o', DB::raw('LOWER(TRIM(c.email))'), '=', DB::raw('LOWER(TRIM(o.email))'))
            ->whereNotNull('c.email')->where('c.email', '!=', '')
            ->select([
                'c.id as customer_id', 'c.email as customer_email', 'c.created_at as customer_created',
                'c.provider as customer_provider', 'c.status as customer_status',
                'o.id as organizer_id', 'o.created_at as organizer_created', 'o.amount as organizer_balance',
                'o.status as organizer_status',
            ])
            ->orderBy('c.email');
        if (AccountTypeGuard::hasMigrationColumns()) {
            // Already linked by an upgrade or an earlier decision.
            $query->where(fn ($q) => $q->whereNull('c.migrated_to_organizer_id')->orWhereColumn('c.migrated_to_organizer_id', '!=', 'o.id'));
            $query->addSelect('o.provider as organizer_provider');
        }

        return $query->get()->map(fn ($row) => $this->describe($row))->all();
    }

    public function row(int $customerId, int $organizerId): array
    {
        foreach ($this->find() as $row) {
            if ($row['customer']['id'] === $customerId && $row['organizer']['id'] === $organizerId) {
                return $row;
            }
        }
        throw new RuntimeException(__('That duplicate is no longer open. Refresh the list.'));
    }

    public function resolve(int $customerId, int $organizerId, string $decision, ?int $adminId): string
    {
        $row = $this->row($customerId, $organizerId);
        $customer = Customer::query()->findOrFail($customerId);
        $organizer = Organizer::query()->findOrFail($organizerId);
        $audit = ['email' => $row['email'], 'customer_id' => $customerId, 'organizer_id' => $organizerId, 'admin_id' => $adminId];

        if ($decision === self::KEEP_ORGANIZER) {
            DB::transaction(fn () => app(OrganizerUpgradeService::class)->linkCustomer($customer, $organizer));
            $this->audit('duplicate_account.kept_organizer', $organizer, $audit);

            return __('Kept as an organizer. The customer history now shows under the organizer\'s My tickets, and the customer log-in is off.');
        }

        if ($decision === self::KEEP_CUSTOMER) {
            if (!$row['organizer']['can_retire']) {
                throw new RuntimeException(__('This organizer has events, a balance or payouts, so it must be kept.'));
            }
            DB::transaction(function () use ($organizer) {
                $organizer->forceFill([
                    'status' => 0,
                    'email' => 'duplicate+' . $organizer->id . '@removed.tica.invalid',
                ])->saveQuietly();
            });
            $this->audit('duplicate_account.kept_customer', $organizer, $audit + ['retired_organizer_email' => $row['email']]);

            return __('Kept as a customer. The empty organizer account was switched off and its email released.');
        }

        $this->audit('duplicate_account.left_as_is', $organizer, $audit);

        return __('Left as is. Nothing was changed.');
    }

    private function describe(object $row): array
    {
        $customerId = (int) $row->customer_id;
        $organizerId = (int) $row->organizer_id;
        $orders = $this->count('bookings', fn ($q) => $q->where('customer_id', $customerId));
        $tickets = Schema::hasTable('bookings')
            ? (int) DB::table('bookings')->where('customer_id', $customerId)->sum('quantity')
            : 0;
        $events = $this->count('events', fn ($q) => $q->where('organizer_id', $organizerId));
        $payouts = $this->count('withdraws', fn ($q) => $q->where('organizer_id', $organizerId));
        $balance = round((float) ($row->organizer_balance ?? 0), 2);

        return [
            'email' => AccountTypeGuard::normalize($row->customer_email),
            'customer' => [
                'id' => $customerId,
                'created_at' => $row->customer_created,
                'login' => filled($row->customer_provider) ? ucfirst((string) $row->customer_provider) : __('Email'),
                'active' => (int) $row->customer_status === 1,
                'orders' => $orders,
                'tickets' => $tickets,
            ],
            'organizer' => [
                'id' => $organizerId,
                'created_at' => $row->organizer_created,
                'login' => filled($row->organizer_provider ?? null) ? ucfirst((string) $row->organizer_provider) : __('Email or Google'),
                'active' => (int) $row->organizer_status === 1,
                'events' => $events,
                'balance' => $balance,
                'payouts' => $payouts,
                'can_retire' => $events === 0 && $payouts === 0 && abs($balance) < 0.005,
            ],
        ];
    }

    private function count(string $table, callable $where): int
    {
        return Schema::hasTable($table) ? (int) $where(DB::table($table))->count() : 0;
    }

    private function audit(string $action, Organizer $organizer, array $metadata): void
    {
        rescue(fn () => AuditLogger::record($action, $organizer, [], [], $metadata), null, false);
    }
}
