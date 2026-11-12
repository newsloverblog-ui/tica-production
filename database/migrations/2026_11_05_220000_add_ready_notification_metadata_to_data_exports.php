<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('data_exports')
            || Schema::hasColumn('data_exports', 'ready_notification_delivery_id')) {
            return;
        }

        Schema::table('data_exports', function (Blueprint $table): void {
            $table->unsignedBigInteger('ready_notification_delivery_id')->nullable()->unique()
                ->after('dispatch_alerted_at');
            $table->unsignedSmallInteger('ready_notification_attempts')->default(0)
                ->after('ready_notification_delivery_id');
            $table->timestamp('ready_notification_last_attempt_at')->nullable()
                ->after('ready_notification_attempts');
            $table->text('ready_notification_last_error')->nullable()
                ->after('ready_notification_last_attempt_at');
            $table->timestamp('ready_notification_alerted_at')->nullable()
                ->after('ready_notification_last_error');
            $table->timestamp('ready_notified_at')->nullable()
                ->after('ready_notification_alerted_at');

            $table->index(
                ['status', 'ready_notification_delivery_id', 'expires_at'],
                'data_exports_ready_notification_idx'
            );
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('data_exports')
            || !Schema::hasColumn('data_exports', 'ready_notification_delivery_id')) {
            return;
        }

        Schema::table('data_exports', function (Blueprint $table): void {
            $table->dropIndex('data_exports_ready_notification_idx');
            $table->dropUnique(['ready_notification_delivery_id']);
            $table->dropColumn([
                'ready_notification_delivery_id',
                'ready_notification_attempts',
                'ready_notification_last_attempt_at',
                'ready_notification_last_error',
                'ready_notification_alerted_at',
                'ready_notified_at',
            ]);
        });
    }
};
