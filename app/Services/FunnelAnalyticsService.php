<?php

namespace App\Services;

use App\Models\FunnelAnalyticsEvent;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class FunnelAnalyticsService
{
    public const BROWSER_EVENTS = [
        'search',
        'view_event',
        'select_ticket',
        'begin_checkout',
        'checkout_details',
    ];

    public const SERVER_EVENTS = [
        'payment_attempt',
        'payment_success',
        'payment_failed',
        'ticket_delivered',
        'ticket_scanned',
    ];

    public const FUNNEL_EVENTS = [
        'view_event',
        'select_ticket',
        'begin_checkout',
        'payment_attempt',
        'payment_success',
        'ticket_delivered',
        'ticket_scanned',
    ];

    public function recordBrowser(Request $request, array $payload): FunnelAnalyticsEvent
    {
        $eventName = (string) ($payload['event'] ?? '');
        if (!in_array($eventName, self::BROWSER_EVENTS, true)) {
            throw new InvalidArgumentException('This analytics event cannot be accepted from a browser.');
        }

        $occurredAt = $this->browserOccurredAt($payload['occurred_at'] ?? null);
        $properties = is_array($payload['properties'] ?? null)
            ? $payload['properties']
            : Arr::except($payload, ['event', 'occurred_at']);

        $eventId = $this->positiveInteger($properties['event_id'] ?? null);
        unset($properties['event_id']);

        return FunnelAnalyticsEvent::create([
            'event_name' => $eventName,
            'source' => 'browser',
            'event_id' => $eventId,
            'customer_id' => $request->user('customer')->id ?? null,
            'session_hash' => $this->sessionHash($request),
            'properties' => $this->sanitizeBrowserProperties($eventName, $properties),
            'occurred_at' => $occurredAt,
        ]);
    }

    public function recordServer(string $eventName, array $context = [], ?string $dedupeKey = null): FunnelAnalyticsEvent
    {
        if (!in_array($eventName, self::SERVER_EVENTS, true)) {
            throw new InvalidArgumentException('Unknown server analytics event.');
        }

        $attributes = [
            'event_name' => $eventName,
            'source' => 'server',
            'event_id' => $this->positiveInteger($context['event_id'] ?? null),
            'booking_id' => $this->positiveInteger($context['booking_id'] ?? null),
            'payment_intent_id' => $this->positiveInteger($context['payment_intent_id'] ?? null),
            'organizer_id' => $this->positiveInteger($context['organizer_id'] ?? null),
            'customer_id' => $this->positiveInteger($context['customer_id'] ?? null),
            'dedupe_key' => $dedupeKey ? mb_substr($dedupeKey, 0, 191) : null,
            'properties' => $this->sanitizeServerProperties(Arr::except($context, [
                'event_id', 'booking_id', 'payment_intent_id', 'organizer_id', 'customer_id', 'occurred_at',
            ])),
            'occurred_at' => $context['occurred_at'] ?? now(),
        ];

        if (!$attributes['dedupe_key']) {
            return FunnelAnalyticsEvent::create($attributes);
        }

        try {
            return FunnelAnalyticsEvent::firstOrCreate(
                ['dedupe_key' => $attributes['dedupe_key']],
                Arr::except($attributes, ['dedupe_key'])
            );
        } catch (QueryException $exception) {
            $existing = FunnelAnalyticsEvent::where('dedupe_key', $attributes['dedupe_key'])->first();
            if ($existing) {
                return $existing;
            }
            throw $exception;
        }
    }

    public function recordServerSafely(string $eventName, array $context = [], ?string $dedupeKey = null): ?FunnelAnalyticsEvent
    {
        try {
            return $this->recordServer($eventName, $context, $dedupeKey);
        } catch (Throwable $exception) {
            Log::warning('Unable to persist operational funnel event.', [
                'event_name' => $eventName,
                'dedupe_key' => $dedupeKey,
                'exception_type' => $exception::class,
                'exception_code' => $exception->getCode(),
            ]);
            return null;
        }
    }

    private function browserOccurredAt($value): CarbonImmutable
    {
        try {
            $occurredAt = CarbonImmutable::parse($value ?: 'now');
        } catch (Throwable $exception) {
            return CarbonImmutable::now();
        }

        $now = CarbonImmutable::now();
        if ($occurredAt->lt($now->subDay()) || $occurredAt->gt($now->addMinutes(5))) {
            return $now;
        }
        return $occurredAt;
    }

    private function sessionHash(Request $request): ?string
    {
        if (!$request->hasSession()) {
            return null;
        }
        $sessionId = (string) $request->session()->getId();
        if ($sessionId === '') {
            return null;
        }
        return hash_hmac('sha256', $sessionId, (string) config('app.key'));
    }

    private function sanitizeBrowserProperties(string $eventName, array $properties): array
    {
        if ($eventName === 'search') {
            $query = trim((string) ($properties['search_term'] ?? ''));
            return [
                'has_query' => $query !== '',
                'query_length' => min(mb_strlen($query), 200),
                'location' => $this->safeLabel($properties['location'] ?? null),
                'category' => $this->safeLabel($properties['category'] ?? null),
            ];
        }

        $allowed = [
            'view_event' => ['category'],
            'select_ticket' => ['ticket_id', 'quantity'],
            'begin_checkout' => ['value', 'currency', 'quantity'],
            'checkout_details' => ['step'],
        ][$eventName] ?? [];

        return $this->sanitizeValues(Arr::only($properties, $allowed));
    }

    private function sanitizeServerProperties(array $properties): array
    {
        $blocked = ['email', 'phone', 'name', 'fname', 'lname', 'address', 'token', 'authorization', 'payload'];
        return $this->sanitizeValues(Arr::except($properties, $blocked));
    }

    private function sanitizeValues(array $values): array
    {
        $clean = [];
        foreach ($values as $key => $value) {
            if (!is_string($key) || !preg_match('/^[a-z0-9_]{1,64}$/i', $key)) {
                continue;
            }
            if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
                $clean[$key] = $value;
            } elseif (is_string($value)) {
                $clean[$key] = mb_substr(strip_tags($value), 0, 200);
            }
        }
        return $clean;
    }

    private function safeLabel($value): ?string
    {
        $value = trim(strip_tags((string) $value));
        return $value === '' ? null : mb_substr($value, 0, 100);
    }

    private function positiveInteger($value): ?int
    {
        $value = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $value === false ? null : (int) $value;
    }
}
