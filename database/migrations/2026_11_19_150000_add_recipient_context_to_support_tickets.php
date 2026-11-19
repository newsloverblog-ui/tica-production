<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->string('recipient_type', 20)->default('tica')->after('user_type');
            $table->unsignedBigInteger('recipient_organizer_id')->nullable()->after('recipient_type');
            $table->unsignedBigInteger('event_id')->nullable()->after('recipient_organizer_id');
            $table->unsignedBigInteger('booking_id')->nullable()->after('event_id');
            $table->index(['recipient_type', 'recipient_organizer_id', 'last_message'], 'support_recipient_inbox_idx');
        });
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->dropIndex('support_recipient_inbox_idx');
            $table->dropColumn(['recipient_type', 'recipient_organizer_id', 'event_id', 'booking_id']);
        });
    }
};
