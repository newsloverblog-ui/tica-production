<?php

namespace App\Http\Controllers\FrontEnd\PaymentGateway;

use App\Http\Controllers\Controller;
use App\Http\Controllers\FrontEnd\Event\BookingController;
use App\Models\BasicSettings\Basic;
use App\Models\PaymentGateway\OfflineGateway;
use App\Services\EventBookingDocumentStorage;
use App\Rules\PaymentEvidenceFileRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;


class OfflineController extends Controller
{
  public function bookingProcess(Request $request, $eventId)
  {
    $request->validate([
      'fname' => 'required',
      'lname' => 'required',
      'email' => 'required',
      'phone' => 'required',
      'country' => 'required',
      'address' => 'required',
      'gateway' => 'required',
    ]);
    $offlineGateway = OfflineGateway::whereKey($request->gateway)->where('status', 1)->first();
    if (!$offlineGateway) {
      return redirect()->back()
        ->withInput()
        ->with('error', 'The selected offline payment method is no longer available.');
    }

    // check whether attachment is required or not
    if ($offlineGateway->has_attachment == 1 || $request->hasFile('attachment')) {
      $rules = [
        'attachment' => [
          $offlineGateway->has_attachment == 1 ? 'required' : 'nullable',
          'file',
          'max:5120',
          new PaymentEvidenceFileRule(),
        ]
      ];

      $validator = Validator::make($request->all(), $rules);

      Session::flash('gatewayId', $offlineGateway->id);

      if ($validator->fails()) {
        return redirect()->back()->withErrors($validator->errors())->withInput();
      }
    }

    $booking = new BookingController();

    $currencyInfo = $this->getCurrencyInfo();
    $total = Session::get('grand_total');
    $quantity = Session::get('quantity');
    $discount = Session::get('discount');
    $total_early_bird_dicount = Session::get('total_early_bird_dicount');

    //tax and commission end


    $basicSetting = Basic::select('tax', 'commission')->first();

    $tax_amount = Session::get('tax');
    $commission_amount = (float) Session::get('commission', 0);

    $arrData = array(
      'event_id' => $eventId,
      'price' => $total,
      'tax' => $tax_amount,
      'commission' => $commission_amount,
      'quantity' => $quantity,
      'discount' => $discount,
      'total_early_bird_dicount' => $total_early_bird_dicount,
      'currencyText' => $currencyInfo->base_currency_text,
      'currencyTextPosition' => $currencyInfo->base_currency_text_position,
      'currencySymbol' => $currencyInfo->base_currency_symbol,
      'currencySymbolPosition' => $currencyInfo->base_currency_symbol_position,
      'fname' => $request->fname,
      'lname' => $request->lname,
      'email' => $request->email,
      'phone' => $request->phone,
      'country' => $request->country,
      'state' => $request->state,
      'city' => $request->city,
      'zip_code' => $request->city,
      'address' => $request->address,
      'paymentMethod' => $offlineGateway->name,
      'gatewayType' => 'offline',
      'paymentStatus' => 'pending',
    );

    $documents = app(EventBookingDocumentStorage::class);
    $attachment = null;
    $bookingInfo = null;
    try {
      // Keep inventory deduction, booking creation and the evidence reference in
      // one database transaction. A storage failure cannot leave an unsupported
      // pending booking that finance is unable to review.
      DB::transaction(function () use ($booking, $arrData, $request, $documents, &$attachment, &$bookingInfo) {
        $created = $bookingInfo = $booking->storeData($arrData);
        if ($request->hasFile('attachment')) {
          $attachment = $documents->storeAttachment($created, $request->file('attachment'));
          $created->forceFill(['attachmentFile' => $attachment])->save();
        }

        return $created;
      });
    } catch (Throwable $exception) {
      if ($bookingInfo && $attachment) {
        try {
          $documents->deletePrivatePath($bookingInfo, $attachment);
        } catch (Throwable $cleanupException) {
          report($cleanupException);
        }
      }
      report($exception);
      return redirect()->back()
        ->withInput()
        ->with('error', 'We could not securely submit this booking. No ticket was reserved; please try again.');
    }

    $request->session()->forget('event_id');
    $request->session()->forget('selTickets');
    $request->session()->forget('arrData');
    $request->session()->forget('discount');

    return redirect(app(\App\Services\BookingCompletionUrlService::class)->make((int) $eventId, (int) $bookingInfo->id, 'offline'));
  }
}
