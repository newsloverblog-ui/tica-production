<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_ticket_transfers', function (Blueprint $table): void {
            $table->string('delivery_status', 32)->default('pending')->index()->after('status');
            $table->unsignedSmallInteger('delivery_attempts')->default(0)->after('delivery_status');
            $table->text('last_error')->nullable()->after('delivery_attempts');
            $table->timestamp('processing_at')->nullable()->index()->after('last_error');
            $table->timestamp('sent_at')->nullable()->after('processing_at');
            $table->timestamp('alerted_at')->nullable()->index()->after('sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('booking_ticket_transfers', function (Blueprint $table): void {
            $table->dropIndex(['delivery_status']);
            $table->dropIndex(['processing_at']);
            $table->dropIndex(['alerted_at']);
        });

        Schema::table('booking_ticket_transfers', function (Blueprint $table): void {
            $table->dropColumn([
                'delivery_status', 'delivery_attempts', 'last_error',
                'processing_at', 'sent_at', 'alerted_at',
            ]);
        });
    }
};
