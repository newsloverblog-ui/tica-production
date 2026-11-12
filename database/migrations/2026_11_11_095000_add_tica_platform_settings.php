<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Platform-wide choices an admin makes for every organiser: which event
 * features the create-event page offers, which currencies events can sell
 * in, and the booking fee default. Also closes the template's shop, since
 * TICA sells event tickets only.
 */
return new class extends Migration
{
  public function up(): void
  {
    if (!Schema::hasColumn('basic_settings', 'tica_platform')) {
      Schema::table('basic_settings', function (Blueprint $table) {
        $table->json('tica_platform')->nullable();
      });
    }

    if (Schema::hasColumn('basic_settings', 'shop_status')) {
      DB::table('basic_settings')->update(['shop_status' => 0]);
    }
  }

  public function down(): void
  {
    if (Schema::hasColumn('basic_settings', 'tica_platform')) {
      Schema::table('basic_settings', fn (Blueprint $table) => $table->dropColumn('tica_platform'));
    }
  }
};
