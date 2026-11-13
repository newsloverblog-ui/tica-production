<?php

namespace App\Services\TicaUi;

use App\Models\Event\Booking;
use App\Models\Event\BookingTicket;
use App\Models\Event\Coupon;
use App\Models\Language;
use App\Models\Organizer;
use App\Models\OrganizerInfo;
use App\Models\OrganizerPayoutAccount;
use App\Models\OrganizerTeamMember;
use App\Models\TicaTrackingLink;
use App\Models\TicaPromotionCampaign;
use App\Models\TicaPromotionPlan;
use App\Models\TicaTrackingPixel;
use App\Models\Withdraw;
use App\Models\WithdrawPaymentMethod;
use App\Support\TicaCurrency;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Everything the TICA design's organiser workspace shows, built from the
 * organiser's real events, orders, balance, payouts, codes, links and team.
 */
class OrganizerWorkspace
{
  public const PREFERENCE_DEFAULTS = [
    'buyers_pay_fee' => true,
    'show_remaining' => true,
    'waitlist' => true,
    'transfers' => true,
    'notify_sale' => false,
    'daily_summary' => true,
    'payout_text' => true,
  ];

  public function __construct(private Language $language)
  {
  }

  public function build(Organizer $organizer, string $role): array
  {
    $catalog = new EventCatalog($this->language);
    $events = $catalog->forOrganizer((int) $organizer->id);
    $eventIds = $events->pluck('id')->all();
    $bookings = $this->paidBookings((int) $organizer->id);
    $info = OrganizerInfo::query()->where('organizer_id', $organizer->id)->where('language_id', $this->language->id)->first()
      ?: OrganizerInfo::query()->where('organizer_id', $organizer->id)->first();
    $name = $info?->name ?: (string) $organizer->username;
    $account = OrganizerPayoutAccount::query()->where('organizer_id', $organizer->id)->where('is_default', true)->latest('id')->first();
    $now = now();

    $revenueByEvent = $bookings->groupBy('event_id')->map(fn ($rows) => round($rows->sum(fn ($b) => (float) $b->price - (float) $b->commission), 2));
    $eventRows = $events->map(fn ($event) => $this->eventRow($event, $revenueByEvent->get($event['id'], 0.0)))->values();
    $upcoming = $eventRows->filter(fn ($row) => $row['state'] === 'up')->sortBy('ts')->values();

    return [
      'role' => $role,
      'org' => [
        'name' => $name,
        'initials' => $this->initials($name),
        'verified' => $organizer->kyc_status === 'verified',
        'owner' => (string) ($organizer->verified_legal_name ?: $info?->designation ?: $name),
      ],
      'setup' => [
        'identity' => $organizer->kyc_status === 'verified',
        'published' => $events->contains(fn ($event) => $event['status'] === 1),
        'payout' => (bool) ($account && $account->status === 'verified'),
        'kycUrl' => route('organizer.kyc.show'),
      ],
      'kpis' => [
        // Headline sales are in the base currency; other currencies show on Payouts.
        'gross' => round($bookings->filter(fn ($b) => TicaCurrency::isBase($b->currencyText))->sum(fn ($b) => (float) $b->price + (float) $b->tax), 2),
        'sold' => $eventIds ? BookingTicket::query()->whereIn('event_id', $eventIds)->whereNull('revoked_at')->count() : 0,
        'views' => $eventIds && Schema::hasTable('funnel_analytics_events')
          ? DB::table('funnel_analytics_events')->where('event_name', 'view_event')->whereIn('event_id', $eventIds)->count()
          : 0,
        'available' => round((float) $organizer->amount, 2),
      ],
      'chart' => $this->chart($eventIds),
      'account' => $this->accountTotals($organizer, $eventIds),
      'next' => $upcoming->first(),
      'events' => $eventRows->all(),
      'orders' => $bookings->sortByDesc('id')->take(5)->map(fn ($b) => [
        'name' => trim($b->fname . ' ' . $b->lname),
        'tickets' => $this->ticketSummary($b),
        'amount' => round((float) $b->price + (float) $b->tax, 2),
        'cur' => $this->cur($b->currencyText),
        'ago' => Carbon::parse($b->created_at)->diffForHumans(),
      ])->values()->all(),
      'payouts' => $this->payouts($organizer, $account),
      'codes' => $this->codes((int) $organizer->id),
      'links' => $this->links((int) $organizer->id),
      'promotionPlans' => $this->promotionPlans(),
      'promotions' => $this->promotions((int) $organizer->id),
      'trackingPixels' => $this->trackingPixels((int) $organizer->id),
      'team' => $this->team($organizer, $name),
      'profile' => [
        'instagram' => (string) (((array) json_decode((string) ($organizer->preferences ?? ''), true))['instagram'] ?? ''),
        'tiktok' => (string) (((array) json_decode((string) ($organizer->preferences ?? ''), true))['tiktok'] ?? ''),
        'youtube' => (string) (((array) json_decode((string) ($organizer->preferences ?? ''), true))['youtube'] ?? ''),
        'website' => (string) (((array) json_decode((string) ($organizer->preferences ?? ''), true))['website'] ?? ''),
        'photoUrl' => $organizer->photo ? asset('assets/admin/img/organizer-photo/' . basename($organizer->photo)) : null,
        'name' => $name,
        'about' => (string) ($info?->details ?? ''),
        'email' => (string) $organizer->email,
        'username' => (string) $organizer->username,
        'designation' => (string) ($info?->designation ?? ''),
        'country' => (string) ($info?->country ?? ''),
        'city' => (string) ($info?->city ?? ''),
        'state' => (string) ($info?->state ?? ''),
        'zipCode' => (string) ($info?->zip_code ?? ''),
        'address' => (string) ($info?->address ?? ''),
        'phone' => (string) ($organizer->phone ?? ''),
        'facebook' => (string) ($organizer->facebook ?? ''),
        'twitter' => (string) ($organizer->twitter ?? ''),
        'linkedin' => (string) ($organizer->linkedin ?? ''),
        'mfa' => $organizer->two_factor_enabled_at !== null,
        'securityUrl' => route('account-security.show', ['guard' => 'organizer']),
      ],
      'prefs' => array_merge(self::PREFERENCE_DEFAULTS, ['buyers_pay_fee' => \App\Support\TicaPlatform::all()['default_fee_payer'] === 'buyer'], (array) json_decode((string) ($organizer->preferences ?? ''), true)),
      'generatedAt' => $now->toIso8601String(),
    ];
  }

