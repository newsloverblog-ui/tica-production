<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('events', 'timezone')) {
            Schema::table('events', function (Blueprint $table) {
                $table->string('timezone', 64)->default('Africa/Lagos')->after('start_time');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('events', 'timezone')) {
            Schema::table('events', function (Blueprint $table) {
                $table->dropColumn('timezone');
            });
        }
    }
};
