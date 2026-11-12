<?php

namespace App\Http\Controllers\BackEnd;

use App\Http\Controllers\Controller;
use App\Models\Event\EventContent;
use App\Models\FunnelAnalyticsEvent;
use App\Services\FunnelAnalyticsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class FunnelAnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'event_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $to = CarbonImmutable::parse($validated['to'] ?? now()->toDateString())->endOfDay();
        $from = CarbonImmutable::parse($validated['from'] ?? $to->subDays(29)->toDateString())->startOfDay();
        if ($from->gt($to)) {
            [$from, $to] = [$to->startOfDay(), $from->endOfDay()];
        }
        if ($from->diffInDays($to) > 89) {
            $from = $to->subDays(89)->startOfDay();
        }

        $base = FunnelAnalyticsEvent::query()
            ->whereBetween('occurred_at', [$from, $to])
            ->when($validated['event_id'] ?? null, fn ($query, $eventId) => $query->where('event_id', $eventId));

        $counts = (clone $base)
            ->selectRaw('event_name, COUNT(*) as aggregate')
            ->groupBy('event_name')
            ->pluck('aggregate', 'event_name')
            ->map(fn ($value) => (int) $value);

        $uniqueSessions = (clone $base)
            ->whereNotNull('session_hash')
            ->selectRaw('event_name, COUNT(DISTINCT session_hash) as aggregate')
            ->groupBy('event_name')
            ->pluck('aggregate', 'event_name')
            ->map(fn ($value) => (int) $value);

        $steps = collect(FunnelAnalyticsService::FUNNEL_EVENTS)->map(function (string $name, int $index) use ($counts) {
            $count = (int) ($counts[$name] ?? 0);
            $previousName = FunnelAnalyticsService::FUNNEL_EVENTS[$index - 1] ?? null;
            $previous = $previousName ? (int) ($counts[$previousName] ?? 0) : null;
            return [
                'name' => $name,
                'label' => str_replace('_', ' ', ucfirst($name)),
                'count' => $count,
                'step_rate' => $previous > 0 ? round(($count / $previous) * 100, 1) : null,
            ];
        });

        $topEvents = (clone $base)
            ->whereNotNull('event_id')
            ->selectRaw("event_id, SUM(CASE WHEN event_name = 'view_event' THEN 1 ELSE 0 END) as views, SUM(CASE WHEN event_name = 'begin_checkout' THEN 1 ELSE 0 END) as checkouts, SUM(CASE WHEN event_name = 'payment_success' THEN 1 ELSE 0 END) as purchases")
            ->groupBy('event_id')
            ->orderByDesc('purchases')
            ->orderByDesc('views')
            ->limit(20)
            ->get();

        $titles = EventContent::query()
            ->whereIn('event_id', $topEvents->pluck('event_id'))
            ->orderBy('id')
            ->get()
            ->unique('event_id')
            ->pluck('title', 'event_id');

        return view('backend.admin.funnel-analytics.index', compact(
            'from', 'to', 'counts', 'uniqueSessions', 'steps', 'topEvents', 'titles'
        ));
    }
}
