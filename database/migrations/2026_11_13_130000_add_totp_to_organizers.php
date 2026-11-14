<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Authenticator-app two-factor for organisers. Secret and recovery codes are stored encrypted. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizers', function (Blueprint $table) {
            if (!Schema::hasColumn('organizers', 'totp_secret')) {
                $table->text('totp_secret')->nullable();
            }
            if (!Schema::hasColumn('organizers', 'totp_recovery_codes')) {
                $table->text('totp_recovery_codes')->nullable();
            }
            if (!Schema::hasColumn('organizers', 'totp_confirmed_at')) {
                $table->timestamp('totp_confirmed_at')->nullable();
            }
            if (!Schema::hasColumn('organizers', 'totp_last_step')) {
                $table->bigInteger('totp_last_step')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('organizers', function (Blueprint $table) {
            $table->dropColumn(['totp_secret', 'totp_recovery_codes', 'totp_confirmed_at', 'totp_last_step']);
        });
    }
};
