<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_email_verification_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('email_hash', 64);
            $table->string('status', 32)->default('pending')->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('processing_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamp('alerted_at')->nullable();
            $table->timestamps();

            $table->index(
                ['customer_id', 'created_at'],
                'customer_email_delivery_customer_created_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_email_verification_deliveries');
    }
};
