<?php

namespace App\Services\TicaUi;

use App\Models\Event;
use App\Models\Event\Booking;
use App\Models\OperationalIncident;
use App\Models\Organizer;
use App\Models\PaymentIntent;
use App\Models\Withdraw;
use App\Support\EventFinancialBreakdown;
use App\Support\TicaCurrency;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The admin dashboard's reporting periods and activity lists.
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
      'recent' => $this->recent(),
      'upcoming' => $this->upcoming(),
      'payouts' => $this->payouts(),
    ];
  }

  /** Base-currency bookings: no currency recorded, or the base code. */
  private function baseOnly($query)
  {
    $base = TicaCurrency::base();

    return $query->where(fn ($q) => $q->whereNull('currencyText')->orWhere('currencyText', '')->orWhere('currencyText', $base));
  }

  private function month(): array
  {
    $now = now();
    $customerPaid = EventFinancialBreakdown::bookingCustomerPaidSql();
    $platformRevenue = EventFinancialBreakdown::bookingPlatformRevenueSql();
    $ranges = [
      'this' => [$now->copy()->startOfMonth(), $now],
      'last' => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()],
    ];
    $out = [];
    $paidOn = DB::raw('COALESCE(paid_at, reviewed_at, updated_at)');
    foreach ($ranges as $key => [$from, $to]) {
      $paid = $this->baseOnly(Booking::query()->whereIn('paymentStatus', self::PAID)->whereBetween('created_at', [$from, $to]))
        ->selectRaw("SUM({$customerPaid}) as sales, SUM({$platformRevenue}) as fees")->first();
      $out[$key] = [
        'sales' => round((float) ($paid?->sales ?? 0), 2),
        'fees' => round((float) ($paid?->fees ?? 0), 2),
        'tickets' => (int) Booking::query()->whereIn('paymentStatus', self::COUNTED)->whereBetween('created_at', [$from, $to])->sum('quantity'),
        'payouts' => round((float) Withdraw::query()->where('status', Withdraw::STATUS_PAID)->whereBetween($paidOn, [$from, $to])->sum('amount'), 2),
      ];
    }
    $out['from'] = $ranges['this'][0];
    $out['through'] = $now;

    return $out;
  }

  private function today(): array
  {
    $bookings = Booking::query()->whereBetween('created_at', [today(), now()]);
    $counted = (clone $bookings)->whereIn('paymentStatus', self::COUNTED)
      ->selectRaw('COUNT(*) as orders, SUM(quantity) as tickets')->first();
    $paid = $this->baseOnly($bookings->whereIn('paymentStatus', self::PAID));

    return [
      'orders' => (int) ($counted?->orders ?? 0),
      'tickets' => (int) ($counted?->tickets ?? 0),
      'sales' => round((float) $paid->sum(DB::raw(EventFinancialBreakdown::bookingCustomerPaidSql())), 2),
    ];
  }

  private function attention(): array
  {
    return [
      'transfers' => Booking::query()->where('paymentStatus', 'pending')->where('gatewayType', 'offline')->count(),
      'failed' => PaymentIntent::query()->where('status', 'failed')->whereDate('created_at', today())->count(),
      'incidents' => OperationalIncident::query()->where('status', 'open')->count(),
    ];
  }

  /** The last 12 months, oldest first. */
  private function series(): array
  {
    $now = now();
    $start = $now->copy()->startOfMonth()->subMonths(11);
    $monthExpression = match (DB::getDriverName()) {
      'pgsql' => "TO_CHAR(created_at, 'YYYY-MM')",
      'sqlite' => "strftime('%Y-%m', created_at)",
      default => "DATE_FORMAT(created_at, '%Y-%m')",
    };
    $tickets = Booking::query()->whereIn('paymentStatus', self::COUNTED)->whereBetween('created_at', [$start, $now])
      ->selectRaw("{$monthExpression} as ym, SUM(quantity) as tickets, COUNT(*) as orders")
      ->groupByRaw($monthExpression)->get()->keyBy('ym');
    $out = [];
    for ($i = 0; $i < 12; $i++) {
      $month = $start->copy()->addMonths($i);
      $key = $month->format('Y-m');
      $out[] = [
        'label' => $month->format('M'),
        'title' => $month->format('F Y'),
        'tickets' => (int) ($tickets[$key]->tickets ?? 0),
        'orders' => (int) ($tickets[$key]->orders ?? 0),
      ];
    }

    return $out;
  }

  private function titles(array $eventIds): array
  {
    return DB::table('event_contents')->whereIn('event_id', $eventIds)->where('language_id', $this->languageId)->pluck('title', 'event_id')->all();
  }

  private function currencies(): array
  {
    $currencyColumn = DB::connection()->getQueryGrammar()->wrap('currencyText');
    $customerPaid = EventFinancialBreakdown::bookingCustomerPaidSql();
    $platformRevenue = EventFinancialBreakdown::bookingPlatformRevenueSql();

    return Booking::query()->whereIn('paymentStatus', self::PAID)->whereYear('created_at', now()->year)
      ->selectRaw("COALESCE(NULLIF(UPPER(TRIM({$currencyColumn})), ''), ?) as code, SUM({$customerPaid}) as sales, SUM({$platformRevenue}) as fees, COUNT(*) as orders", [TicaCurrency::base()])
      ->groupBy('code')->orderByDesc('sales')->get()
      ->map(fn ($row) => [
        'code' => (string) $row->code,
        'sales' => round((float) $row->sales, 2),
        'fees' => round((float) $row->fees, 2),
        'orders' => (int) $row->orders,
      ])->all();
  }

  private function recent(): array
  {
    $rows = Booking::query()->latest('id')->limit(7)
      ->get(['id', 'event_id', 'fname', 'lname', 'email', 'price', 'tax', 'quantity', 'paymentStatus', 'currencyText', 'created_at']);
    $titles = $this->titles($rows->pluck('event_id')->all());

    return $rows->map(fn (Booking $row) => [
      'id' => (int) $row->id,
      'reference' => $row->order_reference,
      'buyer' => trim($row->fname . ' ' . $row->lname) ?: (string) $row->email,
      'event' => $titles[$row->event_id] ?? __('Event #:id', ['id' => $row->event_id]),
      'tickets' => (int) $row->quantity,
      'amount' => round((float) $row->price + (float) $row->tax, 2),
      'currency' => strtoupper(trim((string) $row->currencyText)) ?: TicaCurrency::base(),
      'status' => (string) $row->paymentStatus,
      'when' => $row->created_at?->diffForHumans(),
    ])->all();
  }

  private function organisers(array $organizerIds): array
  {
    return DB::table('organizer_infos')->whereIn('organizer_id', $organizerIds)->pluck('name', 'organizer_id')->all()
      + Organizer::query()->whereIn('id', $organizerIds)->pluck('username', 'id')->all();
  }

  private function upcoming(): array
  {
    $today = today();
    $events = Event::query()->where('status', 1)->whereNull('cancelled_at')
      ->whereBetween('start_date', [$today->toDateString(), $today->copy()->addDays(60)->toDateString()])
      ->orderBy('start_date')->orderBy('start_time')->limit(5)->get(['id', 'start_date', 'start_time']);
    $titles = $this->titles($events->pluck('id')->all());
    $sold = Booking::query()->whereIn('event_id', $events->pluck('id'))->whereIn('paymentStatus', self::COUNTED)->groupBy('event_id')->selectRaw('event_id, SUM(quantity) as n')->pluck('n', 'event_id');

    return $events->map(fn ($event) => [
      'id' => (int) $event->id,
      'title' => $titles[$event->id] ?? __('Event #:id', ['id' => $event->id]),
      'date' => Carbon::parse($event->start_date . ' ' . ($event->start_time ?: '00:00'))->format('D j M, g:i A'),
      'sold' => (int) ($sold[$event->id] ?? 0),
    ])->all();
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
      'search' => (string) $row->withdraw_id,
      'organiser' => $names[$row->organizer_id] ?? __('Organiser #:id', ['id' => $row->organizer_id]),
      'amount' => round((float) $row->amount, 2),
      'requested' => Carbon::parse($row->created_at)->format('j M Y'),
      'processed' => ($row->paid_at ?? $row->reviewed_at) ? Carbon::parse($row->paid_at ?? $row->reviewed_at)->format('j M Y') : null,
      'status' => $labels[(int) $row->status] ?? ['Unknown', 'pending'],
    ])->all();
  }
}
