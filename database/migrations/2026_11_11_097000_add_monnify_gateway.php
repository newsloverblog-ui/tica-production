<?php

use App\Models\PaymentGateway\OnlineGateway;
use Illuminate\Database\Migrations\Migration;

/**
 * Monnify for naira ticket payments. Starts switched off in sandbox mode;
 * an admin adds the keys under Payment Gateways and turns it on.
 */
return new class extends Migration
{
  public function up(): void
  {
    if (OnlineGateway::query()->where('keyword', 'monnify')->exists()) {
      return;
    }

    OnlineGateway::query()->create([
      'name' => 'Monnify',
      'keyword' => 'monnify',
      'information' => json_encode(['api_key' => '', 'secret_key' => '', 'contract_code' => '', 'mode' => 'sandbox']),
      'status' => 0,
    ]);
  }

  public function down(): void
  {
    OnlineGateway::query()->where('keyword', 'monnify')->delete();
  }
};
