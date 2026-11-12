<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Events sell in their own currency. Money in a currency other than the
 * platform's base currency is tracked per currency for organisers and the
 * platform, and every ledger row records its currency.
 */
return new class extends Migration
{
  public function up(): void
  {
    if (!Schema::hasColumn('events', 'currency')) {
      Schema::table('events', fn (Blueprint $table) => $table->char('currency', 3)->nullable());
    }
    if (!Schema::hasColumn('transactions', 'currency')) {
      Schema::table('transactions', fn (Blueprint $table) => $table->char('currency', 3)->nullable());
    }
    if (!Schema::hasTable('organizer_currency_balances')) {
      Schema::create('organizer_currency_balances', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('organizer_id');
        $table->char('currency', 3);
        $table->decimal('amount', 16, 2)->default(0);
        $table->timestamps();
        $table->unique(['organizer_id', 'currency']);
      });
    }
    if (!Schema::hasTable('platform_currency_earnings')) {
      Schema::create('platform_currency_earnings', function (Blueprint $table) {
        $table->id();
        $table->char('currency', 3)->unique();
        $table->decimal('total_revenue', 18, 2)->default(0);
        $table->decimal('total_earning', 18, 2)->default(0);
        $table->timestamps();
      });
    }
  }

  public function down(): void
  {
    Schema::dropIfExists('platform_currency_earnings');
    Schema::dropIfExists('organizer_currency_balances');
    foreach ([['transactions', 'currency'], ['events', 'currency']] as [$table, $column]) {
      if (Schema::hasColumn($table, $column)) {
        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn($column));
      }
    }
  }
};
