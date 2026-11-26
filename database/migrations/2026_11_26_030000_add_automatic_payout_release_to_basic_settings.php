<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('basic_settings', function (Blueprint $table): void {
            $table->boolean('payout_automatic_release_enabled')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('basic_settings', function (Blueprint $table): void {
            $table->dropColumn('payout_automatic_release_enabled');
        });
    }
};
