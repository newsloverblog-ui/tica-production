<?php

namespace App\Support;

use App\Models\BasicSettings\Basic;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Event currencies. Each event sells in one currency, picked from its
 * country. Sales in the platform's base currency use the organiser's main
 * balance; sales in any other currency are kept in a separate balance for
 * that currency, so amounts in different currencies never mix.
 */
final class TicaCurrency
{
  private const SETTINGS_KEY = 'tica.currency.base-settings';

  /** Currencies Paystack can charge in. Paid tickets need one of these. */
  public const PAYSTACK = ['NGN', 'GHS', 'KES', 'ZAR', 'USD', 'XOF', 'EGP'];

  public const CURRENCIES = [
    'NGN' => ['symbol' => '₦', 'name' => 'Nigerian naira'],
    'GHS' => ['symbol' => 'GH₵', 'name' => 'Ghanaian cedi'],
    'KES' => ['symbol' => 'KSh', 'name' => 'Kenyan shilling'],
    'ZAR' => ['symbol' => 'R', 'name' => 'South African rand'],
    'USD' => ['symbol' => '$', 'name' => 'US dollar'],
    'XOF' => ['symbol' => 'CFA', 'name' => 'West African CFA franc'],
    'EGP' => ['symbol' => 'E£', 'name' => 'Egyptian pound'],
    'GBP' => ['symbol' => '£', 'name' => 'British pound'],
    'EUR' => ['symbol' => '€', 'name' => 'Euro'],
    'CAD' => ['symbol' => 'CA$', 'name' => 'Canadian dollar'],
    'XAF' => ['symbol' => 'FCFA', 'name' => 'Central African CFA franc'],
    'RWF' => ['symbol' => 'RF', 'name' => 'Rwandan franc'],
    'UGX' => ['symbol' => 'USh', 'name' => 'Ugandan shilling'],
    'TZS' => ['symbol' => 'TSh', 'name' => 'Tanzanian shilling'],
    'ZMW' => ['symbol' => 'ZK', 'name' => 'Zambian kwacha'],
    'AED' => ['symbol' => 'AED', 'name' => 'UAE dirham'],
  ];

  public const COUNTRIES = [
    'Nigeria' => 'NGN', 'Ghana' => 'GHS', 'Kenya' => 'KES', 'South Africa' => 'ZAR', 'Egypt' => 'EGP',
    "Côte d'Ivoire" => 'XOF', 'Senegal' => 'XOF', 'Benin' => 'XOF', 'Togo' => 'XOF', 'Burkina Faso' => 'XOF', 'Mali' => 'XOF', 'Niger' => 'XOF',
    'Cameroon' => 'XAF', 'Gabon' => 'XAF', 'Rwanda' => 'RWF', 'Uganda' => 'UGX', 'Tanzania' => 'TZS', 'Zambia' => 'ZMW',
    'United Kingdom' => 'GBP', 'Ireland' => 'EUR', 'France' => 'EUR', 'Germany' => 'EUR', 'Netherlands' => 'EUR', 'Spain' => 'EUR', 'Italy' => 'EUR',
    'United States' => 'USD', 'Canada' => 'CAD', 'United Arab Emirates' => 'AED',
  ];

  public static function base(): string
  {
    return self::settings()['code'];
  }

  public static function normalize(?string $code): string
  {
    $code = strtoupper(trim((string) $code));

    return isset(self::CURRENCIES[$code]) ? $code : self::base();
  }

  public static function isBase(?string $code): bool
  {
    return self::normalize($code) === self::base();
  }

  public static function symbol(?string $code): string
  {
    $code = self::normalize($code);
    if ($code === self::base()) {
      return self::settings()['symbol'];
    }

    return self::CURRENCIES[$code]['symbol'] ?? $code;
  }

  /** Forget request-local settings after an administrator changes currency. */
  public static function reset(): void
  {
    if (app()->bound(self::SETTINGS_KEY)) {
      app()->forgetInstance(self::SETTINGS_KEY);
    }
  }

  /** @return array{code: string, symbol: string} */
  private static function settings(): array
  {
    if (app()->bound(self::SETTINGS_KEY)) {
      return app(self::SETTINGS_KEY);
    }

    $code = 'NGN';
    $symbol = self::CURRENCIES[$code]['symbol'];
    try {
      $settings = Basic::query()->first(['base_currency_text', 'base_currency_symbol']);
      $storedCode = strtoupper(trim((string) ($settings?->base_currency_text ?? '')));
      if (isset(self::CURRENCIES[$storedCode])) {
        $code = $storedCode;
      }
      $symbol = trim((string) ($settings?->base_currency_symbol ?? '')) ?: (self::CURRENCIES[$code]['symbol'] ?? $code);
    } catch (Throwable) {
      // Installation, test, and rolling-deploy schemas may not have the
      // settings table or currency columns yet. Keep known safe defaults.
    }

    $settings = ['code' => $code, 'symbol' => $symbol];
    app()->instance(self::SETTINGS_KEY, $settings);

    return $settings;
  }

  public static function forCountry(?string $country): ?string
  {
    foreach (self::COUNTRIES as $name => $code) {
      if (strcasecmp($name, trim((string) $country)) === 0) {
        return $code;
      }
    }

    return null;
  }

  /** The list the create-event page offers. */
  public static function options(): array
  {
    return collect(self::CURRENCIES)->only(TicaPlatform::currencies())->map(fn ($meta, $code) => [
      'code' => $code,
      'symbol' => $code === self::base() ? self::symbol($code) : $meta['symbol'],
      'name' => $meta['name'],
      'paid' => in_array($code, self::PAYSTACK, true),
    ])->values()->all();
  }

  /**
   * Move an organiser's balance in a non-base currency, returning
   * [before, after]. Call inside a transaction.
   */
  public static function adjustBalance(int $organizerId, string $currency, float $delta): array
  {
    $currency = self::normalize($currency);
    $row = DB::table('organizer_currency_balances')->where('organizer_id', $organizerId)->where('currency', $currency)->lockForUpdate()->first();
    if (!$row) {
      DB::table('organizer_currency_balances')->insert(['organizer_id' => $organizerId, 'currency' => $currency, 'amount' => 0, 'created_at' => now(), 'updated_at' => now()]);
      $row = DB::table('organizer_currency_balances')->where('organizer_id', $organizerId)->where('currency', $currency)->lockForUpdate()->first();
    }
    $before = round((float) $row->amount, 2);
    $after = round($before + $delta, 2);
    DB::table('organizer_currency_balances')->where('id', $row->id)->update(['amount' => $after, 'updated_at' => now()]);

    return [$before, $after];
  }

  /** Platform revenue and earnings for a non-base currency. Call inside a transaction. */
  public static function addPlatformEarning(string $currency, float $revenue, float $earning): void
  {
    $currency = self::normalize($currency);
    $row = DB::table('platform_currency_earnings')->where('currency', $currency)->lockForUpdate()->first();
    if (!$row) {
      DB::table('platform_currency_earnings')->insert(['currency' => $currency, 'total_revenue' => 0, 'total_earning' => 0, 'created_at' => now(), 'updated_at' => now()]);
      $row = DB::table('platform_currency_earnings')->where('currency', $currency)->lockForUpdate()->first();
    }
    DB::table('platform_currency_earnings')->where('id', $row->id)->update([
      'total_revenue' => round((float) $row->total_revenue + $revenue, 2),
      'total_earning' => round((float) $row->total_earning + $earning, 2),
      'updated_at' => now(),
    ]);
  }
}
