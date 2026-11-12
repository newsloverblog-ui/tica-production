<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('earnings') || Schema::hasColumn('earnings', 'singleton_key')) {
            return;
        }

        $count = DB::table('earnings')->count();
        if ($count > 1) {
            throw new \LogicException('Multiple platform earnings rows require finance reconciliation before migration.');
        }

        if ($count === 0) {
            DB::table('earnings')->insert([
                'total_revenue' => 0,
                'total_earning' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('earnings', function (Blueprint $table): void {
            $table->unsignedTinyInteger('singleton_key')->default(1)->unique();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('earnings') || !Schema::hasColumn('earnings', 'singleton_key')) {
            return;
        }

        Schema::table('earnings', function (Blueprint $table): void {
            $table->dropUnique('earnings_singleton_key_unique');
            $table->dropColumn('singleton_key');
        });
    }
};
