<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Event settings from the TICA design's create-event page: who can come,
 * listing visibility, stock and waitlist display, hybrid events, when the
 * join link appears, and an optional question for every attendee.
 */
return new class extends Migration
{
  private const EVENT_COLUMNS = ['age_restriction', 'visibility', 'show_remaining', 'waitlist_enabled', 'is_hybrid', 'join_reveal_minutes', 'attendee_question'];

  public function up(): void
  {
    Schema::table('events', function (Blueprint $table) {
      if (!Schema::hasColumn('events', 'age_restriction')) {
        $table->string('age_restriction', 120)->nullable();
      }
      if (!Schema::hasColumn('events', 'visibility')) {
        $table->string('visibility', 16)->default('public');
      }
      if (!Schema::hasColumn('events', 'show_remaining')) {
        $table->boolean('show_remaining')->default(true);
      }
      if (!Schema::hasColumn('events', 'waitlist_enabled')) {
        $table->boolean('waitlist_enabled')->default(true);
      }
      if (!Schema::hasColumn('events', 'is_hybrid')) {
        $table->boolean('is_hybrid')->default(false);
      }
      if (!Schema::hasColumn('events', 'join_reveal_minutes')) {
        $table->unsignedSmallInteger('join_reveal_minutes')->default(15);
      }
      if (!Schema::hasColumn('events', 'attendee_question')) {
        $table->string('attendee_question', 200)->nullable();
      }
    });

    if (!Schema::hasColumn('booking_tickets', 'attendee_answer')) {
      Schema::table('booking_tickets', function (Blueprint $table) {
        $table->string('attendee_answer', 500)->nullable();
      });
    }
  }

  public function down(): void
  {
    if (Schema::hasColumn('booking_tickets', 'attendee_answer')) {
      Schema::table('booking_tickets', fn (Blueprint $table) => $table->dropColumn('attendee_answer'));
    }
    $existing = array_values(array_filter(self::EVENT_COLUMNS, fn ($column) => Schema::hasColumn('events', $column)));
    if ($existing !== []) {
      Schema::table('events', fn (Blueprint $table) => $table->dropColumn($existing));
    }
  }
};
