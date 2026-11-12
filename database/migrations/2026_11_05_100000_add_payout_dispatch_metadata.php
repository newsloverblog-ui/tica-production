<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('payout_transfers') || Schema::hasColumn('payout_transfers', 'dispatch_attempts')) {
            return;
        }
        Schema::table('payout_transfers', function (Blueprint $table): void {
            $table->unsignedSmallInteger('dispatch_attempts')->default(0)->after('attempts');
            $table->timestamp('dispatch_last_attempt_at')->nullable()->after('dispatch_attempts');
            $table->text('dispatch_last_error')->nullable()->after('dispatch_last_attempt_at');
            $table->timestamp('dispatch_alerted_at')->nullable()->after('dispatch_last_error');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('payout_transfers') || !Schema::hasColumn('payout_transfers', 'dispatch_attempts')) {
            return;
        }
        Schema::table('payout_transfers', function (Blueprint $table): void {
            $table->dropColumn([
                'dispatch_attempts', 'dispatch_last_attempt_at',
                'dispatch_last_error', 'dispatch_alerted_at',
            ]);
        });
    }
};
