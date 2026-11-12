<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('organizer_payout_accounts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('organizer_id');
            $table->string('provider', 30)->default('paystack');
            $table->string('country_code', 2)->default('NG');
            $table->string('currency', 8)->default('NGN');
            $table->string('bank_code', 30);
            $table->string('bank_name');
            $table->text('account_number');
            $table->char('account_number_hash', 64);
            $table->string('account_last4', 4);
            $table->string('resolved_account_name');
            $table->string('name_match_status', 24)->default('matched');
            $table->string('status', 24)->default('verified');
            $table->boolean('is_default')->default(true);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['organizer_id', 'status', 'is_default'], 'payout_account_owner_status_idx');
            $table->unique(
                ['provider', 'bank_code', 'account_number_hash'],
                'payout_account_destination_unique'
            );
        });

        Schema::table('withdraws', function (Blueprint $table) {
            $table->unsignedBigInteger('payout_account_id')->nullable()->after('method_id')->index();
        });
    }

    public function down()
    {
        Schema::table('withdraws', function (Blueprint $table) {
            $table->dropIndex(['payout_account_id']);
            $table->dropColumn('payout_account_id');
        });
        Schema::dropIfExists('organizer_payout_accounts');
    }
};
