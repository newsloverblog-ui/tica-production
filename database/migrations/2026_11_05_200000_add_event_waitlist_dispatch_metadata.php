<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('event_waitlist_entries')
            || Schema::hasColumn('event_waitlist_entries', 'dispatch_attempts')) {
            return;
        }

        Schema::table('event_waitlist_entries', function (Blueprint $table): void {
            $table->unsignedSmallInteger('dispatch_attempts')->default(0)->after('attempts');
            $table->timestamp('dispatch_last_attempt_at')->nullable()->after('dispatch_attempts');
            $table->text('dispatch_last_error')->nullable()->after('dispatch_last_attempt_at');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('event_waitlist_entries')
            || !Schema::hasColumn('event_waitlist_entries', 'dispatch_attempts')) {
            return;
        }

        Schema::table('event_waitlist_entries', function (Blueprint $table): void {
            $table->dropColumn([
                'dispatch_attempts', 'dispatch_last_attempt_at', 'dispatch_last_error',
            ]);
        });
    }
};
