<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The TICA design's fee model: one percentage booking fee on paid tickets,
 * and each event chooses whether buyers pay it on top ("buyer") or the
 * organiser includes it in the ticket price ("organizer").
 */
return new class extends Migration
{
  public function up(): void
  {
    if (!Schema::hasColumn('basic_settings', 'booking_fee_percentage')) {
      Schema::table('basic_settings', function (Blueprint $table) {
        $table->decimal('booking_fee_percentage', 5, 2)->default(6.00);
      });
    }

    if (!Schema::hasColumn('events', 'fee_payer')) {
      Schema::table('events', function (Blueprint $table) {
        $table->string('fee_payer', 16)->default('buyer');
      });
    }
  }

  public function down(): void
  {
    if (Schema::hasColumn('events', 'fee_payer')) {
      Schema::table('events', function (Blueprint $table) {
        $table->dropColumn('fee_payer');
      });
    }

    if (Schema::hasColumn('basic_settings', 'booking_fee_percentage')) {
      Schema::table('basic_settings', function (Blueprint $table) {
        $table->dropColumn('booking_fee_percentage');
      });
    }
  }
};