  /** Completed and free bookings for the organiser's events. */
  private function paidBookings(int $organizerId): Collection
  {
    return Booking::query()
      ->where('organizer_id', $organizerId)
      ->whereIn('paymentStatus', ['completed', 'free'])
      ->get(['id', 'event_id', 'fname', 'lname', 'email', 'price', 'tax', 'commission', 'quantity', 'variation', 'created_at', 'paymentStatus', 'tracking_link_id', 'currencyText']);
  }

  private function eventRow(array $event, float $revenue): array
  {
    $sold = collect($event['tiers'])->sum(fn ($tier) => $tier[3] - $tier[2]);
    $unlimited = $event['unlimited'] || collect($event['tierInfo'])->contains(fn ($info) => $info['unlimited']);
    $cap = $unlimited ? null : collect($event['tiers'])->sum(fn ($tier) => $tier[3]);
    $ended = $event['endTs'] < now()->getTimestampMs();
    $state = $event['status'] !== 1 ? 'draft' : ($ended || in_array($event['lifecycle'], ['cancelled'], true) ? 'past' : 'up');
    $status = match (true) {
      $event['status'] !== 1 => 'Draft',
      $event['lifecycle'] === 'cancelled' => 'Cancelled',
      $event['lifecycle'] === 'cancellation_pending' => 'Cancelling',
      $ended => 'Ended',
      $event['salesClosed'] => 'Sales paused',
      default => 'On sale',
    };

    return [
      'id' => $event['id'],
      'slug' => $event['slug'],
      'name' => $event['name'],
      'art' => $event['art'],
      'ts' => $event['ts'],
      'date' => $event['day'] . ' ' . $event['mon'] . ' ' . $event['y'] . ', ' . $event['time'],
      'day' => (int) $event['day'],
      'mon' => $event['mon'],
      'y' => $event['y'],
      'time' => $event['time'],
      'venue' => $event['venue'],
      'city' => $event['city'],
      'mode' => $event['mode'] === 'online' ? 'Online' : ($event['mode'] === 'hybrid' ? 'Both' : 'In person'),
      'state' => $state,
      'status' => $status,
      'sold' => $sold,
      'cap' => $cap,
      'revenue' => $revenue,
      'cur' => $event['cur'],
      'regMode' => $event['regMode'],
      'payer' => $event['absorb'] ? 'me' : 'buyer',
      'visibility' => $event['visibility'],
      'showRemaining' => !$event['hideStock'],
      'waitlist' => $event['waitlist'],
      'transfers' => $event['transfers'],
      'refund' => $event['refundRule'],
      'platform' => $event['platform'],
      'reveal' => $event['revealMinutes'],
      'tiers' => collect($event['tiers'])->map(fn ($tier, $i) => [
        'id' => $event['tierInfo'][$i]['ticketId'],
        'variation' => $event['tierInfo'][$i]['variation'],
        'name' => $tier[0],
        'price' => $tier[1],
        'sold' => $tier[3] - $tier[2],
        'total' => $event['tierInfo'][$i]['unlimited'] ? null : $tier[3],
      ])->values()->all(),
      'url' => $event['url'],
      'editUrl' => url('/create/' . $event['id']),
      'registrationUrl' => $event['regMode'] === 'corporate' ? route('organizer.event.registrations.index', ['event_id' => $event['id']]) : null,
    ];
  }

