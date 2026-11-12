<?php

namespace App\Services\TicaUi;

use App\Models\Customer;
use App\Models\Event;
use App\Models\Event\Booking;
use App\Models\OperationalIncident;
use App\Models\Organizer;
use App\Models\PaymentIntent;
use App\Models\Transaction;
use App\Models\Withdraw;
use App\Support\TicaCurrency;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The admin dashboard's numbers. Money is in the base currency; sales in
 * other currencies are listed on their own so amounts never mix.
 */
class AdminOverview
{
  private const PAID = ['completed'];
  private const COUNTED = ['completed', 'free', '1'];

  public function __construct(private int $languageId)
  {
  }

  public function build(): array
  {
    $base = TicaCurrency::base();

    return [
      'base' => ['code' => $base, 'symbol' => TicaCurrency::symbol($base), 'position' => (string) (DB::table('basic_settings')->value('base_currency_symbol_position') ?: 'left')],
      'today' => $this->today(),
      'month' => $this->month(),
      'attention' => $this->attention(),
      'series' => $this->series(),
      'currencies' => $this->currencies(),
      'topEvents' => $this->topEvents(),
      'recent' => $this->recent(),
      'upcoming' => $this->upcoming(),
      'totals' => $this->totals(),
      'money' => $this->money(),
      'users' => $this->users(),
      'payouts' => $this->payouts(),
    ];
  }

  /** Base-currency bookings: no currency recorded, or the base code. */
  private function baseOnly($query)
  {
    $base = TicaCurrency::base();

    return $query->where(fn ($q) => $q->whereNull('currencyText')->orWhere('currencyText', '')->orWhere('currencyText', $base));
  }

  private function today(): array
  {
    $paid = $this->baseOnly(Booking::query()->whereIn('paymentStatus', self::PAID)->whereDate('created_at', today()));

    return [
      'sales' => round((float) (clone $paid)->sum(DB::raw('price + COALESCE(tax, 0)')), 2),
      'orders' => Booking::query()->whereIn('paymentStatus', self::COUNTED)->whereDate('created_at', today())->count(),
      'tickets' => (int) Booking::query()->whereIn('paymentStatus', self::COUNTED)->whereDate('created_at', today())->sum('quantity'),
    ];
  }

  private function month(): array
  {
    $now = now();
    $ranges = [
      'this' => [$now->copy()->startOfMonth(), $now],
      'last' => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()],
    ];
    $out = [];
    foreach ($ranges as $key => [$from, $to]) {
      $paid = $this->baseOnly(Booking::query()->whereIn('paymentStatus', self::PAID)->whereBetween('created_at', [$from, $to]));
      $out[$key] = [
        'sales' => round((float) (clone $paid)->sum(DB::raw('price + COALESCE(tax, 0)')), 2),
        'fees' => round((float) (clone $paid)->sum(DB::raw('COALESCE(tax, 0) + COALESCE(commission, 0)')), 2),
        'tickets' => (int) Booking::query()->whereIn('paymentStatus', self::COUNTED)->whereBetween('created_at', [$from, $to])->sum('quantity'),
        'organisers' => Organizer::query()->whereBetween('created_at', [$from, $to])->count(),
        'buyers' => Customer::query()->whereBetween('created_at', [$from, $to])->count(),
      ];
    }
    $out['label'] = $now->format('F');
    $out['previous'] = $ranges['last'][0]->format('F');

