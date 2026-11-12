<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['admins', 'organizers', 'customers'] as $table) {
            if (!Schema::hasColumn($table, 'two_factor_enabled_at')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->timestamp('two_factor_enabled_at')->nullable()->after('password');
                });
            }
        }

        if (!Schema::hasTable('account_login_challenges')) {
            Schema::create('account_login_challenges', function (Blueprint $table) {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->string('actor_type', 20);
                $table->unsignedBigInteger('actor_id');
                $table->string('purpose', 20);
                $table->char('email_hash', 64);
                $table->char('code_hash', 64);
                $table->char('session_hash', 64);
                $table->string('redirect_path', 500)->nullable();
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->timestamp('expires_at');
                $table->timestamp('consumed_at')->nullable();
                $table->timestamps();
                $table->index(['actor_type', 'actor_id', 'purpose']);
                $table->index(['expires_at', 'consumed_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('account_login_challenges');
        foreach (['admins', 'organizers', 'customers'] as $table) {
            if (Schema::hasColumn($table, 'two_factor_enabled_at')) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn('two_factor_enabled_at'));
            }
        }
    }
};
