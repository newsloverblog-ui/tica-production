<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Http\UploadedFile;

class KycDocumentRule implements Rule
{
    public const MAX_BYTES = 10 * 1024 * 1024;
    public const MAX_PIXELS = 25_000_000;

    public function passes($attribute, $value): bool
    {
        if (!$value instanceof UploadedFile || !$value->isValid()) {
            return false;
        }

        $path = $value->getRealPath();
        if (!is_string($path) || !is_file($path) || $value->getSize() <= 0 || $value->getSize() > self::MAX_BYTES) {
            return false;
        }

        $mime = strtolower((string) $value->getMimeType());
        $extension = strtolower((string) $value->getClientOriginalExtension());
        if (in_array($mime, ['image/jpeg', 'image/png'], true)) {
            return $this->validImage($path, $mime, $extension);
        }

        if (in_array($mime, ['application/pdf', 'application/x-pdf'], true) && $extension === 'pdf') {
            return $this->validPassivePdf($path);
        }

        return false;
    }

    public function message(): string
    {
        return 'Upload a genuine JPG, PNG or passive PDF document no larger than 10 MB.';
    }

    public static function extension(UploadedFile $file): string
    {
        return match (strtolower((string) $file->getMimeType())) {
            'image/png' => 'png',
            'application/pdf', 'application/x-pdf' => 'pdf',
            default => 'jpg',
        };
    }

    private function validImage(string $path, string $mime, string $extension): bool
    {
        $allowedExtensions = $mime === 'image/png' ? ['png'] : ['jpg', 'jpeg'];
        if (!in_array($extension, $allowedExtensions, true)) {
            return false;
        }

        $dimensions = @getimagesize($path);
        $width = (int) ($dimensions[0] ?? 0);
        $height = (int) ($dimensions[1] ?? 0);
        $detectedMime = strtolower((string) ($dimensions['mime'] ?? ''));

        return $width > 0
            && $height > 0
            && ($width * $height) <= self::MAX_PIXELS
            && hash_equals($mime, $detectedMime);
    }

    private function validPassivePdf(string $path): bool
    {
        $contents = file_get_contents($path);
        if (!is_string($contents) || !str_starts_with($contents, '%PDF-') || !preg_match('/%%EOF\s*$/s', $contents)) {
            return false;
        }

        return !preg_match('/\/(?:JavaScript|JS\b|OpenAction|AA\b|Launch\b|RichMedia\b|EmbeddedFile\b|XFA\b)/i', $contents);
    }
}
