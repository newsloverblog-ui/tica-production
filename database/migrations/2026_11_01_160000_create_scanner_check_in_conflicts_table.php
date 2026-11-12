<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('booking_tickets', function (Blueprint $table) {
            $table->unsignedBigInteger('checked_in_scanner_device_id')->nullable()->index()->after('checked_in_by_organizer_id');
        });

        Schema::create('scanner_check_in_conflicts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organizer_id')->index();
            $table->unsignedBigInteger('event_id')->index();
            $table->unsignedBigInteger('booking_ticket_id')->index();
            $table->unsignedBigInteger('original_scanner_device_id')->index();
            $table->unsignedBigInteger('attempted_scanner_device_id')->index();
            $table->string('source', 20)->default('scanner');
            $table->unsignedInteger('occurrences')->default(1);
            $table->timestamp('first_detected_at');
            $table->timestamp('last_detected_at');
            $table->timestamp('resolved_at')->nullable()->index();
            $table->unsignedBigInteger('resolved_by_organizer_id')->nullable();
            $table->string('resolution_note', 500)->nullable();
            $table->timestamps();

            $table->unique(
                ['booking_ticket_id', 'original_scanner_device_id', 'attempted_scanner_device_id'],
                'scanner_conflict_ticket_gate_unique'
            );
            $table->index(['organizer_id', 'resolved_at', 'last_detected_at'], 'scanner_conflict_review_index');
        });
    }

    public function down()
    {
        Schema::dropIfExists('scanner_check_in_conflicts');
        Schema::table('booking_tickets', function (Blueprint $table) {
            $table->dropColumn('checked_in_scanner_device_id');
        });
    }
};
