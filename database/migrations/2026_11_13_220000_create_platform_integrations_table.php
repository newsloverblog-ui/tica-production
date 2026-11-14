<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin > Integrations: keys and switches for services that used to live only
 * in server environment variables (SMS, web push, file storage, payout extras),
 * plus the last connection test of every integration. Settings are stored
 * encrypted with the app key.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('platform_integrations')) {
            return;
        }
        Schema::create('platform_integrations', function (Blueprint $table) {
            $table->string('key', 60)->primary();
            $table->boolean('enabled')->default(false);
            $table->text('settings')->nullable();
            $table->timestamp('tested_at')->nullable();
            $table->boolean('test_ok')->nullable();
            $table->string('test_message', 500)->nullable();
            $table->unsignedBigInteger('updated_by_admin_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_integrations');
    }
};
