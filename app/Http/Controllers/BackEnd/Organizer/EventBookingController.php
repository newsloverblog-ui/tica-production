<?php

namespace App\Http\Controllers\BackEnd\Organizer;

use App\Http\Controllers\Controller;
use App\Models\BasicSettings\Basic;
use App\Models\Event;
use App\Models\Event\Booking;
use App\Models\Event\EventContent;
use App\Models\PaymentGateway\OfflineGateway;
use App\Models\PaymentGateway\OnlineGateway;
use App\Models\PaymentIntent;
use App\Models\PaymentRefund;
use App\Models\PaymentRefundDispute;
use App\Services\AuditLogger;
use App\Services\BookingTicketDeliveryService;
use App\Services\DataExportService;
use App\Services\EventBookingDocumentStorage;
use App\Services\ManualBookingPaymentService;
use App\Services\PaidBookingFulfillmentService;
use App\Services\ManualPaymentDecisionNotificationService;
use App\Services\PaymentRefundService;
use App\Services\TicketDocumentService;
use App\Services\TicketLifecycleService;
use Carbon\Carbon;
use DomainException;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use PDF;

class EventBookingController extends Controller
{
  public function index(Request $request)
  {
    $bookingId = $paymentStatus = null;
    $eventIds = [];
    if ($request->filled('booking_id')) {
      $bookingId = $request['booking_id'];
    }

    if ($request->filled('event_title')) {
      $event_contents = EventContent::where('title', 'like', '%' . $request->event_title . '%')->get();
      foreach ($event_contents as $event_content) {
        if (!in_array($event_content->event_id, $eventIds)) {
          array_push($eventIds, $event_content->event_id);
        }
      }
    }

    if ($request->filled('status')) {
      $paymentStatus = $request['status'];
    }

    $organizer_id = Auth::guard('organizer')->user()->id;

    $bookings = Booking::join('events', 'events.id', 'bookings.event_id')
      ->when($bookingId, function ($query, $bookingId) {
        return $query->where('bookings.booking_id', 'like', '%' . $bookingId . '%');
      })
      ->when($eventIds, function ($query) use ($eventIds) {
        return $query->whereIn('event_id', $eventIds);
      })
      ->when($paymentStatus, function ($query, $paymentStatus) {
        return $query->where('bookings.paymentStatus', '=', $paymentStatus);
      })
      ->select('bookings.*')
      ->where('events.organizer_id', $organizer_id)
      ->orderByDesc('id')
      ->paginate(10);
    return view('organizer.event.booking.index', compact('bookings'));
  }
  //updatePaymentStatus
  public function updatePaymentStatus(
    Request $request,
    $id,
    ManualBookingPaymentService $payments,
    PaidBookingFulfillmentService $fulfillment,
    ManualPaymentDecisionNotificationService $notifications
  )
  {
    $validated = $request->validate(['payment_status' => ['required', 'in:completed,rejected']]);

    try {
      $result = $payments->transition(
        (int) $id,
        $validated['payment_status'],
        (int) Auth::guard('organizer')->id()
      );
    } catch (DomainException $exception) {
      return back()->with('warning', $exception->getMessage());
    }

    $booking = $result->booking;
    if (!$result->changed) {
      return back()->with('info', 'Booking payment status is already up to date.');
    }

    if ($validated['payment_status'] === 'completed') {
      try {
        $fulfillment->dispatch((int) $booking->id);
        Session::flash('success', 'Payment approved. Ticket fulfillment was queued.');
      } catch (\Throwable $exception) {
        report($exception);
        Session::flash('warning', 'Payment approved. Fulfillment will be retried automatically.');
      }
    } else {
      try {
        $notifications->dispatchBookingRejection((int) $booking->id);
        Session::flash('success', 'Booking rejected. Customer notification was queued.');
      } catch (\Throwable $exception) {
        report($exception);
        Session::flash('warning', 'Booking rejected. Customer notification will be retried automatically.');
      }
    }

    return redirect()->back();
  }

