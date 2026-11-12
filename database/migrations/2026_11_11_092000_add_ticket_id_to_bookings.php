<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BookingController::storeData records the single ticket an online booking
 * was made for, and TicketIssuanceService reads it back, but no migration
 * created the column, so every new booking failed to insert.
 */
return new class extends Migration
{
  public function up(): void
  {
    if (!Schema::hasColumn('bookings', 'ticket_id')) {
      Schema::table('bookings', function (Blueprint $table) {
        $table->unsignedBigInteger('ticket_id')->nullable()->index();
      });
    }
  }

  public function down(): void
  {
    if (Schema::hasColumn('bookings', 'ticket_id')) {
      Schema::table('bookings', function (Blueprint $table) {
        $table->dropIndex(['ticket_id']);
        $table->dropColumn('ticket_id');
      });
    }
  }
};
