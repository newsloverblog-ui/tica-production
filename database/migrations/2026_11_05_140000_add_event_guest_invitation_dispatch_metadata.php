<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('event_guest_invitations')
            || Schema::hasColumn('event_guest_invitations', 'dispatch_token')) {
            return;
        }

        Schema::table('event_guest_invitations', function (Blueprint $table): void {
            $table->text('dispatch_token')->nullable()->after('token_hash');
            $table->unsignedSmallInteger('dispatch_attempts')->default(0)->after('dispatch_token');
            $table->timestamp('dispatch_last_attempt_at')->nullable()->after('dispatch_attempts');
            $table->text('dispatch_last_error')->nullable()->after('dispatch_last_attempt_at');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('event_guest_invitations')
            || !Schema::hasColumn('event_guest_invitations', 'dispatch_token')) {
            return;
        }

        Schema::table('event_guest_invitations', function (Blueprint $table): void {
            $table->dropColumn([
                'dispatch_token', 'dispatch_attempts',
                'dispatch_last_attempt_at', 'dispatch_last_error',
            ]);
        });
    }
};
