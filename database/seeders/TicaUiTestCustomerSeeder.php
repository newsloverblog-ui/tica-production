<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Event\Booking;
use App\Models\Event\Wishlist;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * A ticket buyer for checking the TICA design's buyer dashboard (/account)
 * on a local machine. Takes over the local test booking made for
 * ada.test@example.com and saves one event. Refuses to run outside local.
 *
 *   php artisan db:seed --class=TicaUiTestCustomerSeeder
 *   Log in at /login (I'm attending): tica-ui-buyer@example.test / TicaUiBuyer2026
 */
class TicaUiTestCustomerSeeder extends Seeder
{
  public function run(): void
  {
    if (!app()->environment('local')) {
      throw new RuntimeException('TicaUiTestCustomerSeeder only runs in the local environment.');
    }

    $customer = Customer::query()->firstOrNew(['email' => 'tica-ui-buyer@example.test']);
    $customer->forceFill([
      'fname' => 'Ada',
      'lname' => 'Test',
      'username' => 'ada_test_buyer',
      'phone' => '08012345678',
      'city' => 'Owerri',
      'country' => 'Nigeria',
      'password' => Hash::make('TicaUiBuyer2026'),
      'status' => 1,
      'email_verified_at' => now(),
    ])->save();

    Booking::query()->where('email', 'ada.test@example.com')->whereNull('customer_id')->update(['customer_id' => $customer->id]);

    $saved = Booking::query()->where('customer_id', '!=', $customer->id)->value('event_id');
    if ($saved && !Wishlist::query()->where('customer_id', $customer->id)->where('event_id', $saved)->exists()) {
      Wishlist::query()->create(['customer_id' => $customer->id, 'event_id' => $saved]);
    }
  }
}
