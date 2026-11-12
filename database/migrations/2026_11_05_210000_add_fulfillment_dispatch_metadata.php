<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('booking_deliveries')
            && !Schema::hasColumn('booking_deliveries', 'dispatch_attempts')) {
            Schema::table('booking_deliveries', function (Blueprint $table): void {
                $table->unsignedSmallInteger('dispatch_attempts')->default(0)->after('attempts');
                $table->timestamp('dispatch_last_attempt_at')->nullable()->after('dispatch_attempts');
                $table->text('dispatch_last_error')->nullable()->after('dispatch_last_attempt_at');
                $table->index(
                    ['kind', 'status', 'dispatch_last_attempt_at'],
                    'booking_delivery_dispatch_pending_index'
                );
            });
        }

        if (Schema::hasTable('product_order_deliveries')
            && !Schema::hasColumn('product_order_deliveries', 'dispatch_attempts')) {
            Schema::table('product_order_deliveries', function (Blueprint $table): void {
                $table->unsignedSmallInteger('dispatch_attempts')->default(0)->after('attempts');
                $table->timestamp('dispatch_last_attempt_at')->nullable()->after('dispatch_attempts');
                $table->text('dispatch_last_error')->nullable()->after('dispatch_last_attempt_at');
                $table->index(
                    ['kind', 'status', 'dispatch_last_attempt_at'],
                    'product_delivery_dispatch_pending_index'
                );
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('product_order_deliveries')
            && Schema::hasColumn('product_order_deliveries', 'dispatch_attempts')) {
            Schema::table('product_order_deliveries', function (Blueprint $table): void {
                $table->dropIndex('product_delivery_dispatch_pending_index');
                $table->dropColumn([
                    'dispatch_attempts',
                    'dispatch_last_attempt_at',
                    'dispatch_last_error',
                ]);
            });
        }

        if (Schema::hasTable('booking_deliveries')
            && Schema::hasColumn('booking_deliveries', 'dispatch_attempts')) {
            Schema::table('booking_deliveries', function (Blueprint $table): void {
                $table->dropIndex('booking_delivery_dispatch_pending_index');
                $table->dropColumn([
                    'dispatch_attempts',
                    'dispatch_last_attempt_at',
                    'dispatch_last_error',
                ]);
            });
        }
    }
};
