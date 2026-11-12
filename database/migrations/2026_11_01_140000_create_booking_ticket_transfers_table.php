<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('booking_tickets', function (Blueprint $table) {
            $table->unsignedBigInteger('holder_customer_id')->nullable()->index()->after('holder_email');
        });

        Schema::create('booking_ticket_transfers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('booking_ticket_id')->index();
            $table->unsignedBigInteger('from_customer_id')->index();
            $table->string('recipient_name');
            $table->string('recipient_email')->index();
            $table->char('token_hash', 64)->unique();
            $table->string('status', 32)->default('pending')->index();
            $table->timestamp('expires_at')->index();
            $table->unsignedBigInteger('accepted_by_customer_id')->nullable()->index();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('booking_ticket_transfers');
        Schema::table('booking_tickets', function (Blueprint $table) {
            $table->dropColumn('holder_customer_id');
        });
    }
};
