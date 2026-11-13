<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use App\Http\Helpers\UploadFile;
use App\Http\Controllers\FrontEnd\Event\BookingController;
use App\Models\Event;
use App\Models\Event\Booking;
use App\Models\Event\BookingTicket;
use App\Models\Event\Coupon;
use App\Models\Event\EventContent;
use App\Models\Event\Ticket;
use App\Models\Event\TicketContent;
use App\Models\Language;
use App\Models\Admin;
use App\Models\Organizer;
use App\Models\OrganizerInfo;
use App\Models\OrganizerKycSubmission;
use App\Models\OrganizerPayoutAccount;
use App\Models\PaymentIntent;
use App\Models\TicaTrackingLink;
use App\Models\TicaPromotionCampaign;
use App\Models\TicaPromotionPlan;
use App\Models\TicaTrackingPixel;
use App\Models\WithdrawPaymentMethod;
use App\Rules\ImageMimeTypeRule;
use App\Services\ApplicationMailService;
use App\Services\AuditLogger;
use App\Services\BookingTicketDeliveryService;
use App\Services\EventCancellationService;
use App\Services\EventLifecycleNotificationService;
use App\Services\OrganizerPayoutAccountService;
use App\Services\OrganizerEmailVerificationService;
use App\Services\PasswordPolicy;
use App\Services\PaidBookingFulfillmentService;
use App\Services\PaymentRefundService;
use App\Services\PaystackGatewayClient;
use App\Services\TicaUi\OrganizerAttendeeDirectory;
use App\Services\TicaUi\OrganizerTeam;
use App\Services\TicaUi\OrganizerWorkspace;
use App\Services\WithdrawalService;
use App\Services\TicaPromotionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Support\TicaPlatform;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Actions behind the TICA design's organiser workspace. Every action runs
 * for the signed-in organiser (or a team member acting for them, limited by
 * their role) through Tica's existing services.
 */
class TicaWorkspaceController extends Controller
{
  private const REFUND_POLICIES = [
    'standard' => null,
    '7' => 'Refunds are available up to 7 days before the event starts.',
    '48' => 'Refunds are available up to 48 hours before the event starts.',
  ];

  /* ---------------------------------------------------------------- events */

  public function updateEvent(Request $request, int $id): JsonResponse
  {
    $this->allow('events.manage');
    $event = $this->ownedEvent($id);
    $validated = $request->validate([
      'selling' => ['required', 'boolean'],
      'payer' => ['required', Rule::in(TicaPlatform::on('organizer_pays') || $event->fee_payer === 'organizer' ? ['buyer', 'me'] : ['buyer'])],
      'visibility' => ['required', Rule::in(array_unique(array_merge(TicaPlatform::visibilities(), [(string) $event->visibility])))],
      'show_remaining' => ['required', 'boolean'],
      'waitlist' => ['required', 'boolean'],
      'transfers' => ['required', 'boolean'],
      'refund' => ['required', Rule::in(array_keys(self::REFUND_POLICIES))],
      'totals' => ['nullable', 'array', 'max:60'],
      'totals.*.ticket_id' => ['required', 'integer'],
      'totals.*.variation' => ['nullable', 'string', 'max:255'],
      'totals.*.total' => ['required', 'integer', 'min:0', 'max:1000000'],
      'join_url' => ['nullable', 'url', 'max:2000'],
      'reveal' => ['nullable', Rule::in([0, 15, 30, 60])],
      'new_ticket.name' => ['nullable', 'required_with:new_ticket', 'string', 'max:120'],
      'new_ticket.price' => ['nullable', 'numeric', 'min:0'],
      'new_ticket.quantity' => ['nullable', 'integer', 'min:1', 'max:1000000'],
    ]);

    DB::transaction(function () use ($event, $validated) {
      $event = Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();
      $before = $event->only(['sales_closed_at', 'fee_payer', 'visibility', 'show_remaining', 'waitlist_enabled', 'allow_transfers']);
      $event->forceFill([
        'sales_closed_at' => $validated['selling'] ? null : ($event->sales_closed_at ?: now()),
        'fee_payer' => $validated['payer'] === 'me' ? 'organizer' : 'buyer',
        'visibility' => $validated['visibility'],
        'show_remaining' => $validated['show_remaining'],
        'waitlist_enabled' => $validated['waitlist'] && (TicaPlatform::on('waitlist') || $event->waitlist_enabled),
        'allow_transfers' => $validated['transfers'] && (TicaPlatform::on('transfers') || $event->allow_transfers),
      ]);
      if (!empty($validated['join_url'])) {
        $event->online_join_url = $validated['join_url'];
      }
      if (array_key_exists('reveal', $validated) && $validated['reveal'] !== null) {
        $event->join_reveal_minutes = (int) $validated['reveal'];
      }
      $event->save();

      EventContent::query()->where('event_id', $event->id)->update(['refund_policy' => self::REFUND_POLICIES[$validated['refund']]]);
      foreach ($validated['totals'] ?? [] as $line) {
        $this->setTicketTotal($event, (int) $line['ticket_id'], $line['variation'] ?? null, (int) $line['total']);
      }
      if (!empty($validated['new_ticket']['name'])) {
        $this->addTicket($event, $validated['new_ticket']);
      }

      AuditLogger::record('event.settings_updated_from_tica_ui', $event, $before, $event->only(array_keys($before)));
    });

    return response()->json(['message' => 'Saved.']);
  }

