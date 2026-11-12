<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_deliveries', function (Blueprint $table): void {
            $table->unique('provider_reference', 'booking_delivery_provider_reference_unique');
        });
    }

    public function down(): void
    {
        Schema::table('booking_deliveries', function (Blueprint $table): void {
            $table->dropUnique('booking_delivery_provider_reference_unique');
        });
    }
};
