<?php

namespace App\Services;

use App\Models\OperationalIncident;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class IncidentAlertService
{
    private const LEVELS = ['critical', 'error', 'warning'];

    public function alert(
        string $key,
        string $title,
        array $context = [],
        string $severity = 'critical',
        ?int $cooldownSeconds = null
    ): array {
        $severity = in_array($severity, self::LEVELS, true) ? $severity : 'critical';
        $cooldown = max(60, $cooldownSeconds ?? (int) config('operations.incident_alert_cooldown_seconds', 900));
        $cacheKey = 'ops:incident-alert:' . hash('sha256', $key);
        $sanitizedContext = AuditLogger::sanitize($context);
        $incident = $this->recordOccurrence($key, $title, $sanitizedContext, $severity);

        try {
            if (!Cache::add($cacheKey, true, now()->addSeconds($cooldown))) {
                return [
                    'sent' => false,
                    'suppressed' => true,
                    'destinations' => [],
                    'incident_id' => $incident?->public_id,
                ];
            }
        } catch (Throwable $exception) {
            Log::error('Incident alert cooldown cache is unavailable.', [
                'alert_key' => $key,
                ...$this->safeExceptionContext($exception),
            ]);
        }

        $payload = [
            'alert_id' => (string) \Illuminate\Support\Str::uuid(),
            'key' => mb_substr($key, 0, 191),
            'severity' => $severity,
            'title' => mb_substr($title, 0, 500),
            'environment' => (string) config('app.env'),
            'app_url' => (string) config('app.url'),
            'occurred_at' => now()->toIso8601String(),
            'context' => $sanitizedContext,
        ];

        match ($severity) {
            'warning' => Log::warning($payload['title'], $payload),
            'error' => Log::error($payload['title'], $payload),
            default => Log::critical($payload['title'], $payload),
        };
        $destinations = [];
        $email = (string) config('operations.incident_email', '');
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            try {
                Mail::raw($this->emailBody($payload), function ($message) use ($email, $payload): void {
                    $message->to($email)->subject('[Tica ' . strtoupper($payload['severity']) . '] ' . $payload['title']);
                });
                $destinations['email'] = 'sent';
            } catch (Throwable $exception) {
                $destinations['email'] = 'failed';
                Log::error('Incident email delivery failed.', [
                    'alert_id' => $payload['alert_id'],
                    ...$this->safeExceptionContext($exception),
                ]);
            }
        }

        $webhook = (string) config('operations.incident_webhook_url', '');
        $secret = (string) config('operations.incident_webhook_secret', '');
        if ($this->isSecureWebhook($webhook) && strlen($secret) >= 32) {
            try {
                $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                $response = Http::acceptJson()
                    ->connectTimeout(max(1, (int) config('operations.incident_connect_timeout_seconds', 2)))
                    ->timeout(max(2, (int) config('operations.incident_timeout_seconds', 5)))
                    ->withHeaders([
                        'X-Tica-Alert-Id' => $payload['alert_id'],
                        'X-Tica-Signature' => hash_hmac('sha256', $json, $secret),
                    ])
                    ->withBody($json, 'application/json')
                    ->send('POST', $webhook);
                if (!$response->successful()) {
                    throw new \RuntimeException('Incident webhook returned HTTP ' . $response->status() . '.');
                }
                $destinations['webhook'] = 'sent';
            } catch (Throwable $exception) {
                $destinations['webhook'] = 'failed';
                Log::error('Incident webhook delivery failed.', [
                    'alert_id' => $payload['alert_id'],
                    ...$this->safeExceptionContext($exception),
                ]);
            }
        }

        if ($incident) {
            try {
                $incident->forceFill([
                    'delivery_status' => $destinations,
                    'last_alerted_at' => now(),
                ])->save();
            } catch (Throwable $exception) {
                Log::error('Incident history delivery state could not be updated.', [
                    'incident_id' => $incident->public_id,
                    ...$this->safeExceptionContext($exception),
                ]);
            }
        }

        return [
            'sent' => in_array('sent', $destinations, true),
            'suppressed' => false,
            'destinations' => $destinations,
            'alert_id' => $payload['alert_id'],
            'incident_id' => $incident?->public_id,
        ];
    }

    private function recordOccurrence(
        string $key,
        string $title,
        array $context,
        string $severity
    ): ?OperationalIncident {
        try {
            $key = mb_substr($key, 0, 191);
            $incident = OperationalIncident::firstOrCreate([
                'key_hash' => hash('sha256', $key),
            ], [
                'incident_key' => $key,
                'severity' => $severity,
                'status' => 'open',
                'title' => mb_substr($title, 0, 500),
                'occurrence_count' => 0,
                'context' => $context,
                'first_seen_at' => now(),
                'last_seen_at' => now(),
            ]);
            $incident->increment('occurrence_count');
            $incident->forceFill([
                'severity' => $severity,
                'status' => 'open',
                'title' => mb_substr($title, 0, 500),
                'context' => $context,
                'last_seen_at' => now(),
                'resolved_at' => null,
                'resolution_note' => null,
                'resolved_by_admin_id' => null,
            ])->save();
            return $incident->fresh();
        } catch (Throwable $exception) {
            Log::error('Operational incident history could not be recorded.', [
                'alert_key' => mb_substr($key, 0, 191),
                ...$this->safeExceptionContext($exception),
            ]);
            return null;
        }
    }

    private function safeExceptionContext(Throwable $exception): array
    {
        return [
            'exception_type' => $exception::class,
            'exception_code' => $exception->getCode(),
        ];
    }

    private function isSecureWebhook(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https';
    }

    private function emailBody(array $payload): string
    {
        return $payload['title'] . "\n\n"
            . 'Severity: ' . strtoupper($payload['severity']) . "\n"
            . 'Environment: ' . $payload['environment'] . "\n"
            . 'Occurred: ' . $payload['occurred_at'] . "\n"
            . 'Alert ID: ' . $payload['alert_id'] . "\n\n"
            . json_encode($payload['context'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
