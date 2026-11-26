<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funnel_analytics_events', function (Blueprint $table): void {
            $table->string('page_key', 24)->nullable();
            $table->string('referrer_host', 253)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('funnel_analytics_events', function (Blueprint $table): void {
            $table->dropColumn(['page_key', 'referrer_host']);
        });
    }
};
