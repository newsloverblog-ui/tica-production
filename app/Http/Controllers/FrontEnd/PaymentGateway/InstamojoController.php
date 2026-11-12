<?php

namespace App\Http\Controllers\FrontEnd\PaymentGateway;

use App\Http\Controllers\Controller;
use App\Http\Controllers\FrontEnd\Event\BookingController;
use App\Http\Helpers\Instamojo;
use App\Models\BasicSettings\Basic;
use App\Models\Earning;
use App\Models\PaymentGateway\OnlineGateway;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class InstamojoController extends Controller
{
  private $api;

  public function __construct()
  {
    $this->middleware(function ($request, $next) {
      $data = OnlineGateway::whereKeyword('instamojo')->firstOrFail();
      $instamojoData = json_decode($data->information, true);
      $this->api = $instamojoData['sandbox_status'] == 1
        ? new Instamojo($instamojoData['key'], $instamojoData['token'], 'https://test.instamojo.com/api/1.1/')
        : new Instamojo($instamojoData['key'], $instamojoData['token']);
      return $next($request);
    });
  }

  public function bookingProcess(Request $request, $eventId)
  {
    $rules = [
      'fname' => 'required',
      'lname' => 'required',
      'email' => 'required',
      'phone' => 'required',
      'country' => 'required',
      'address' => 'required',
      'gateway' => 'required',
    ];

    $message = [];

    $message['fname.required'] = 'The first name feild is required';
    $message['lname.required'] = 'The last name feild is required';
    $message['gateway.required'] = 'The payment gateway feild is required';
    $request->validate($rules, $message);

    $booking = new BookingController();

    $currencyInfo = $this->getCurrencyInfo();

    $total = Session::get('grand_total');
    $quantity = Session::get('quantity');
    $discount = Session::get('discount');

    //tax and commission end
    $basicSetting = Basic::select('commission')->first();

    $tax_amount = Session::get('tax');
    $commission_amount = (float) Session::get('commission', 0);

    $total_early_bird_dicount = Session::get('total_early_bird_dicount');
    // checking whether the currency is set to 'INR' or not
    if ($currencyInfo->base_currency_text !== 'INR') {
      return redirect()->back()->with('currency_error', 'Invalid currency for instamojo payment.')->withInput();
    }

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
      'paymentMethod' => 'Instamojo',
      'gatewayType' => 'online',
      'paymentStatus' => 'completed',
    );


    $title = 'Event Booking';
    $notifyURL = route('event_booking.instamojo.notify');

    try {
      $response = $this->api->paymentRequestCreate(array(
        'purpose' => $title,
        'amount' => round((Session::get('grand_total') + $tax_amount), 2),
        'buyer_name' => $request->fname . ' ' . $request->lname,
        'email' => $request->email,
        'send_email' => false,
        'phone' => $request->phone,
        'send_sms' => false,
        'redirect_url' => $notifyURL
      ));


      // put some data in session before redirect to instamojo url
      $request->session()->put('eventId', $eventId);
      $request->session()->put('arrData', $arrData);
      $request->session()->put('paymentId', $response['id']);

      return redirect($response['longurl']);
    } catch (Exception $e) {
      return redirect()->back()->with('error', 'Sorry, transaction failed!')->withInput();
    }
  }

  public function notify(Request $request)
  {
    // get the information from session
    $eventId = $request->session()->get('eventId');
    $arrData = $request->session()->get('arrData');
    $paymentId = $request->session()->get('paymentId');

    $urlInfo = $request->all();

    if ($urlInfo['payment_request_id'] == $paymentId) {
      $enrol = new BookingController();

      $bookingInfo['transcation_type'] = 1;

      // store the course enrolment information in database
      $bookingInfo = $enrol->storeData($arrData);


      //add blance to admin revinue
      $earning = Earning::first();
      $earning->total_revenue = $earning->total_revenue + $arrData['price'] + $bookingInfo->tax;
      if ($bookingInfo['organizer_id'] != null) {
        $earning->total_earning = $earning->total_earning + ($bookingInfo->tax + $bookingInfo->commission);
      } else {
        $earning->total_earning = $earning->total_earning + $arrData['price'] + $bookingInfo->tax;
      }
      $earning->save();

      //storeTransaction
      $bookingInfo['paymentStatus'] = 1;
      $bookingInfo['transcation_type'] = 1;

      storeTranscation($bookingInfo);

      //store amount to organizer
      $organizerData['organizer_id'] = $bookingInfo['organizer_id'];
      $organizerData['price'] = $arrData['price'];
      $organizerData['tax'] = $bookingInfo->tax;
      $organizerData['commission'] = $bookingInfo->commission;
      storeOrganizer($organizerData);

      // queue durable booking fulfilment after accounting succeeds
      $enrol->sendMail($bookingInfo);

      // remove all session data
      $request->session()->forget('event_id');
      $request->session()->forget('selTickets');
      $request->session()->forget('arrData');
      $request->session()->forget('paymentId');
      $request->session()->forget('discount');
      return redirect(app(\App\Services\BookingCompletionUrlService::class)->make((int) $eventId, (int) $bookingInfo->id));
    } else {
      // remove all session data
      $request->session()->forget('eventId');
      $request->session()->forget('arrData');
      $request->session()->forget('paymentId');
      $request->session()->forget('discount');

      return redirect(eventBookingCancelUrl($eventId));
    }
  }
}
