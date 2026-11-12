<?php

namespace App\Http\Controllers\FrontEnd\Event;

use App\Http\Controllers\Controller;
use App\Models\Event\Booking;
use App\Models\Event\BookingTicket;
use App\Models\Event\BookingTicketTransfer;
use App\Services\AuditLogger;
use App\Services\BookingTicketDeliveryService;
use App\Services\EventBookingDocumentStorage;
use App\Services\EventBookingInvoiceService;
use App\Services\TicketLifecycleService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use DomainException;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use RuntimeException;

class CustomerBookingController extends Controller
{
  public function my_booking()
  {
    $bookings = Booking::where('customer_id', Auth::guard('customer')->user()->id)->orderBy('created_at', 'desc')->paginate(20);
    $heldTickets = BookingTicket::query()
      ->with(['booking.event', 'pendingTransfer'])
      ->where('holder_customer_id', Auth::guard('customer')->id())
      ->where('status', 'issued')
      ->latest()
      ->get();
    return view('frontend.customer.dashboard.booking.my_booking', compact('bookings', 'heldTickets'));
  }
  //details
  public function details($id)
  {
    $booking = Booking::where('id', $id)->firstOrFail();
    if (Auth::guard('customer')->user()->id != $booking->customer_id) {
      return back();
    }
    $booking->load(['evnt', 'ticketUnits.events', 'ticketUnits.pendingTransfer', 'ticketDeliveryRequests']);
    return view('frontend.customer.dashboard.booking.details', compact('booking'));
  }

  public function resendTickets($id, BookingTicketDeliveryService $deliveries)
  {
    try {
      $deliveries->requestForCustomer((int) $id, (int) Auth::guard('customer')->id());
    } catch (DomainException $exception) {
      return back()->with('error', $exception->getMessage());
    }

    return back()->with('success', 'Your ticket email has been queued for delivery to the booking email address.');
  }

  public function invoice($id, EventBookingDocumentStorage $documents)
  {
    $booking = Booking::whereKey($id)
      ->where('customer_id', Auth::guard('customer')->id())
      ->firstOrFail();

    AuditLogger::record('event_booking.invoice_downloaded', $booking);

    return $documents->response($booking, 'invoice', 'attachment');
  }

  public function currentTickets(
    $id,
    BookingTicketDeliveryService $deliveries,
    EventBookingInvoiceService $invoices,
    EventBookingDocumentStorage $documents
  ) {
    $booking = Booking::whereKey($id)
      ->where('customer_id', Auth::guard('customer')->id())
      ->firstOrFail();

    try {
      $deliveries->assertDeliverable($booking);
    } catch (DomainException $exception) {
      return back()->with('error', $exception->getMessage());
    }

    $invoices->generateCurrentTickets($booking);

    AuditLogger::record('event_booking.current_tickets_downloaded', $booking);

    return $documents->response($booking, 'tickets', 'attachment');
  }

  public function transferTicket(Request $request, $id, TicketLifecycleService $tickets)
  {
    $validated = $request->validate([
      'holder_name' => ['required', 'string', 'max:255'],
      'holder_email' => ['required', 'email', 'max:255'],
    ]);

    $tickets->requestTransferForCustomer(
      (int) $id,
      (int) Auth::guard('customer')->id(),
      $validated['holder_name'],
      $validated['holder_email']
    );

    return back()->with('success', 'Invitation queued. The current QR remains active until the recipient accepts.');
  }

  public function cancelTransfer($id, TicketLifecycleService $tickets)
  {
    $tickets->cancelTransferForCustomer((int) $id, (int) Auth::guard('customer')->id());
    return back()->with('success', 'The pending transfer invitation has been cancelled.');
  }

  public function reissueTicket(Request $request, $id, TicketLifecycleService $tickets)
  {
    $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);
    $tickets->reissueForCustomer(
      (int) $id,
      (int) Auth::guard('customer')->id(),
      $validated['reason']
    );

    return back()->with('success', 'A new QR code has been issued. The previous QR code is no longer valid.');
  }

  public function ticketQr($id)
  {
    $customerId = (int) Auth::guard('customer')->id();
    $ticket = BookingTicket::query()
      ->whereKey($id)
      ->where(function ($query) use ($customerId) {
        $query->where('holder_customer_id', $customerId)
          ->orWhere(function ($query) use ($customerId) {
            $query->whereNull('holder_customer_id')
              ->whereHas('booking', fn ($booking) => $booking->where('customer_id', $customerId));
          });
      })
      ->firstOrFail();

    abort_unless($ticket->status === 'issued', 410, 'This ticket QR is no longer active.');
    $svg = QrCode::format('svg')->size(420)->margin(2)->generate(
      signedTicketUnitPayload($ticket->public_id, $ticket->event_id)
    );

    return response($svg, 200, [
      'Content-Type' => 'image/svg+xml',
      'Content-Disposition' => 'attachment; filename="tica-ticket-' . $ticket->sequence . '.svg"',
      'Cache-Control' => 'private, no-store, max-age=0',
    ]);
  }

  public function showTransferInvitation(Request $request, $transfer, $token)
  {
    $invitation = BookingTicketTransfer::query()
      ->with(['ticket.booking.event'])
      ->findOrFail((int) $transfer);

    abort_unless(hash_equals($invitation->token_hash, hash('sha256', (string) $token)), 404);
    abort_unless($invitation->status === 'pending' && $invitation->expires_at->isFuture(), 410, 'This transfer invitation is no longer available.');

    if (!Auth::guard('customer')->check()) {
      $request->session()->put('redirectTo', $request->fullUrl());
    }

    $acceptUrl = URL::temporarySignedRoute(
      'ticket.transfer.accept',
      $invitation->expires_at,
      ['transfer' => $invitation->id, 'token' => $token]
    );
    $customer = Auth::guard('customer')->user();
    $emailMatches = $customer
      ? mb_strtolower((string) $customer->email) === mb_strtolower($invitation->recipient_email)
      : false;

    return view('frontend.event.ticket-transfer-invitation', compact('invitation', 'acceptUrl', 'customer', 'emailMatches'));
  }

  public function acceptTransfer($transfer, $token, TicketLifecycleService $tickets)
  {
    try {
      $tickets->acceptTransfer((int) $transfer, (string) $token, (int) Auth::guard('customer')->id());
    } catch (AuthorizationException | RuntimeException $exception) {
      return back()->with('error', $exception->getMessage());
    }

    return redirect()->route('customer.booking.my_booking')
      ->with('success', 'Ticket accepted. Your new QR code is ready below, and the previous QR is invalid.');
  }
}
