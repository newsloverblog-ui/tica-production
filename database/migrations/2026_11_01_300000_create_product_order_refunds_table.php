<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('product_order_refunds')) {
            Schema::create('product_order_refunds', function (Blueprint $table): void {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->unsignedBigInteger('product_order_id');
                $table->unsignedBigInteger('sale_transaction_id');
                $table->unsignedBigInteger('requested_by_admin_id');
                $table->string('external_reference', 191)->unique();
                $table->decimal('amount', 18, 2);
                $table->string('currency', 8)->nullable();
                $table->text('reason');
                $table->boolean('restore_inventory')->default(false);
                $table->string('status', 32)->default('processed');
                $table->timestamp('financial_applied_at')->nullable();
                $table->timestamp('inventory_restored_at')->nullable();
                $table->timestamp('processed_at');
                $table->timestamps();

                $table->unique('product_order_id');
                $table->index(['status', 'created_at']);
            });
        }

        if (Schema::hasTable('product_orders')) {
            $addRefundedAmount = !Schema::hasColumn('product_orders', 'refunded_amount');
            $addRefundStatus = !Schema::hasColumn('product_orders', 'refund_status');
            $addRefundedAt = !Schema::hasColumn('product_orders', 'refunded_at');
            Schema::table('product_orders', function (Blueprint $table) use ($addRefundedAmount, $addRefundStatus, $addRefundedAt): void {
                if ($addRefundedAmount) {
                    $table->decimal('refunded_amount', 18, 2)->default(0)->after('payment_status');
                }
                if ($addRefundStatus) {
                    $table->string('refund_status', 32)->nullable()->after('refunded_amount');
                }
                if ($addRefundedAt) {
                    $table->timestamp('refunded_at')->nullable()->after('refund_status');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('product_orders')) {
            $columns = array_values(array_filter([
                Schema::hasColumn('product_orders', 'refunded_at') ? 'refunded_at' : null,
                Schema::hasColumn('product_orders', 'refund_status') ? 'refund_status' : null,
                Schema::hasColumn('product_orders', 'refunded_amount') ? 'refunded_amount' : null,
            ]));
            Schema::table('product_orders', function (Blueprint $table) use ($columns): void {
                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            });
        }
        Schema::dropIfExists('product_order_refunds');
    }
};
