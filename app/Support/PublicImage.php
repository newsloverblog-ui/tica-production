<?php

namespace App\Support;

use App\Http\Helpers\UploadFile;
use App\Services\PublicAssetStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * The one place image URLs are built and uploaded images are saved.
 *
 * Why: uploads live in Supabase storage, but pages linked to /assets/...,
 * which isn't in the deployed files, so every image went through a PHP
 * function that redirected to Supabase. That's a slow extra hop per image
 * and fails under load. Images that were saved to the server's own disk
 * before storage was set up are gone after a redeploy. Now: shipped images
 * (listed in resources/static-images.json) are served as static files;
 * everything else links straight to Supabase.
 */
class PublicImage
{
  private static ?array $static = null;

  /** Public URL for a stored image, or null when there's no usable file name. */
  public static function url(string $directory, ?string $file, mixed $version = null): ?string
  {
    $file = basename((string) $file);
    if ($file === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $file)) {
      return null;
    }
    $relative = trim($directory, '/') . '/' . $file;
    $storage = app(PublicAssetStorage::class);
    $url = $storage->enabled() && !self::isStatic($relative)
      ? $storage->publicUrl($relative)
      : asset($relative);
    $stamp = $version instanceof \DateTimeInterface ? $version->getTimestamp() : $version;

    return $url . ($stamp ? '?v=' . $stamp : '');
  }

  public static function flyer(?string $file, mixed $version = null): ?string
  {
    return self::url('assets/admin/img/event/thumbnail', $file, $version);
  }

  public static function isStatic(string $relative): bool
  {
    self::$static ??= array_flip((array) json_decode((string) @file_get_contents(resource_path('static-images.json')), true));

    return isset(self::$static[trim($relative, '/')]);
  }

  /**
   * Save an uploaded image, scaled down to $maxSide and converted to WebP
   * (GIFs keep their animation). If conversion fails the original is kept
   * and the failure is logged, so an upload never breaks.
   */
  public static function store(UploadedFile $file, string $directory, int $maxSide): string
  {
    $mime = strtolower((string) $file->getMimeType());
    if ($mime !== 'image/gif' && function_exists('imagewebp')) {
      try {
        $webp = self::toWebp((string) $file->getRealPath(), $maxSide);
        $name = (string) Str::uuid() . '.webp';
        UploadFile::storeContents(public_path(trim($directory, '/') . '/'), $webp, $name, 'image/webp');

        return $name;
      } catch (Throwable $exception) {
        Log::warning('Image optimisation failed; keeping the original upload.', ['directory' => $directory, 'mime' => $mime, 'error' => $exception->getMessage()]);
      }
    }
    $name = (string) Str::uuid() . '.' . \App\Rules\ImageMimeTypeRule::extension($file);
    UploadFile::storeAs(public_path(trim($directory, '/') . '/'), $file, $name);

    return $name;
  }

  public static function toWebp(string $path, int $maxSide, int $quality = 82): string
  {
    $data = @file_get_contents($path);
    $source = $data === false ? false : @imagecreatefromstring($data);
    if (!$source) {
      throw new \RuntimeException('The image could not be read.');
    }
    $width = imagesx($source);
    $height = imagesy($source);
    $scale = min(1, $maxSide / max($width, $height));
    $target = imagecreatetruecolor(max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));
    imagealphablending($target, false);
    imagesavealpha($target, true);
    imagecopyresampled($target, $source, 0, 0, 0, 0, imagesx($target), imagesy($target), $width, $height);
    ob_start();
    $ok = imagewebp($target, null, $quality);
    $out = (string) ob_get_clean();
    imagedestroy($source);
    imagedestroy($target);
    if (!$ok || $out === '') {
      throw new \RuntimeException('WebP encoding failed.');
    }

    return $out;
  }
}