    return $out;
  }

  private function attention(): array
  {
    $payouts = Withdraw::query()->whereIn('status', [Withdraw::STATUS_PENDING, Withdraw::STATUS_HELD]);

    return [
      'payouts' => ['count' => (clone $payouts)->count(), 'amount' => round((float) (clone $payouts)->sum('amount'), 2)],
      'kyc' => Schema::hasTable('organizer_kyc_submissions') ? DB::table('organizer_kyc_submissions')->where('status', 'pending')->count() : 0,
      'approvals' => (int) DB::table('basic_settings')->value('organizer_admin_approval') === 1 ? Organizer::query()->where('status', 0)->count() : 0,
      'transfers' => Booking::query()->where('paymentStatus', 'pending')->where('gatewayType', 'offline')->count(),
      'failed' => PaymentIntent::query()->where('status', 'failed')->whereDate('created_at', today())->count(),
      'incidents' => OperationalIncident::query()->where('status', 'open')->count(),
      'support' => Schema::hasTable('support_tickets') ? DB::table('support_tickets')->where('status', 1)->count() : 0,
    ];
  }

  /** The last 12 months, oldest first. */
  private function series(): array
  {
    $start = now()->startOfMonth()->subMonths(11);
    $monthExpression = DB::getDriverName() === 'pgsql'
      ? "TO_CHAR(created_at, 'YYYY-MM')"
      : "DATE_FORMAT(created_at, '%Y-%m')";
    $rows = $this->baseOnly(Booking::query()->whereIn('paymentStatus', self::PAID)->where('created_at', '>=', $start))
      ->selectRaw("{$monthExpression} as ym, SUM(price + COALESCE(tax, 0)) as sales, SUM(COALESCE(tax, 0) + COALESCE(commission, 0)) as fees")
      ->groupByRaw($monthExpression)->get()->keyBy('ym');
    $tickets = Booking::query()->whereIn('paymentStatus', self::COUNTED)->where('created_at', '>=', $start)
      ->selectRaw("{$monthExpression} as ym, SUM(quantity) as tickets, COUNT(*) as orders")
      ->groupByRaw($monthExpression)->get()->keyBy('ym');
    $out = [];
    for ($i = 0; $i < 12; $i++) {
      $month = $start->copy()->addMonths($i);
      $key = $month->format('Y-m');
      $out[] = [
        'label' => $month->format('M'),
        'title' => $month->format('F Y'),
        'sales' => round((float) ($rows[$key]->sales ?? 0), 2),
        'fees' => round((float) ($rows[$key]->fees ?? 0), 2),
        'tickets' => (int) ($tickets[$key]->tickets ?? 0),
        'orders' => (int) ($tickets[$key]->orders ?? 0),
      ];
    }

    return $out;
  }

  /** Paid sales this year in each currency. */
  private function currencies(): array
  {
    $currencyColumn = DB::getDriverName() === 'pgsql' ? '"currencyText"' : 'currencyText';
    $rows = Booking::query()->whereIn('paymentStatus', self::PAID)->whereYear('created_at', now()->year)
      ->selectRaw("COALESCE(NULLIF({$currencyColumn}, ''), ?) as code, SUM(price + COALESCE(tax, 0)) as sales, SUM(COALESCE(tax, 0) + COALESCE(commission, 0)) as fees, COUNT(*) as orders", [TicaCurrency::base()])
      ->groupBy('code')->orderByDesc('sales')->get();

    return $rows->map(fn ($row) => [
      'code' => TicaCurrency::normalize($row->code),
      'symbol' => TicaCurrency::symbol($row->code),
      'base' => TicaCurrency::isBase($row->code),
      'sales' => round((float) $row->sales, 2),
      'fees' => round((float) $row->fees, 2),
      'orders' => (int) $row->orders,
    ])->values()->all();
  }

  private function titles(array $eventIds): array
  {
    return DB::table('event_contents')->whereIn('event_id', $eventIds)->where('language_id', $this->languageId)->pluck('title', 'event_id')->all();
  }

  private function organisers(array $organizerIds): array
  {
    return DB::table('organizer_infos')->whereIn('organizer_id', $organizerIds)->pluck('name', 'organizer_id')->all()
      + Organizer::query()->whereIn('id', $organizerIds)->pluck('username', 'id')->all();
  }

  /** Best sellers over the last 30 days. */
  private function topEvents(): array
  {
    $paymentStatusColumn = DB::getDriverName() === 'pgsql' ? '"paymentStatus"' : 'paymentStatus';
    $currencyColumn = DB::getDriverName() === 'pgsql' ? '"currencyText"' : 'currencyText';
    $rows = Booking::query()->whereIn('paymentStatus', self::COUNTED)->where('created_at', '>=', now()->subDays(30))
      ->selectRaw("event_id, organizer_id, SUM(quantity) as tickets, SUM(CASE WHEN {$paymentStatusColumn} = ? THEN price + COALESCE(tax, 0) ELSE 0 END) as sales, MAX({$currencyColumn}) as cur", ['completed'])
      ->groupBy('event_id', 'organizer_id')->orderByDesc('tickets')->limit(5)->get();
    $titles = $this->titles($rows->pluck('event_id')->all());
    $names = $this->organisers($rows->pluck('organizer_id')->filter()->all());

    return $rows->map(fn ($row) => [
      'id' => (int) $row->event_id,
      'title' => $titles[$row->event_id] ?? __('Event #:id', ['id' => $row->event_id]),
      'organiser' => $row->organizer_id ? ($names[$row->organizer_id] ?? '') : 'TICA',
      'tickets' => (int) $row->tickets,
      'sales' => round((float) $row->sales, 2),
      'symbol' => TicaCurrency::symbol($row->cur),
    ])->all();
  }

  private function recent(): array
  {
    $rows = Booking::query()->latest('id')->limit(7)->get(['id', 'event_id', 'fname', 'lname', 'email', 'price', 'tax', 'quantity', 'paymentStatus', 'currencyText', 'created_at']);
    $titles = $this->titles($rows->pluck('event_id')->all());

    return $rows->map(fn ($row) => [
      'id' => (int) $row->id,
      'buyer' => trim($row->fname . ' ' . $row->lname) ?: (string) $row->email,
      'event' => $titles[$row->event_id] ?? __('Event #:id', ['id' => $row->event_id]),
      'tickets' => (int) $row->quantity,
      'amount' => round((float) $row->price + (float) $row->tax, 2),
      'symbol' => TicaCurrency::symbol($row->currencyText),
      'status' => (string) $row->paymentStatus,
      'when' => Carbon::parse($row->created_at)->diffForHumans(),
    ])->all();
  }

  private function upcoming(): array
  {
    $events = Event::query()->where('status', 1)->whereNull('cancelled_at')
      ->whereBetween('start_date', [today()->toDateString(), today()->addDays(60)->toDateString()])
      ->orderBy('start_date')->orderBy('start_time')->limit(5)->get(['id', 'organizer_id', 'start_date', 'start_time', 'event_type']);
    $titles = $this->titles($events->pluck('id')->all());
    $sold = Booking::query()->whereIn('event_id', $events->pluck('id'))->whereIn('paymentStatus', self::COUNTED)->groupBy('event_id')->selectRaw('event_id, SUM(quantity) as n')->pluck('n', 'event_id');
    $capacity = DB::table('tickets')->whereIn('event_id', $events->pluck('id'))->where('ticket_available_type', 'limited')->groupBy('event_id')->selectRaw('event_id, SUM(ticket_available) as n')->pluck('n', 'event_id');

    return $events->map(fn ($event) => [
      'id' => (int) $event->id,
      'title' => $titles[$event->id] ?? __('Event #:id', ['id' => $event->id]),
      'date' => Carbon::parse($event->start_date . ' ' . ($event->start_time ?: '00:00'))->format('D j M, g:i A'),
      'in' => $this->inDays((int) today()->diffInDays(Carbon::parse($event->start_date)->startOfDay())),
      'sold' => (int) ($sold[$event->id] ?? 0),
      'capacity' => isset($capacity[$event->id]) ? (int) $capacity[$event->id] + (int) ($sold[$event->id] ?? 0) : null,
      'online' => $event->event_type === 'online',
    ])->all();
  }

  /**
   * Refunds paid back and payouts sent to organisers: all time, and this
   * month against the same days of last month.
   */
  private function money(): array
  {
    $now = now();
    $ranges = [
      'this' => [$now->copy()->startOfMonth(), $now],
      'last' => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()],
    ];
    $refunds = fn () => DB::table('payment_refunds')->where('status', 'refunded');
    $paid = fn () => Withdraw::query()->where('status', Withdraw::STATUS_PAID);
    $paidOn = DB::raw('COALESCE(paid_at, reviewed_at, updated_at)');
    $out = [
      'refunds' => round((float) $refunds()->sum('amount'), 2),
      'payouts' => round((float) $paid()->sum('amount'), 2),
      'tickets' => (int) Booking::query()->whereIn('paymentStatus', self::COUNTED)->sum('quantity'),
    ];
    foreach ($ranges as $key => [$from, $to]) {
      $out[$key] = [
        'refunds' => round((float) $refunds()->whereBetween(DB::raw('COALESCE(completed_at, updated_at)'), [$from, $to])->sum('amount'), 2),
        'payouts' => round((float) $paid()->whereBetween($paidOn, [$from, $to])->sum('amount'), 2),
      ];
    }

    return $out;
  }

  private function users(): array
  {
    return [
      'organisers' => Organizer::query()->count(),
      'buyers' => Customer::query()->count(),
      'admins' => DB::table('admins')->count(),
    ];
  }

  /** The latest payout requests, newest first. */
  private function payouts(): array
  {
    $rows = Withdraw::query()->latest('id')->limit(5)->get(['id', 'withdraw_id', 'organizer_id', 'amount', 'status', 'created_at', 'reviewed_at', 'paid_at']);
    $names = $this->organisers($rows->pluck('organizer_id')->filter()->all());
    $labels = [
      Withdraw::STATUS_PENDING => ['Pending', 'pending'],
      Withdraw::STATUS_PAID => ['Paid', 'paid'],
      Withdraw::STATUS_REJECTED => ['Declined', 'declined'],
      Withdraw::STATUS_HELD => ['On hold', 'held'],
      Withdraw::STATUS_PROCESSING => ['Processing', 'processing'],
    ];

    return $rows->map(fn ($row) => [
      'id' => (int) $row->id,
      'ref' => (string) ($row->withdraw_id ?: '#' . $row->id),
      'organiser' => $names[$row->organizer_id] ?? __('Organiser #:id', ['id' => $row->organizer_id]),
      'amount' => round((float) $row->amount, 2),
      'requested' => Carbon::parse($row->created_at)->format('j M Y'),
      'processed' => ($row->paid_at ?? $row->reviewed_at) ? Carbon::parse($row->paid_at ?? $row->reviewed_at)->format('j M Y') : null,
      'status' => $labels[(int) $row->status] ?? ['Unknown', 'pending'],
    ])->all();
  }

  private function inDays(int $days): string
  {
    return match (true) {
      $days <= 0 => __('Today'),
      $days === 1 => __('Tomorrow'),
      default => __('In :days days', ['days' => $days]),
    };
  }

  private function totals(): array
  {
    $earning = DB::table('earnings')->first();

    return [
      'revenue' => round((float) ($earning->total_revenue ?? 0), 2),
      'earning' => round((float) ($earning->total_earning ?? 0), 2),
      'events' => Event::query()->count(),
      'live' => Event::query()->where('status', 1)->whereNull('cancelled_at')->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', today()->toDateString()))->count(),
      'organisers' => Organizer::query()->count(),
      'buyers' => Customer::query()->count(),
      'bookings' => Booking::query()->count(),
      'transactions' => Transaction::query()->count(),
    ];
  }
}
