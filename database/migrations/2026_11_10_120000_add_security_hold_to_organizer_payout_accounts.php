<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizer_payout_accounts', function (Blueprint $table): void {
            $table->timestamp('hold_until')->nullable()->after('verified_at')->index();
            $table->timestamp('locked_at')->nullable()->after('hold_until');
        });
    }

    public function down(): void
    {
        Schema::table('organizer_payout_accounts', function (Blueprint $table): void {
            $table->dropColumn(['hold_until', 'locked_at']);
        });
    }
};