  /** New total = sold + remaining; the total can never drop below what has sold. */
  private function setTicketTotal(Event $event, int $ticketId, ?string $variation, int $total): void
  {
    $ticket = Ticket::query()->where('event_id', $event->id)->whereKey($ticketId)->lockForUpdate()->firstOrFail();
    $sold = BookingTicket::query()->where('ticket_id', $ticket->id)
      ->when($variation, fn ($query) => $query->where('variation_name', $variation))
      ->whereNull('revoked_at')->count();
    if ($total < $sold) {
      throw ValidationException::withMessages(['totals' => "You've already sold {$sold}. Set the total to at least that."]);
    }

    if ($ticket->pricing_type === 'variation') {
      $variations = json_decode((string) $ticket->variations, true) ?: [];
      foreach ($variations as &$option) {
        if ((string) ($option['name'] ?? '') === (string) $variation && ($option['ticket_available_type'] ?? '') === 'limited') {
          $option['ticket_available'] = $total - $sold;
        }
      }
      unset($option);
      $ticket->variations = json_encode($variations);
    } elseif ($ticket->ticket_available_type === 'limited') {
      $ticket->ticket_available = $total - $sold;
    }
    $ticket->save();
  }

  private function addTicket(Event $event, array $data): void
  {
    if ($event->event_type === 'online' && Ticket::query()->where('event_id', $event->id)->exists()) {
      throw ValidationException::withMessages(['new_ticket' => 'Online events have one ticket type.']);
    }
    $price = round((float) ($data['price'] ?? 0), 2);
    $ticket = Ticket::create([
      'event_id' => $event->id,
      'event_type' => $event->event_type,
      'title' => $data['name'],
      'pricing_type' => $price > 0 ? 'normal' : 'free',
      'price' => $price,
      'f_price' => $price > 0 ? $price : null,
      'ticket_available_type' => empty($data['quantity']) ? 'unlimited' : 'limited',
      'ticket_available' => empty($data['quantity']) ? null : (int) $data['quantity'],
      'max_ticket_buy_type' => 'unlimited',
      'early_bird_discount' => 'disable',
    ]);
    foreach (Language::all() as $language) {
      TicketContent::create(['language_id' => $language->id, 'ticket_id' => $ticket->id, 'title' => $data['name']]);
    }
  }

  public function changeDate(Request $request, int $id, EventLifecycleNotificationService $notifications): JsonResponse
  {
    $this->allow('events.manage');
    $event = $this->ownedEvent($id);
    $validated = $request->validate(['date' => ['required', 'date', 'after_or_equal:today'], 'time' => ['required', 'date_format:H:i']]);
    if ($event->date_type === 'multiple') {
      throw ValidationException::withMessages(['date' => 'This event runs on several dates. Change them from the full event editor.']);
    }

    $oldStart = $notifications->start($event);
    $start = Carbon::parse($validated['date'] . ' ' . $validated['time']);
    $length = $oldStart && $event->end_date_time ? max(60, $oldStart->diffInMinutes(Carbon::parse($event->end_date_time))) : 180;
    $end = $start->copy()->addMinutes((int) $length);
    $event->forceFill([
      'start_date' => $start->toDateString(),
      'start_time' => $start->format('H:i'),
      'end_date' => $end->toDateString(),
      'end_time' => $end->format('H:i'),
      'end_date_time' => $end,
      'duration' => DurationCalulate($start, $end),
    ])->save();
    $sent = $notifications->notifyDateChanged($event->fresh(), $oldStart);
    AuditLogger::record('event.date_changed_from_tica_ui', $event, ['start' => $oldStart?->toIso8601String()], ['start' => $start->toIso8601String(), 'notified' => $sent]);

    return response()->json(['message' => 'New date saved.']);
  }

  public function cancel(Request $request, int $id, EventCancellationService $cancellations): JsonResponse
  {
    $this->allow('events.manage');
    $event = $this->ownedEvent($id);
    $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);
    try {
      $cancellations->requestByOrganizer($event->id, $this->organizerId(), $validated['reason']);
    } catch (RuntimeException $exception) {
      throw ValidationException::withMessages(['reason' => $exception->getMessage()]);
    }

