<?php

namespace App\Services\TicaUi;

use App\Models\Event\Booking;
use App\Models\Event\BookingTicket;
use App\Support\TicaCurrency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Period-scoped metrics for the organiser overview.
 *
 * The dashboard uses inclusive calendar-day windows: "Last 14 days" means
 * today plus the preceding 13 days. Free admissions count as tickets sold,
 * but only completed paid bookings contribute to gross sales.
 */
class OrganizerOverviewMetrics
{
    public function build(int $organizerId, array $eventIds, float $available): array
    {
        $fourteenDaysAgo = now()->subDays(13)->startOfDay();
        $thirtyDaysAgo = now()->subDays(29)->startOfDay();

        $gross = $this->grossSales($organizerId, $fourteenDaysAgo, $thirtyDaysAgo);
        $tickets = $this->ticketSales($eventIds, $fourteenDaysAgo, $thirtyDaysAgo);
        $views = $this->pageViews($eventIds, $fourteenDaysAgo, $thirtyDaysAgo);

        return collect(['14', '30', 'all'])->mapWithKeys(fn (string $range) => [
            $range => [
                'gross' => round((float) ($gross[$range] ?? 0), 2),
                'sold' => (int) ($tickets[$range] ?? 0),
                'views' => (int) ($views[$range] ?? 0),
                'available' => round($available, 2),
            ],
        ])->all();
    }

    private function grossSales(int $organizerId, $fourteenDaysAgo, $thirtyDaysAgo): array
    {
        $query = Booking::query()
            ->where('organizer_id', $organizerId)
            ->where('paymentStatus', 'completed');

        $this->baseCurrencyOnly($query);

        $row = $query->selectRaw(
            'COALESCE(SUM(price + COALESCE(tax, 0)), 0) as all_total, '
            .'COALESCE(SUM(CASE WHEN created_at >= ? THEN price + COALESCE(tax, 0) ELSE 0 END), 0) as d14, '
            .'COALESCE(SUM(CASE WHEN created_at >= ? THEN price + COALESCE(tax, 0) ELSE 0 END), 0) as d30',
            [$fourteenDaysAgo, $thirtyDaysAgo]
        )->first();

        return [
            '14' => (float) ($row?->d14 ?? 0),
            '30' => (float) ($row?->d30 ?? 0),
            'all' => (float) ($row?->all_total ?? 0),
        ];
    }

    private function ticketSales(array $eventIds, $fourteenDaysAgo, $thirtyDaysAgo): array
    {
        if ($eventIds === []) {
            return [];
        }

        $row = BookingTicket::query()
            ->whereIn('event_id', $eventIds)
            ->whereNull('revoked_at')
            ->selectRaw(
                'COUNT(*) as all_total, '
                .'SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as d14, '
                .'SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as d30',
                [$fourteenDaysAgo, $thirtyDaysAgo]
            )->first();

        return [
            '14' => (int) ($row?->d14 ?? 0),
            '30' => (int) ($row?->d30 ?? 0),
            'all' => (int) ($row?->all_total ?? 0),
        ];
    }

    private function pageViews(array $eventIds, $fourteenDaysAgo, $thirtyDaysAgo): array
    {
        if ($eventIds === [] || ! Schema::hasTable('funnel_analytics_events')) {
            return [];
        }

        $row = DB::table('funnel_analytics_events')
            ->where('event_name', 'view_event')
            ->whereIn('event_id', $eventIds)
            ->selectRaw(
                'COUNT(*) as all_total, '
                .'SUM(CASE WHEN occurred_at >= ? THEN 1 ELSE 0 END) as d14, '
                .'SUM(CASE WHEN occurred_at >= ? THEN 1 ELSE 0 END) as d30',
                [$fourteenDaysAgo, $thirtyDaysAgo]
            )->first();

        return [
            '14' => (int) ($row?->d14 ?? 0),
            '30' => (int) ($row?->d30 ?? 0),
            'all' => (int) ($row?->all_total ?? 0),
        ];
    }

    private function baseCurrencyOnly(Builder $query): void
    {
        $base = TicaCurrency::base();

        $query->where(fn (Builder $builder) => $builder
            ->whereNull('currencyText')
            ->orWhere('currencyText', '')
            ->orWhere('currencyText', $base));
    }
}
