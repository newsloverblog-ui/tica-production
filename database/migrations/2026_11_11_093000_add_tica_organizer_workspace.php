<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backing data for the TICA design's organiser workspace: payout schedule and
 * saved preferences, per-event ticket transfers, organiser discount codes
 * with use limits, promoter tracking links, and team members with roles.
 */
return new class extends Migration
{
  public function up(): void
  {
    Schema::table('organizers', function (Blueprint $table) {
      if (!Schema::hasColumn('organizers', 'payout_schedule')) {
        $table->string('payout_schedule', 16)->default('manual');
      }
      if (!Schema::hasColumn('organizers', 'preferences')) {
        $table->json('preferences')->nullable();
      }
    });

    if (!Schema::hasColumn('events', 'allow_transfers')) {
      Schema::table('events', fn (Blueprint $table) => $table->boolean('allow_transfers')->default(true));
    }

    Schema::table('coupons', function (Blueprint $table) {
      if (!Schema::hasColumn('coupons', 'organizer_id')) {
        $table->unsignedBigInteger('organizer_id')->nullable()->index();
      }
      if (!Schema::hasColumn('coupons', 'max_uses')) {
        $table->unsignedInteger('max_uses')->nullable();
      }
      if (!Schema::hasColumn('coupons', 'used_count')) {
        $table->unsignedInteger('used_count')->default(0);
      }
    });

    if (!Schema::hasTable('tica_tracking_links')) {
      Schema::create('tica_tracking_links', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('organizer_id')->index();
        $table->unsignedBigInteger('event_id')->nullable()->index();
        $table->string('name', 120);
        $table->string('code', 32)->unique();
        $table->unsignedInteger('clicks')->default(0);
        $table->timestamps();
      });
    }

    if (!Schema::hasColumn('bookings', 'tracking_link_id')) {
      Schema::table('bookings', fn (Blueprint $table) => $table->unsignedBigInteger('tracking_link_id')->nullable()->index());
    }

    if (!Schema::hasTable('organizer_team_members')) {
      Schema::create('organizer_team_members', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('organizer_id')->index();
        $table->string('name', 120)->nullable();
        $table->string('email', 254);
        $table->string('role', 16);
        $table->json('event_ids')->nullable();
        $table->string('password')->nullable();
        $table->char('invite_token_hash', 64)->nullable()->unique();
        $table->timestamp('invite_expires_at')->nullable();
        $table->timestamp('accepted_at')->nullable();
        $table->string('status', 16)->default('invited');
        $table->unsignedBigInteger('scanner_staff_id')->nullable();
        $table->timestamps();
        $table->unique(['organizer_id', 'email']);
      });
    }
  }

  public function down(): void
  {
    Schema::dropIfExists('organizer_team_members');
    if (Schema::hasColumn('bookings', 'tracking_link_id')) {
      Schema::table('bookings', fn (Blueprint $table) => $table->dropColumn('tracking_link_id'));
    }
    Schema::dropIfExists('tica_tracking_links');
    foreach (['used_count', 'max_uses', 'organizer_id'] as $column) {
      if (Schema::hasColumn('coupons', $column)) {
        Schema::table('coupons', fn (Blueprint $table) => $table->dropColumn($column));
      }
    }
    if (Schema::hasColumn('events', 'allow_transfers')) {
      Schema::table('events', fn (Blueprint $table) => $table->dropColumn('allow_transfers'));
    }
    foreach (['preferences', 'payout_schedule'] as $column) {
      if (Schema::hasColumn('organizers', $column)) {
        Schema::table('organizers', fn (Blueprint $table) => $table->dropColumn($column));
      }
    }
  }
};
