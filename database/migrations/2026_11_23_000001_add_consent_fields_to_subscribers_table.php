<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscribers', function (Blueprint $table): void {
            $table->string('source', 40)->default('website')->after('email_id');
            $table->timestamp('subscribed_at')->nullable()->after('source');
            $table->timestamp('unsubscribed_at')->nullable()->index()->after('subscribed_at');
            $table->char('consent_ip_hash', 64)->nullable()->after('unsubscribed_at');
        });
    }

    public function down(): void
    {
        Schema::table('subscribers', function (Blueprint $table): void {
            $table->dropIndex(['unsubscribed_at']);
            $table->dropColumn(['source', 'subscribed_at', 'unsubscribed_at', 'consent_ip_hash']);
        });
    }
};
