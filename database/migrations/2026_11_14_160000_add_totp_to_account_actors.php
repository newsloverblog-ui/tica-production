<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['admins', 'organizers', 'customers'] as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (!Schema::hasColumn($tableName, 'two_factor_secret')) {
                    $table->text('two_factor_secret')->nullable();
                }
                if (!Schema::hasColumn($tableName, 'two_factor_recovery_codes')) {
                    $table->text('two_factor_recovery_codes')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['admins', 'organizers', 'customers'] as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }
            foreach (['two_factor_recovery_codes', 'two_factor_secret'] as $column) {
                if (Schema::hasColumn($tableName, $column)) {
                    Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn($column));
                }
            }
        }
    }
};