  /** Tickets issued per day: the last 14 days, the last 30 days and every month. */
  /**
   * The figures from Tica's earlier organiser dashboard: balance, event,
   * booking and transaction totals, and this year's income and bookings by
   * month (completed bookings, ticket sales in the base currency).
   */
  private function accountTotals(Organizer $organizer, array $eventIds): array
  {
    $year = (int) now()->format('Y');
    $monthly = Booking::query()
      ->where('organizer_id', $organizer->id)
      ->whereIn('paymentStatus', ['completed', 'free', '1'])
      ->whereYear('created_at', $year)
      ->get(['price', 'created_at', 'currencyText', 'paymentStatus']);
    $income = array_fill(1, 12, 0.0);
    $bookings = array_fill(1, 12, 0);
    foreach ($monthly as $booking) {
      $month = (int) Carbon::parse($booking->created_at)->format('n');
      if ($booking->paymentStatus === 'completed' && TicaCurrency::isBase($booking->currencyText)) {
        $income[$month] = round($income[$month] + (float) $booking->price, 2);
      }
      $bookings[$month]++;
    }

    return [
      'year' => $year,
      'balance' => round((float) $organizer->amount, 2),
      'events' => count($eventIds),
      'bookings' => Booking::query()->where('organizer_id', $organizer->id)->count(),
      'transactions' => \App\Models\Transaction::query()->where('organizer_id', $organizer->id)->count(),
      'income' => array_values($income),
      'monthlyBookings' => array_values($bookings),
    ];
  }

  private function chart(array $eventIds): array
  {
    $empty = ['14' => [], '30' => [], 'all' => []];
    if ($eventIds === []) {
      $empty['14'] = $this->days([], 14);
      $empty['30'] = $this->days([], 30);
      return $empty;
    }
    $daily = BookingTicket::query()
      ->whereIn('event_id', $eventIds)
      ->whereNull('revoked_at')
      ->where('created_at', '>=', now()->subDays(30)->startOfDay())
      ->selectRaw('DATE(created_at) as day, count(*) as total')
      ->groupBy('day')
      ->pluck('total', 'day')
      ->all();
    $monthly = BookingTicket::query()
      ->whereIn('event_id', $eventIds)
      ->whereNull('revoked_at')
      ->selectRaw((DB::getDriverName() === 'pgsql' ? "TO_CHAR(created_at, 'YYYY-MM')" : "DATE_FORMAT(created_at, '%Y-%m')") . ' as month, count(*) as total')
      ->groupByRaw(DB::getDriverName() === 'pgsql' ? "TO_CHAR(created_at, 'YYYY-MM')" : "DATE_FORMAT(created_at, '%Y-%m')")
      ->orderBy('month')
      ->pluck('total', 'month');

    return [
      '14' => $this->days($daily, 14),
      '30' => $this->days($daily, 30),
      'all' => $monthly->map(fn ($total, $month) => [Carbon::parse($month . '-01')->format('M Y'), (int) $total])->values()->all(),
    ];
  }

  private function days(array $daily, int $count): array
  {
    $rows = [];
    for ($i = $count - 1; $i >= 0; $i--) {
      $day = now()->subDays($i);
      $rows[] = [$day->format('j M'), (int) ($daily[$day->toDateString()] ?? 0)];
    }

    return $rows;
  }

  private function ticketSummary(Booking $booking): string
  {
    $selection = json_decode((string) $booking->variation, true);
    if (is_array($selection) && $selection !== []) {
      return collect($selection)->map(fn ($line) => (int) ($line['qty'] ?? 0) . ' x ' . ($line['name'] ?? 'Ticket'))->implode(', ');
    }

    return (int) $booking->quantity . ' x ' . __('Ticket');
  }

