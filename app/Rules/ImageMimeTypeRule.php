<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Http\UploadedFile;

class ImageMimeTypeRule implements Rule
{
  private const MIME_EXTENSIONS = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'image/gif' => 'gif',
  ];

  /**
   * Create a new rule instance.
   *
   * @return void
   */
  public function __construct()
  {
    //
  }

  /**
   * Determine if the validation rule passes.
   *
   * @param  string  $attribute
   * @param  mixed  $value
   * @return bool
   */
  public function passes($attribute, $value)
  {
    if (!$value instanceof UploadedFile || !$value->isValid()) {
      return false;
    }

    $path = $value->getRealPath();
    if (!is_string($path) || !is_file($path) || $value->getSize() > 15 * 1024 * 1024) {
      return false;
    }

    $allowed = [
      'image/jpeg' => ['jpg', 'jpeg'],
      'image/png' => ['png'],
      'image/webp' => ['webp'],
      'image/gif' => ['gif'],
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
      && ($width * $height) <= 40_000_000
      && hash_equals($mime, $detectedMime);
  }

  /**
   * Get the validation error message.
   *
   * @return string
   */
  public function message()
  {
    return 'Upload a valid JPG, PNG, WebP or GIF image no larger than 15 MB and 40 megapixels.';
  }

  /** Return only a server-detected extension after the rule has passed. */
  public static function extension(UploadedFile $file): string
  {
    $extension = self::MIME_EXTENSIONS[strtolower((string) $file->getMimeType())] ?? null;
    if ($extension === null) {
      throw new \InvalidArgumentException('Unsupported image MIME type.');
    }

    return $extension;
  }
}
