<?php

namespace Tests\Unit;

use App\Models\Organizer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class OrganizerAvatarAndProfileTest extends TestCase
{
  public function test_avatar_url_is_built_in_one_place_with_a_cache_busting_version(): void
  {
    $organizer = $this->organizer(['photo' => 'abc-123.webp', 'updated_at' => Carbon::parse('2026-11-13 10:00:00')]);

    $this->assertSame(
      asset('assets/admin/img/organizer-photo/abc-123.webp') . '?v=' . Carbon::parse('2026-11-13 10:00:00')->getTimestamp(),
      $organizer->avatar_url
    );
  }

  public function test_new_save_changes_the_version_so_browsers_fetch_the_new_photo(): void
  {
    $before = $this->organizer(['photo' => 'a.png', 'updated_at' => Carbon::parse('2026-11-13 10:00:00')])->avatar_url;
    $after = $this->organizer(['photo' => 'a.png', 'updated_at' => Carbon::parse('2026-11-13 10:05:00')])->avatar_url;

    $this->assertNotSame($before, $after);
  }

  public function test_missing_or_unsafe_photo_names_fall_back_to_initials(): void
  {
    $this->assertNull($this->organizer(['photo' => null])->avatar_url);
    $this->assertNull($this->organizer(['photo' => 'bad name<script>.png'])->avatar_url);
    $this->assertSame('AO', $this->organizer(['verified_legal_name' => 'Ada Obi', 'photo' => null])->avatar_initials);
  }

  public function test_shared_component_renders_photo_or_initials(): void
  {
    $with = Blade::render('<x-organizer-avatar :organizer="$o" size="32" />', ['o' => $this->organizer(['photo' => 'p.jpg', 'verified_legal_name' => 'Ada Obi'])]);
    $this->assertStringContainsString('organizer-photo/p.jpg', $with);
    $this->assertStringContainsString('AO', $with);

    $without = Blade::render('<x-organizer-avatar :organizer="$o" />', ['o' => $this->organizer(['photo' => null, 'verified_legal_name' => 'Ada Obi'])]);
    $this->assertStringNotContainsString('<img', $without);
    $this->assertStringContainsString('AO', $without);
  }

  public function test_no_view_builds_organizer_photo_paths_by_hand(): void
  {
    $offenders = [];
    $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));
    foreach ($files as $file) {
      if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')
        && str_contains(file_get_contents($file->getPathname()), "organizer-photo/' .")) {
        $offenders[] = $file->getPathname();
      }
    }

    $this->assertSame([], $offenders, 'Use $organizer->avatar_url or <x-organizer-avatar> instead.');
  }

  public function test_settings_and_admin_forms_no_longer_write_designation(): void
  {
    foreach ([
      app_path('Http/Controllers/FrontEnd/TicaWorkspaceController.php'),
      app_path('Http/Controllers/BackEnd/Organizer/OrganizerController.php'),
      app_path('Http/Controllers/BackEnd/Organizer/OrganizerManagementController.php'),
    ] as $path) {
      $this->assertStringNotContainsString("_designation'", file_get_contents($path), $path);
      $this->assertStringNotContainsString("'designation' => \$validated['designation']", file_get_contents($path), $path);
    }
    foreach ([
      resource_path('views/tica-ui/pages/dashboard.blade.php'),
      resource_path('views/organizer/edit-profile.blade.php'),
      resource_path('views/backend/end-user/organizer/create.blade.php'),
      resource_path('views/backend/end-user/organizer/edit.blade.php'),
    ] as $path) {
      $this->assertStringNotContainsString('designation', strtolower(file_get_contents($path)), $path);
    }
  }

  public function test_verbatim_tica_pages_contain_no_blade_loops_that_would_print_raw(): void
  {
    foreach (glob(resource_path('views/tica-ui/pages/*.blade.php')) as $path) {
      $source = file_get_contents($path);
      if (!str_contains($source, '@verbatim')) {
        continue;
      }
      $inside = preg_replace('/@endverbatim.*?(@verbatim|$)/s', '', $source);
      $this->assertDoesNotMatchRegularExpression('/@(foreach|if|php)\b/', $inside, basename($path) . ' is inside @verbatim.');
    }
  }

  private function organizer(array $attributes): Organizer
  {
    $organizer = new Organizer();
    $organizer->forceFill(array_merge(['id' => 7, 'username' => 'ada', 'email' => 'ada@example.com'], $attributes));
    $organizer->setRelation('organizer_info', null);

    return $organizer;
  }
}
