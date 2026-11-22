<?php

namespace App\Services\TicaUi;

use App\Models\Event\Booking;
use App\Models\Event\BookingTicket;
use App\Support\TicaCurrency;
use App\Support\EventFinancialBreakdown;
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
        $orders = $this->orderCounts($organizerId, $fourteenDaysAgo, $thirtyDaysAgo);
        // The window just before each period, so the overview can show change.
        $previous = [
            '14' => $this->window($organizerId, $eventIds, now()->subDays(27)->startOfDay(), $fourteenDaysAgo),
            '30' => $this->window($organizerId, $eventIds, now()->subDays(59)->startOfDay(), $thirtyDaysAgo),
        ];

        return collect(['14', '30', 'all'])->mapWithKeys(fn (string $range) => [
            $range => [
                'gross' => round((float) ($gross[$range] ?? 0), 2),
                'sold' => (int) ($tickets[$range] ?? 0),
                'views' => (int) ($views[$range] ?? 0),
                'orders' => (int) ($orders[$range] ?? 0),
                'available' => round($available, 2),
                'prev' => $previous[$range] ?? null,
            ],
        ])->all();
    }

    /** Totals for one closed window [$from, $to). */
    private function window(int $organizerId, array $eventIds, $from, $to): array
    {
        $paid = Booking::query()->where('organizer_id', $organizerId)->where('paymentStatus', 'completed')
            ->where('created_at', '>=', $from)->where('created_at', '<', $to);
        $this->baseCurrencyOnly($paid);
        $revenueSql = EventFinancialBreakdown::bookingOrganizerRevenueSql();
        $gross = (float) ((clone $paid)->selectRaw("COALESCE(SUM({$revenueSql}), 0) as total")->value('total') ?? 0);
        $orders = Booking::query()->where('organizer_id', $organizerId)->whereIn('paymentStatus', ['completed', 'free'])
            ->where('created_at', '>=', $from)->where('created_at', '<', $to)->count();
        $sold = $eventIds === [] ? 0 : BookingTicket::query()->whereIn('event_id', $eventIds)->whereNull('revoked_at')
            ->where('created_at', '>=', $from)->where('created_at', '<', $to)->count();
        $views = ($eventIds === [] || ! Schema::hasTable('funnel_analytics_events')) ? 0 : DB::table('funnel_analytics_events')
            ->where('event_name', 'view_event')->whereIn('event_id', $eventIds)
            ->where('occurred_at', '>=', $from)->where('occurred_at', '<', $to)->count();

        return ['gross' => round($gross, 2), 'sold' => $sold, 'views' => $views, 'orders' => $orders];
    }

    private function orderCounts(int $organizerId, $fourteenDaysAgo, $thirtyDaysAgo): array
    {
        $row = Booking::query()->where('organizer_id', $organizerId)->whereIn('paymentStatus', ['completed', 'free'])
            ->selectRaw(
                'COUNT(*) as all_total, '
                .'SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as d14, '
                .'SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as d30',
                [$fourteenDaysAgo, $thirtyDaysAgo]
            )->first();

        return ['14' => (int) ($row?->d14 ?? 0), '30' => (int) ($row?->d30 ?? 0), 'all' => (int) ($row?->all_total ?? 0)];
    }

    private function grossSales(int $organizerId, $fourteenDaysAgo, $thirtyDaysAgo): array
    {
        $query = Booking::query()
            ->where('organizer_id', $organizerId)
            ->where('paymentStatus', 'completed');

        $this->baseCurrencyOnly($query);

        $revenueSql = EventFinancialBreakdown::bookingOrganizerRevenueSql();
        $row = $query->selectRaw(
            "COALESCE(SUM({$revenueSql}), 0) as all_total, "
            ."COALESCE(SUM(CASE WHEN created_at >= ? THEN {$revenueSql} ELSE 0 END), 0) as d14, "
            ."COALESCE(SUM(CASE WHEN created_at >= ? THEN {$revenueSql} ELSE 0 END), 0) as d30",
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
