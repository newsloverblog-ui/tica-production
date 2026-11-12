<?php

namespace App\Http\Controllers\FrontEnd\Event;

use App\Http\Controllers\Controller;
use App\Http\Controllers\FrontEnd\PaymentGateway\FlutterwaveController;
use App\Http\Controllers\FrontEnd\PaymentGateway\InstamojoController;
use App\Http\Controllers\FrontEnd\PaymentGateway\IyzipayController;
use App\Http\Controllers\FrontEnd\PaymentGateway\MercadoPagoController;
use App\Http\Controllers\FrontEnd\PaymentGateway\MidtransController;
use App\Http\Controllers\FrontEnd\PaymentGateway\MollieController;
use App\Http\Controllers\FrontEnd\PaymentGateway\MyFatoorahController;
use App\Http\Controllers\FrontEnd\PaymentGateway\OfflineController;
use App\Http\Controllers\FrontEnd\PaymentGateway\PaystackController;
use App\Http\Controllers\FrontEnd\PaymentGateway\PaytabsController;
use App\Http\Controllers\FrontEnd\PaymentGateway\PaytmController;
use App\Http\Controllers\FrontEnd\PaymentGateway\PerfectMoneyController;
use App\Http\Controllers\FrontEnd\PaymentGateway\PhonepeController;
use App\Http\Controllers\FrontEnd\PaymentGateway\RazorpayController;
use App\Http\Controllers\FrontEnd\PaymentGateway\StripeController;
use App\Http\Controllers\FrontEnd\PaymentGateway\ToyyibpayController;
use App\Http\Controllers\FrontEnd\PaymentGateway\XenditController;
use App\Http\Controllers\FrontEnd\PaymentGateway\YocoController;
use App\Models\BasicSettings\Basic;
use App\Models\Event;
use App\Models\Event\Booking;
use App\Models\Event\EventContent;
use App\Models\Event\EventDates;
use App\Models\Event\EventImage;
use App\Models\Event\Ticket;
use App\Models\Organizer;
use App\Models\PaymentGateway\OfflineGateway;
use App\Models\PaymentGateway\OnlineGateway;
use App\Services\AuditLogger;
use App\Services\PaymentGatewayPolicy;
use App\Services\TicketReservationService;
use App\Services\TicketIssuanceService;
use App\Services\EventBookingDocumentStorage;
use App\Services\EventBookingInvoiceService;
use App\Services\ConfiguredMailer;
use App\Services\LifecycleMailService;
use App\Services\TicketDocumentService;
use App\Support\LifecycleMailCatalog;
use App\Services\PaidBookingFulfillmentService;
use App\Services\EventCheckoutQuoteService;
use App\Services\EventTicketPurchaseLimitService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Schema;
use App\Models\Event\Coupon;
use Illuminate\Support\Str;
use RuntimeException;

