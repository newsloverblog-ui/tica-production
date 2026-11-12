<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('ticket_reservations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('token');
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('ticket_id');
            $table->string('variation_name')->nullable();
            $table->unsignedInteger('quantity');
            $table->string('status', 24)->default('active');
            $table->string('session_hash', 64)->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->index(['token', 'status']);
            $table->index(['status', 'expires_at']);
            $table->index(['event_id', 'ticket_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('ticket_reservations');
    }
};

