<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_ticket_delivery_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('booking_id');
            $table->string('requested_by_type', 20);
            $table->unsignedBigInteger('requested_by_id');
            $table->char('recipient_email_hash', 64);
            $table->string('status', 24)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('processing_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('alerted_at')->nullable();
            $table->timestamps();

            $table->index(['booking_id', 'created_at']);
            $table->index(['requested_by_type', 'requested_by_id', 'created_at'], 'ticket_delivery_request_actor_index');
            $table->index(['status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_ticket_delivery_requests');
    }
};
