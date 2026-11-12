<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('payment_intents', function (Blueprint $table) {
            $table->text('authorization_url')->nullable()->after('payload');
            $table->unsignedInteger('verification_attempts')->default(0)->after('last_error');
            $table->timestamp('last_verified_at')->nullable()->after('verification_attempts');
        });
    }

    public function down()
    {
        Schema::table('payment_intents', function (Blueprint $table) {
            $table->dropColumn(['authorization_url', 'verification_attempts', 'last_verified_at']);
        });
    }
};