class BookingController extends Controller
{
  public function index(Request $request, $id)
  {
    $guestCheckoutEnabled = (int) Basic::query()->value('event_guest_checkout_status') === 1;
    if (!$guestCheckoutEnabled && Auth::guard('customer')->check() === false) {
      return redirect()->route('customer.login', ['redirectPath' => 'event_checkout']);
    }

    $request->merge([
      'fname' => trim((string) $request->input('fname')),
      'lname' => trim((string) $request->input('lname')),
      'email' => trim((string) $request->input('email')),
      'phone' => trim((string) $request->input('phone')),
    ]);

    // Validate attendee identity once, before dispatching to a gateway. This
    // keeps free, online and offline bookings on the same contract instead of
    // relying on each gateway's historically inconsistent rules.
    $request->validate([
      'fname' => ['required', 'string', 'max:100'],
      'lname' => ['required', 'string', 'max:100'],
      'email' => ['required', 'string', 'email:rfc', 'max:191'],
      'phone' => ['required', 'string', 'max:40'],
    ]);

    $wasPaidBooking = Session::has('grand_total')
      ? (float) Session::get('grand_total', 0) > 0
      : (float) Session::get('sub_total', 0) > 0;
    if ($wasPaidBooking && !$request->exists('gateway')) {
      Session::flash('error', 'Please select a payment method.');

      return redirect()->back();
    }

    try {
      app(EventCheckoutQuoteService::class)->refreshSession((int) $id);
    } catch (RuntimeException $exception) {
      return redirect()->back()->with('error', $exception->getMessage())->withInput();
    } catch (\Throwable $exception) {
      report($exception);

      return redirect()->back()
        ->with('error', 'We could not refresh your ticket selection. Please try again.')
        ->withInput();
    }

    $isPaidBooking = (float) Session::get('grand_total', 0) > 0;

    // payment
    if ($isPaidBooking) {
      if (!$request->exists('gateway')) {
        Session::flash('error', 'Please select a payment method.');

        return redirect()->back();
      }

      $gatewaySelection = strtolower(trim((string) $request->input('gateway')));
      $onlineGatewayKeywords = \App\Services\PaymentGatewayPolicy::TICA_GATEWAYS;

      $eventCurrency = \App\Support\TicaCurrency::normalize(Event::query()->whereKey((int) $id)->value('currency'));
      if (!\App\Support\TicaCurrency::isBase($eventCurrency) && !in_array($gatewaySelection, ['paystack', 'stripe'], true)) {
        Session::flash('error', 'This event is priced in ' . $eventCurrency . ' and can only be paid with Paystack or Stripe.');
        return redirect()->back()->withInput();
      }

      if (in_array($gatewaySelection, $onlineGatewayKeywords, true)) {
        $gatewayIsActive = OnlineGateway::where('keyword', $gatewaySelection)
          ->where('status', 1)
          ->exists();
        $gatewayIsAllowed = app(PaymentGatewayPolicy::class)->canOfferOnline($gatewaySelection);

        if (!$gatewayIsActive || !$gatewayIsAllowed) {
          Session::flash('error', 'That payment method is not currently available. Please choose another method.');
          return redirect()->back()->withInput();
        }
      } elseif (!ctype_digit($gatewaySelection) || !OfflineGateway::whereKey((int) $gatewaySelection)->where('status', 1)->exists()) {
        Session::flash('error', 'The selected payment method is invalid or unavailable.');
        return redirect()->back()->withInput();
      }

      $bookingEvent = Event::query()->findOrFail((int) $id);
      if (($bookingEvent->registration_mode ?: 'ticketing') === 'corporate') {
        return redirect()->route('event.registration.show', $bookingEvent)
          ->with('error', 'This event accepts registration requests instead of ticket checkout.');
      }
      if (!$bookingEvent->isBookable()) {
        return back()->with('error', 'Ticket sales are closed for this event.');
      }

      if ($gatewaySelection == 'razorpay') {
        $razorpay = new RazorpayController();

        return $razorpay->bookingProcess($request, $id);
      } else if ($gatewaySelection == 'instamojo') {
        $instamojo = new InstamojoController();

        return $instamojo->bookingProcess($request, $id);
      } else if ($gatewaySelection == 'paystack') {
        $paystack = new PaystackController();

        return $paystack->bookingProcess($request, $id);
      } else if ($gatewaySelection == 'monnify') {
        return (new \App\Http\Controllers\FrontEnd\PaymentGateway\MonnifyController())->bookingProcess($request, $id);
      } else if ($gatewaySelection == 'flutterwave') {
        $flutterwave = new FlutterwaveController();

        return $flutterwave->bookingProcess($request, $id);
      } else if ($gatewaySelection == 'mercadopago') {
        $mercadopago = new MercadoPagoController();

        return $mercadopago->bookingProcess($request, $id);
      } else if ($gatewaySelection == 'mollie') {
        $mollie = new MollieController();

        return $mollie->bookingProcess($request, $id);
      } else if ($gatewaySelection == 'stripe') {
        $stripe = new StripeController();

        return $stripe->bookingProcess($request, $id);
      } else if ($gatewaySelection == 'paytm') {
        $paytm = new PaytmController();

        return $paytm->bookingProcess($request, $id);
      } else if ($gatewaySelection == 'midtrans') {
        Session::put('midtrans_payment_type', 'event');
        $paytm = new MidtransController();

        return $paytm->makePayment($request, $id);
      } else if ($gatewaySelection == 'iyzico') {
        $paytm = new IyzipayController();

        return $paytm->makePayment($request, $id);
      } else if ($gatewaySelection == 'paytabs') {
        $paytabs = new PaytabsController();

        return $paytabs->makePayment($request, $id);
      } else if ($gatewaySelection == 'toyyibpay') {
        $toyyibpay = new ToyyibpayController();

        return $toyyibpay->makePayment($request, $id);
      } else if ($gatewaySelection == 'phonepe') {
        $phonepe = new PhonepeController();

        return $phonepe->makePayment($request, $id);
      } else if ($gatewaySelection == 'yoco') {
        $yoco = new YocoController();

        return $yoco->makePayment($request, $id);
      } else if ($gatewaySelection == 'xendit') {
        $xindit = new XenditController();

        return $xindit->makePayment($request, $id);
      } else if ($gatewaySelection == 'myfatoorah') {
        $xindit = new MyFatoorahController();

        return $xindit->makePayment($request, $id);
      } else if ($gatewaySelection == 'perfect_money') {
        $perfect_money = new PerfectMoneyController();

        return $perfect_money->makePayment($request, $id);
      } else {
        $offline = new OfflineController();
        return $offline->bookingProcess($request, $id);
      }
    } else {
      try {
        $event = Event::query()->findOrFail((int) $id);
        $submissionToken = (string) Session::get('checkout_submission_token', '');
        if (!Str::isUuid($submissionToken)) {
          throw new RuntimeException('Your checkout session has expired. Please review the tickets again.');
        }
        $submissionHash = hash('sha256', $submissionToken);
        $bookingInfo = Booking::query()
          ->where('checkout_submission_hash', $submissionHash)
          ->first();
        if ($bookingInfo && (int) $bookingInfo->event_id !== (int) $event->id) {
          throw new RuntimeException('The checkout submission is already bound to another event.');
        }
        if (($event->registration_mode ?: 'ticketing') === 'corporate') {
          return redirect()->route('event.registration.show', $event)
            ->with('error', 'This event accepts registration requests instead of ticket checkout.');
        }
        if (!$bookingInfo && !$event->isBookable()) {
          throw new RuntimeException('Ticket sales are closed for this event.');
        }

        $arrData = array(
          'event_id'                 => (int) $event->id,
          'price'                    => 0,
          'tax'                      => 0,
          'commission'               => 0,
          'quantity'                 => (int) Session::get('quantity', 0),
          'discount'                 => round((float) Session::get('discount', 0), 2),
          'total_early_bird_dicount' => round((float) Session::get('total_early_bird_dicount', 0), 2),
          'currencyText'             => null,
          'currencyTextPosition'     => null,
          'currencySymbol'           => null,
          'currencySymbolPosition'   => null,
          'fname'                    => $request->fname,
          'lname'                    => $request->lname,
          'email'                    => $request->email,
          'phone'                    => $request->phone,
          'country'                  => $request->country,
          'state'                    => $request->state,
          'city'                     => $request->city,
          'zip_code'                 => $request->zip_code,
          'address'                  => $request->address,
          'paymentMethod'            => null,
          'gatewayType'              => null,
          'paymentStatus'            => 'free',
          'event_date'               => Session::get('event_date'),
          'checkout_submission_hash' => $submissionHash,
          'attendees'                => Session::get('tica_attendees'),
          'coupon_id'                => Session::get('event_coupon_id'),
          'tracking_link_id'         => Session::get('tica_tracking_link_id'),
        );

        $bookingInfo ??= $this->storeData($arrData);
        app(PaidBookingFulfillmentService::class)->dispatch($bookingInfo->id);

        $request->session()->forget('event_id');
        $request->session()->forget('selTickets');
        $request->session()->forget('arrData');
        $request->session()->forget('discount');
        $request->session()->forget('event_coupon_id');

        return redirect(app(\App\Services\BookingCompletionUrlService::class)->make(
          (int) $event->id,
          (int) $bookingInfo->id,
          'offline'
        ));
      } catch (\Throwable $th) {
        report($th);

        return redirect()->back()
          ->with('error', 'We could not complete this booking. No payment was taken; please review the selection and try again.')
          ->withInput();
      }
    }
  }

