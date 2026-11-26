<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Event\EventContent;
use App\Models\FunnelAnalyticsEvent;
use App\Models\Language;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class PageTrafficAnalytics
{
    private const PAGES = [
        'home' => ['Home', '/'],
        'discover' => ['Discover events', '/discover'],
        'event' => ['Event pages', null],
        'how' => ['How TICA works', '/how'],
        'pricing' => ['Pricing', '/pricing'],
        'about' => ['About us', '/about'],
        'contact' => ['Contact', '/contact'],
        'join' => ['Join TICA', '/join'],
        'policies' => ['Policies', '/policies'],
        'content' => ['Articles and website pages', null],
    ];

    public function record(Request $request, array $properties, ?string $sessionHash): FunnelAnalyticsEvent
    {
        $attributes = [
            'event_name' => 'page_view',
            'source' => 'browser',
            'session_hash' => $sessionHash,
            'occurred_at' => now(),
        ];

        $page = $properties['page'] ?? null;
        if (!is_string($page) || !isset(self::PAGES[$page])) {
            return FunnelAnalyticsEvent::create($attributes);
        }

        $details = [
            'page_key' => $page,
            'referrer_host' => $this->referrer($properties['referrer_host'] ?? null, $request->getHost()),
        ];
        $eventId = filter_var($properties['event_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($page === 'event' && $eventId && $this->publicEvents()->whereKey($eventId)->exists()) {
            $details['event_id'] = $eventId;
        }

        try {
            return FunnelAnalyticsEvent::create($attributes + $details);
        } catch (QueryException $exception) {
            // During rollout the previous schema still accepts aggregate page views.
            if ($this->hasDetails()) {
                throw $exception;
            }

            return FunnelAnalyticsEvent::create($attributes);
        }
    }

    public function report(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $visits = FunnelAnalyticsEvent::query()->pageViews()
            ->where('occurred_at', '>=', $from)
            ->where('occurred_at', '<', $to->addDay());

        $totals = (clone $visits)->toBase()
            ->selectRaw('COUNT(*) AS views, COUNT(DISTINCT session_hash) AS sessions, COUNT(session_hash) AS session_views')
            ->first();

        $daily = (clone $visits)->toBase()
            ->selectRaw('DATE(occurred_at) AS day, COUNT(*) AS views, COUNT(DISTINCT session_hash) AS sessions')
            ->groupByRaw('DATE(occurred_at)')
            ->get()
            ->keyBy('day');

        $days = [];
        for ($date = $from; $date->lte($to); $date = $date->addDay()) {
            $row = $daily->get($date->toDateString());
            $days[] = [
                'date' => $date->toDateString(),
                'label' => $date->translatedFormat('j M'),
                'views' => (int) ($row?->views ?? 0),
                'sessions' => (int) ($row?->sessions ?? 0),
            ];
        }

        $hasDetails = $this->hasDetails();
        $rankedVisits = (clone $visits)->whereNotNull('page_key');

        return [
            'views' => (int) $totals->views,
            'sessions' => (int) $totals->sessions,
            'viewsPerSession' => $totals->sessions ? $totals->session_views / $totals->sessions : null,
            'days' => $days,
            'pages' => $hasDetails ? $this->pages(clone $rankedVisits) : [],
            'referrers' => $hasDetails ? $this->referrers($rankedVisits) : [],
        ];
    }

    private function pages(Builder $visits): array
    {
        $rows = $visits->toBase()->select('page_key', 'event_id')->selectRaw('COUNT(*) AS views')
            ->groupBy('page_key', 'event_id')
            ->orderByDesc('views')->orderBy('page_key')->orderBy('event_id')
            ->limit(10)->get();

        $eventIds = $rows->where('page_key', 'event')->pluck('event_id')->filter();
        $events = $eventIds->isEmpty() ? collect() : EventContent::query()
            ->whereIn('event_id', $this->publicEvents()->whereKey($eventIds->all())->select('events.id'))
            ->where('language_id', Language::query()->where('is_default', 1)->value('id'))
            ->orderBy('id')
            ->get(['event_id', 'title', 'slug'])
            ->unique('event_id')->keyBy('event_id');

        return $rows->map(function ($row) use ($events) {
            $event = $row->page_key === 'event' ? $events->get($row->event_id) : null;
            [$label, $url] = self::PAGES[$row->page_key] ?? ['Other pages', null];

            return [
                'label' => $event ? $event->title : __($label),
                'url' => $event ? route('event.details', ['slug' => $event->slug, 'id' => $event->event_id], false) : $url,
                'views' => (int) $row->views,
            ];
        })->all();
    }

    private function referrers(Builder $visits): array
    {
        return $visits->toBase()->select('referrer_host')->selectRaw('COUNT(*) AS views')
            ->groupBy('referrer_host')
            ->orderByDesc('views')->orderBy('referrer_host')
            ->limit(10)->get()
            ->map(fn ($row) => [
                'label' => match ($row->referrer_host) {
                    null => __('Direct or unknown'),
                    'internal' => __('Within TICA'),
                    default => $row->referrer_host,
                },
                'url' => null,
                'views' => (int) $row->views,
            ])->all();
    }

    private function referrer(mixed $value, string $host): ?string
    {
        if (!is_string($value) || strlen($value) > 253) {
            return null;
        }

        $domain = $this->withoutWww($value);
        if ($domain === $this->withoutWww($host)) {
            return 'internal';
        }

        return preg_match('/\A(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z](?:[a-z0-9-]{0,61}[a-z0-9])?\z/', $domain)
            ? $domain
            : null;
    }

    private function withoutWww(string $host): string
    {
        $domain = rtrim(strtolower($host), '.');

        return str_starts_with($domain, 'www.') ? substr($domain, 4) : $domain;
    }

    private function publicEvents(): Builder
    {
        $query = Event::query();
        app(EventPublicVisibility::class)->apply($query);

        return $query->where('events.visibility', 'public');
    }

    private function hasDetails(): bool
    {
        return Schema::hasColumns('funnel_analytics_events', ['page_key', 'referrer_host']);
    }
}
