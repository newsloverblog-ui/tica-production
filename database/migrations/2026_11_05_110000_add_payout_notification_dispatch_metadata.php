<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('payout_transfers')
            || Schema::hasColumn('payout_transfers', 'notification_dispatch_attempts')) {
            return;
        }

        Schema::table('payout_transfers', function (Blueprint $table): void {
            $table->unsignedSmallInteger('notification_dispatch_attempts')->default(0)
                ->after('dispatch_alerted_at');
            $table->timestamp('notification_dispatch_last_attempt_at')->nullable()
                ->after('notification_dispatch_attempts');
            $table->text('notification_dispatch_last_error')->nullable()
                ->after('notification_dispatch_last_attempt_at');
            $table->timestamp('notification_dispatch_alerted_at')->nullable()
                ->after('notification_dispatch_last_error');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('payout_transfers')
            || !Schema::hasColumn('payout_transfers', 'notification_dispatch_attempts')) {
            return;
        }

        Schema::table('payout_transfers', function (Blueprint $table): void {
            $table->dropColumn([
                'notification_dispatch_attempts',
                'notification_dispatch_last_attempt_at',
                'notification_dispatch_last_error',
                'notification_dispatch_alerted_at',
            ]);
        });
    }
};
