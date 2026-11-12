<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PublicAssetStorage
{
    private bool $bucketReady = false;

    public function enabled(): bool
    {
        return $this->baseUrl() !== '' && $this->apiKey() !== '' && $this->bucket() !== '';
    }

    public function put(UploadedFile $file, string $objectPath): void
    {
        abort_unless($file->isValid(), 422, 'The uploaded file is invalid.');
        $contents = file_get_contents($file->getRealPath());
        if ($contents === false) {
            throw ValidationException::withMessages(['file' => ['The uploaded file could not be read.']]);
        }

        $this->putContents($contents, $objectPath, $file->getMimeType() ?: 'application/octet-stream');
    }

    public function putContents(string $contents, string $objectPath, string $contentType): void
    {
        $path = $this->safePath($objectPath);

        if (!$this->enabled()) {
            throw ValidationException::withMessages([
                'file' => ['Durable public storage is not configured.'],
            ]);
        }

        if ($contents === '' || strlen($contents) > (int) config('services.supabase.public_asset_max_bytes', 52428800)) {
            throw ValidationException::withMessages(['file' => ['The uploaded file is empty or too large.']]);
        }

        $contentType = strtolower(trim(strtok($contentType, ';') ?: 'application/octet-stream'));
        if (!preg_match('#^[a-z0-9.+-]+/[a-z0-9.+-]+$#', $contentType)) {
            $contentType = 'application/octet-stream';
        }

        $this->ensureBucket();

        try {
            $response = $this->request()
                ->withHeaders([
                    'cache-control' => 'max-age=31536000',
                    'x-upsert' => 'false',
                ])
                ->withBody($contents, $contentType)
                ->post($this->endpoint('/storage/v1/object/' . $this->encodedPath($this->bucket() . '/' . $path)));
        } catch (ConnectionException $exception) {
            $this->fail('upload_connection_failed', $path, null, $exception);
        }

        if (!$response->successful()) {
            $this->fail('upload_rejected', $path, $response->status());
        }
    }

    public function delete(string $objectPath): void
    {
        if (!$this->enabled()) {
            return;
        }

        $path = $this->safePath($objectPath);

        try {
            $response = $this->request()->delete(
                $this->endpoint('/storage/v1/object/' . $this->encodedPath($this->bucket() . '/' . $path))
            );
        } catch (ConnectionException $exception) {
            Log::warning('public_asset.delete_connection_failed', [
                'path_hash' => hash('sha256', $path),
                'exception' => $exception::class,
            ]);
            return;
        }

        if (!$response->successful() && $response->status() !== 404) {
            Log::warning('public_asset.delete_rejected', [
                'path_hash' => hash('sha256', $path),
                'status' => $response->status(),
            ]);
        }
    }

    public function publicUrl(string $objectPath): string
    {
        $path = $this->safePath($objectPath);

        return $this->endpoint('/storage/v1/object/public/' . $this->encodedPath($this->bucket() . '/' . $path));
    }

    private function ensureBucket(): void
    {
        if ($this->bucketReady) {
            return;
        }

        try {
            $lookup = $this->request()->get(
                $this->endpoint('/storage/v1/bucket/' . rawurlencode($this->bucket()))
            );

            if ($lookup->successful()) {
                $this->bucketReady = true;
                return;
            }

            if ($lookup->status() !== 404 && !$this->isBucketMissing($lookup->status(), $lookup->json())) {
                $this->fail('bucket_lookup_failed', $this->bucket(), $lookup->status());
            }

            $create = $this->request()->post($this->endpoint('/storage/v1/bucket'), [
                'id' => $this->bucket(),
                'name' => $this->bucket(),
                'public' => true,
                'file_size_limit' => (int) config('services.supabase.public_asset_max_bytes', 52428800),
            ]);
        } catch (ConnectionException $exception) {
            $this->fail('bucket_connection_failed', $this->bucket(), null, $exception);
        }

        if (!$create->successful() && !$this->isAlreadyExists($create->status(), $create->json())) {
            $this->fail('bucket_create_failed', $this->bucket(), $create->status());
        }

        $this->bucketReady = true;
    }

    private function request()
    {
        return Http::acceptJson()
            ->withHeaders([
                'apikey' => $this->apiKey(),
                'Authorization' => 'Bearer ' . $this->apiKey(),
            ])
            ->timeout((int) config('services.supabase.storage_timeout', 15));
    }

    private function fail(
        string $reason,
        string $path,
        ?int $status = null,
        ?\Throwable $exception = null
    ): never {
        Log::error('public_asset.' . $reason, array_filter([
            'path_hash' => hash('sha256', $path),
            'status' => $status,
            'exception' => $exception?->getMessage() ? $exception::class : null,
        ], fn ($value) => $value !== null));

        throw ValidationException::withMessages([
            'file' => ['We could not save this file. Please try again.'],
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

    private function safePath(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path), '/');
        if ($path === '' || str_contains($path, "\0") || str_contains($path, '../') || str_contains($path, '/..')) {
            throw ValidationException::withMessages(['file' => ['The upload path is invalid.']]);
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $segment)) {
                throw ValidationException::withMessages(['file' => ['The upload path is invalid.']]);
            }
        }

        return $path;
    }

    private function encodedPath(string $path): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $path)));
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
        return trim((string) config('services.supabase.public_asset_bucket', 'tica-public'));
    }
}
