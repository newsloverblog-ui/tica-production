<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'event_registration_allocations' => 'access_token_hash',
            'event_registration_allocation_claims' => 'token_hash',
        ] as $tableName => $after) {
            if (!Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'dispatch_token')) {
                continue;
            }
            Schema::table($tableName, function (Blueprint $table) use ($after): void {
                $table->text('dispatch_token')->nullable()->after($after);
                $table->unsignedSmallInteger('dispatch_attempts')->default(0)->after('dispatch_token');
                $table->timestamp('dispatch_last_attempt_at')->nullable()->after('dispatch_attempts');
                $table->text('dispatch_last_error')->nullable()->after('dispatch_last_attempt_at');
            });
        }
    }

    public function down(): void
    {
        foreach (['event_registration_allocation_claims', 'event_registration_allocations'] as $tableName) {
            if (!Schema::hasTable($tableName) || !Schema::hasColumn($tableName, 'dispatch_token')) {
                continue;
            }
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn([
                    'dispatch_token', 'dispatch_attempts',
                    'dispatch_last_attempt_at', 'dispatch_last_error',
                ]);
            });
        }
    }
};
