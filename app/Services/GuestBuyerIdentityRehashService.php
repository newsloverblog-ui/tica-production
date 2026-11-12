<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class GuestBuyerIdentityRehashService
{
    public function __construct(
        private readonly EventTicketPurchaseLimitService $identities,
    ) {
    }

    public function inspect(): array
    {
        $this->assertSchema();
        $version = $this->identities->buyerIdentityHashVersion();
        $total = 0;
        $stale = 0;
        $invalid = 0;

        DB::table('bookings')
            ->select(['id', 'email', 'buyer_email_hash', 'buyer_email_hash_version'])
            ->where(fn (Builder $query) => $this->guest($query))
            ->orderBy('id')
            ->chunkById(500, function ($bookings) use (&$total, &$stale, &$invalid, $version): void {
                foreach ($bookings as $booking) {
                    $total++;
                    $expected = $this->identities->buyerIdentityHash((string) $booking->email);
                    if ($expected === null) {
                        $invalid++;
                        continue;
                    }
                    if ((int) $booking->buyer_email_hash_version !== $version
                        || !is_string($booking->buyer_email_hash)
                        || !hash_equals($expected, $booking->buyer_email_hash)) {
                        $stale++;
                    }
                }
            });

        $staleActiveReservations = DB::table('ticket_reservations')
            ->where(fn (Builder $query) => $this->guest($query))
            ->where('status', 'active')
            ->where(function (Builder $query) use ($version): void {
                $query->whereNull('buyer_email_hash')
                    ->orWhereNull('buyer_email_hash_version')
                    ->orWhere('buyer_email_hash_version', '<>', $version);
            })
            ->count();

        return [
            'version' => $version,
            'guest_bookings' => $total,
            'stale_bookings' => $stale,
            'invalid_guest_emails' => $invalid,
            'stale_active_reservations' => $staleActiveReservations,
        ];
    }

    public function rehash(): array
    {
        $report = $this->inspect();
        if ($report['invalid_guest_emails'] > 0 || $report['stale_active_reservations'] > 0) {
            throw new RuntimeException(
                'Guest buyer identities cannot be rehashed while invalid guest emails or stale active reservations remain.'
            );
        }

        $changed = DB::transaction(function () use ($report): int {
            $changed = 0;
            DB::table('bookings')
                ->select(['id', 'email', 'buyer_email_hash', 'buyer_email_hash_version'])
                ->where(fn (Builder $query) => $this->guest($query))
                ->orderBy('id')
                ->lockForUpdate()
                ->chunkById(500, function ($bookings) use (&$changed, $report): void {
                    foreach ($bookings as $booking) {
                        $expected = $this->identities->buyerIdentityHash((string) $booking->email);
                        if ($expected === null) {
                            throw new RuntimeException('A guest booking email became invalid during buyer identity rehash.');
                        }
                        if ((int) $booking->buyer_email_hash_version === $report['version']
                            && is_string($booking->buyer_email_hash)
                            && hash_equals($expected, $booking->buyer_email_hash)) {
                            continue;
                        }
                        DB::table('bookings')->where('id', $booking->id)->update([
                            'buyer_email_hash' => $expected,
                            'buyer_email_hash_version' => $report['version'],
                        ]);
                        $changed++;
                    }
                });

            return $changed;
        }, 3);

        return [...$this->inspect(), 'changed_bookings' => $changed];
    }

    private function guest(Builder $query): void
    {
        $query->whereNull('customer_id')->orWhere('customer_id', '');
    }

    private function assertSchema(): void
    {
        foreach (['bookings', 'ticket_reservations'] as $table) {
            if (!Schema::hasTable($table)) {
                throw new RuntimeException("The {$table} table is missing.");
            }
            foreach (['buyer_email_hash', 'buyer_email_hash_version'] as $column) {
                if (!Schema::hasColumn($table, $column)) {
                    throw new RuntimeException("The {$table}.{$column} column is missing. Run migrations first.");
                }
            }
        }
    }
}
