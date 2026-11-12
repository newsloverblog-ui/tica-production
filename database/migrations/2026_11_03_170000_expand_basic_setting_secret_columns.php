<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('basic_settings', function (Blueprint $table): void {
            $table->text('smtp_password')->nullable()->change();
            $table->text('google_recaptcha_secret_key')->nullable()->change();
            $table->text('facebook_app_secret')->nullable()->change();
            $table->text('google_client_secret')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Deliberately retain text capacity: narrowing encrypted values can truncate credentials.
    }
};