    return response()->json(['message' => 'Cancellation requested.']);
  }

  /* ------------------------------------------------------------- attendees */

  public function attendees(Request $request, OrganizerAttendeeDirectory $directory): JsonResponse
  {
    $this->allow('orders.view');
    $filters = $this->attendeeFilters($request);
    $page = $directory->page(
      $this->organizerId(),
      $filters,
      OrganizerAttendeeDirectory::PAGE_SIZE,
      $request->query('cursor'),
    );

    return response()->json($page);
  }

  public function attendeesCsv(Request $request, OrganizerAttendeeDirectory $directory): StreamedResponse
  {
    $this->allow('orders.export');
    $filters = $this->attendeeFilters($request);
    $organizerId = $this->organizerId();
    $filename = 'tica-attendees-' . now()->format('Y-m-d') . '.csv';

    return response()->streamDownload(function () use ($directory, $filters, $organizerId) {
      $out = fopen('php://output', 'w');
      fputcsv($out, ['Name', 'Email', 'Ticket', 'Event', 'Order', 'Amount', 'Status', 'Answer']);
      foreach ($directory->batches($organizerId, $filters) as $rows) {
        foreach ($rows as $row) {
          fputcsv($out, array_map(fn ($value) => preg_match('/^[=+\-@]/', (string) $value) ? "'" . $value : $value, [
            $row['name'], $row['email'], $row['tier'], $row['event'], $row['ref'], $row['amount'], $row['status'], $row['answer'],
          ]));
        }
      }
      fclose($out);
    }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
  }

  private function attendeeFilters(Request $request): array
  {
    return $request->validate([
      'q' => ['nullable', 'string', 'max:255'],
      'event' => ['nullable', 'integer', 'min:1'],
      'status' => ['nullable', Rule::in(['paid', 'checked_in', 'refunded', 'guest'])],
      'from' => ['nullable', 'date_format:Y-m-d'],
      'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
      'cursor' => ['nullable', 'string', 'max:255'],
    ]);
  }

  public function resend(int $booking, BookingTicketDeliveryService $deliveries): JsonResponse
  {
    $this->allow('orders.manage');
    $this->ownedBooking($booking);
    try {
      $deliveries->requestForOrganizer($booking, $this->organizerId());
    } catch (RuntimeException $exception) {
      throw ValidationException::withMessages(['booking' => $exception->getMessage()]);
    }

    return response()->json(['message' => 'Tickets sent again.']);
  }

  public function refund(Request $request, int $booking, PaymentRefundService $refunds): JsonResponse
  {
    $this->allow('refunds.manage');
    $order = $this->ownedBooking($booking);
    $validated = $request->validate(['amount' => ['required', Rule::in(['full', 'ticket'])], 'reason' => ['required', 'string', 'max:300']]);
    $intent = PaymentIntent::query()->where('booking_id', $order->id)->where('status', 'completed')->first();
    if (!$intent) {
      throw ValidationException::withMessages(['booking' => 'This order has no card or bank payment to refund.']);
    }
    $amount = $validated['amount'] === 'full' ? round((float) $order->price + (float) $order->tax, 2) : round((float) $order->price, 2);
    try {
      $refunds->requestByOrganizer($intent->id, $amount, $validated['reason'], $this->organizerId());
    } catch (RuntimeException $exception) {
      throw ValidationException::withMessages(['booking' => $exception->getMessage()]);
    }

    return response()->json(['message' => 'Refund requested.']);
  }

  /** Email everyone holding a ticket (or everyone not yet checked in) for one event. */
  public function message(Request $request, ApplicationMailService $mail): JsonResponse
  {
    $this->allow('attendees.message');
    $validated = $request->validate([
      'event_id' => ['required', 'integer'],
      'audience' => ['required', Rule::in(['all', 'unscanned'])],
      'message' => ['required', 'string', 'min:5', 'max:2000'],
    ]);
    $event = $this->ownedEvent((int) $validated['event_id']);
    $key = 'tica-message:' . $event->id;
    if (Cache::has($key)) {
      throw ValidationException::withMessages(['message' => 'You just messaged these attendees. Wait a few minutes before sending another.']);
    }
    $title = EventContent::query()->where('event_id', $event->id)->orderBy('language_id')->value('title') ?: 'your event';
    $organizerName = OrganizerInfo::query()->where('organizer_id', $this->organizerId())->value('name') ?: 'The organiser';
    $emails = BookingTicket::query()
      ->join('bookings', 'bookings.id', '=', 'booking_tickets.booking_id')
      ->where('booking_tickets.event_id', $event->id)
      ->where('booking_tickets.status', 'issued')
      ->when($validated['audience'] === 'unscanned', fn ($query) => $query->whereNull('booking_tickets.checked_in_at'))
      ->get(['booking_tickets.holder_email', 'bookings.email'])
      ->map(fn ($row) => mb_strtolower(trim((string) ($row->holder_email ?: $row->email))))
      ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
      ->unique()
      ->values();
    $body = '<p>' . nl2br(e($validated['message'])) . '</p><p style="color:#526079">' . e($organizerName) . ', via TICA</p>';
    foreach ($emails as $email) {
      $mail->queueHtml('organizer_attendee_message', $email, 'A message about ' . $title, $body);
    }
    Cache::put($key, true, now()->addMinutes(5));
    AuditLogger::record('event.attendees_messaged', $event, [], ['recipients' => $emails->count(), 'audience' => $validated['audience']]);

    return response()->json(['message' => 'Message queued for ' . $emails->count() . ' ' . ($emails->count() === 1 ? 'person' : 'people') . '. They get it within a few minutes.']);
  }

  /** A free ticket for a guest, artist or sponsor. It uses stock like any sale. */
  public function guest(Request $request): JsonResponse
  {
    $this->allow('events.manage');
    $validated = $request->validate([
      'event_id' => ['required', 'integer'],
      'ticket_id' => ['required', 'integer'],
      'variation' => ['nullable', 'string', 'max:255'],
      'name' => ['required', 'string', 'min:2', 'max:200'],
      'email' => ['required', 'email:rfc', 'max:191'],
    ]);
    $event = $this->ownedEvent((int) $validated['event_id']);
    $ticket = Ticket::query()->where('event_id', $event->id)->whereKey((int) $validated['ticket_id'])->firstOrFail();
    $title = $validated['variation'] ?: (TicketContent::query()->where('ticket_id', $ticket->id)->orderBy('language_id')->value('title') ?: ($ticket->title ?: 'Guest'));
    $parts = preg_split('/\s+/u', trim($validated['name'])) ?: [];
    $first = array_shift($parts) ?: $validated['name'];

    try {
      $booking = app(BookingController::class)->storeData([
        'event_id' => $event->id,
        'price' => 0, 'tax' => 0, 'commission' => 0, 'quantity' => 1, 'discount' => 0, 'total_early_bird_dicount' => 0,
        'currencyText' => null, 'currencyTextPosition' => null, 'currencySymbol' => null, 'currencySymbolPosition' => null,
        'fname' => $first, 'lname' => $parts ? implode(' ', $parts) : $first,
        'email' => mb_strtolower($validated['email']), 'phone' => '',
        'paymentMethod' => 'Guest', 'gatewayType' => null, 'paymentStatus' => 'free',
        'event_date' => $event->date_type === 'multiple' ? null : FullDateTime(trim($event->start_date . ' ' . $event->start_time)),
        'selected_tickets' => [['ticket_id' => $ticket->id, 'early_bird_dicount' => 0, 'name' => $title, 'qty' => 1, 'price' => 0]],
        'reservation_token' => null,
        'customer_id' => null,
      ]);
    } catch (RuntimeException $exception) {
      throw ValidationException::withMessages(['ticket_id' => $exception->getMessage()]);
    }
    app(PaidBookingFulfillmentService::class)->dispatch($booking->id);
    AuditLogger::record('event.guest_ticket_issued', $booking, [], ['event_id' => $event->id, 'ticket_id' => $ticket->id]);

    return response()->json(['message' => 'Free ticket sent.']);
  }

  /* --------------------------------------------------------------- payouts */

  public function banks(\App\Services\Payouts\BankDirectory $directory): JsonResponse
  {
    $this->allow('bank.manage');
    try {
      $list = $directory->list();
    } catch (RuntimeException $exception) {
      return response()->json(['message' => $exception->getMessage()], 503);
    }

    return response()->json(['banks' => $list['banks'], 'stale' => $list['stale']]);
  }

  /**
   * Look up the account name so the organiser can confirm it before saving.
   * Before identity is approved, the name is checked against the names typed
   * on the identity form (or the ones already sent for review).
   */
  public function resolveBank(Request $request, OrganizerPayoutAccountService $accounts): JsonResponse
  {
    $this->allow('bank.manage');
    $validated = $request->validate([
      'bank_code' => ['required', 'string', 'max:30'],
      'account_number' => ['required', 'regex:/^[0-9]{10}$/'],
      'legal_name' => ['nullable', 'string', 'max:191'],
      'business_name' => ['nullable', 'string', 'max:191'],
    ], ['account_number.regex' => 'Enter your 10-digit account number.']);
    $organizer = $this->organizer();
    if ($organizer->kyc_status === 'verified') {
      $names = [(string) $organizer->verified_legal_name];
    } else {
      $pending = OrganizerKycSubmission::query()->where('organizer_id', $organizer->id)->where('status', 'pending')->latest('id')->first();
      $names = $pending ? [$pending->legal_name, $pending->business_name] : [$validated['legal_name'] ?? null, $validated['business_name'] ?? null];
    }
    try {
      $looked = $accounts->lookup($validated['bank_code'], $validated['account_number']);
    } catch (\App\Exceptions\PayoutProviderUnavailable $exception) {
      return response()->json(['message' => $exception->getMessage(), 'retry' => true], 503);
    } catch (RuntimeException $exception) {
      throw ValidationException::withMessages(['account_number' => $exception->getMessage()]);
    }
    $given = array_filter($names, fn ($name) => trim((string) $name) !== '');

    return response()->json([
      'name' => $looked['name'],
      'bank' => $looked['bank'],
      'matches' => $given ? $accounts->matchesAny($given, $looked['name']) : null,
    ]);
  }

  /** A six-digit code by email confirms a change to where money is sent. */
  /**
   * Save the payout account. The owner confirms with their password (and their
   * authenticator code when 2FA is on). A bank name that doesn't match the
   * verified identity is kept for TICA to review instead of being used.
   */
  public function saveBank(Request $request, OrganizerPayoutAccountService $accounts): JsonResponse
  {
    $this->allow('bank.manage');
    $validated = $request->validate([
      'bank_code' => ['required', 'string', 'max:30'],
      'account_number' => ['required', 'regex:/^[0-9]{10}$/'],
      'password' => ['required', 'string', 'max:200'],
      'otp' => ['nullable', 'string', 'max:20'],
    ], ['password.required' => 'Enter your password to confirm.']);
    $organizer = $this->organizer();
    app(\App\Services\SensitiveActionConfirmation::class)->confirm($organizer, $validated['password'], $validated['otp'] ?? null, 'payout-account');
    try {
      $account = $accounts->verify((int) $organizer->id, $validated['bank_code'], $validated['account_number']);
    } catch (\App\Exceptions\PayoutProviderUnavailable $exception) {
      return response()->json(['message' => $exception->getMessage(), 'retry' => true], 503);
    } catch (RuntimeException $exception) {
      throw ValidationException::withMessages(['account_number' => $exception->getMessage()]);
    }

    return response()->json($account->status === 'needs_review'
      ? ['message' => 'Sent to TICA for review. We\'ll email you within one working day.', 'status' => 'needs_review']
      : ['message' => 'Bank account saved.', 'status' => 'verified']);
  }

  public function payoutSchedule(Request $request): JsonResponse
  {
    $this->allow('bank.manage');
    $validated = $request->validate(['schedule' => ['required', Rule::in(['auto', 'manual'])]]);
    $organizer = $this->organizer();
    $before = $organizer->payout_schedule;
    $organizer->forceFill(['payout_schedule' => $validated['schedule']])->save();
    AuditLogger::record('organizer.payout_schedule_changed', $organizer, ['schedule' => $before], ['schedule' => $validated['schedule']]);

    return response()->json(['message' => 'Saved.']);
  }

  public function withdraw(Request $request): JsonResponse
  {
    $this->allow('withdrawals.request');
    $validated = $request->validate(['amount' => ['required', 'numeric', 'min:1']]);
    $method = WithdrawPaymentMethod::query()->where('status', 1)->orderByRaw("CASE WHEN LOWER(name) LIKE '%bank%' THEN 0 ELSE 1 END")->orderBy('id')->first();
    if (!$method) {
      throw ValidationException::withMessages(['amount' => 'Withdrawals aren\'t open right now. Contact TICA support.']);
    }
    $currency = $this->getCurrencyInfo();
    $withdrawal = (new WithdrawalService())->request(
      $this->organizerId(), (int) $method->id, round((float) $validated['amount'], 2), null,
      (string) $currency->base_currency_symbol, (string) $currency->base_currency_text_position
    );

    return response()->json(['reference' => (string) ($withdrawal->withdraw_id)]);
  }

  /* --------------------------------------------------------------- promote */

  public function storeCode(Request $request): JsonResponse
  {
    $this->allow('promote.manage');
    $request->merge(['code' => strtoupper(preg_replace('/\s+/', '', (string) $request->input('code')))]);
    $validated = $request->validate([
      'code' => ['required', 'regex:/^[A-Z0-9]{3,30}$/', Rule::unique('coupons', 'code')],
      'type' => ['required', Rule::in(['percentage', 'fixed'])],
      'value' => ['required', 'numeric', 'min:1', $request->input('type') === 'percentage' ? 'max:100' : 'max:100000000'],
      'max_uses' => ['nullable', 'integer', 'min:1', 'max:1000000'],
    ], ['code.unique' => 'That code is already taken. Try another.']);
    $events = Event::query()->where('organizer_id', $this->organizerId())->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
    if ($events === []) {
      throw ValidationException::withMessages(['code' => 'Create an event first, then add discount codes for it.']);
    }
    $coupon = new Coupon();
    $coupon->forceFill([
      'name' => $validated['code'],
      'code' => $validated['code'],
      'type' => $validated['type'],
      'value' => $validated['value'],
      'events' => json_encode($events),
      'start_date' => now()->toDateString(),
      'end_date' => now()->addYears(2)->toDateString(),
      'organizer_id' => $this->organizerId(),
      'max_uses' => $validated['max_uses'] ?? null,
      'used_count' => 0,
    ])->save();
    AuditLogger::record('coupon.created_by_organizer', $coupon, [], $coupon->only(['code', 'type', 'value', 'max_uses']));

    return response()->json(['message' => 'Code created.']);
  }

  public function storeLink(Request $request): JsonResponse
  {
    $this->allow('promote.manage');
    $validated = $request->validate(['name' => ['required', 'string', 'min:2', 'max:120'], 'event_id' => ['nullable', 'integer']]);
    $eventId = !empty($validated['event_id']) ? $this->ownedEvent((int) $validated['event_id'])->id : null;
    do {
      $code = strtoupper(Str::random(8));
    } while (TicaTrackingLink::query()->where('code', $code)->exists());
    TicaTrackingLink::create(['organizer_id' => $this->organizerId(), 'event_id' => $eventId, 'name' => $validated['name'], 'code' => $code, 'clicks' => 0]);

    return response()->json(['message' => 'Link created.']);
  }

  public function storePromotion(Request $request): JsonResponse
  {
    $this->allow('promote.manage');
    $validated = $request->validate([
      'event_id' => ['required', 'integer'],
      'plan_id' => ['required', 'integer'],
      'headline' => ['nullable', 'string', 'max:160'],
      'body' => ['nullable', 'string', 'max:1000'],
      'destination_url' => ['nullable', 'url:http,https', 'max:2048'],
      'starts_at' => ['required', 'date', 'after_or_equal:today', 'before_or_equal:' . now()->addDays(180)->toDateString()],
      'creative' => ['nullable', 'file', 'max:3072', new ImageMimeTypeRule()],
    ]);
    $event = $this->ownedEvent((int) $validated['event_id']);
    abort_unless((int) $event->status === 1, 422, 'Publish the event before promoting it.');
    $plan = TicaPromotionPlan::query()->whereKey($validated['plan_id'])->where('is_active', true)->firstOrFail();

    $creativePath = null;
    if ($request->hasFile('creative')) {
      $file = $request->file('creative');
      $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
      $extension = $extensions[$file->getMimeType()] ?? null;
      abort_if($extension === null, 422, 'Unsupported campaign image type.');
      $directory = public_path('assets/admin/img/tica-promotions/');
      $creativePath = Str::uuid() . '.' . $extension;
      UploadFile::storeAs($directory, $file, $creativePath);
    }

    $starts = Carbon::parse($validated['starts_at'])->startOfDay();
    $campaign = TicaPromotionCampaign::create([
      'organizer_id' => $this->organizerId(),
      'event_id' => $event->id,
      'plan_id' => $plan->id,
      'status' => (float) $plan->price > 0 ? 'pending_payment' : 'pending_review',
      'headline' => $validated['headline'] ?? null,
      'body' => $validated['body'] ?? null,
      'destination_url' => $validated['destination_url'] ?? null,
      'creative_path' => $creativePath,
      'price' => $plan->price,
      'currency' => $plan->currency,
      'starts_at' => $starts,
      'ends_at' => $starts->copy()->addDays($plan->duration_days),
    ]);
    AuditLogger::record('promotion.requested', $campaign, [], ['status' => $campaign->status, 'price' => $campaign->price, 'currency' => $campaign->currency]);

    return response()->json(['message' => (float) $campaign->price > 0 ? 'Promotion created. Pay from your TICA balance to send it for review.' : 'Promotion sent for review.']);
  }

  public function payPromotion(Request $request, int $campaign, TicaPromotionService $promotions): JsonResponse
  {
    $this->allow('promote.pay');
    $validated = $request->validate(['current_password' => ['required', 'string', 'max:255']]);
    if (!Hash::check($validated['current_password'], (string) $this->organizer()->password)) {
      throw ValidationException::withMessages(['current_password' => 'Your password is incorrect.']);
    }
    $promotions->payFromBalance($campaign, $this->organizerId());

    return response()->json(['message' => 'Paid. TICA will review the promotion before it goes live.']);
  }

  public function cancelPromotion(int $campaign): JsonResponse
  {
    $this->allow('promote.pay');
    $promotion = TicaPromotionCampaign::query()->whereKey($campaign)->where('organizer_id', $this->organizerId())->firstOrFail();
    if (in_array($promotion->status, ['pending_payment', 'pending_review', 'rejected'], true)) {
      $promotion->status = 'cancelled';
    } elseif (in_array($promotion->status, ['approved', 'scheduled', 'active'], true)) {
      $promotion->status = 'cancellation_requested';
    } else {
      throw ValidationException::withMessages(['campaign' => 'This promotion cannot be cancelled in its current state.']);
    }
    $promotion->save();
    AuditLogger::record('promotion.cancellation_requested', $promotion, [], ['status' => $promotion->status]);

    return response()->json(['message' => $promotion->status === 'cancelled' ? 'Promotion cancelled.' : 'Cancellation sent to TICA.']);
  }

  public function storePixel(Request $request): JsonResponse
  {
    $this->allow('promote.manage');
    $validated = $request->validate([
      'provider' => ['required', Rule::in(['meta', 'tiktok', 'google'])],
      'pixel_identifier' => ['required', 'string', 'min:5', 'max:100', 'regex:/^[A-Za-z0-9_-]+$/'],
      'label' => ['required', 'string', 'min:2', 'max:100'],
    ]);
    $pixel = TicaTrackingPixel::query()->updateOrCreate(
      ['organizer_id' => $this->organizerId(), 'provider' => $validated['provider'], 'pixel_identifier' => $validated['pixel_identifier']],
      ['label' => $validated['label'], 'status' => 'pending_review', 'approved_by' => null, 'approved_at' => null, 'admin_notes' => null]
    );
    AuditLogger::record('tracking_pixel.submitted', $pixel, [], ['provider' => $pixel->provider, 'status' => $pixel->status]);

    return response()->json(['message' => 'Tracking ID sent for review. TICA loads only approved provider integrations; custom scripts are never accepted.']);
  }

  /* -------------------------------------------------------- team, settings */

  public function invite(Request $request, OrganizerTeam $team): JsonResponse
  {
    $this->allow('team.manage');
    $validated = $request->validate(['email' => ['required', 'email:rfc', 'max:254'], 'role' => ['required', Rule::in(['gate', 'manager', 'finance'])]]);
    $team->invite($this->organizer(), $validated['email'], $validated['role']);

    return response()->json(['message' => 'Invite sent.']);
  }

  public function changeMemberRole(Request $request, int $member, OrganizerTeam $team): JsonResponse
  {
    $this->allow('team.manage');
    $validated = $request->validate(['role' => ['required', Rule::in(['manager', 'finance'])]]);
    $team->changeRole($this->organizer(), $member, $validated['role']);

    return response()->json(['message' => 'Role updated.']);
  }

  public function removeMember(int $member, OrganizerTeam $team): JsonResponse
  {
    $this->allow('team.manage');
    $team->remove($this->organizer(), $member);

    return response()->json(['message' => 'Removed.']);
  }

  public function profile(Request $request, OrganizerEmailVerificationService $verifications): JsonResponse
  {
    $this->allow('settings.manage');
    $organizer = $this->organizer();
    $requestedEmail = mb_strtolower(trim((string) $request->input('email')));
    $emailChanged = !hash_equals(mb_strtolower(trim((string) $organizer->email)), $requestedEmail);
    $validated = $request->validate([
      'name' => ['required', 'string', 'min:2', 'max:100'],
      'about' => ['nullable', 'string', 'max:2000'],
      'email' => ['required', 'email:rfc', 'max:254', Rule::unique('organizers', 'email')->ignore($organizer->id)],
      'username' => ['required', 'string', 'alpha_dash', 'max:100', Rule::unique('organizers', 'username')->ignore($organizer->id), Rule::notIn(Admin::query()->pluck('username')->filter()->all())],
      'current_password' => $emailChanged ? ['required', 'string'] : ['nullable', 'string'],
      'country' => ['nullable', 'string', 'max:100'],
      'city' => ['nullable', 'string', 'max:100'],
      'state' => ['nullable', 'string', 'max:100'],
      'zip_code' => ['nullable', 'string', 'max:30'],
      'address' => ['nullable', 'string', 'max:500'],
      'instagram' => ['nullable', 'string', 'max:100', 'regex:/^@?[A-Za-z0-9._]{1,30}$/'],
      'tiktok' => ['nullable', 'string', 'max:100', 'regex:/^@?[A-Za-z0-9._]{1,30}$/'],
      'facebook' => ['nullable', 'url:http,https', 'max:500'],
      'twitter' => ['nullable', 'url:http,https', 'max:500'],
      'linkedin' => ['nullable', 'url:http,https', 'max:500'],
      'youtube' => ['nullable', 'url:http,https', 'max:500'],
      'website' => ['nullable', 'url:http,https', 'max:500'],
      'phone' => ['nullable', 'string', 'max:24', 'regex:/^[0-9+() .-]{7,24}$/'],
      'photo' => ['nullable', 'file', 'max:3072', new ImageMimeTypeRule()],
    ]);
    if ($emailChanged && !Hash::check((string) ($validated['current_password'] ?? ''), (string) $organizer->password)) {
      throw ValidationException::withMessages(['current_password' => 'Your current password is incorrect.']);
    }
    $before = $organizer->only(['photo', 'email', 'username', 'phone', 'facebook', 'twitter', 'linkedin', 'preferences']);
    foreach (Language::all() as $language) {
      OrganizerInfo::query()->updateOrCreate(
        ['organizer_id' => $organizer->id, 'language_id' => $language->id],
        [
          'name' => $validated['name'],
          'details' => $validated['about'] ?? null,
          'country' => $validated['country'] ?? null,
          'city' => $validated['city'] ?? null,
          'state' => $validated['state'] ?? null,
          'zip_code' => $validated['zip_code'] ?? null,
          'address' => $validated['address'] ?? null,
        ]
      );
    }
    $prefs = (array) json_decode((string) ($organizer->preferences ?? ''), true);
    $prefs['instagram'] = $validated['instagram'] ?? '';
    $prefs['tiktok'] = $validated['tiktok'] ?? '';
    $prefs['youtube'] = $validated['youtube'] ?? '';
    $prefs['website'] = $validated['website'] ?? '';

    $changes = [
      'email' => $requestedEmail,
      'username' => trim((string) $validated['username']),
      'phone' => $validated['phone'] ?: null,
      'facebook' => $validated['facebook'] ?? null,
      'twitter' => $validated['twitter'] ?? null,
      'linkedin' => $validated['linkedin'] ?? null,
      'preferences' => json_encode($prefs),
    ];
    if ($emailChanged) {
      $changes['email_verified_at'] = null;
    }
    if ($request->hasFile('photo')) {
      // A 512px WebP: sharp at every avatar size, a fraction of a phone photo's weight.
      $directory = public_path('assets/admin/img/organizer-photo/');
      $filename = \App\Support\PublicImage::store($request->file('photo'), 'assets/admin/img/organizer-photo', 512);
      UploadFile::delete($directory, (string) $organizer->photo);
      $changes['photo'] = $filename;
    }
    $organizer->forceFill($changes)->save();
    AuditLogger::record('organizer.public_profile_updated', $organizer, $before, $organizer->fresh()->only(array_keys($before)));

    if ($emailChanged) {
      try {
        $verifications->request($organizer->fresh());
      } catch (\Throwable $exception) {
        report($exception);
      }
      Auth::guard('organizer')->logout();
      $request->session()->invalidate();
      $request->session()->regenerateToken();

      return response()->json([
        'message' => 'Email updated. Verify the new address before signing in again.',
        'redirect' => route('organizer.login'),
      ]);
    }

    $organizer->refresh();

    return response()->json(['message' => 'Saved.', 'photoUrl' => $organizer->avatar_url]);
  }

  public function password(Request $request): JsonResponse
  {
    $this->allow('settings.manage');
    $validated = $request->validate([
      'current_password' => ['required', 'string'],
      'new_password' => ['required', 'string', 'confirmed', PasswordPolicy::rule()],
    ]);
    $organizer = $this->organizer();
    if (!Hash::check($validated['current_password'], (string) $organizer->password)) {
      throw ValidationException::withMessages(['current_password' => 'Your current password is incorrect.']);
    }
    $organizer->forceFill(['password' => Hash::make($validated['new_password'])])->save();
    AuditLogger::record('organizer.password_changed', $organizer);

    return response()->json(['message' => 'Password updated.']);
  }

  public function prefs(Request $request): JsonResponse
  {
    $this->allow('settings.manage');
    $keys = array_keys(OrganizerWorkspace::PREFERENCE_DEFAULTS);
    $validated = $request->validate(array_fill_keys($keys, ['sometimes', 'boolean']));
    $organizer = $this->organizer();
    $prefs = array_merge((array) json_decode((string) ($organizer->preferences ?? ''), true), array_map('boolval', $validated));
    $organizer->forceFill(['preferences' => json_encode($prefs)])->save();

    return response()->json(['message' => 'Saved.']);
  }

  /* ---------------------------------------------------------------- helpers */

  private function organizer(): Organizer
  {
    return Auth::guard('organizer')->user();
  }

  private function organizerId(): int
  {
    return (int) Auth::guard('organizer')->id();
  }

  /** Checks the permission again inside the action; config/tica_team.php is the single source. */
  private function allow(string $permission): void
  {
    abort_unless(\App\Support\TeamPermissions::can($permission), 403, 'Your team role doesn\'t include this.');
  }

  private function ownedEvent(int $id): Event
  {
    return Event::query()->whereKey($id)->where('organizer_id', $this->organizerId())->firstOrFail();
  }

  private function ownedBooking(int $id): Booking
  {
    return Booking::query()->whereKey($id)->where('organizer_id', $this->organizerId())->firstOrFail();
  }
}