  public function generateInvoice($bookingInfo)
  {
    (new TicketDocumentService())->generateQrCodes($bookingInfo);

    // get course title
    $language = $this->getLanguage();

    $eventInfo = EventContent::where('event_id', $bookingInfo->event_id)->where('language_id', $language->id)->first();

    $width = "50%";
    $float = "right";
    $mb = "35px";
    $ml = "18px";

    $contents = PDF::loadView(
      'frontend.event.invoice',
      compact('bookingInfo', 'eventInfo', 'width', 'float', 'mb', 'ml')
    )->output();

    return app(EventBookingDocumentStorage::class)->storeInvoice($bookingInfo, $contents);
  }

  //show
  public function show($id, PaymentRefundService $refunds)
  {
    $booking = $this->ownedBooking($id);
    $booking->load(['evnt', 'ticketUnits.events', 'ticketDeliveryRequests']);

    // get course title
    $language = $this->getLanguage();

    $eventContent = EventContent::where('event_id', $booking->event_id)->where('language_id', $language->id)->first();
    if (empty($eventContent)) {
      $eventContent = EventContent::where('event_id', $booking->event_id)->first();
    }

    $paymentIntent = PaymentIntent::where('booking_id', $booking->id)
      ->whereIn('gateway', ['paystack', 'flutterwave'])
      ->where('status', 'completed')
      ->latest('id')
      ->first();
    $refundHistory = $paymentIntent
      ? PaymentRefund::where('payment_intent_id', $paymentIntent->id)->latest('id')->get()
      : collect();
    $remainingRefund = $paymentIntent ? $refunds->remainingRefundableAmount($paymentIntent) : 0.0;
    $refundDisputes = $paymentIntent
      ? PaymentRefundDispute::where('payment_intent_id', $paymentIntent->id)->latest('id')->get()
      : collect();
    $hasCheckedInTickets = (int) $booking->scan_status === 1
      || $booking->ticketUnits->contains(fn ($ticket) => $ticket->status === 'checked_in');

    return view('organizer.event.booking.details', compact(
      'booking', 'paymentIntent', 'refundHistory', 'remainingRefund',
      'refundDisputes', 'hasCheckedInTickets'
    ));
  }

  public function invoice($id, EventBookingDocumentStorage $documents)
  {
    $booking = $this->ownedBooking($id);
    AuditLogger::record('event_booking.invoice_viewed', $booking);

    return $documents->response($booking, 'invoice', 'inline');
  }

  public function resendTickets($id, BookingTicketDeliveryService $deliveries)
  {
    try {
      $deliveries->requestForOrganizer((int) $id, (int) Auth::guard('organizer')->id());
    } catch (DomainException $exception) {
      return back()->with('warning', $exception->getMessage());
    }

    return back()->with('success', 'Ticket delivery has been queued to the original booking email address.');
  }

  public function attachment($id, EventBookingDocumentStorage $documents)
  {
    $booking = $this->ownedBooking($id);
    AuditLogger::record('event_booking.payment_evidence_viewed', $booking);

    return $documents->response($booking, 'attachment', 'inline');
  }

