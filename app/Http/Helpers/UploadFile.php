<?php

namespace App\Http\Helpers;

use App\Rules\ImageMimeTypeRule;
use App\Services\PublicAssetStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UploadFile
{
  public static function store($directory, $file)
  {
    abort_unless($file instanceof UploadedFile && $file->isValid(), 422, 'The uploaded file is invalid.');
    $courseUpload = Route::is('admin.course_management.lesson.upload_video')
      || Route::is('admin.course_management.lesson.upload_file');
    $originalName = self::displayName($file);
    $extension = $courseUpload ? self::safeContentExtension($file) : self::safeImageExtension($file);
    $fileName = (string) Str::uuid() . '.' . $extension;
    $duration = null;
    if (Route::is('admin.course_management.lesson.upload_video')) {
      $getID3 = new \getID3;
      $fileInfo = $getID3->analyze($file->getRealPath());
      $duration = date('H:i:s', $fileInfo['playtime_seconds']);
    }

    self::storeAs($directory, $file, $fileName);

    if (Route::is('admin.course_management.lesson.upload_video')) {
      return array(
        'originalName' => $originalName,
        'uniqueName' => $fileName,
        'duration' => $duration
      );
    } elseif (Route::is('admin.course_management.lesson.upload_file')) {
      return array(
        'originalName' => $originalName,
        'uniqueName' => $fileName
      );
    } else {
      return $fileName;
    }
  }

  public static function update($directory, $newFile, $oldFile)
  {
    $fileName = self::store($directory, $newFile);
    if (is_array($fileName)) {
      throw ValidationException::withMessages(['file' => ['This upload cannot replace an image.']]);
    }
    $oldName = basename((string) $oldFile);
    if ($oldName !== '' && preg_match('/^[A-Za-z0-9._-]+$/', $oldName)) {
      self::delete($directory, $oldName);
    }

    return $fileName;
  }

  public static function storeAs($directory, $file, string $fileName): string
  {
    abort_unless($file instanceof UploadedFile && $file->isValid(), 422, 'The uploaded file is invalid.');
    if (!preg_match('/^[A-Za-z0-9._-]+$/', $fileName)) {
      throw ValidationException::withMessages(['file' => ['The upload file name is invalid.']]);
    }

    $relativeDirectory = self::publicRelativeDirectory($directory);
    $storage = app(PublicAssetStorage::class);
    if ($relativeDirectory !== null && $storage->enabled()) {
      $storage->put($file, $relativeDirectory . $fileName);
      return $fileName;
    }

    $directory = rtrim((string) $directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
      throw ValidationException::withMessages(['file' => ['The upload directory is unavailable.']]);
    }
    $file->move($directory, $fileName);

    return $fileName;
  }

  public static function delete($directory, string $fileName): void
  {
    $fileName = basename($fileName);
    if ($fileName === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $fileName)) {
      return;
    }

    $relativeDirectory = self::publicRelativeDirectory($directory);
    $storage = app(PublicAssetStorage::class);
    if ($relativeDirectory !== null && $storage->enabled()) {
      $storage->delete($relativeDirectory . $fileName);
      return;
    }

    $directory = rtrim((string) $directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    @unlink($directory . $fileName);
  }

  public static function storeContents($directory, string $contents, string $fileName, string $contentType): string
  {
    if ($contents === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $fileName)) {
      throw ValidationException::withMessages(['file' => ['The uploaded file is invalid.']]);
    }

    $relativeDirectory = self::publicRelativeDirectory($directory);
    $storage = app(PublicAssetStorage::class);
    if ($relativeDirectory !== null && $storage->enabled()) {
      $storage->putContents($contents, $relativeDirectory . $fileName, $contentType);
      return $fileName;
    }

    $directory = rtrim((string) $directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
      throw ValidationException::withMessages(['file' => ['The upload directory is unavailable.']]);
    }
    if (file_put_contents($directory . $fileName, $contents, LOCK_EX) === false) {
      throw ValidationException::withMessages(['file' => ['The uploaded file could not be saved.']]);
    }

    return $fileName;
  }

  private static function publicRelativeDirectory($directory): ?string
  {
    $public = rtrim(str_replace('\\', '/', public_path()), '/') . '/';
    $directory = rtrim(str_replace('\\', '/', (string) $directory), '/') . '/';

    if (str_contains($directory, '/../') || !str_starts_with($directory, $public)) {
      return null;
    }

    $relative = ltrim(substr($directory, strlen($public)), '/');

    return $relative === '' ? null : $relative;
  }

  private static function safeImageExtension(UploadedFile $file): string
  {
    $rule = new ImageMimeTypeRule();
    if (!$rule->passes('image', $file)) {
      throw ValidationException::withMessages(['image' => [$rule->message()]]);
    }

    return ImageMimeTypeRule::extension($file);
  }

  private static function safeContentExtension(UploadedFile $file): string
  {
    $allowed = [
      'application/pdf' => 'pdf',
      'application/zip' => 'zip',
      'application/msword' => 'doc',
      'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
      'application/vnd.ms-excel' => 'xls',
      'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
      'application/vnd.ms-powerpoint' => 'ppt',
      'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
      'text/plain' => 'txt',
      'text/csv' => 'csv',
      'video/mp4' => 'mp4',
      'video/webm' => 'webm',
      'video/quicktime' => 'mov',
      'audio/mpeg' => 'mp3',
      'audio/wav' => 'wav',
    ];
    $extension = $allowed[strtolower((string) $file->getMimeType())] ?? null;
    if ($extension === null) {
      throw ValidationException::withMessages(['file' => ['This file type is not allowed.']]);
    }

    return $extension;
  }

  private static function displayName(UploadedFile $file): string
  {
    $name = preg_replace('/[\x00-\x1F\x7F]+/u', '', basename($file->getClientOriginalName())) ?: 'file';

    return mb_substr($name, 0, 191);
  }
}
