<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Admin review of payout accounts whose bank name didn't match the verified identity. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizer_payout_accounts', function (Blueprint $table) {
            if (!Schema::hasColumn('organizer_payout_accounts', 'review_reason')) {
                $table->string('review_reason', 500)->nullable();
            }
            if (!Schema::hasColumn('organizer_payout_accounts', 'reviewed_by_admin_id')) {
                $table->unsignedBigInteger('reviewed_by_admin_id')->nullable();
            }
            if (!Schema::hasColumn('organizer_payout_accounts', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('organizer_payout_accounts', function (Blueprint $table) {
            $table->dropColumn(['review_reason', 'reviewed_by_admin_id', 'reviewed_at']);
        });
    }
};