  public function storeData($info)
  {
    return DB::transaction(function () use ($info) {
      $event = Event::whereKey($info['event_id'])->lockForUpdate()->firstOrFail();
      $submissionHash = trim((string) ($info['checkout_submission_hash'] ?? ''));
      if ($submissionHash !== '') {
        if (!preg_match('/\A[a-f0-9]{64}\z/', $submissionHash)) {
          throw new RuntimeException('Invalid checkout submission identity.');
        }
        $existing = Booking::query()
          ->where('checkout_submission_hash', $submissionHash)
          ->lockForUpdate()
          ->first();
        if ($existing) {
          if ((int) $existing->event_id !== (int) $event->id || (string) $existing->paymentStatus !== 'free') {
            throw new RuntimeException('Checkout submission identity conflicts with an existing booking.');
          }

          return $existing;
        }
      }
      if (!$event->isBookable()) {
        throw new RuntimeException('Ticket sales are closed for this event.');
      }
      $selectedTickets = $info['selected_tickets'] ?? Session::get('selTickets');
      $reservationToken = $info['reservation_token'] ?? Session::get('ticket_reservation_token');
      $customerId = $info['customer_id'] ?? Auth::guard('customer')->id();
      $buyerEmailHash = app(EventTicketPurchaseLimitService::class)
        ->buyerIdentityHash($info['email'] ?? null);
      $singleTicketId = null;

      if ($reservationToken) {
        (new TicketReservationService())->consume(
          $reservationToken,
          (int) $event->id,
          false,
          $customerId,
          $buyerEmailHash,
        );
        $variations = !empty($selectedTickets) ? json_encode($selectedTickets) : null;
      } elseif (!empty($selectedTickets)) {
        foreach ($selectedTickets as $selection) {
          $quantity = (int) ($selection['qty'] ?? 0);
          if ($quantity < 1) {
            throw new RuntimeException('Invalid ticket quantity.');
          }

          $ticket = Ticket::where('id', $selection['ticket_id'])
            ->where('event_id', $event->id)
            ->lockForUpdate()
            ->firstOrFail();

          app(EventTicketPurchaseLimitService::class)->assertCanSelect(
            $ticket,
            $ticket->pricing_type === 'variation' ? (string) ($selection['name'] ?? '') : null,
            $quantity,
            $customerId,
            false,
            $buyerEmailHash,
          );

          if ($ticket->pricing_type === 'variation') {
            $ticketVariations = json_decode($ticket->variations, true) ?: [];
            $matched = false;

            foreach ($ticketVariations as &$ticketVariation) {
              if ((string) $ticketVariation['name'] !== (string) ($selection['name'] ?? '')) {
                continue;
              }

              $matched = true;
              if (($ticketVariation['ticket_available_type'] ?? null) === 'limited') {
                $available = (int) ($ticketVariation['ticket_available'] ?? 0);
                if ($available < $quantity) {
                  throw new RuntimeException('The selected ticket is no longer available in the requested quantity.');
                }
                $ticketVariation['ticket_available'] = $available - $quantity;
              }
              break;
            }
            unset($ticketVariation);

            if (!$matched) {
              throw new RuntimeException('The selected ticket option no longer exists.');
            }

            $ticket->variations = json_encode($ticketVariations);
            $ticket->save();
          } elseif ($ticket->ticket_available_type === 'limited') {
            $available = (int) $ticket->ticket_available;
            if ($available < $quantity) {
              throw new RuntimeException('The selected ticket is no longer available in the requested quantity.');
            }
            $ticket->ticket_available = $available - $quantity;
            $ticket->save();
          }
        }

        $variations = json_encode($selectedTickets);
      } else {
        $quantity = (int) ($info['quantity'] ?? 0);
        if ($quantity < 1) {
          throw new RuntimeException('Invalid ticket quantity.');
        }

        $ticket = Ticket::where('event_id', $event->id)->lockForUpdate()->firstOrFail();
        $singleTicketId = $ticket->id;
        app(EventTicketPurchaseLimitService::class)->assertCanSelect(
          $ticket,
          null,
          $quantity,
          $customerId,
          false,
          $buyerEmailHash,
        );
        if ($ticket->ticket_available_type === 'limited') {
          $available = (int) $ticket->ticket_available;
          if ($available < $quantity) {
            throw new RuntimeException('Tickets are no longer available in the requested quantity.');
          }
          $ticket->ticket_available = $available - $quantity;
          $ticket->save();
        }
        $variations = null;
      }

      $basic = Basic::where('uniqid', 12345)->select('booking_fee_percentage')->firstOrFail();
      $feePercentage = (float) ($basic->booking_fee_percentage ?? 6);
      $organizerPaysFee = ($event->fee_payer ?: 'buyer') === 'organizer';
      $bookingAttributes = [
        'customer_id'            => $customerId,
        'booking_id'             => (string) Str::uuid(),
        'checkout_submission_hash' => $submissionHash !== '' ? $submissionHash : null,
        'fname'                  => $info['fname'],
        'lname'                  => $info['lname'],
        'email'                  => $info['email'],
        'phone'                  => $info['phone'],
        'country'                => $info['country'] ?? null,
        'state'                  => $info['state'] ?? null,
        'city'                   => $info['city'] ?? null,
        'zip_code'               => $info['zip_code'] ?? null,
        'address'                => $info['address'] ?? null,
        'event_id'               => $event->id,
        'organizer_id'           => $event->organizer_id,
        'ticket_id'              => $singleTicketId,
        'variation'              => $variations,
        'price'                  => round($info['price'], 2),
        'tax'                    => round($info['tax'], 2),
        'commission'             => round($info['commission'], 2),
        'tax_percentage'         => $organizerPaysFee ? 0 : $feePercentage,
        'commission_percentage'  => $organizerPaysFee ? $feePercentage : 0,
        'quantity'               => $info['quantity'],
        'discount'               => round($info['discount'], 2),
        'early_bird_discount'    => round($info['total_early_bird_dicount'], 2),
        'currencyText'           => $info['currencyText'],
        'currencyTextPosition'   => $info['currencyTextPosition'],
        'currencySymbol'         => $info['currencySymbol'],
        'currencySymbolPosition' => $info['currencySymbolPosition'],
        'paymentMethod'          => $info['paymentMethod'],
        'gatewayType'            => $info['gatewayType'],
        'paymentStatus'          => $info['paymentStatus'],
        'invoice'                => null,
        'attachmentFile'         => $info['attachmentFile'] ?? null,
        'event_date'             => $info['event_date'] ?? Session::get('event_date'),
        'conversation_id'        => $info['conversation_id'] ?? null,
      ];
      if (Schema::hasColumn('bookings', 'buyer_email_hash')) {
        $bookingAttributes['buyer_email_hash'] = $customerId ? null : $buyerEmailHash;
      }
      if (Schema::hasColumn('bookings', 'buyer_email_hash_version')) {
        $bookingAttributes['buyer_email_hash_version'] = $customerId || !$buyerEmailHash
          ? null
          : app(EventTicketPurchaseLimitService::class)->buyerIdentityHashVersion();
      }
      $booking = Booking::create($bookingAttributes);

      AuditLogger::record('booking.created', $booking, [], [
        'event_id' => $booking->event_id,
        'organizer_id' => $booking->organizer_id,
        'quantity' => $booking->quantity,
        'price' => $booking->price,
        'payment_status' => $booking->paymentStatus,
        'payment_method' => $booking->paymentMethod,
      ]);

      // Promotion attribution: count a discount code's use and credit the
      // tracking link the buyer arrived through.
      $couponId = filter_var($info['coupon_id'] ?? null, FILTER_VALIDATE_INT);
      if ($couponId && (float) ($info['discount'] ?? 0) > 0) {
        Coupon::query()->whereKey($couponId)->increment('used_count');
      }
      $trackingLinkId = filter_var($info['tracking_link_id'] ?? null, FILTER_VALIDATE_INT);
      if ($trackingLinkId && Schema::hasColumn('bookings', 'tracking_link_id')) {
        $booking->forceFill(['tracking_link_id' => $trackingLinkId])->save();
      }

      $units = (new TicketIssuanceService())->issueForBooking($booking);
      $this->assignAttendees($units, $info['attendees'] ?? null);

      return $booking;
    }, 3);
  }

