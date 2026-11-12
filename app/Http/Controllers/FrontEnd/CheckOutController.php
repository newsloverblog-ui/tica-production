<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use App\Models\BasicSettings\Basic;
use App\Models\PaymentGateway\OfflineGateway;
use App\Models\PaymentGateway\OnlineGateway;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Auth;
use App\Models\Event\EventContent;
use App\Models\Event\Booking;
use App\Models\Event\EventDates;
use App\Models\Event\Ticket;
use Illuminate\Http\Request;
use App\Models\Event;
use App\Models\Event\TicketContent;
use App\Models\Language;
use App\Services\PaymentGatewayPolicy;
use App\Services\EventFeeCalculatorService;
use App\Services\EventCheckoutQuoteService;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CheckOutController extends Controller
{
  //checkout
  public function checkout2(Request $request)
  {
    $checkoutEvent = Event::query()->findOrFail((int) $request->event_id);
    if (($checkoutEvent->registration_mode ?: 'ticketing') === 'corporate') {
      return redirect()->route('event.registration.show', $checkoutEvent)
        ->with('error', 'This event accepts registration requests instead of ticket checkout.');
    }
    if (!$checkoutEvent->isBookable()) {
      return back()->with(['alert-type' => 'error', 'message' => 'Ticket sales are closed for this event.']);
    }
    if (($checkoutEvent->visibility ?? 'public') === 'private') {
      return back()->with(['alert-type' => 'error', 'message' => 'This event is by invitation only.']);
    }

    if ($checkoutEvent->date_type === 'multiple') {
      $request->validate(['event_date' => ['required', 'string', 'max:100']]);
      $submittedDate = (string) $request->input('event_date');
      $selectedDate = EventDates::query()
        ->where('event_id', $checkoutEvent->id)
        ->where('end_date_time', '>=', now())
        ->get()
        ->first(fn (EventDates $date): bool => FullDateTime($date->start_date_time) === $submittedDate);
      if (!$selectedDate) {
        throw ValidationException::withMessages([
          'event_date' => 'The selected event date is unavailable.',
        ]);
      }
      $request->merge(['event_date' => FullDateTime($selectedDate->start_date_time)]);
    } else {
      $endsAt = $checkoutEvent->end_date_time
        ?: trim((string) $checkoutEvent->end_date . ' ' . (string) $checkoutEvent->end_time);
      if ($endsAt !== '' && Carbon::parse($endsAt)->isPast()) {
        return back()->with(['alert-type' => 'error', 'message' => 'Ticket sales are closed for this event.']);
      }
      $request->merge([
        'event_date' => FullDateTime(trim((string) $checkoutEvent->start_date . ' ' . (string) $checkoutEvent->start_time)),
      ]);
    }

    if ($checkoutEvent->event_type === 'online') {
      $onlineTicket = Ticket::query()
        ->where('event_id', $checkoutEvent->id)
        ->firstOrFail();

      if (!in_array($onlineTicket->pricing_type, ['normal', 'free'], true)) {
        return back()->with(['alert-type' => 'error', 'message' => 'This ticket configuration is not available for online checkout.']);
      }

      $maximum = $onlineTicket->max_ticket_buy_type === 'limited'
        ? max(1, (int) $onlineTicket->max_buy_ticket)
        : 10000;
      if ($onlineTicket->max_ticket_buy_type === 'limited' && Auth::guard('customer')->check()) {
        $previousQuantity = (int) Booking::query()
          ->where('customer_id', Auth::guard('customer')->id())
          ->where('event_id', $checkoutEvent->id)
          ->where('paymentStatus', '!=', 'rejected')
          ->sum('quantity');
        $maximum = max(0, $maximum - $previousQuantity);
      }
      if ($maximum < 1) {
        return back()->with(['alert-type' => 'error', 'message' => 'You have reached the ticket purchase limit for this event.']);
      }
      $request->validate([
        'quantity' => ['required', 'integer', 'min:1', 'max:' . $maximum],
      ]);
      $request->merge(['quantity' => $request->integer('quantity')]);
    } else {
      $request->validate([
        'quantity' => ['required', 'array', 'min:1', 'max:500'],
        'quantity.*' => ['required', 'integer', 'min:0', 'max:10000'],
      ]);
      $request->merge(['quantity' => array_values($request->input('quantity'))]);
    }

    $event_guest_checkout_status = (int) Basic::query()->value('event_guest_checkout_status');
    if ($event_guest_checkout_status !== 1) {
      if (!Auth::guard('customer')->user()) {
        return redirect()->route('customer.login', ['redirectPath' => 'event_checkout']);
      }
    }
    $select = false;
    $event_type = Event::where('id', $request->event_id)->select('event_type')->first();
    if ($event_type->event_type == 'venue') {
      foreach ($request->quantity as $qty) {
        if ($qty > 0) {
          $select = true;
          break;
        }
        continue;
      }
    } else {
      if ($onlineTicket->pricing_type == 'free') {
        $select = true;
      } elseif ($onlineTicket->pricing_type == 'normal') {
        if ($request->quantity == 0) {
          $select = false;
        } else {
          $select = true;
        }
      } else {
        foreach ($request->quantity as $qty) {
          if ($qty > 0) {
            $select = true;
            break;
          }
          continue;
        }
      }
    }


    if ($select == false) {
      return back()->with(['alert-type' => 'error', 'message' => 'Please Select at least one ticket']);
    }
    $information = [];
    $information['selTickets'] = '';
    $event = Event::where('id', $request->event_id)->select('event_type')->first();

    $check = false;


    if ($event->event_type == 'online') {
      //**************** stock check start *************** */
      $stock = StockCheck($request->event_id, $request->quantity);
      if ($stock == 'error') {
        $check = true;
      }

      //*************** stock check end **************** */

      if ($onlineTicket->pricing_type == 'normal') {

        $price = $onlineTicket;
        $information['quantity'] = $request->quantity;
        $total = $request->quantity * $price->price;

        //check guest checkout status enable or not
        if ($event_guest_checkout_status !== 1) {
          //check max buy by customer 
          $max_buy = isTicketPurchaseOnline($request->event_id, $price->max_buy_ticket);
          if ($max_buy['status'] == 'true') {
            $check = true;
          } else {
            $check = false;
          }
        } else {
          $check = false;
        }



        if ($price->early_bird_discount == 'enable') {

          $start = Carbon::parse($price->early_bird_discount_date . $price->early_bird_discount_time);
          $end = Carbon::parse($price->early_bird_discount_date . $price->early_bird_discount_time);
          $today = Carbon::now();
          if ($today <= ($end)) {
            if ($price->early_bird_discount_type == 'fixed') {
              $early_bird_dicount = $price->early_bird_discount_amount;
            } else {
              $early_bird_dicount = ($price->early_bird_discount_amount * $price->price) / 100;
            }
          } else {
            $early_bird_dicount = 0;
          }
        } else {
          $early_bird_dicount = 0;
        }

        $totalEarlyBirdDiscount = $early_bird_dicount * $request->quantity;
        Session::put('total_early_bird_dicount', $totalEarlyBirdDiscount);
        Session::put('selTickets', [[
          'ticket_id' => $price->id,
          'early_bird_dicount' => $totalEarlyBirdDiscount,
          'name' => 'General admission',
          'qty' => (int) $request->quantity,
          'price' => (float) $price->price,
        ]]);
        $information['total'] = $total;
        Session::put('total', $total);
        Session::put('sub_total', $total);
        Session::put('quantity', $request->quantity);
      } elseif ($onlineTicket->pricing_type == 'free') {
        $price = $onlineTicket;
        //check guest checkout status enable or not
        if ($event_guest_checkout_status !== 1) {
          //check max buy by customer 
          $max_buy = isTicketPurchaseOnline($request->event_id, $price->max_buy_ticket);
          if ($max_buy['status'] == 'true') {
            $check = true;
          }
        }

        $information['quantity'] = $request->quantity;
        $information['total'] = 0;
        Session::put('total', 0);
        Session::put('sub_total', 0);
        Session::put('quantity', $request->quantity);
        Session::put('selTickets', [[
          'ticket_id' => $price->id,
          'early_bird_dicount' => 0,
          'name' => 'General admission',
          'qty' => (int) $request->quantity,
          'price' => 0,
        ]]);
      }
    } else {
      $tickets = Ticket::where('event_id', $request->event_id)->select('id', 'title', 'pricing_type', 'price', 'variations', 'early_bird_discount', 'early_bird_discount_amount', 'early_bird_discount_type', 'early_bird_discount_date', 'early_bird_discount_time')->get();
      $ticketArr = [];

      foreach ($tickets as $key => $ticket) {

        if ($ticket->pricing_type == 'variation') {
          $varArr1 = json_decode($ticket->variations, true);
          foreach ($varArr1 as $key => $var1) {

            $stock[] = [
              'name' => $var1['name'],
              'price' => $var1['price'],
              'ticket_available' => $var1['ticket_available'] - ($request->quantity[$key] ?? 0),
            ];

            //check early_bird discount
            if ($ticket->early_bird_discount == 'enable') {

              $start = Carbon::parse($ticket->early_bird_discount_date . $ticket->early_bird_discount_time);
              $end = Carbon::parse($ticket->early_bird_discount_date . $ticket->early_bird_discount_time);
              $today = Carbon::now();
              if ($today <= ($end)) {
                if ($ticket->early_bird_discount_type == 'fixed') {
                  $early_bird_dicount = $ticket->early_bird_discount_amount;
                } else {
                  $early_bird_dicount = ($var1['price'] * $ticket->early_bird_discount_amount) / 100;
                }
              } else {
                $early_bird_dicount = 0;
              }
            } else {
              $early_bird_dicount = 0;
            }

            $var1['type'] = $ticket->pricing_type;
            $var1['early_bird_dicount'] = $early_bird_dicount;
            $var1['ticket_id'] = $ticket->id;

            $ticketArr[] = $var1;
          }
          Session::put('stock', $stock);
        } elseif ($ticket->pricing_type == 'normal') {

          //check early_bird discount
          if ($ticket->early_bird_discount == 'enable') {

            $start = Carbon::parse($ticket->early_bird_discount_date . $ticket->early_bird_discount_time);
            $end = Carbon::parse($ticket->early_bird_discount_date . $ticket->early_bird_discount_time);
            $today = Carbon::now();
            if ($today <= ($end)) {
              if ($ticket->early_bird_discount_type == 'fixed') {
                $early_bird_dicount = $ticket->early_bird_discount_amount;
              } else {
                $early_bird_dicount = ($ticket->price * $ticket->early_bird_discount_amount) / 100;
              }
            } else {
              $early_bird_dicount = 0;
            }
          } else {
            $early_bird_dicount = 0;
          }

          $language = Language::where('is_default', 1)->first();

          $ticketContent = TicketContent::where([['ticket_id', $ticket->id], ['language_id', $language->id]])->first();
          if (empty($ticketContent)) {
            $ticketContent = TicketContent::where('ticket_id', $ticket->id)->first();
          }

          $ticketArr[] = [
            'ticket_id' => $ticket->id,
            'early_bird_dicount' => $early_bird_dicount,
            'name' => $ticketContent?->title ?: ($ticket->title ?: 'General admission'),
            'price' => $ticket->price,
            'type' => $ticket->pricing_type
          ];
        } elseif ($ticket->pricing_type == 'free') {
          $language = Language::where('is_default', 1)->first();
          $ticketContent = TicketContent::where([['ticket_id', $ticket->id], ['language_id', $language->id]])->first();
          if (empty($ticketContent)) {
            $ticketContent = TicketContent::where('ticket_id', $ticket->id)->first();
          }

          $ticketArr[] = [
            'ticket_id' => $ticket->id,
            'early_bird_dicount' => 0,
            'name' => $ticketContent?->title ?: ($ticket->title ?: 'General admission'),
            'price' => 0,
            'type' => $ticket->pricing_type
          ];
        }
      }

      $selTickets = [];
      if (count($request->quantity) !== count($ticketArr)) {
        return back()->with(['alert-type' => 'error', 'message' => 'The ticket selection is invalid. Please review it and try again.']);
      }
      foreach ($request->quantity as $key => $qty) {
        if ($qty > 0) {
          $selTickets[] = [
            'ticket_id' => $ticketArr[$key]['ticket_id'],
            'early_bird_dicount' => $qty * $ticketArr[$key]['early_bird_dicount'],
            'name' => $ticketArr[$key]['name'],
            'qty' => $qty,
            'price' => $ticketArr[$key]['price'],
          ];
        }
      }

      $sub_total = 0;
      $total_ticket = 0;
      $total_early_bird_dicount = 0;
      foreach ($selTickets as $key => $var) {
        $sub_total += $var['price'] * $var['qty'];
        $total_ticket += $var['qty'];
        $total_early_bird_dicount += $var['early_bird_dicount'];
      }

      $total = $sub_total - $total_early_bird_dicount;

      Session::put('total', round($total, 2));
      Session::put('sub_total', round($sub_total, 2));
      Session::put('quantity', $total_ticket);
      Session::put('selTickets', $selTickets);
      Session::put('discount', NULL);
      Session::put('total_early_bird_dicount', NULL);
      Session::put('total_early_bird_dicount', round($total_early_bird_dicount, 2));

      //stock check 
      foreach ($selTickets as $selTicket) {
        $stock = TicketStockCheck($selTicket['ticket_id'], $selTicket['qty'], $selTicket['name']);

        if ($stock == 'error') {
          $check = true;
          break;
        }
        //check guest checkout status enable or not
        if ($event_guest_checkout_status !== 1) {
          $check_v = isTicketPurchaseVenueBackend($request->event_id, $selTicket['ticket_id'], $selTicket['name']);
          if ($check_v['status'] == 'true') {
            $check = true;
            break;
          }
        }
      }
    }


    if ($check == true) {
      $notification = array('message' => 'Something went wrong..!', 'alert-type' => 'error');
      return back()->with($notification);
    }

    $event =  EventContent::join('events', 'events.id', 'event_contents.event_id')
      ->where('events.id', $request->event_id)
      ->select('events.*', 'event_contents.title', 'event_contents.slug', 'event_contents.city', 'event_contents.country')
      ->first();
    Session::put('event', $event);
    $gatewayPolicy = app(PaymentGatewayPolicy::class);
    $online_gateways = OnlineGateway::where('status', 1)
      ->get()
      ->filter(fn (OnlineGateway $gateway) => $gatewayPolicy->canOfferOnline((string) $gateway->keyword))
      ->values();
    $offline_gateways = OfflineGateway::where('status', 1)->orderBy('serial_number', 'asc')->get();
    Session::put('online_gateways', $online_gateways);
    Session::put('offline_gateways', $offline_gateways);
    Session::put('event_date', $request->event_date);
    // One token represents one reviewed checkout selection. Free bookings use
    // its hash as a durable idempotency key so a double click or lost response
    // cannot consume inventory twice.
    Session::put('checkout_submission_token', (string) Str::uuid());
    // Guests may continue only when the administrator has explicitly enabled
    // event guest checkout. The previous unconditional redirect made the
    // setting ineffective even after a valid ticket selection.
    if ($event_guest_checkout_status !== 1 && Auth::guard('customer')->check() === false) {
      return redirect()->route('customer.login', ['redirectPath' => 'event_checkout']);
    }
    return redirect()->route('check-out');
  }

  public function checkout(EventFeeCalculatorService $feeCalculator, EventCheckoutQuoteService $quotes)
  {
    // FIX: Guard against direct URL access or expired session.
    // If $event is null the blade crashes on line 34 with "Attempt to read
    // property id on null". Redirect to home with a friendly message instead.
    $event = Session::get('event');
    if (empty($event)) {
      return redirect()->route('index')
        ->with('error', 'Your session has expired or no event was selected. Please choose an event and try again.');
    }
    if (($event->registration_mode ?? 'ticketing') === 'corporate') {
      Session::forget(['event', 'selTickets', 'ticket_reservation_token']);

      return redirect()->route('event.registration.show', (int) $event->id)
        ->with('error', 'This event accepts registration requests instead of ticket checkout.');
    }

    try {
      $quotes->refreshSession((int) $event->id);
    } catch (RuntimeException $exception) {
      return redirect()->route('event.details', [$event->slug, $event->id])
        ->with('error', $exception->getMessage());
    } catch (\Throwable $exception) {
      report($exception);

      return redirect()->route('event.details', [$event->slug, $event->id])
        ->with('error', 'We could not refresh your ticket selection. Please try again.');
    }

    $selectedTickets = Session::get('selTickets') ?: [];
    $subTotal = (float) Session::get('sub_total', 0);
    $earlyBirdDiscount = max(0, (float) Session::get('total_early_bird_dicount', 0));
    $discountableTotal = max(0, $subTotal - $earlyBirdDiscount);
    $discount = min(max(0, (float) Session::get('discount', 0)), $discountableTotal);
    $basicData = Basic::select('tax', 'commission', 'booking_fee_percentage')->first();
    $paidQuantity = 0;

    foreach ($selectedTickets as $selection) {
      if ((float) ($selection['price'] ?? 0) > 0) {
        $paidQuantity += max(0, (int) ($selection['qty'] ?? 0));
      }
    }

    $quote = $feeCalculator->quotePercentage(
      max(0, $discountableTotal - $discount),
      $paidQuantity,
      (float) ($basicData->booking_fee_percentage ?? 6),
      (string) (Event::query()->whereKey($event->id)->value('fee_payer') ?: 'buyer')
    );
    $tax = $quote['attendee_booking_fees'];
    $grandTotal = $quote['attendee_total'];
    Session::put('commission', $quote['commission']);

    // Payment controllers consume these server-owned values. Calculating them
    // here prevents request fields from deciding whether an order is paid and
    // keeps the amount shown in the review step identical to the gateway total.
    Session::put('discount', $discount);
    Session::put('tax', $tax);
    Session::put('grand_total', $grandTotal);

    $information['selTickets']              = $selectedTickets;
    $information['total']                   = Session::get('total');
    $information['sub_total']               = $subTotal;
    $information['discount']                = $discount;
    $information['tax']                     = $tax;
    $information['grand_total']             = $grandTotal;
    $information['quantity']                = Session::get('quantity');
    $information['total_early_bird_dicount'] = $earlyBirdDiscount;
    $information['event']                   = $event;
    $information['online_gateways']         = Session::get('online_gateways');
    $information['offline_gateways']        = Session::get('offline_gateways');
    $information['basicData']               = $basicData;
    $stripe                                 = OnlineGateway::where('keyword', 'stripe')->where('status', 1)->first();
    $stripe_info                            = $stripe ? (json_decode($stripe->information, true) ?: []) : [];
    $information['stripe_key']              = $stripe_info['key'] ?? '';

    return view('frontend.check-out', $information);
  }
}
