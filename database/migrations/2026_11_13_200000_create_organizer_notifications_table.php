<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** In-app notifications for organisers: what TICA decided about their account, payouts and promotions. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('organizer_notifications')) {
            return;
        }
        Schema::create('organizer_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organizer_id');
            $table->string('kind', 40);
            $table->string('title', 191);
            $table->string('body', 500)->nullable();
            $table->string('url', 500)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['organizer_id', 'read_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizer_notifications');
    }
};
