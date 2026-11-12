<?php

namespace App\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;

class RuntimeDocumentStore
{
    public const WEB_MANIFEST = 'manifest.web';
    public const SCANNER_MANIFEST = 'manifest.scanner';
    public const ADMIN_TRANSLATIONS = 'translations.admin';
    public const DEFAULT_TRANSLATIONS = 'translations.default';

    public function get(string $key, array $fallback = []): array
    {
        try {
            $settings = $this->settings();
            $content = data_get($settings, 'runtime_documents.' . $this->safeKey($key));
        } catch (QueryException $exception) {
            Log::warning('runtime_document.read_failed', [
                'key_hash' => hash('sha256', $key),
                'exception' => $exception::class,
            ]);

            return $fallback;
        }

        if (is_string($content)) {
            $content = json_decode($content, true);
        }

        return is_array($content) ? $content : $fallback;
    }

    public function put(string $key, array $content): void
    {
        $key = $this->safeKey($key);
        DB::transaction(function () use ($key, $content): void {
            $row = DB::table('basic_settings')
                ->where('uniqid', 12345)
                ->lockForUpdate()
                ->first(['id', 'tica_site']);
            if (!$row) {
                throw new \RuntimeException('Website settings are unavailable.');
            }

            $settings = $this->decode($row->tica_site ?? null);
            data_set($settings, 'runtime_documents.' . $key, $content);
            DB::table('basic_settings')->where('id', $row->id)->update([
                'tica_site' => json_encode($settings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
        });
    }

    public function delete(string $key): void
    {
        $key = $this->safeKey($key);
        DB::transaction(function () use ($key): void {
            $row = DB::table('basic_settings')
                ->where('uniqid', 12345)
                ->lockForUpdate()
                ->first(['id', 'tica_site']);
            if (!$row) {
                return;
            }

            $settings = $this->decode($row->tica_site ?? null);
            data_forget($settings, 'runtime_documents.' . $key);
            DB::table('basic_settings')->where('id', $row->id)->update([
                'tica_site' => json_encode($settings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
        });
    }

    public function translationKey(string $locale): string
    {
        $locale = strtolower(trim($locale));
        if ($locale === '' || !preg_match('/^[a-z0-9_-]{2,20}$/', $locale)) {
            throw new \InvalidArgumentException('Invalid translation locale.');
        }

        return 'translations.' . $locale;
    }

    public function translations(string $locale, ?string $fallbackFile = null): array
    {
        $fallback = $fallbackFile ? $this->readJsonFile($fallbackFile) : [];

        return $this->get($this->translationKey($locale), $fallback);
    }

    public function loadTranslations(string $locale, ?string $fallbackFile = null): void
    {
        $overrides = $this->translations($locale, $fallbackFile);
        if ($overrides === []) {
            return;
        }

        $translator = Lang::getFacadeRoot();
        $base = $translator->getLoader()->load($locale, '*', '*');
        $translator->setLoaded([
            '*' => [
                '*' => [
                    $locale => array_replace(is_array($base) ? $base : [], $overrides),
                ],
            ],
        ]);
    }

    public function readJsonFile(string $path): array
    {
        if (!is_file($path) || !is_readable($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    public function webManifestDefaults(): array
    {
        return [
            'short_name' => 'TICA',
            'name' => 'TICA Events',
            'background_color' => '#F5F7FB',
            'theme_color' => '#2463D4',
            'start_url' => '/',
            'scope' => '/',
            'display' => 'standalone',
            'icons' => [
                ['src' => 'assets/front/images/favicon.png', 'type' => 'image/png', 'sizes' => '128x128'],
                ['src' => 'assets/front/images/favicon.png', 'type' => 'image/png', 'sizes' => '256x256'],
                ['src' => 'assets/front/images/favicon.png', 'type' => 'image/png', 'sizes' => '512x512'],
            ],
        ];
    }

    public function scannerManifestDefaults(): array
    {
        return [
            'short_name' => 'TICA Scanner',
            'name' => 'TICA Event Scanner',
            'background_color' => '#081426',
            'theme_color' => '#FE9B59',
            'start_url' => '/scanner/workspace',
            'scope' => '/',
            'display' => 'standalone',
            'icons' => [
                ['src' => '/assets/pwa_scanner/6ad3c78372e08.png', 'type' => 'image/png', 'sizes' => '128x128'],
                ['src' => '/assets/pwa_scanner/6ad3c78372ec2.png', 'type' => 'image/png', 'sizes' => '256x256'],
                ['src' => '/assets/pwa_scanner/6ad3c78372f11.png', 'type' => 'image/png', 'sizes' => '512x512'],
            ],
        ];
    }

    private function safeKey(string $key): string
    {
        $key = trim($key);
        if ($key === '' || strlen($key) > 120 || !preg_match('/^[A-Za-z0-9._-]+$/', $key)) {
            throw new \InvalidArgumentException('Invalid runtime document key.');
        }

        return $key;
    }

    private function settings(): array
    {
        return $this->decode(
            DB::table('basic_settings')->where('uniqid', 12345)->value('tica_site')
        );
    }

    private function decode(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
