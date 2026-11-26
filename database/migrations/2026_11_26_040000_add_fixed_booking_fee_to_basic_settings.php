<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('basic_settings', function (Blueprint $table): void {
            $table->decimal('booking_fee_fixed', 10, 2)->default(100);
        });
    }

    public function down(): void
    {
        Schema::table('basic_settings', function (Blueprint $table): void {
            $table->dropColumn('booking_fee_fixed');
        });
    }
};
