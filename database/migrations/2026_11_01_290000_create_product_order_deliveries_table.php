<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('product_order_deliveries')) {
            return;
        }

        Schema::create('product_order_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_order_id');
            $table->string('kind', 64);
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('processing_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('alerted_at')->nullable();
            $table->timestamps();

            $table->unique(['product_order_id', 'kind'], 'product_order_delivery_unique');
            $table->index(['status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_order_deliveries');
    }
};
