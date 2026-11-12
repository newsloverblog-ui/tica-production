<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->char('checkout_submission_hash', 64)
                ->nullable()
                ->unique()
                ->after('booking_id');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropUnique(['checkout_submission_hash']);
            $table->dropColumn('checkout_submission_hash');
        });
    }
};
