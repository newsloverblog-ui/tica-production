<?php

namespace App\Services\TicaUi;

use App\Models\Event;
use App\Models\Event\Booking;
use Carbon\Carbon;
use App\Models\Event\BookingTicket;
use App\Models\Event\TicketContent;
use App\Models\Event\Wishlist;
use App\Services\BookingCompletionUrlService;
use App\Models\Language;
use App\Models\OrganizerInfo;
use App\Models\OrganizerPayoutAccount;
use App\Services\TicketIssuanceService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/** Builds window.TICA_DATA, the live data the design scripts read. */
class TicaUiPayload
{
  public function build(Language $language, string $path, ?array $event = null, array $extra = []): array
  {
    $catalog = new EventCatalog($language);
    $events = $catalog->upcoming();
    if ($event && !$events->contains(fn ($item) => $item['id'] === $event['id'])) {
      $events->push($event);
    }

    $account = $this->account($catalog, $events);
    $organizer = Auth::guard('organizer')->user();
    $section = explode('/', $path)[0];
    $dashboard = $organizer && in_array($section, ['dashboard', 'create', 'checkin'], true)
      ? (new OrganizerWorkspace($language))->build($organizer, (string) (Session::get('tica_team_member.role') ?: 'owner'))
      : null;

    return $extra + [
      'dashboard' => $dashboard,
      'gate' => $section === 'checkin' ? $this->gate($language) : null,
      'path' => $path,
      'events' => $events->values()->all(),
      'account' => $account,
      'categories' => $catalog->categories(),
      'currency' => $catalog->currency(),
      'user' => $this->customer(),
      'organizer' => $this->organizer($language),
      'csrf' => csrf_token(),
      'googleSignIn' => \App\Http\Controllers\FrontEnd\SocialSignInController::googleReady(),
      'routes' => $this->routes(),
      'flash' => array_filter([
        'success' => Session::get('success'),
        'error' => Session::get('error') ?: Session::get('alert') ?: (Session::get('alert-type') === 'error' ? Session::get('message') : null),
        'message' => Session::get('alert-type') === 'success' ? Session::get('message') : null,
      ]),
    ];
  }

  /**
   * The signed-in attendee's orders and saved events. Events no longer on
   * sale travel separately so they never appear in Discover listings.
   */
  private function account(EventCatalog $catalog, \Illuminate\Support\Collection $events): ?array
  {
    $customer = Auth::guard('customer')->user();
    if (!$customer) {
      return null;
    }

    $bookings = Booking::query()
      ->where('customer_id', $customer->id)
      ->whereIn('paymentStatus', ['completed', 'free'])
      ->latest('id')
      ->limit(100)
      ->get();
    $saved = Wishlist::query()->where('customer_id', $customer->id)->pluck('event_id')->map(fn ($id) => (int) $id)->all();
    $known = $events->keyBy('id');
    $other = [];
    foreach (array_unique(array_merge($bookings->pluck('event_id')->map(fn ($id) => (int) $id)->all(), $saved)) as $eventId) {
      if (!$known->has($eventId) && ($event = $catalog->find($eventId))) {
        $other[$eventId] = $event;
      }
    }
    $slugOf = fn (int $id) => ($known->get($id) ?? $other[$id] ?? null)['slug'] ?? null;
    $urls = app(BookingCompletionUrlService::class);

    return [
      'events' => array_values($other),
      'saved' => array_values(array_filter(array_map($slugOf, $saved))),
      'orders' => $bookings->map(function (Booking $booking) use ($slugOf, $urls) {
        $slug = $slugOf((int) $booking->event_id);
        if (!$slug) {
          return null;
        }
        $checkedIn = BookingTicket::query()->where('booking_id', $booking->id)->whereNotNull('checked_in_at')->exists();
        $total = round((float) $booking->price + (float) $booking->tax, 2);
        $refunded = in_array((string) $booking->refund_status, ['refunded', 'partially_refunded', 'completed'], true) || (float) $booking->refunded_amount > 0;

        return [
          'slug' => $slug,
          'n' => (int) $booking->quantity,
          'ref' => $booking->order_reference,
          'url' => $urls->make((int) $booking->event_id, (int) $booking->id),
          'attended' => $checkedIn,
          'id' => (int) $booking->id,
          'placed' => Carbon::parse($booking->created_at)->format('j M Y'),
          'total' => $total,
          'symbol' => \App\Support\TicaCurrency::symbol($booking->currencyText),
          'status' => $refunded ? 'refunded' : ($booking->paymentStatus === 'free' || $total <= 0 ? 'free' : 'paid'),
          'pdf' => route('customer.booking.current-tickets', ['id' => $booking->id]),
          'invoice' => $total > 0 ? route('customer.booking_invoice', ['id' => $booking->id]) : null,
          'resend' => route('customer.booking.resend-tickets', ['id' => $booking->id]),
        ];
      })->filter()->values()->all(),
      'profile' => [
        'fname' => (string) $customer->fname,
        'lname' => (string) $customer->lname,
        'username' => (string) $customer->username,
        'email' => (string) $customer->email,
        'phone' => (string) ($customer->phone ?? ''),
        'city' => (string) ($customer->city ?? ''),
        'country' => (string) ($customer->country ?? ''),
        'since' => Carbon::parse($customer->created_at)->format('F Y'),
        'social' => !empty($customer->provider),
        'mfa' => $customer->two_factor_enabled_at !== null,
      ],
      'links' => [
        'profile' => route('customer.profile.update'),
        'password' => route('customer.password.update'),
        'security' => route('account-security.show', ['guard' => 'customer']),
        'data' => route('customer.data-export.index'),
        'support' => optional(\Illuminate\Support\Facades\DB::table('support_ticket_statuses')->first())->support_ticket_status === 'active'
          ? route('customer.support_tickert')
          : null,
      ],
    ];
  }

