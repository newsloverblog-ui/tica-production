<?php

namespace App\Http\Controllers\FrontEnd\Shop\PaymentGateway;

use App\Http\Controllers\Controller;
use App\Http\Controllers\FrontEnd\Shop\OrderController;
use App\Models\BasicSettings\Basic;
use App\Models\PaymentGateway\OfflineGateway;
use App\Models\ShopManagement\ShippingCharge;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Services\ProductOrderDocumentStorage;
use App\Rules\PaymentEvidenceFileRule;
use DomainException;
use Throwable;

class OfflineController extends Controller
{
  public function enrolmentProcess(Request $request)
  {
    $offlineGateway = OfflineGateway::whereKey((int) $request->gateway)
      ->where('status', 1)
      ->first();

    if (!$offlineGateway) {
      return redirect()->back()
        ->withInput()
        ->with('error', 'The selected payment method is invalid or unavailable.');
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

    $enrol = new OrderController();

    $currencyInfo = $this->getCurrencyInfo();
    $cart_items = (array) Session::get('cart', []);
    if ($cart_items === []) {
      return redirect()->route('shopping.cart')->with('error', 'Your cart is empty.');
    }

    $total = 0;
    $quantity = 0;
    foreach ($cart_items as $p) {
      $total += $p['price'] * $p['qty'];
      $quantity += $p['price'] * $p['qty'];
    }
    if ($request->shipping_method) {
      $shipping_cost = ShippingCharge::where('id', $request->shipping_method)->first();
      if (!$shipping_cost) {
        return redirect()->back()->withInput()->with('error', 'The selected shipping method is no longer available.');
      }
      $shipping_charge = $shipping_cost->charge;
      $shipping_method = $shipping_cost->title;
    } else {
      $shipping_charge = 0;
      $shipping_method = NULL;
    }

    $discount = Session::get('Shop_discount');
    $tax = Basic::select('shop_tax')->first();
    $tax_percentage = $tax->shop_tax;
    $total_tax_amount = ($tax_percentage / 100) * ($total - $discount);


    $grand_total = ($shipping_charge + $total + $total_tax_amount) - $discount;


    if (Auth::guard('customer')->user()) {
      $user_id = Auth::guard('customer')->user()->id;
    } else {
      $user_id = 0;
    }



    $arrData = array(
      'user_id' => $user_id,
      'fname' => $request->fname,
      'lname' => $request->lname,
      'email' => $request->email,
      'phone' => $request->phone,
      'country' => $request->country,
      'state' => $request->state,
      'city' => $request->city,
      'zip_code' => $request->zip_code,
      'address' => $request->address,

      's_fname' => $request->sameas_shipping == NULL ? $request->s_fname : $request->fname,
      's_lname' => $request->sameas_shipping == NULL ? $request->s_lname : $request->lname,
      's_email' => $request->sameas_shipping == NULL ? $request->s_email : $request->email,
      's_phone' => $request->sameas_shipping == NULL ? $request->s_phone : $request->phone,
      's_country' => $request->sameas_shipping == NULL ? $request->s_country : $request->country,
      's_state' => $request->sameas_shipping == NULL ? $request->s_state : $request->state,
      's_city' => $request->sameas_shipping == NULL ? $request->s_city : $request->city,
      's_zip_code' => $request->sameas_shipping == NULL ? $request->s_zip_code : $request->zip_code,
      's_address' => $request->sameas_shipping == NULL ? $request->s_address : $request->address,

      'cart_total' => $total,
      'discount' => $discount,
      'tax_percentage' => $tax_percentage,
      'tax' => $total_tax_amount,
      'grand_total' => $grand_total,
      'currency_code' => '',

      'shipping_charge' => $shipping_charge,
      'shipping_method' => $shipping_method,
      'charge_id' => $request->shipping_method,

      'method' => $offlineGateway->name,
      'gateway_type' => 'offline',
      'payment_status' => 'pending',
      'order_status' => 'pending',
      'tnxid' => '',
    );

    $documents = app(ProductOrderDocumentStorage::class);
    $orderInfo = null;
    $receiptPath = null;
    try {
      // Order identity, private evidence and stock reservation are one logical
      // submission. A failure rolls back database state and removes any file
      // already written to object storage.
      DB::transaction(function () use (
        $enrol, $arrData, $request, $documents, &$orderInfo, &$receiptPath
      ): void {
        $orderInfo = $enrol->storeData($arrData);
        if ($request->hasFile('attachment')) {
          $receiptPath = $documents->storeReceipt($orderInfo, $request->file('attachment'));
          $orderInfo->forceFill(['receipt' => $receiptPath, 'invoice' => $receiptPath])->save();
        }
        $enrol->storeOders($orderInfo);
      }, 3);
    } catch (Throwable $exception) {
      if ($orderInfo && $receiptPath) {
        try {
          $documents->deletePrivatePath($orderInfo, $receiptPath);
        } catch (Throwable $cleanupException) {
          report($cleanupException);
        }
      }
      report($exception);

      $message = $exception instanceof DomainException
        ? $exception->getMessage()
        : 'We could not securely submit this order. No stock was reserved; please try again.';
      return redirect()->route('shopping.cart')
        ->withInput()
        ->with('error', $message);
    }

    // Clear the cart only after both order persistence and inventory reservation succeed.
    Session::forget(['cart', 'Shop_discount', 'Shop_coupon_code', 'shipping_cost']);
    return redirect()->route('product_order.complete');
  }
}
