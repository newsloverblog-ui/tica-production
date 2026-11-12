<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_intents', function (Blueprint $table) {
            $table->string('gateway_transaction_id', 191)->nullable()->after('reference');
            $table->unique(['gateway', 'gateway_transaction_id'], 'payment_intents_gateway_transaction_unique');
        });
    }

    public function down(): void
    {
        Schema::table('payment_intents', function (Blueprint $table) {
            $table->dropUnique('payment_intents_gateway_transaction_unique');
            $table->dropColumn('gateway_transaction_id');
        });
    }
};