  /** A completed order as the design's confirmation page renders it. */
  public function order(Booking $booking): array
  {
    $titles = TicketContent::query()
      ->whereIn('ticket_id', BookingTicket::query()->where('booking_id', $booking->id)->pluck('ticket_id')->filter())
      ->orderBy('language_id')
      ->get()
      ->unique('ticket_id')
      ->pluck('title', 'ticket_id');
    $reference = $booking->order_reference;
    $buyer = trim($booking->fname . ' ' . $booking->lname);
    $total = round((float) $booking->price + (float) $booking->tax, 2);
    $free = $booking->paymentStatus === 'free' || $total <= 0;

    $tickets = BookingTicket::query()
      ->where('booking_id', $booking->id)
      ->orderBy('sequence')
      ->get()
      ->map(fn (BookingTicket $ticket) => [
        'name' => $ticket->holder_name ?: $buyer,
        'email' => $ticket->holder_email ?: $booking->email,
        'tier' => $ticket->variation_name ?: ($titles[$ticket->ticket_id] ?? __('General admission')),
        'code' => $reference . '-' . $ticket->sequence,
        'status' => $ticket->status,
        'qr' => $ticket->status === 'issued' ? signedTicketUnitPayload($ticket->public_id, $ticket->event_id) : null,
      ])
      ->values()
      ->all();

    // Bookings made before ticket units existed still list their admissions.
    if ($tickets === []) {
      $allocations = rescue(fn () => (new TicketIssuanceService())->expectedAllocations($booking), [], false);
      foreach ($allocations as $index => $allocation) {
        $tickets[] = [
          'name' => $buyer,
          'email' => (string) $booking->email,
          'tier' => $allocation['variation_name'] ?: ($titles[$allocation['ticket_id']] ?? TicketContent::query()->where('ticket_id', $allocation['ticket_id'])->value('title') ?? __('General admission')),
          'code' => $reference . '-' . ($index + 1),
          'status' => 'issued',
          'qr' => null,
        ];
      }
    }

    [$join, $reveal] = $this->joinLink($booking);

    return [
      'ref' => $reference,
      'join' => $join,
      'reveal' => $reveal,
      'buyer' => (string) $booking->email,
      'buyerName' => $buyer,
      'total' => $free ? __('Free') : $this->money($booking, $total),
      'free' => $free,
      'guest' => empty($booking->customer_id),
      'known' => false,
      'pending' => in_array((string) $booking->paymentStatus, ['pending', 'processing'], true),
      'tickets' => $tickets,
    ];
  }

