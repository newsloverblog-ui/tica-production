<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_guest_invitations', function (Blueprint $table): void {
            $table->timestamp('anonymized_at')->nullable()->after('last_error')->index();
            $table->string('anonymization_reason', 64)->nullable()->after('anonymized_at');
        });
    }

    public function down(): void
    {
        Schema::table('event_guest_invitations', function (Blueprint $table): void {
            $table->dropColumn(['anonymized_at', 'anonymization_reason']);
        });
    }
};
