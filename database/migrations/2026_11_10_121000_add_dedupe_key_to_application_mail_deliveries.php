<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_mail_deliveries', function (Blueprint $table): void {
            $table->char('dedupe_key', 64)->nullable()->after('recipient_hash')->index();
        });
    }

    public function down(): void
    {
        Schema::table('application_mail_deliveries', fn (Blueprint $table) => $table->dropColumn('dedupe_key'));
    }
};
