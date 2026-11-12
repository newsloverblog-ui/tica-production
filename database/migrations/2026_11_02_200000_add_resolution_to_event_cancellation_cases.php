<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_cancellation_cases', function (Blueprint $table): void {
            $table->unsignedBigInteger('payment_refund_dispute_id')->nullable()->after('payment_refund_id')->index();
            $table->unsignedBigInteger('resolved_by_admin_id')->nullable()->after('payment_refund_dispute_id');
            $table->string('external_refund_reference', 191)->nullable()->after('resolved_by_admin_id')->unique();
            $table->text('resolution')->nullable()->after('reason');
            $table->timestamp('resolved_at')->nullable()->after('resolution');
        });
    }

    public function down(): void
    {
        Schema::table('event_cancellation_cases', function (Blueprint $table): void {
            $table->dropIndex(['payment_refund_dispute_id']);
            $table->dropUnique(['external_refund_reference']);
            $table->dropColumn([
                'payment_refund_dispute_id', 'resolved_by_admin_id', 'external_refund_reference',
                'resolution', 'resolved_at',
            ]);
        });
    }
};
