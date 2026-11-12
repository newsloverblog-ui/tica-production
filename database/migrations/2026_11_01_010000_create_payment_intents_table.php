<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('payment_intents', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('gateway', 40);
            $table->string('reference', 191);
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('booking_id')->nullable();
            $table->string('status', 32)->default('initialized');
            $table->decimal('amount', 18, 2);
            $table->string('currency', 8);
            $table->string('email');
            $table->longText('payload');
            $table->text('last_error')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['gateway', 'reference']);
            $table->index(['status', 'expires_at']);
            $table->index('booking_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('payment_intents');
    }
};

