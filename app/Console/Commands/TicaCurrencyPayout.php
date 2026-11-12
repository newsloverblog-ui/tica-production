<?php

namespace App\Console\Commands;

use App\Models\Organizer;
use App\Models\Transaction;
use App\Services\AuditLogger;
use App\Support\TicaCurrency;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Record a payout TICA sent by hand from an organiser's balance in a
 * currency other than the base currency, after the transfer has been made.
 */
class TicaCurrencyPayout extends Command
{
  protected $signature = 'tica:currency-payout {organizer : Organiser id} {currency : Currency code, e.g. GHS} {amount : Amount paid} {reference : Bank or transfer reference}';

  protected $description = 'Record a manual payout from an organiser\'s non-base-currency balance';

  public function handle(): int
  {
    $organizer = Organizer::query()->find((int) $this->argument('organizer'));
    $currency = strtoupper((string) $this->argument('currency'));
    $amount = round((float) $this->argument('amount'), 2);
    if (!$organizer || !isset(TicaCurrency::CURRENCIES[$currency]) || TicaCurrency::isBase($currency) || $amount <= 0) {
      $this->error('Give an organiser id, a non-base currency code and a positive amount.');
      return self::FAILURE;
    }

    try {
      DB::transaction(function () use ($organizer, $currency, $amount) {
        $current = (float) DB::table('organizer_currency_balances')->where('organizer_id', $organizer->id)->where('currency', $currency)->lockForUpdate()->value('amount');
        if ($amount > $current + 0.001) {
          throw new \RuntimeException("The {$currency} balance is only {$current}.");
        }
        [$before, $after] = TicaCurrency::adjustBalance((int) $organizer->id, $currency, -$amount);
        $transaction = Transaction::create([
          'currency' => $currency,
          'transcation_id' => (string) $this->argument('reference'),
          'transcation_type' => 3,
          'organizer_id' => $organizer->id,
          'payment_status' => 'paid',
          'payment_method' => 'Manual ' . $currency . ' payout',
          'grand_total' => $amount,
          'commission' => 0,
          'tax' => 0,
          'pre_balance' => $before,
          'after_balance' => $after,
          'gateway_type' => 'manual',
          'currency_symbol' => TicaCurrency::symbol($currency),
          'currency_symbol_position' => 'left',
        ]);
        AuditLogger::record('organizer.currency_payout_recorded', $transaction, [], ['currency' => $currency, 'amount' => $amount]);
      });
    } catch (\RuntimeException $exception) {
      $this->error($exception->getMessage());
      return self::FAILURE;
    }

    $this->info("Recorded a {$currency} {$amount} payout for organiser {$organizer->id}.");
    return self::SUCCESS;
  }
}
