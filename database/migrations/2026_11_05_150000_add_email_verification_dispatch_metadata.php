<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'organizer_email_verification_deliveries',
            'customer_email_verification_deliveries',
        ] as $tableName) {
            if (!Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'dispatch_attempts')) {
                continue;
            }
            Schema::table($tableName, function (Blueprint $table): void {
                $table->unsignedSmallInteger('dispatch_attempts')->default(0)->after('attempts');
                $table->timestamp('dispatch_last_attempt_at')->nullable()->after('dispatch_attempts');
                $table->text('dispatch_last_error')->nullable()->after('dispatch_last_attempt_at');
            });
        }
    }

    public function down(): void
    {
        foreach ([
            'organizer_email_verification_deliveries',
            'customer_email_verification_deliveries',
        ] as $tableName) {
            if (!Schema::hasTable($tableName) || !Schema::hasColumn($tableName, 'dispatch_attempts')) {
                continue;
            }
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn([
                    'dispatch_attempts', 'dispatch_last_attempt_at', 'dispatch_last_error',
                ]);
            });
        }
    }
};
