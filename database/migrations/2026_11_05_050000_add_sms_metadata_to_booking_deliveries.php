<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_deliveries', function (Blueprint $table): void {
            $table->string('channel', 24)->nullable()->after('kind');
            $table->char('recipient_hash', 64)->nullable()->after('channel');
            $table->string('provider_reference', 191)->nullable()->after('recipient_hash');
            $table->index(['channel', 'status', 'updated_at'], 'booking_delivery_channel_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('booking_deliveries', function (Blueprint $table): void {
            $table->dropIndex('booking_delivery_channel_status_index');
            $table->dropColumn(['channel', 'recipient_hash', 'provider_reference']);
        });
    }
};
