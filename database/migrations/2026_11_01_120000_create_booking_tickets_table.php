<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('booking_tickets', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('booking_id')->index();
            $table->unsignedBigInteger('event_id')->index();
            $table->unsignedBigInteger('organizer_id')->nullable()->index();
            $table->unsignedBigInteger('ticket_id')->nullable()->index();
            $table->unsignedInteger('sequence');
            $table->string('variation_name')->nullable();
            $table->string('holder_name')->nullable();
            $table->string('holder_email')->nullable();
            $table->string('status', 32)->default('issued')->index();
            $table->timestamp('checked_in_at')->nullable();
            $table->unsignedBigInteger('checked_in_by_organizer_id')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_reason', 500)->nullable();
            $table->timestamps();

            $table->unique(['booking_id', 'sequence']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('booking_tickets');
    }
};
