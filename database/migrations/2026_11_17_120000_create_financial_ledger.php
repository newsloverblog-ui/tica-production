<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_ledger_accounts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->nullableMorphs('owner');
            $table->string('code', 64);
            $table->string('name', 120);
            $table->string('category', 24);
            $table->string('normal_balance', 6);
            $table->char('currency', 3);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['owner_type', 'owner_id', 'code', 'currency'], 'financial_ledger_account_owner_unique');
            $table->index(['code', 'currency']);
        });

        Schema::create('financial_ledger_transactions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('reference', 191)->unique();
            $table->string('kind', 48);
            $table->nullableMorphs('source');
            $table->timestamp('occurred_at');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['kind', 'occurred_at']);
        });

        Schema::create('financial_ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('financial_ledger_transaction_id');
            $table->unsignedBigInteger('financial_ledger_account_id');
            $table->foreign('financial_ledger_transaction_id', 'ledger_entries_transaction_foreign')
                ->references('id')->on('financial_ledger_transactions')->cascadeOnDelete();
            $table->foreign('financial_ledger_account_id', 'ledger_entries_account_foreign')
                ->references('id')->on('financial_ledger_accounts')->restrictOnDelete();
            $table->string('direction', 6);
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->timestamps();
            $table->index(['financial_ledger_account_id', 'id']);
        });

        Schema::create('organizer_earning_releases', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organizer_id')->constrained('organizers')->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->unsignedBigInteger('booking_id')->unique();
            $table->unsignedBigInteger('financial_ledger_transaction_id');
            $table->foreign('financial_ledger_transaction_id', 'earning_releases_transaction_foreign')
                ->references('id')->on('financial_ledger_transactions')->restrictOnDelete();
            $table->char('currency', 3);
            $table->unsignedBigInteger('gross_amount_minor');
            $table->unsignedBigInteger('platform_fee_minor')->default(0);
            $table->unsignedBigInteger('processor_fee_minor')->default(0);
            $table->unsignedBigInteger('organizer_amount_minor');
            $table->string('status', 24)->default('pending');
            $table->timestamp('available_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('held_at')->nullable();
            $table->text('hold_reason')->nullable();
            $table->timestamps();
            $table->index(['status', 'available_at']);
            $table->index(['organizer_id', 'currency', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizer_earning_releases');
        Schema::dropIfExists('financial_ledger_entries');
        Schema::dropIfExists('financial_ledger_transactions');
        Schema::dropIfExists('financial_ledger_accounts');
    }
};
