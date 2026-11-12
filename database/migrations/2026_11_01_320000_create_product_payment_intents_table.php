<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('product_payment_intents')) {
            return;
        }

        Schema::create('product_payment_intents', function (Blueprint $table): void {
            $table->id();
            $table->string('gateway', 32);
            $table->string('reference', 100);
            $table->unsignedBigInteger('product_order_id');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('status', 32)->default('created');
            $table->decimal('amount', 18, 2);
            $table->string('currency', 10);
            $table->string('email');
            $table->text('authorization_url')->nullable();
            $table->string('gateway_transaction_id', 100)->nullable();
            $table->unsignedInteger('verification_attempts')->default(0);
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['gateway', 'reference'], 'product_payment_intents_gateway_reference_unique');
            $table->unique('product_order_id', 'product_payment_intents_order_unique');
            $table->index(['status', 'expires_at'], 'product_payment_intents_expiry_index');
            $table->foreign('product_order_id')->references('id')->on('product_orders')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_payment_intents');
    }
};
