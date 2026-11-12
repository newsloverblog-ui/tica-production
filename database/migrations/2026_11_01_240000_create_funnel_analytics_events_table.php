<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funnel_analytics_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('event_name', 64)->index();
            $table->string('source', 16)->index();
            $table->unsignedBigInteger('event_id')->nullable()->index();
            $table->unsignedBigInteger('booking_id')->nullable()->index();
            $table->unsignedBigInteger('payment_intent_id')->nullable()->index();
            $table->unsignedBigInteger('organizer_id')->nullable()->index();
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->char('session_hash', 64)->nullable()->index();
            $table->string('dedupe_key', 191)->nullable()->unique();
            $table->longText('properties')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->index(['event_name', 'occurred_at']);
            $table->index(['organizer_id', 'occurred_at']);
            $table->index(['event_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funnel_analytics_events');
    }
};
