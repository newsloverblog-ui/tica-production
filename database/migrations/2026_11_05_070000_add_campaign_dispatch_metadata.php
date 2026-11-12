<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['subscriber_campaigns', 'push_notification_campaigns'] as $table) {
            if (!Schema::hasTable($table) || Schema::hasColumn($table, 'dispatch_attempts')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->unsignedSmallInteger('dispatch_attempts')->default(0)->after('failed_count');
                $blueprint->timestamp('dispatch_last_attempt_at')->nullable()->after('dispatch_attempts');
                $blueprint->text('dispatch_last_error')->nullable()->after('dispatch_last_attempt_at');
                $blueprint->timestamp('dispatch_alerted_at')->nullable()->after('dispatch_last_error');
            });
        }
    }

    public function down(): void
    {
        foreach (['subscriber_campaigns', 'push_notification_campaigns'] as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'dispatch_attempts')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropColumn([
                    'dispatch_attempts', 'dispatch_last_attempt_at',
                    'dispatch_last_error', 'dispatch_alerted_at',
                ]);
            });
        }
    }
};