  /**
   * Name each issued ticket after the attendee entered for it at checkout.
   * Units follow the selection order, so attendee N holds ticket sequence N;
   * a blank attendee email keeps the buyer as the ticket's recipient.
   */
  private function assignAttendees($units, $attendees): void
  {
    if (!is_array($attendees) || $attendees === []) {
      return;
    }
    foreach ($units->sortBy('sequence')->values() as $index => $unit) {
      $attendee = $attendees[$index] ?? null;
      $name = trim((string) ($attendee['name'] ?? ''));
      if ($name === '') {
        continue;
      }
      $email = trim((string) ($attendee['email'] ?? ''));
      $answer = trim((string) ($attendee['answer'] ?? ''));
      $unit->forceFill(array_filter([
        'holder_name' => mb_substr($name, 0, 255),
        'holder_email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? mb_strtolower($email) : $unit->holder_email,
        'attendee_answer' => $answer !== '' ? mb_substr($answer, 0, 500) : null,
      ], fn ($value) => $value !== null))->save();
    }
  }

  public function complete(Request $request)
  {
    $language = $this->getLanguage();

    $id = $request->integer('id');
    $booking_id = $request->integer('booking_id');

    $booking = app(\App\Services\BookingCompletionUrlService::class)
      ->booking($id, $booking_id);
    $information['booking'] = $booking;

    $event = Event::where('id', $id)->with([
      'information' => function ($query) use ($language) {
        return $query->where('language_id', $language->id)->first();
      }
    ])->firstOrFail();

    $information['event'] = $event;

    Session::forget('selTickets');
    Session::forget('total');
    Session::forget('quantity');
    Session::forget('total_early_bird_dicount');
    Session::forget('event');
    Session::forget('ticket_reservation_token');
    Session::forget('checkout_submission_token');
    Session::forget('event_coupon_id');

    return app(\App\Http\Controllers\FrontEnd\TicaUiController::class)
      ->order($request, app(\App\Services\TicaUi\TicaUiPayload::class), $booking);
  }

