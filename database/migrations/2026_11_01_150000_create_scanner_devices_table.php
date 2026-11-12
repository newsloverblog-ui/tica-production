<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('scanner_devices', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('organizer_id')->index();
            $table->string('name', 100);
            $table->char('secret_hash', 64);
            $table->string('status', 20)->default('active')->index();
            $table->timestamp('last_seen_at')->nullable();
            $table->string('last_ip', 45)->nullable();
            $table->text('last_user_agent')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->unsignedBigInteger('revoked_by_organizer_id')->nullable();
            $table->timestamps();

            $table->index(['organizer_id', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('scanner_devices');
    }
};
