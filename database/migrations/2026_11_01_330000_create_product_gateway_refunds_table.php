<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('product_gateway_refunds')) {
            return;
        }
        Schema::create('product_gateway_refunds', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('product_payment_intent_id');
            $table->unsignedBigInteger('product_order_id')->unique();
            $table->unsignedBigInteger('sale_transaction_id');
            $table->unsignedBigInteger('requested_by_admin_id');
            $table->string('gateway', 32);
            $table->string('gateway_reference', 100);
            $table->string('gateway_refund_id', 100)->nullable()->index();
            $table->string('gateway_status', 64)->nullable();
            $table->decimal('amount', 18, 2);
            $table->string('currency', 10);
            $table->text('reason');
            $table->boolean('restore_inventory')->default(false);
            $table->string('status', 32)->default('requested');
            $table->text('gateway_response')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('financial_applied_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'updated_at']);
            $table->foreign('product_payment_intent_id')->references('id')->on('product_payment_intents')->cascadeOnDelete();
            $table->foreign('product_order_id')->references('id')->on('product_orders')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_gateway_refunds');
    }
};