  public function cancel($id, Request $request)
  {
    $reservationToken = $request->session()->pull('ticket_reservation_token');
    if ($reservationToken) {
      (new TicketReservationService())->release($reservationToken, 'cancelled');
    }

    return redirect()->route('check-out');
  }

  public function sendMail($bookingInfo, bool $throwOnFailure = false)
  {
    if (!$throwOnFailure) {
      app(PaidBookingFulfillmentService::class)->dispatch((int) $bookingInfo->id);
      return true;
    }

    $customerName = $bookingInfo->fname . ' ' . $bookingInfo->lname;
    $language     = $this->getLanguage();
    $eventContent = EventContent::where('event_id', $bookingInfo->event_id)->where('language_id', $language->id)->first();
    $event = Event::findOrFail($bookingInfo->event_id);
    $eventTitle = $eventContent?->title ?: 'Your event';
    $eventUrl = $eventContent
      ? route('event.details', [$eventContent->slug, $eventContent->event_id])
      : url('/events');
    $symbol = (string) ($bookingInfo->currencySymbol ?: $bookingInfo->currencyText ?: '₦');
    $money = fn ($amount) => $symbol . number_format((float) $amount, 2);
    $start = $bookingInfo->event_date
      ? \Carbon\Carbon::parse($bookingInfo->event_date)
      : \Carbon\Carbon::parse($event->start_date . ' ' . $event->start_time);
    $end = $event->end_date_time ? \Carbon\Carbon::parse($event->end_date_time) : $start->copy()->addHours(3);
    $venue = $event->event_type === 'online'
      ? 'Online event'
      : trim(collect([$eventContent?->address, $eventContent?->city, $eventContent?->state])->filter()->implode(', '));
    $calendarUrl = 'https://calendar.google.com/calendar/render?' . http_build_query([
      'action' => 'TEMPLATE', 'text' => $eventTitle,
      'dates' => $start->utc()->format('Ymd\THis\Z') . '/' . $end->utc()->format('Ymd\THis\Z'),
      'details' => 'Your Tica booking reference is ' . $bookingInfo->booking_id,
      'location' => $venue,
    ]);
    $units = app(TicketDocumentService::class)->generateQrCodes($bookingInfo, true);
    $tickets = $units->map(fn ($unit) => [
      'label' => $unit->variation_name ?: 'Event access',
      'holder' => $unit->holder_name ?: $customerName,
      'reference' => $unit->public_id,
      'qr' => $unit->qr_data_uri,
    ])->values()->all();
    $ticketsUrl = $bookingInfo->customer_id
      ? route('customer.booking_details', ['id' => $bookingInfo->id])
      : route('customer.signup');
    $rendered = app(LifecycleMailService::class)->render(
      LifecycleMailCatalog::BUYER_ORDER_CONFIRMATION,
      [
        'first_name' => $bookingInfo->fname,
        'event_title' => $eventTitle,
        'event_image' => $event->thumbnail ? asset('assets/admin/img/event/thumbnail/' . $event->thumbnail) : null,
        'event_date' => $start->timezone(config('app.timezone'))->format('D, j M Y'),
        'event_time' => $start->timezone(config('app.timezone'))->format('g:i A'),
        'event_venue' => $venue,
        'order_reference' => $bookingInfo->order_reference,
        'ticket_count' => $units->count(),
        'subtotal' => $money($bookingInfo->price),
        'booking_fee' => $money($bookingInfo->tax),
        'total' => $money((float) $bookingInfo->price + (float) $bookingInfo->tax),
        'tickets' => $tickets,
        'tickets_url' => $ticketsUrl,
        'calendar_url' => $calendarUrl,
        'event_url' => $eventUrl,
      ]
    );

    try {
      $attachments = [];
      if (!is_null($bookingInfo->invoice)) {
        $attachments[] = app(EventBookingDocumentStorage::class)->invoiceAttachmentPath($bookingInfo);
      }
      app(ConfiguredMailer::class)->sendHtml(
        $bookingInfo->email,
        $rendered['subject'],
        $rendered['html'],
        $attachments
      );

      if (!$bookingInfo->customer_id) {
        $claimUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
          'customer.ticket-claim',
          now()->addDays(30),
          ['booking' => $bookingInfo->id]
        );
        app(LifecycleMailService::class)->queueSafely(
          LifecycleMailCatalog::BUYER_SAVE_TICKETS,
          $bookingInfo->email,
          [
            'event_name' => $eventTitle,
            'order_reference' => $bookingInfo->order_reference,
            'claim_url' => $claimUrl,
          ],
          $bookingInfo
        );
      }

      if ($bookingInfo->organizer?->email) {
        app(LifecycleMailService::class)->queueOnce(
          LifecycleMailCatalog::ORGANIZER_NEW_SALE,
          $bookingInfo->organizer->email,
          [
            'first_name' => $bookingInfo->organizer->username,
            'event_title' => $eventTitle,
            'event_url' => $eventUrl,
            'order_reference' => $bookingInfo->order_reference,
            'ticket_count' => $units->count(),
            'gross_amount' => $money((float) $bookingInfo->price + (float) $bookingInfo->tax),
            'net_amount' => $money(max(0, (float) $bookingInfo->price - (float) $bookingInfo->commission)),
            'booking_url' => route('organizer.event_booking.details', ['id' => $bookingInfo->id]),
          ],
          $bookingInfo
        );
      }

      return true;
    } catch (\Exception $e) {
      throw $e;
    }
  }

  public function generateInvoice($bookingInfo, $eventId, bool $bookingRecipientOnly = false)
  {
    /*
     | FIX: Removed the silent try-catch that was swallowing PDF generation
     | errors and returning null, which caused sendMail() to fail on the
     | attachment line and silently drop the entire email.
     | Errors will now bubble up so they are visible and debuggable.
     */
    return app(EventBookingInvoiceService::class)->generate($bookingInfo, $bookingRecipientOnly);
  }
}
