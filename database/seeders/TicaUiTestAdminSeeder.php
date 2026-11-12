<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * A super admin for looking around the admin panel on a local machine.
 * Refuses to run outside the local environment.
 *
 *   php artisan db:seed --class=TicaUiTestAdminSeeder
 *   Log in at /admin: tica_ui_admin / TicaUiAdmin2026
 */
class TicaUiTestAdminSeeder extends Seeder
{
  public function run(): void
  {
    if (!app()->environment('local')) {
      throw new RuntimeException('TicaUiTestAdminSeeder only runs in the local environment.');
    }

    $admin = Admin::query()->firstOrNew(['username' => 'tica_ui_admin']);
    $admin->forceFill([
      'role_id' => null,
      'first_name' => 'TICA',
      'last_name' => 'Test Admin',
      'email' => 'tica-ui-admin@example.test',
      'password' => Hash::make('TicaUiAdmin2026'),
      'status' => 1,
    ])->save();
  }
}
