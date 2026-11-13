<?php

namespace Tests\Unit;

use App\Support\PublicImage;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PublicImageTest extends TestCase
{
  public function test_uploaded_images_link_straight_to_storage_with_a_version(): void
  {
    $this->storageOn();

    $url = PublicImage::flyer('abc-123.webp', 1700000000);

    $this->assertSame('https://store.example.test/storage/v1/object/public/tica-public/assets/admin/img/event/thumbnail/abc-123.webp?v=1700000000', $url);
  }

  public function test_images_shipped_with_the_code_stay_static(): void
  {
    $this->storageOn();
    $shipped = json_decode(file_get_contents(resource_path('static-images.json')), true)[0];

    $this->assertSame(asset($shipped), PublicImage::url(dirname($shipped), basename($shipped)));
  }

  public function test_without_storage_it_falls_back_to_local_assets(): void
  {
    config()->set('services.supabase.url', null);
    $this->assertSame(asset('assets/admin/img/event/thumbnail/x.png'), PublicImage::flyer('x.png'));
  }

  public function test_missing_or_unsafe_names_give_no_url(): void
  {
    $this->assertNull(PublicImage::flyer(null));
    $this->assertNull(PublicImage::flyer(''));
    $this->assertNull(PublicImage::flyer('bad name.png'));
    $this->assertSame(PublicImage::flyer('ok.png'), PublicImage::flyer('../../ok.png'), 'Directories are stripped.');
  }

  public function test_uploads_are_scaled_down_and_saved_as_webp(): void
  {
    if (!function_exists('imagewebp')) {
      $this->markTestSkipped('GD WebP support is not installed.');
    }
    $public = sys_get_temp_dir() . '/tica-public-' . uniqid();
    mkdir($public . '/assets/admin/img/event/thumbnail', 0777, true);
    $this->app->usePublicPath($public);
    config()->set('services.supabase.url', null);
    $photo = UploadedFile::fake()->image('phone-photo.jpg', 3840, 2160);

    $name = PublicImage::store($photo, 'assets/admin/img/event/thumbnail', 1600);

    $this->assertStringEndsWith('.webp', $name);
    $saved = $public . '/assets/admin/img/event/thumbnail/' . $name;
    [$width, $height, $type] = getimagesize($saved);
    $this->assertSame([1600, 900, IMAGETYPE_WEBP], [$width, $height, $type]);
    $this->assertLessThan(filesize($photo->getRealPath()), filesize($saved));
  }

  public function test_small_images_are_not_enlarged(): void
  {
    if (!function_exists('imagewebp')) {
      $this->markTestSkipped('GD WebP support is not installed.');
    }
    $small = UploadedFile::fake()->image('a.png', 200, 100); // keep a reference so its temp file lives
    $webp = PublicImage::toWebp($small->getRealPath(), 512);
    $info = getimagesizefromstring($webp);
    $this->assertSame([200, 100], [$info[0], $info[1]]);
  }

  public function test_static_manifest_matches_the_files_in_the_repo(): void
  {
    $listed = json_decode(file_get_contents(resource_path('static-images.json')), true);
    $actual = [];
    foreach (['assets/admin/img/event/thumbnail', 'assets/admin/img/organizer-photo'] as $dir) {
      foreach (glob(public_path($dir) . '/*') ?: [] as $file) {
        if (is_file($file)) {
          $actual[] = $dir . '/' . basename($file);
        }
      }
    }
    sort($actual);
    sort($listed);
    $this->assertSame($actual, $listed, 'Run: php artisan tica:static-images to refresh resources/static-images.json');
  }

  public function test_shipped_images_are_actually_deployed(): void
  {
    // Images listed as static must not be excluded from Vercel deployments,
    // or their links fall through to storage where they may not exist.
    $ignore = file_get_contents(base_path('.vercelignore'));
    $this->assertStringContainsString('!public/assets/admin/img/event/thumbnail', $ignore);
    $this->assertStringContainsString('!public/assets/admin/img/organizer-photo', $ignore);
    $this->assertDoesNotMatchRegularExpression('/^public\/assets\/admin\/img\/?$/m', $ignore, 'The whole admin img folder must not be excluded.');
  }

  public function test_no_code_builds_flyer_urls_by_hand(): void
  {
    $hits = [];
    foreach ([app_path(), resource_path('views')] as $root) {
      $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
      foreach ($it as $file) {
        if ($file->isFile() && str_ends_with($file->getFilename(), '.php')
          && preg_match("/asset\\(\\s*'assets\\/admin\\/img\\/event\\/thumbnail\\/'\\s*\\./", file_get_contents($file->getPathname()))) {
          $hits[] = $file->getPathname();
        }
      }
    }
    $this->assertSame([], $hits, 'Use App\\Support\\PublicImage::flyer().');
  }

  private function storageOn(): void
  {
    config()->set('services.supabase.url', 'https://store.example.test');
    config()->set('services.supabase.secret_key', 'test-key');
    config()->set('services.supabase.public_asset_bucket', 'tica-public');
  }
}
