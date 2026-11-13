<?php

namespace App\Support;

use App\Models\BasicSettings\Basic;
use Illuminate\Support\Facades\Schema;

/**
 * What the admin allows across the whole platform: the event features
 * organisers can switch on, the currencies events can sell in, and who pays
 * the booking fee on a new event. Turning a feature off hides it on the
 * create-event page and stops organisers turning it on; events that already
 * use it keep it.
 */
final class TicaPlatform
{
  /** [key => [label, help]] in the order the settings page lists them. */
  public const FEATURES = [
    'online' => ['Online events', 'Events streamed on Zoom, Meet or a live link.'],
    'hybrid' => ['Hybrid events', 'A venue and an online link for the same event.'],
    'registration' => ['Registration events', 'People apply and the organiser approves them, instead of buying tickets.'],
    'unlisted' => ['Link-only events', 'Events that sell by link and stay off Discover.'],
    'private' => ['Private events', 'Invite-only events that never appear in search.'],
    'waitlist' => ['Waitlists', 'Buyers can join a waitlist when an event sells out.'],
    'question' => ['Attendee questions', 'Organisers can ask every attendee one question, like T-shirt size.'],
    'transfers' => ['Ticket transfers', 'Ticket holders can pass a ticket to someone else.'],
    'organizer_pays' => ['Organisers can absorb the booking fee', 'If off, buyers always pay the booking fee on top of the ticket price.'],
  ];

  private static ?array $cache = null;

  public static function all(): array
  {
    if (self::$cache !== null) {
      return self::$cache;
    }
    $stored = [];
    if (Schema::hasColumn('basic_settings', 'tica_platform')) {
      $stored = json_decode((string) Basic::query()->value('tica_platform'), true) ?: [];
    }
    $features = array_map(fn () => true, self::FEATURES);
    foreach ($stored['features'] ?? [] as $key => $on) {
      if (array_key_exists($key, $features)) {
        $features[$key] = (bool) $on;
      }
    }
    $currencies = array_values(array_intersect(array_keys(TicaCurrency::CURRENCIES), (array) ($stored['currencies'] ?? array_keys(TicaCurrency::CURRENCIES))));

    return self::$cache = [
      'features' => $features,
      'currencies' => $currencies,
      'default_fee_payer' => ($stored['default_fee_payer'] ?? 'buyer') === 'organizer' && $features['organizer_pays'] ? 'organizer' : 'buyer',
    ];
  }

  public static function save(array $features, array $currencies, string $defaultFeePayer): void
  {
    $value = [
      'features' => array_map(fn ($key) => !empty($features[$key]), array_combine(array_keys(self::FEATURES), array_keys(self::FEATURES))),
      'currencies' => array_values(array_unique(array_merge([TicaCurrency::base()], array_intersect(array_keys(TicaCurrency::CURRENCIES), $currencies)))),
      'default_fee_payer' => $defaultFeePayer === 'organizer' ? 'organizer' : 'buyer',
    ];
    // Keep other keys stored alongside (such as the payout provider).
    $value = array_merge(self::stored(), $value);
    Basic::query()->update(['tica_platform' => json_encode($value)]);
    self::$cache = null;
  }

  /** Which provider sends organiser payouts: "paystack" (default) or "monnify". */
  public static function payoutProvider(): string
  {
    $provider = (string) (self::stored()['payout_provider'] ?? 'paystack');

    return in_array($provider, ['paystack', 'monnify'], true) ? $provider : 'paystack';
  }

  public static function savePayoutProvider(string $provider): void
  {
    if (!in_array($provider, ['paystack', 'monnify'], true)) {
      throw new \InvalidArgumentException('Unknown payout provider.');
    }
    Basic::query()->update(['tica_platform' => json_encode(array_merge(self::stored(), ['payout_provider' => $provider]))]);
    self::$cache = null;
  }

  private static function stored(): array
  {
    if (!Schema::hasColumn('basic_settings', 'tica_platform')) {
      return [];
    }

    return json_decode((string) Basic::query()->value('tica_platform'), true) ?: [];
  }

  public static function on(string $feature): bool
  {
    return self::all()['features'][$feature] ?? true;
  }

  /** Currencies events can sell in. The base currency is always allowed. */
  public static function currencies(): array
  {
    return array_values(array_unique(array_merge([TicaCurrency::base()], self::all()['currencies'])));
  }

  public static function modes(): array
  {
    return array_values(array_filter(['In person', self::on('online') ? 'Online' : null, self::on('hybrid') ? 'Hybrid' : null]));
  }

  public static function visibilities(): array
  {
    return array_values(array_filter(['public', self::on('unlisted') ? 'unlisted' : null, self::on('private') ? 'private' : null]));
  }

  /** What the create-event page needs to know. */
  public static function forOrganizers(): array
  {
    return self::all()['features'] + ['default_fee_payer' => self::all()['default_fee_payer']];
  }
}