  public function reissueTicket(Request $request, $id, TicketLifecycleService $tickets)
  {
    $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);
    $tickets->reissueForOrganizer((int) $id, (int) Auth::guard('organizer')->id(), $validated['reason']);
    return back()->with('success', 'Ticket QR reissued. The previous QR is now invalid.');
  }

  public function revokeTicket(Request $request, $id, TicketLifecycleService $tickets)
  {
    $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);
    $tickets->revokeForOrganizer((int) $id, (int) Auth::guard('organizer')->id(), $validated['reason']);
    return back()->with('success', 'Ticket revoked successfully.');
  }

  public function destroy($id)
  {
    $Booking = $this->ownedBooking($id);
    if (!BookingPaymentState::canDelete($Booking->paymentStatus)) {
      return back()->with('warning', 'Completed financial booking records cannot be deleted.');
    }
    $before = $Booking->only(['booking_id', 'event_id', 'organizer_id', 'paymentStatus', 'price', 'quantity']);

    $documents = app(EventBookingDocumentStorage::class);
    $documents->delete($Booking, 'attachment');
    $documents->delete($Booking, 'invoice');

    DB::transaction(function () use ($Booking, $before) {
      AuditLogger::record('booking.deleted', $Booking, $before);
      $Booking->delete();
    });

    return redirect()->back()->with('success', 'Booking deleted successfully!');
  }

  public function bulkDestroy(Request $request)
  {
    $validated = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']]);
    $bookings = Booking::where('organizer_id', Auth::guard('organizer')->id())
      ->whereIn('id', $validated['ids'])
      ->whereNotIn('paymentStatus', ['completed', 'free', '1'])
      ->get();

    foreach ($bookings as $booking) {
      $before = $booking->only(['booking_id', 'event_id', 'organizer_id', 'paymentStatus', 'price', 'quantity']);

      $documents = app(EventBookingDocumentStorage::class);
      $documents->delete($booking, 'attachment');
      $documents->delete($booking, 'invoice');

      DB::transaction(function () use ($booking, $before) {
        AuditLogger::record('booking.deleted', $booking, $before, [], ['bulk' => true]);
        $booking->delete();
      });
    }

    Session::flash('success', 'Booking deleted successfully!');

    return response()->json(['status' => 'success'], 200);
  }

  public function report(Request $request)
  {
    $language = $this->getLanguage();

    $fromDate = $request->from_date;
    $toDate = $request->to_date;
    $paymentStatus = $request->payment_status;
    $paymentMethod = $request->payment_method;

    if (!empty($fromDate) && !empty($toDate)) {
      $bookings = Booking::join('event_contents', 'event_contents.event_id', 'bookings.event_id')
        ->leftJoin('customers', 'customers.id', 'bookings.customer_id')
        ->where('event_contents.language_id', $language->id)
        ->where('bookings.organizer_id', Auth::guard('organizer')->id())
        ->when($fromDate, function ($query, $fromDate) {
          return $query->whereDate('bookings.created_at', '>=', Carbon::parse($fromDate));
        })->when($toDate, function ($query, $toDate) {
          return $query->whereDate('bookings.created_at', '<=', Carbon::parse($toDate));
        })->when($paymentMethod, function ($query, $paymentMethod) {
          return $query->where('bookings.paymentMethod', $paymentMethod);
        })->when($paymentStatus, function ($query, $paymentStatus) {
          return $query->where('bookings.paymentStatus', '=', $paymentStatus);
        })
        ->select('event_contents.title', 'customers.fname as customerfname', 'customers.lname as customerlname', 'event_contents.slug', 'bookings.*')
        ->orderByDesc('id');

      $data['bookings'] = $bookings->paginate(10);
    } else {
      $data['bookings'] = [];
    }


    $data['onPms'] = OnlineGateway::where('status', 1)->get();
    $data['offPms'] = OfflineGateway::where('status', 1)->get();
    $data['deLang'] = $language;
    $data['abs'] = Basic::select('base_currency_symbol_position', 'base_currency_symbol')->first();


    return view('organizer.event.booking.report', $data);
  }

  public function export(Request $request, DataExportService $exports)
  {
    $validated = $request->validate([
      'booking_id' => ['nullable', 'string', 'max:100'],
      'event_title' => ['nullable', 'string', 'max:191'],
      'status' => ['nullable', 'string', 'max:40'],
      'payment_status' => ['nullable', 'string', 'max:40'],
      'payment_method' => ['nullable', 'string', 'max:100'],
      'from_date' => ['nullable', 'date'],
      'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
      'fileType' => ['required', 'in:csv,xlsx,pdf'],
    ]);
    $language = $this->getLanguage();
    $organizerId = (int) Auth::guard('organizer')->id();
    try {
      $exports->queueBookings(
        'organizer', $organizerId, $organizerId, (int) $language->id,
        $validated, $validated['fileType']
      );
    } catch (DomainException $exception) {
      Session::flash('warning', $exception->getMessage());
      return back();
    }
    return redirect()->route('organizer.data_exports.index')
      ->with('success', 'Export queued. The private download will appear when processing finishes.');
  }

  private function ownedBooking($id): Booking
  {
    return Booking::where('id', $id)
      ->where('organizer_id', Auth::guard('organizer')->id())
      ->firstOrFail();
  }
}
