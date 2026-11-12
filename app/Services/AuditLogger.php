<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    private const SENSITIVE_KEYS = [
        'password', 'password_confirmation', 'token', 'secret', 'authorization',
        'card', 'card_number', 'cvv', 'cvc', 'pin', 'account_number', 'feilds',
    ];

    public static function record(
        string $action,
        ?Model $subject = null,
        array $before = [],
        array $after = [],
        array $metadata = []
    ): AuditLog {
        [$actorType, $actorId] = self::actor();
        $request = request();

        return AuditLog::create([
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'action' => $action,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject ? $subject->getKey() : null,
            'before' => $before ? self::sanitize($before) : null,
            'after' => $after ? self::sanitize($after) : null,
            'metadata' => $metadata ? self::sanitize($metadata) : null,
            'request_id' => $request->attributes->get('request_id'),
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            'created_at' => now(),
        ]);
    }

    public static function sanitize(array $data): array
    {
        foreach ($data as $key => $value) {
            if (in_array(strtolower((string) $key), self::SENSITIVE_KEYS, true)) {
                $data[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $data[$key] = self::sanitize($value);
            }
        }

        return $data;
    }

    private static function actor(): array
    {
        foreach (['admin', 'organizer', 'scanner', 'customer', 'web'] as $guard) {
            if (Auth::guard($guard)->check()) {
                return [$guard, Auth::guard($guard)->id()];
            }
        }

        return ['system', null];
    }
}

