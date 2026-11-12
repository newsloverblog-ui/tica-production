<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('gateway', 40)->nullable()->after('transcation_id');
            $table->string('gateway_reference', 191)->nullable()->after('gateway');
            $table->unique(['gateway', 'gateway_reference'], 'transactions_gateway_reference_unique');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropUnique('transactions_gateway_reference_unique');
            $table->dropColumn(['gateway', 'gateway_reference']);
        });
    }
};
