<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Http\UploadedFile;

class PaymentEvidenceFileRule implements Rule
{
    public const MAX_BYTES = 5 * 1024 * 1024;
    public const MAX_PIXELS = 25_000_000;

    public function passes($attribute, $value): bool
    {
        if (!$value instanceof UploadedFile || !$value->isValid()) {
            return false;
        }

        $path = $value->getRealPath();
        if (!is_string($path) || !is_file($path) || $value->getSize() > self::MAX_BYTES) {
            return false;
        }

        $allowed = [
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
        ];
        $mime = strtolower((string) $value->getMimeType());
        $extension = strtolower((string) $value->getClientOriginalExtension());
        if (!isset($allowed[$mime]) || !in_array($extension, $allowed[$mime], true)) {
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

    public function message(): string
    {
        return 'Upload a genuine JPG or PNG payment image no larger than 5 MB and 25 megapixels.';
    }

    public static function extension(UploadedFile $file): string
    {
        return strtolower((string) $file->getMimeType()) === 'image/png' ? 'png' : 'jpg';
    }
}
