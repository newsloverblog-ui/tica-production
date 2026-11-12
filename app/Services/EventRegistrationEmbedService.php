<?php

namespace App\Services;

use App\Models\EventRegistrationEmbedSetting;
use App\Models\EventRegistrationForm;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class EventRegistrationEmbedService
{
    public const MAX_ALLOWED_ORIGINS = 10;

    public function save(EventRegistrationForm $form, int $organizerId, array $data): EventRegistrationEmbedSetting
    {
        $this->authorize($form, $organizerId);
        $origins = $this->normalizeOrigins($data['allowed_origins'] ?? []);
        $enabled = filter_var($data['enabled'] ?? false, FILTER_VALIDATE_BOOL);
        if ($enabled && $origins === []) {
            throw ValidationException::withMessages([
                'allowed_origins' => 'Add at least one trusted website origin before enabling the embed.',
            ]);
        }

        $accent = $this->color($data['accent_color'] ?? '#FE9B59', 'accent_color');
        $background = $this->color($data['background_color'] ?? '#F4F6FA', 'background_color');
        $before = $form->embedSetting?->only(['enabled', 'allowed_origins', 'accent_color', 'background_color']) ?? [];
        $setting = EventRegistrationEmbedSetting::updateOrCreate(['form_id' => $form->id], [
            'event_id' => $form->event_id,
            'organizer_id' => $organizerId,
            'enabled' => $enabled,
            'allowed_origins' => $origins,
            'accent_color' => $accent,
            'background_color' => $background,
        ]);

        AuditLogger::record('event.registration_embed.settings_saved', $setting, $before, [
            'enabled' => $setting->enabled,
            'allowed_origins' => $setting->allowed_origins,
            'accent_color' => $setting->accent_color,
            'background_color' => $setting->background_color,
        ], ['event_id' => $form->event_id, 'form_id' => $form->id]);

        return $setting;
    }

    public function enabledSetting(EventRegistrationForm $form): EventRegistrationEmbedSetting
    {
        $setting = $form->embedSetting;
        abort_unless($setting?->enabled && is_array($setting->allowed_origins) && $setting->allowed_origins !== [], 404);

        return $setting;
    }

    public function applyFramePolicy($request, EventRegistrationEmbedSetting $setting): void
    {
        $request->attributes->set('secure_embed_frame_ancestors', $setting->allowed_origins);
    }

    public function normalizeOrigins(array|string|null $input): array
    {
        $values = is_array($input) ? $input : preg_split('/[\r\n,]+/', (string) $input);
        $values = array_values(array_unique(array_filter(array_map(
            static fn ($value): string => trim((string) $value),
            $values ?: []
        ))));
        if (count($values) > self::MAX_ALLOWED_ORIGINS) {
            throw ValidationException::withMessages([
                'allowed_origins' => 'You can allow up to '.self::MAX_ALLOWED_ORIGINS.' trusted website origins.',
            ]);
        }

        return array_map(function (string $origin): string {
            if (str_contains($origin, '*')) {
                throw ValidationException::withMessages(['allowed_origins' => 'Wildcard origins are not allowed.']);
            }
            $parts = parse_url($origin);
            $scheme = strtolower((string) ($parts['scheme'] ?? ''));
            $host = strtolower((string) ($parts['host'] ?? ''));
            $port = isset($parts['port']) ? (int) $parts['port'] : null;
            $isLocal = in_array($host, ['localhost', '127.0.0.1', '::1'], true);
            $validHost = filter_var($host, FILTER_VALIDATE_IP)
                || filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME);
            if (!$validHost || !in_array($scheme, ['http', 'https'], true) || ($scheme === 'http' && !$isLocal)
                || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
                || (($parts['path'] ?? '') !== '' && ($parts['path'] ?? '') !== '/')
                || ($port !== null && ($port < 1 || $port > 65535))) {
                throw ValidationException::withMessages([
                    'allowed_origins' => 'Use exact HTTPS origins such as https://events.example.com. HTTP is allowed only for localhost development.',
                ]);
            }

            return $scheme.'://'.$host.($port !== null ? ':'.$port : '');
        }, $values);
    }

    private function color(mixed $value, string $field): string
    {
        $color = strtoupper(trim((string) $value));
        if (!preg_match('/^#[0-9A-F]{6}$/', $color)) {
            throw ValidationException::withMessages([$field => 'Enter a six-digit hex colour.']);
        }

        return $color;
    }

    private function authorize(EventRegistrationForm $form, int $organizerId): void
    {
        if ((int) $form->organizer_id !== $organizerId) {
            throw new AuthorizationException('You cannot configure another organizer’s registration embed.');
        }
    }
}
