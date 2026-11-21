<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Group tickets: one ticket can admit several people (a couple pass, a table for 10). */
    public function up(): void
    {
        if (Schema::hasTable('tickets') && !Schema::hasColumn('tickets', 'admits')) {
            Schema::table('tickets', function (Blueprint $table): void {
                $table->unsignedSmallInteger('admits')->default(1);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tickets') && Schema::hasColumn('tickets', 'admits')) {
            Schema::table('tickets', function (Blueprint $table): void {
                $table->dropColumn('admits');
            });
        }
    }
};
