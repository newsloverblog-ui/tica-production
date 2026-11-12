<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_refunds', function (Blueprint $table) {
            $table->unsignedBigInteger('requested_by_admin_id')->nullable()->change();
            $table->unsignedBigInteger('requested_by_organizer_id')->nullable()->after('requested_by_admin_id');
            $table->unsignedBigInteger('reviewed_by_admin_id')->nullable()->after('requested_by_organizer_id');
            $table->timestamp('reviewed_at')->nullable()->after('requested_at');
            $table->text('rejection_reason')->nullable()->after('reason');

            $table->index(['status', 'organizer_id', 'created_at'], 'payment_refunds_approval_queue');
        });
    }

    public function down(): void
    {
        Schema::table('payment_refunds', function (Blueprint $table) {
            $table->dropIndex('payment_refunds_approval_queue');
            $table->dropColumn([
                'requested_by_organizer_id',
                'reviewed_by_admin_id',
                'reviewed_at',
                'rejection_reason',
            ]);
        });
    }
};
