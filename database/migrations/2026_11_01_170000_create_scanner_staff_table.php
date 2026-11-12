<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('scanner_staff', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organizer_id')->index();
            $table->string('name', 100);
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role', 30)->default('check_in')->index();
            $table->string('status', 20)->default('active')->index();
            $table->unsignedInteger('auth_version')->default(1);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->index(['organizer_id', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('scanner_staff');
    }
};