  /**
   * The online join link, shown on the ticket from the organiser's chosen
   * time before the start (or straight away) until the event ends.
   *
   * @return array{0: ?string, 1: int}
   */
  private function joinLink(Booking $booking): array
  {
    $event = Event::query()->find($booking->event_id);
    if (!$event || !($event->event_type === 'online' || $event->is_hybrid) || empty($event->online_join_url)) {
      return [null, 15];
    }
    $reveal = (int) ($event->join_reveal_minutes ?? 15);
    $start = rescue(fn () => Carbon::parse($booking->event_date ?: trim($event->start_date . ' ' . $event->start_time)), null, false);
    $opens = $reveal === 0 || !$start ? now() : $start->copy()->subMinutes($reveal);
    $open = now()->greaterThanOrEqualTo($opens) && now()->lessThan(Carbon::parse($event->end_date_time));

    return [$open ? (string) $event->online_join_url : null, $reveal];
  }

  private function money(Booking $booking, float $amount): string
  {
    $symbol = (string) ($booking->currencySymbol ?: '₦');
    $value = preg_replace('/\.00$/', '', number_format($amount, 2));

    return $booking->currencySymbolPosition === 'right' ? $value . $symbol : $symbol . $value;
  }

  /** Gate staff (scanner accounts) see only check-in, for their organiser's events. */
  private function gate(Language $language): ?array
  {
    $staff = Auth::guard('scanner')->user();
    if (!$staff || $staff->status !== 'active' || (int) Session::get('scanner_auth_version', -1) !== (int) $staff->auth_version) {
      return null;
    }
    $events = (new EventCatalog($language))->forOrganizer((int) $staff->organizer_id)
      ->filter(fn ($event) => $event['status'] === 1 && $event['endTs'] > now()->subDays(2)->getTimestampMs())
      ->sortBy('ts')
      ->map(fn ($event) => ['id' => $event['id'], 'label' => $event['name'] . ', ' . date('D', intdiv($event['ts'], 1000)) . ' ' . $event['day'] . ' ' . $event['mon']])
      ->values()
      ->all();

    return ['name' => (string) $staff->name, 'events' => $events];
  }

  private function customer(): ?array
  {
    $customer = Auth::guard('customer')->user();
    if (!$customer) {
      return null;
    }

    return [
      'id' => (int) $customer->id,
      'name' => trim($customer->fname . ' ' . $customer->lname) ?: $customer->username,
      'email' => (string) $customer->email,
      'phone' => (string) ($customer->phone ?? ''),
    ];
  }

  private function organizer(Language $language): ?array
  {
    $organizer = Auth::guard('organizer')->user();
    if (!$organizer) {
      return null;
    }
    $name = OrganizerInfo::query()->where('organizer_id', $organizer->id)->where('language_id', $language->id)->value('name');

    $account = OrganizerPayoutAccount::query()
      ->where('organizer_id', $organizer->id)
      ->orderByDesc('is_default')
      ->latest('id')
      ->first();

    return [
      'id' => (int) $organizer->id,
      'name' => $name ?: (string) $organizer->username,
      'email' => (string) $organizer->email,
      'kyc' => (string) ($organizer->kyc_status ?? ''),
      'payout' => $account ? [
        'name' => (string) $account->resolved_account_name,
        'bank' => (string) $account->bank_name,
        'num' => (string) $account->account_last4,
        'verified' => $account->verified_at !== null && $account->status === 'verified',
      ] : null,
    ];
  }

  private function routes(): array
  {
    return [
      'checkout' => route('check-out2'),
      'waitlist' => url('event/{id}/waitlist'),
      'wishlistAdd' => url('addto/wishlist/{id}'),
      'contact' => route('contact.send_mail'),
      'subscribe' => route('store_subscriber'),
    ];
  }
}
