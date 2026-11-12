<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_incidents', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->char('key_hash', 64)->unique();
            $table->string('incident_key', 191);
            $table->string('severity', 16)->index();
            $table->string('status', 16)->default('open')->index();
            $table->string('title', 500);
            $table->unsignedBigInteger('occurrence_count')->default(0);
            $table->longText('context')->nullable();
            $table->json('delivery_status')->nullable();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at')->index();
            $table->timestamp('last_alerted_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->unsignedBigInteger('resolved_by_admin_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_incidents');
    }
};