  private function payouts(Organizer $organizer, ?OrganizerPayoutAccount $account): array
  {
    $withdrawals = Withdraw::query()->where('organizer_id', $organizer->id)->latest('id')->limit(50)->get();
    $method = WithdrawPaymentMethod::query()->where('status', 1)->orderByRaw("CASE WHEN LOWER(name) LIKE '%bank%' THEN 0 ELSE 1 END")->orderBy('id')->first();
    $label = fn (Withdraw $w) => match ((int) $w->status) {
      Withdraw::STATUS_PENDING => 'Requested',
      Withdraw::STATUS_HELD => 'On hold',
      Withdraw::STATUS_PROCESSING => 'Sending',
      Withdraw::STATUS_PAID => 'Paid',
      default => 'Declined',
    };

    return [
      'available' => round((float) $organizer->amount, 2),
      'pending' => round((float) $withdrawals->filter(fn ($w) => in_array((int) $w->status, [Withdraw::STATUS_PENDING, Withdraw::STATUS_HELD, Withdraw::STATUS_PROCESSING], true))->sum(fn ($w) => (float) $w->amount), 2),
      'paid' => round((float) Withdraw::query()->where('organizer_id', $organizer->id)->where('status', Withdraw::STATUS_PAID)->get()->sum(fn ($w) => (float) $w->payable_amount), 2),
      'schedule' => (string) ($organizer->payout_schedule ?? 'manual'),
      'currencies' => DB::table('organizer_currency_balances')->where('organizer_id', $organizer->id)->where('amount', '!=', 0)->orderBy('currency')->get()
        ->map(fn ($row) => ['code' => $row->currency, 'amount' => round((float) $row->amount, 2), 'cur' => $this->cur($row->currency)])->values()->all(),
      'kyc' => (string) ($organizer->kyc_status ?? ''),
      'account' => $account ? [
        'name' => (string) $account->resolved_account_name,
        'bank' => (string) $account->bank_name,
        'last4' => (string) $account->account_last4,
        'verified' => $account->status === 'verified',
        'held' => $account->hold_until && now()->lt($account->hold_until),
        'status' => (string) $account->status,
      ] : null,
      // An account given with the identity check, waiting for the approval.
      'staged' => ($staged = OrganizerPayoutAccount::query()->where('organizer_id', $organizer->id)->whereIn('status', ['pending_kyc', 'name_mismatch'])->latest('id')->first()) ? [
        'name' => (string) $staged->resolved_account_name,
        'bank' => (string) $staged->bank_name,
        'last4' => (string) $staged->account_last4,
        'status' => (string) $staged->status,
      ] : null,
      'method' => $method ? [
        'id' => (int) $method->id,
        'min' => (float) $method->min_limit,
        'max' => (float) $method->max_limit,
        'fixed' => (float) $method->fixed_charge,
        'percent' => (float) $method->percentage_charge,
      ] : null,
      'history' => $withdrawals->map(fn (Withdraw $w) => [
        'date' => Carbon::parse($w->created_at)->format('j M Y'),
        'ref' => (string) ($w->payout_reference ?: $w->withdraw_id),
        'amount' => round((float) $w->amount, 2),
        'to' => $this->payoutDestination($w),
        'status' => $label($w),
      ])->values()->all(),
    ];
  }

  private function payoutDestination(Withdraw $withdrawal): string
  {
    $fields = json_decode((string) $withdrawal->feilds, true) ?: [];
    $bank = (string) ($fields['bank_name'] ?? '');
    $last4 = (string) ($fields['account_last4'] ?? '');

    return trim($bank . ($last4 !== '' ? ' •••• ' . $last4 : ''));
  }

  private function codes(int $organizerId): array
  {
    if (!Schema::hasColumn('coupons', 'organizer_id')) {
      return [];
    }

    return Coupon::query()->where('organizer_id', $organizerId)->latest('id')->get()->map(function (Coupon $coupon) {
      $events = json_decode((string) $coupon->events, true) ?: [];
      $ended = $coupon->end_date && Carbon::parse($coupon->end_date)->endOfDay()->isPast();
      $usedUp = $coupon->max_uses !== null && (int) $coupon->used_count >= (int) $coupon->max_uses;

      return [
        'id' => (int) $coupon->id,
        'code' => (string) $coupon->code,
        'label' => $coupon->type === 'percentage' ? rtrim(rtrim(number_format((float) $coupon->value, 2), '0'), '.') . '% off' : 'fixed:' . (float) $coupon->value,
        'scope' => $events === [] ? 'All your events' : count($events) . (count($events) === 1 ? ' event' : ' events'),
        'used' => (int) $coupon->used_count,
        'max' => $coupon->max_uses !== null ? (int) $coupon->max_uses : null,
        'active' => !$ended && !$usedUp,
      ];
    })->values()->all();
  }

