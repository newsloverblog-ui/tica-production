<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_registration_attendees', function (Blueprint $table): void {
            $table->string('variation_name', 191)->nullable()->after('ticket_id');
            $table->index(['ticket_id', 'variation_name'], 'registration_attendee_ticket_variation');
        });
    }

    public function down(): void
    {
        Schema::table('event_registration_attendees', function (Blueprint $table): void {
            $table->dropIndex('registration_attendee_ticket_variation');
            $table->dropColumn('variation_name');
        });
    }
};
