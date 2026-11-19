<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class SupportTicketAttachmentStorage
{
    private bool $bucketReady = false;

    public function store(UploadedFile $file, string $folder): string
    {
        abort_unless($file->isValid(), 422, 'The uploaded file is invalid.');

        $contents = file_get_contents($file->getRealPath());

        if ($contents === false) {
            throw ValidationException::withMessages([
                'file' => ['The attachment could not be read.'],
            ]);
        }

        return $this->storeContents($contents, $folder);
    }

    public function migrateLegacy(string $storedValue, string $legacyObjectPath, string $folder): ?string
    {
        if ($this->isManagedPath($storedValue)) {
            return $storedValue;
        }

        $filename = basename($storedValue);
        if ($filename !== $storedValue || !preg_match('/^[A-Za-z0-9._-]+\.zip$/i', $filename)) {
            return null;
        }

        $legacyObjectPath = $this->safeLegacyPath($legacyObjectPath);
        if (!str_ends_with($legacyObjectPath, '/' . $filename)
            || !str_starts_with($legacyObjectPath, 'assets/admin/img/support-ticket/')) {
            return null;
        }

        $contents = null;
        $localPath = public_path($legacyObjectPath);

        if (is_file($localPath)) {
            $contents = file_get_contents($localPath);
        } else {
            $publicStorage = app(PublicAssetStorage::class);
            if ($publicStorage->enabled()) {
                try {
                    $response = Http::timeout((int) config('services.supabase.storage_timeout', 15))
                        ->get($publicStorage->publicUrl($legacyObjectPath));
                } catch (ConnectionException $exception) {
                    Log::warning('support_attachment.legacy_download_connection_failed', [
                        'path_hash' => hash('sha256', $legacyObjectPath),
                        'exception' => $exception::class,
                    ]);

                    return null;
                }

                if ($response->successful()) {
                    $contents = $response->body();
                }
            }
        }

        if (!is_string($contents) || $contents === '') {
            return null;
        }

        return $this->storeContents($contents, $folder);
    }

    public function deleteLegacy(string $legacyObjectPath): void
    {
        $legacyObjectPath = $this->safeLegacyPath($legacyObjectPath);
        if (!str_starts_with($legacyObjectPath, 'assets/admin/img/support-ticket/')
            || !preg_match('/\.zip$/i', $legacyObjectPath)) {
            return;
        }

        $localPath = public_path($legacyObjectPath);
        if (is_file($localPath)) {
            @unlink($localPath);
        }

        app(PublicAssetStorage::class)->delete($legacyObjectPath);
    }

    private function storeContents(string $contents, string $folder): string
    {
        $folder = $this->safeFolder($folder);
        $path = 'support-ticket/' . $folder . '/' . Str::uuid() . '.zip';

        if ($contents === '' || strlen($contents) > 20 * 1024 * 1024) {
            throw ValidationException::withMessages([
                'file' => ['The attachment is empty or too large.'],
            ]);
        }

        if ($this->usesSupabase()) {
            $this->ensurePrivateBucket();

            try {
                $response = $this->request()
                    ->withHeaders([
                        'cache-control' => 'private, no-store',
                        'x-upsert' => 'false',
                    ])
                    ->withBody($contents, 'application/zip')
                    ->post($this->endpoint('/storage/v1/object/' . $this->encodedPath($this->bucket() . '/' . $path)));
            } catch (ConnectionException $exception) {
                $this->fail('upload_connection_failed', $path, null, $exception);
            }

            if (!$response->successful()) {
                $this->fail('upload_rejected', $path, $response->status());
            }

            return $path;
        }

        if (!Storage::disk($this->disk())->put($path, $contents)) {
            throw ValidationException::withMessages([
                'file' => ['The attachment could not be stored securely. Please try again.'],
            ]);
        }

        return $path;
    }

    public function download(string $path, string $downloadName = 'support-attachment.zip'): Response
    {
        $path = $this->safeManagedPath($path);
        $downloadName = $this->safeDownloadName($downloadName);
        $headers = [
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Type' => 'application/zip',
        ];

        if ($this->usesSupabase()) {
            try {
                $response = $this->request()->get(
                    $this->endpoint('/storage/v1/object/authenticated/' . $this->encodedPath($this->bucket() . '/' . $path))
                );
            } catch (ConnectionException $exception) {
                Log::warning('support_attachment.download_connection_failed', [
                    'path_hash' => hash('sha256', $path),
                    'exception' => $exception::class,
                ]);
                abort(503, 'The attachment store is temporarily unavailable.');
            }

            abort_unless($response->successful(), 404);

            return response($response->body(), 200, $headers + [
                'Content-Disposition' => 'attachment; filename="' . $downloadName . '"',
            ]);
        }

        abort_unless(Storage::disk($this->disk())->exists($path), 404);

        return Storage::disk($this->disk())->download($path, $downloadName, $headers);
    }

    public function delete(?string $path): void
    {
        if (!$path || !$this->isManagedPath($path)) {
            return;
        }

        $path = $this->safeManagedPath($path);

        if ($this->usesSupabase()) {
            try {
                $response = $this->request()->delete(
                    $this->endpoint('/storage/v1/object/' . $this->encodedPath($this->bucket() . '/' . $path))
                );
            } catch (ConnectionException $exception) {
                Log::warning('support_attachment.delete_connection_failed', [
                    'path_hash' => hash('sha256', $path),
                    'exception' => $exception::class,
                ]);
                return;
            }

            if (!$response->successful() && $response->status() !== 404) {
                Log::warning('support_attachment.delete_rejected', [
                    'path_hash' => hash('sha256', $path),
                    'status' => $response->status(),
                ]);
            }
            return;
        }

        Storage::disk($this->disk())->delete($path);
    }

    public function deleteStoredOrLegacy(?string $storedValue, string $legacyObjectPath): void
    {
        if (!$storedValue) {
            return;
        }

        if ($this->isManagedPath($storedValue)) {
            $this->delete($storedValue);
            return;
        }

        if (basename($storedValue) !== $storedValue
            || !preg_match('/^[A-Za-z0-9._-]+\.zip$/i', $storedValue)) {
            return;
        }

        $legacyObjectPath = $this->safeLegacyPath($legacyObjectPath);
        if (!str_ends_with($legacyObjectPath, '/' . $storedValue)) {
            return;
        }

        $this->deleteLegacy($legacyObjectPath);
    }

    public function isManagedPath(?string $path): bool
    {
        return is_string($path)
            && str_starts_with($path, 'support-ticket/')
            && !str_contains($path, '..')
            && !str_contains($path, "\0")
            && preg_match('/^[A-Za-z0-9._\/-]+$/', $path) === 1;
    }

    private function ensurePrivateBucket(): void
    {
        if ($this->bucketReady) {
            return;
        }

        try {
            $lookup = $this->request()->get(
                $this->endpoint('/storage/v1/bucket/' . rawurlencode($this->bucket()))
            );

            if ($lookup->successful()) {
                if ((bool) data_get($lookup->json(), 'public', false)) {
                    $this->fail('public_bucket_rejected', $this->bucket(), $lookup->status());
                }
                $this->bucketReady = true;
                return;
            }

            if (!$this->isBucketMissing($lookup->status(), $lookup->json())) {
                $this->fail('bucket_lookup_failed', $this->bucket(), $lookup->status());
            }

            $create = $this->request()->post($this->endpoint('/storage/v1/bucket'), [
                'id' => $this->bucket(),
                'name' => $this->bucket(),
                'public' => false,
                'file_size_limit' => 20 * 1024 * 1024,
                'allowed_mime_types' => ['application/zip', 'application/x-zip-compressed'],
            ]);
        } catch (ConnectionException $exception) {
            $this->fail('bucket_connection_failed', $this->bucket(), null, $exception);
        }

        if (!$create->successful() && !$this->isAlreadyExists($create->status(), $create->json())) {
            $this->fail('bucket_create_failed', $this->bucket(), $create->status());
        }

        $this->bucketReady = true;
    }

    private function fail(
        string $reason,
        string $path,
        ?int $status = null,
        ?\Throwable $exception = null
    ): never {
        Log::error('support_attachment.' . $reason, array_filter([
            'path_hash' => hash('sha256', $path),
            'status' => $status,
            'exception' => $exception ? $exception::class : null,
        ], fn ($value) => $value !== null));

        throw ValidationException::withMessages([
            'file' => ['The attachment could not be stored securely. Please try again.'],
        ]);
    }

    private function isAlreadyExists(int $status, mixed $body): bool
    {
        if (!in_array($status, [400, 409], true)) {
            return false;
        }

        $message = mb_strtolower(is_array($body) ? json_encode($body) : (string) $body);

        return str_contains($message, 'already exists') || str_contains($message, 'duplicate');
    }

    private function isBucketMissing(int $status, mixed $body): bool
    {
        if (!in_array($status, [400, 404], true)) {
            return false;
        }

        $message = mb_strtolower(is_array($body) ? json_encode($body) : (string) $body);

        return str_contains($message, 'bucket not found') || str_contains($message, 'not found');
    }

    private function request()
    {
        $key = $this->apiKey();
        $headers = ['apikey' => $key];

        if (!str_starts_with($key, 'sb_secret_')) {
            $headers['Authorization'] = 'Bearer ' . $key;
        }

        return Http::acceptJson()
            ->withHeaders($headers)
            ->timeout((int) config('services.supabase.storage_timeout', 15));
    }

    private function safeFolder(string $folder): string
    {
        abort_unless(in_array($folder, ['attachments', 'replies'], true), 422);

        return $folder;
    }

    private function safeLegacyPath(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path), '/');
        abort_if(
            $path === ''
                || str_contains($path, '..')
                || str_contains($path, "\0")
                || !preg_match('/^[A-Za-z0-9._\/-]+$/', $path),
            404
        );

        return $path;
    }

    private function safeManagedPath(string $path): string
    {
        abort_unless($this->isManagedPath($path), 404);

        return $path;
    }

    private function safeDownloadName(string $name): string
    {
        $name = preg_replace('/[^A-Za-z0-9._-]/', '-', basename($name)) ?: 'support-attachment.zip';

        return str_ends_with(strtolower($name), '.zip') ? $name : $name . '.zip';
    }

    private function usesSupabase(): bool
    {
        return $this->baseUrl() !== '' && $this->apiKey() !== '' && $this->bucket() !== '';
    }

    private function endpoint(string $path): string
    {
        return $this->baseUrl() . '/' . ltrim($path, '/');
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.supabase.url'), '/');
    }

    private function apiKey(): string
    {
        return (string) (config('services.supabase.secret_key') ?: config('services.supabase.service_role_key'));
    }

    private function bucket(): string
    {
        return trim((string) config('services.supabase.support_attachment_bucket', 'tica-private-support'));
    }

    private function disk(): string
    {
        return (string) config('filesystems.support_ticket_attachments_disk', 'local');
    }

    private function encodedPath(string $path): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $path)));
    }
}
