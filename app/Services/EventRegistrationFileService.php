<?php

namespace App\Services;

use App\Models\EventRegistrationFile;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EventRegistrationFileService
{
    private const ALLOWED_TYPES = [
        'application/pdf' => ['pdf'],
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
    ];

    public function inspect(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        $size = (int) $file->getSize();
        $maximum = (int) config('registration.upload_max_file_kb', 5120) * 1024;
        if (!$file->isValid() || !is_string($path) || !is_file($path) || $size < 1 || $size > $maximum) {
            throw new DomainException('Upload a non-empty file within the permitted size limit.');
        }

        $mime = strtolower((string) $file->getMimeType());
        $extension = strtolower((string) $file->getClientOriginalExtension());
        if (!isset(self::ALLOWED_TYPES[$mime]) || !in_array($extension, self::ALLOWED_TYPES[$mime], true)) {
            throw new DomainException('Upload a genuine PDF, JPG or PNG file whose extension matches its contents.');
        }

        if (str_starts_with($mime, 'image/')) {
            $dimensions = @getimagesize($path);
            $width = (int) ($dimensions[0] ?? 0);
            $height = (int) ($dimensions[1] ?? 0);
            $detected = strtolower((string) ($dimensions['mime'] ?? ''));
            if ($width < 1 || $height < 1 || ($width * $height) > 25_000_000 || !hash_equals($mime, $detected)) {
                throw new DomainException('Upload a valid image no larger than 25 megapixels.');
            }
        } else {
            $prefix = (string) file_get_contents($path, false, null, 0, 1024);
            if (!str_starts_with(ltrim($prefix), '%PDF-')) {
                throw new DomainException('The uploaded PDF does not have a valid PDF header.');
            }
            $contents = (string) file_get_contents($path);
            if (preg_match('/\/(JavaScript|JS|Launch|OpenAction|EmbeddedFile)\b/i', $contents)) {
                throw new DomainException('Active or embedded content is not permitted in uploaded PDFs.');
            }
        }

        return [
            'mime_type' => $mime,
            'extension' => $extension === 'jpeg' ? 'jpg' : $extension,
            'size' => $size,
            'sha256' => hash_file('sha256', $path),
            'original_name' => mb_substr(basename($file->getClientOriginalName()), 0, 191),
        ];
    }

    public function store(UploadedFile $file, array $inspection, int $eventId): array
    {
        $disk = (string) config('registration.upload_disk', 'local');
        $diskConfig = (array) config('filesystems.disks.'.$disk, []);
        if ($disk === 'public' || $diskConfig === [] || ($diskConfig['visibility'] ?? 'private') === 'public') {
            throw new DomainException('Registration upload storage must be private.');
        }

        $publicId = (string) Str::ulid();
        $path = 'registration-uploads/'.$eventId.'/'.now()->format('Y/m').'/'.$publicId.'.'.$inspection['extension'];
        $stored = $file->storeAs(dirname($path), basename($path), $disk);
        if (!$stored || !Storage::disk($disk)->exists($path)) {
            throw new DomainException('The private registration file could not be stored.');
        }

        return $inspection + ['public_id' => $publicId, 'disk' => $disk, 'path' => $path];
    }

    public function deleteStored(array|EventRegistrationFile $file): void
    {
        $disk = $file instanceof EventRegistrationFile ? $file->disk : ($file['disk'] ?? null);
        $path = $file instanceof EventRegistrationFile ? $file->path : ($file['path'] ?? null);
        if (is_string($disk) && $disk !== '' && is_string($path) && $path !== '') {
            Storage::disk($disk)->delete($path);
        }
    }
}
