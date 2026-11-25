<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('withdraws', fn (Blueprint $table) => $table->json('release_approval')->nullable());
        Schema::table('payout_transfers', fn (Blueprint $table) => $table->uuid('release_approval_id')->nullable());
    }

    public function down(): void
    {
        Schema::table('payout_transfers', fn (Blueprint $table) => $table->dropColumn('release_approval_id'));
        Schema::table('withdraws', fn (Blueprint $table) => $table->dropColumn('release_approval'));
    }
};
