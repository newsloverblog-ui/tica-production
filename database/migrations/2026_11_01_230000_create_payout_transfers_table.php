<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizer_payout_accounts', function (Blueprint $table) {
            // Treat provider recipient identifiers as credentials at rest. The model
            // encrypts this value, so use text to leave room for the ciphertext.
            $table->text('provider_recipient_code')->nullable()->after('provider');
            $table->timestamp('recipient_created_at')->nullable()->after('provider_recipient_code');
        });

        Schema::create('payout_transfers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('withdrawal_id')->index();
            $table->unsignedBigInteger('payout_account_id')->index();
            $table->string('gateway', 30)->default('paystack');
            $table->string('reference', 191)->unique();
            $table->text('recipient_code')->nullable();
            $table->string('gateway_transfer_code')->nullable()->index();
            $table->string('status', 32)->default('queued');
            $table->decimal('amount', 18, 2);
            $table->string('currency', 8);
            $table->unsignedBigInteger('requested_by_admin_id');
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('verification_attempts')->default(0);
            $table->string('gateway_status', 40)->nullable();
            $table->longText('gateway_response')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamp('webhook_received_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('organizer_notified_status', 32)->nullable();
            $table->timestamp('organizer_notified_at')->nullable();
            $table->string('finance_notified_status', 32)->nullable();
            $table->timestamp('finance_notified_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'updated_at']);
            $table->index(['withdrawal_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_transfers');
        Schema::table('organizer_payout_accounts', function (Blueprint $table) {
            $table->dropColumn(['provider_recipient_code', 'recipient_created_at']);
        });
    }
};
