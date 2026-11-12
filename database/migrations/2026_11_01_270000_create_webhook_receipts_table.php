<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_receipts', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('gateway', 32)->index();
            $table->string('event_type', 100)->nullable()->index();
            $table->char('body_hash', 64)->index();
            $table->char('reference_hash', 64)->nullable()->index();
            $table->string('request_id', 100)->nullable()->index();
            $table->boolean('signature_present')->default(false);
            $table->boolean('signature_valid')->nullable();
            $table->string('outcome', 32)->default('received')->index();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('processing_ms')->nullable();
            $table->string('error_code', 100)->nullable();
            $table->timestamp('received_at')->index();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['gateway', 'received_at'], 'webhook_receipts_gateway_received_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_receipts');
    }
};
