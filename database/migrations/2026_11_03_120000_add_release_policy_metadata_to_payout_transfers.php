<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payout_transfers', function (Blueprint $table): void {
            $table->unsignedBigInteger('requested_by_admin_id')->nullable()->change();
            $table->string('release_source', 24)->default('manual')->after('requested_by_admin_id');
            $table->string('policy_version', 64)->nullable()->after('release_source');
        });
    }

    public function down(): void
    {
        Schema::table('payout_transfers', function (Blueprint $table): void {
            $table->dropColumn(['release_source', 'policy_version']);
            // Keep the actor nullable: automatic release rows legitimately have
            // no administrator actor and must remain auditable after rollback.
        });
    }
};
