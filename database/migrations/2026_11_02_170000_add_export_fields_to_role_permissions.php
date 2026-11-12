<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('role_permissions', function (Blueprint $table): void {
            $table->longText('export_fields')->nullable()->after('permissions');
        });
    }

    public function down(): void
    {
        Schema::table('role_permissions', function (Blueprint $table): void {
            $table->dropColumn('export_fields');
        });
    }
};