  private function links(int $organizerId): array
  {
    $sales = Schema::hasColumn('bookings', 'tracking_link_id')
      ? Booking::query()->where('organizer_id', $organizerId)->whereNotNull('tracking_link_id')->whereIn('paymentStatus', ['completed', 'free'])
        ->selectRaw('tracking_link_id, SUM(quantity) as total')->groupBy('tracking_link_id')->pluck('total', 'tracking_link_id')
      : collect();

    return TicaTrackingLink::query()->where('organizer_id', $organizerId)->orderBy('id')->get()->map(fn (TicaTrackingLink $link) => [
      'id' => (int) $link->id,
      'name' => $link->name,
      'code' => $link->code,
      'eventId' => $link->event_id ? (int) $link->event_id : null,
      'clicks' => (int) $link->clicks,
      'sales' => (int) ($sales[$link->id] ?? 0),
    ])->values()->all();
  }

  private function promotionPlans(): array
  {
    if (!Schema::hasTable('tica_promotion_plans')) {
      return [];
    }

    return TicaPromotionPlan::query()->where('is_active', true)->orderBy('price')->get()->map(fn (TicaPromotionPlan $plan) => [
      'id' => (int) $plan->id,
      'name' => (string) $plan->name,
      'placement' => (string) $plan->placement,
      'description' => (string) $plan->description,
      'price' => (float) $plan->price,
      'currency' => (string) $plan->currency,
      'days' => (int) $plan->duration_days,
    ])->values()->all();
  }

  private function promotions(int $organizerId): array
  {
    if (!Schema::hasTable('tica_promotion_campaigns')) {
      return [];
    }

    return TicaPromotionCampaign::query()->with(['plan:id,name,placement', 'event.information'])->where('organizer_id', $organizerId)->latest('id')->limit(50)->get()->map(fn (TicaPromotionCampaign $campaign) => [
      'id' => (int) $campaign->id,
      'event' => (string) (optional(optional($campaign->event)->information)->title ?: 'Event'),
      'plan' => (string) optional($campaign->plan)->name,
      'placement' => (string) optional($campaign->plan)->placement,
      'status' => (string) $campaign->status,
      'price' => (float) $campaign->price,
      'currency' => (string) $campaign->currency,
      'starts' => $campaign->starts_at?->format('j M Y'),
      'canPay' => $campaign->status === 'pending_payment',
      'canCancel' => in_array($campaign->status, ['pending_payment', 'pending_review', 'approved', 'scheduled', 'active', 'rejected'], true),
    ])->values()->all();
  }

  private function trackingPixels(int $organizerId): array
  {
    if (!Schema::hasTable('tica_tracking_pixels')) {
      return [];
    }

    return TicaTrackingPixel::query()->where('organizer_id', $organizerId)->latest('id')->get()->map(fn (TicaTrackingPixel $pixel) => [
      'id' => (int) $pixel->id,
      'provider' => (string) $pixel->provider,
      'identifier' => (string) $pixel->pixel_identifier,
      'label' => (string) $pixel->label,
      'status' => (string) $pixel->status,
    ])->values()->all();
  }

  private function team(Organizer $organizer, string $name): array
  {
    $members = OrganizerTeamMember::query()->where('organizer_id', $organizer->id)->where('status', '!=', 'removed')->orderBy('id')->get();
    $roles = ['manager' => 'Manager', 'finance' => 'Finance', 'gate' => 'Gate staff'];

    return array_merge([[
      'id' => null,
      'name' => (string) ($organizer->verified_legal_name ?: $name),
      'email' => (string) $organizer->email,
      'role' => 'Owner',
      'scope' => 'You',
    ]], $members->map(fn (OrganizerTeamMember $member) => [
      'id' => (int) $member->id,
      'name' => (string) ($member->name ?: strstr($member->email, '@', true)),
      'email' => (string) $member->email,
      'role' => $roles[$member->role] ?? $member->role,
      'scope' => $member->status === 'invited' ? 'Invite sent' : ($member->role === 'gate' && $member->event_ids ? count($member->event_ids) . ' event' . (count($member->event_ids) === 1 ? '' : 's') : ''),
    ])->all());
  }

  /** Formatting info for amounts in a currency; null means the base currency. */
  private function cur(?string $code): ?array
  {
    return TicaCurrency::isBase($code) ? null : ['code' => TicaCurrency::normalize($code), 'symbol' => TicaCurrency::symbol($code), 'position' => 'left'];
  }

  private function initials(string $name): string
  {
    $words = preg_split('/\s+/u', trim($name)) ?: [];
    $letters = array_map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)), array_slice($words, 0, 2));

    return implode('', $letters) ?: 'T';
  }
}
