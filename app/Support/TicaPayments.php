<?php

namespace App\Support;

use App\Models\PaymentGateway\OnlineGateway;
use App\Services\PaymentGatewayPolicy;

/**
 * The online gateways a TICA buyer can pay with. Paystack takes every
 * Paystack currency, Monnify takes naira only, Flutterwave charges in the
 * base currency, and Stripe takes the currencies its Checkout supports.
 */
final class TicaPayments
{
  /** [keyword => [label, currencies; null means any Paystack currency, 'base' the base currency]] in display order */
  public const GATEWAYS = [
    'paystack' => ['Paystack', null],
    'monnify' => ['Monnify', ['NGN']],
    'flutterwave' => ['Flutterwave', 'base'],
    'stripe' => ['Stripe', \App\Services\StripeGatewayClient::CURRENCIES],
  ];

  public static function currencies(string $keyword): array
  {
    $currencies = self::GATEWAYS[$keyword][1] ?? null;

    return match (true) {
      $currencies === 'base' => [TicaCurrency::base()],
      is_array($currencies) => $currencies,
      default => TicaCurrency::PAYSTACK,
    };
  }

  /** Active gateways that can charge in this currency, in display order. */
  public static function available(?string $currency = null): array
  {
    $currency = $currency ? TicaCurrency::normalize($currency) : null;
    $active = OnlineGateway::query()->whereIn('keyword', array_keys(self::GATEWAYS))->where('status', 1)->pluck('keyword')->all();
    $policy = app(PaymentGatewayPolicy::class);

    return array_values(array_filter(array_keys(self::GATEWAYS), function ($keyword) use ($active, $policy, $currency) {
      if (!in_array($keyword, $active, true) || !$policy->canOfferOnline($keyword)) {
        return false;
      }
      return $currency === null || in_array($currency, self::currencies($keyword), true);
    }));
  }

  /** What the checkout page shows: [{key, label}] for gateways that are on. */
  public static function forCheckout(): array
  {
    return array_map(fn ($keyword) => ['key' => $keyword, 'label' => self::GATEWAYS[$keyword][0], 'currencies' => self::currencies($keyword)], self::available());
  }
}
