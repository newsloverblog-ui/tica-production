<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_tickets', function (Blueprint $table): void {
            $table->unsignedBigInteger('checked_in_scanner_staff_id')
                ->nullable()
                ->index()
                ->after('checked_in_scanner_device_id');
        });

        Schema::table('scanner_check_in_conflicts', function (Blueprint $table): void {
            $table->unsignedBigInteger('original_scanner_staff_id')->nullable()->index()
                ->after('attempted_scanner_device_id');
            $table->unsignedBigInteger('attempted_scanner_staff_id')->nullable()->index()
                ->after('original_scanner_staff_id');
        });
    }

    public function down(): void
    {
        Schema::table('scanner_check_in_conflicts', function (Blueprint $table): void {
            $table->dropIndex(['original_scanner_staff_id']);
            $table->dropIndex(['attempted_scanner_staff_id']);
            $table->dropColumn(['original_scanner_staff_id', 'attempted_scanner_staff_id']);
        });

        Schema::table('booking_tickets', function (Blueprint $table): void {
            $table->dropIndex(['checked_in_scanner_staff_id']);
            $table->dropColumn('checked_in_scanner_staff_id');
        });
    }
};
