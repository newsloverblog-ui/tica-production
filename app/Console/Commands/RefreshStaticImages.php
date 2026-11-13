<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/** Rebuilds resources/static-images.json: images shipped in the repo, served as static files. */
class RefreshStaticImages extends Command
{
  protected $signature = 'tica:static-images';

  protected $description = 'List the event and organiser images that ship with the code';

  public function handle(): int
  {
    $files = [];
    foreach (['assets/admin/img/event/thumbnail', 'assets/admin/img/organizer-photo'] as $dir) {
      foreach (glob(public_path($dir) . '/*') ?: [] as $file) {
        if (is_file($file)) {
          $files[] = $dir . '/' . basename($file);
        }
      }
    }
    sort($files);
    file_put_contents(resource_path('static-images.json'), json_encode($files, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    $this->info(count($files) . ' images listed.');

    return self::SUCCESS;
  }
}
