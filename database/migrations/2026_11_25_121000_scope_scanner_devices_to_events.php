<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('scanner_devices', function (Blueprint $table): void {
            $table->unsignedBigInteger('event_id')->nullable()->index();
            $table->unsignedBigInteger('admission_ticket_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('scanner_devices', function (Blueprint $table): void {
            $table->dropIndex(['event_id']);
            $table->dropColumn(['event_id', 'admission_ticket_id']);
        });
    }
};
