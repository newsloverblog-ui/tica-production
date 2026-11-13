<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('event_categories', 'icon')) {
            Schema::table('event_categories', function (Blueprint $table) {
                $table->string('icon', 40)->nullable()->after('name');
            });
        }

        // Categories now use an icon; the old uploaded image becomes optional.
        Schema::table('event_categories', function (Blueprint $table) {
            $table->string('image')->nullable()->change();
        });

        $defaults = ['wedding' => 'wedding', 'business' => 'business', 'career' => 'career', 'conference' => 'conference', 'sports' => 'sport'];
        foreach ($defaults as $slug => $icon) {
            DB::table('event_categories')->where('slug', $slug)->whereNull('icon')->update(['icon' => $icon]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('event_categories', 'icon')) {
            Schema::table('event_categories', function (Blueprint $table) {
                $table->dropColumn('icon');
            });
        }
    }
};
