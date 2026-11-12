<?php

namespace Database\Seeders;

use App\Models\Language;
use App\Models\Organizer;
use App\Models\OrganizerInfo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * A verified organiser for checking the TICA design's organiser pages on a
 * local machine. Refuses to run outside the local environment.
 *
 *   php artisan db:seed --class=TicaUiTestOrganizerSeeder
 *   Log in at /login (I'm organising): tica-ui-test@example.test / TicaUiTest2026
 */
class TicaUiTestOrganizerSeeder extends Seeder
{
  public function run(): void
  {
    if (!app()->environment('local')) {
      throw new RuntimeException('TicaUiTestOrganizerSeeder only runs in the local environment.');
    }

    $organizer = Organizer::query()->updateOrCreate(
      ['email' => 'tica-ui-test@example.test'],
      [
        'username' => 'tica_ui_test',
        'phone' => '+2348000000000',
        'password' => Hash::make('TicaUiTest2026'),
        'status' => 1,
        'email_verified_at' => now(),
      ]
    );

    foreach (Language::all() as $language) {
      OrganizerInfo::query()->updateOrCreate(
        ['organizer_id' => $organizer->id, 'language_id' => $language->id],
        ['name' => 'TICA UI Test Events']
      );
    }
  }
}
