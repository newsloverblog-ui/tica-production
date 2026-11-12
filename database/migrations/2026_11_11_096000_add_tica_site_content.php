<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Website content an admin edits for the public TICA pages: home page,
 * menu, footer and contact details. Empty means the design's own content.
 */
return new class extends Migration
{
  public function up(): void
  {
    if (!Schema::hasColumn('basic_settings', 'tica_site')) {
      Schema::table('basic_settings', function (Blueprint $table) {
        $table->json('tica_site')->nullable();
      });
    }
  }

  public function down(): void
  {
    if (Schema::hasColumn('basic_settings', 'tica_site')) {
      Schema::table('basic_settings', fn (Blueprint $table) => $table->dropColumn('tica_site'));
    }
  }
};
