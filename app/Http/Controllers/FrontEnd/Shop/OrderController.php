<?php

namespace App\Http\Controllers\FrontEnd\Shop;

use App\Http\Controllers\Controller;
use App\Http\Controllers\FrontEnd\Shop\PaymentGateway\FlutterwaveController;
use App\Http\Controllers\FrontEnd\Shop\PaymentGateway\InstamojoController;
use App\Http\Controllers\FrontEnd\Shop\PaymentGateway\IyzipayController;
use App\Http\Controllers\FrontEnd\Shop\PaymentGateway\MercadoPagoController;
use App\Http\Controllers\FrontEnd\Shop\PaymentGateway\MidtransController;
use App\Http\Controllers\FrontEnd\Shop\PaymentGateway\MollieController;
use App\Http\Controllers\FrontEnd\Shop\PaymentGateway\MyFatoorahController;
use App\Http\Controllers\FrontEnd\Shop\PaymentGateway\OfflineController;
use App\Http\Controllers\FrontEnd\Shop\PaymentGateway\PaystackController;
use App\Http\Controllers\FrontEnd\Shop\PaymentGateway\PaytabsController;
use App\Http\Controllers\FrontEnd\Shop\PaymentGateway\PaytmController;
use App\Http\Controllers\FrontEnd\Shop\PaymentGateway\PerfectMoneyController;
use App\Http\Controllers\FrontEnd\Shop\PaymentGateway\PhonepeController;
use App\Http\Controllers\FrontEnd\Shop\PaymentGateway\RazorpayController;
use App\Http\Controllers\FrontEnd\Shop\PaymentGateway\StripeController;
use App\Http\Controllers\FrontEnd\Shop\PaymentGateway\ToyyibpayController;
use App\Http\Controllers\FrontEnd\Shop\PaymentGateway\XenditController;
use App\Http\Controllers\FrontEnd\Shop\PaymentGateway\YocoController;
use App\Models\ShopManagement\ProductContent;
use App\Models\ShopManagement\ProductOrder;
use App\Models\BasicSettings\Basic;
use App\Models\BasicSettings\MailTemplate;
use App\Models\ShopManagement\OrderItem;
use App\Models\PaymentGateway\OfflineGateway;
use App\Models\PaymentGateway\OnlineGateway;
use App\Services\PaymentGatewayPolicy;
use App\Services\ProductOrderAccountingService;
use App\Services\ProductOrderInventoryService;
use App\Services\ProductOrderDocumentStorage;
use App\Services\PaidProductOrderFulfillmentService;
use App\Services\ConfiguredMailer;
use App\Services\ShopCheckoutPricingService;
use App\Services\ShopCheckoutSessionService;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use PDF;

class OrderController extends Controller
{
  public function enrol(Request $request)
  {
    $guestCheckoutEnabled = (int) Basic::query()->value('shop_guest_checkout') === 1;
    if (!Auth::guard('customer')->check() && !$guestCheckoutEnabled) {
      return redirect()->route('customer.login', ['redirectPath' => 'checkout']);
    }

    $sameAsShipping = $request->boolean('sameas_shipping');
    $request->merge([
      'fname' => trim((string) $request->input('fname')),
      'lname' => trim((string) $request->input('lname')),
      'email' => trim((string) $request->input('email')),
      'phone' => trim((string) $request->input('phone')),
      'country' => trim((string) $request->input('country')),
      'state' => trim((string) $request->input('state')),
      'city' => trim((string) $request->input('city')),
      'zip_code' => trim((string) $request->input('zip_code')),
      'address' => trim((string) $request->input('address')),
      'gateway' => trim((string) $request->input('gateway')),
      'sameas_shipping' => $sameAsShipping ? 1 : null,
    ]);

    $rules = [
      'fname' => ['required', 'string', 'max:100'],
      'lname' => ['required', 'string', 'max:100'],
      'email' => ['required', 'string', 'email:rfc', 'max:191'],
      'phone' => ['required', 'string', 'max:40'],
      'city' => ['required', 'string', 'max:120'],
      'country' => ['required', 'string', 'max:120'],
      'state' => ['nullable', 'string', 'max:120'],
      'zip_code' => ['required', 'string', 'max:30'],
      'gateway' => ['required', 'string', 'max:100'],
      'address' => ['required', 'string', 'max:500'],
      'shipping_method' => ['nullable', 'integer', 'min:1'],
    ];

    if (!$sameAsShipping) {
      $rules['s_fname'] = ['required', 'string', 'max:100'];
      $rules['s_lname'] = ['required', 'string', 'max:100'];
      $rules['s_email'] = ['required', 'string', 'email:rfc', 'max:191'];
      $rules['s_phone'] = ['required', 'string', 'max:40'];
      $rules['s_country'] = ['required', 'string', 'max:120'];
      $rules['s_state'] = ['nullable', 'string', 'max:120'];
      $rules['s_city'] = ['required', 'string', 'max:120'];
      $rules['s_zip_code'] = ['required', 'string', 'max:30'];
      $rules['s_address'] = ['required', 'string', 'max:500'];
    }

    $message = [];

    $message['fname.required'] = 'The first name feild is required';
    $message['lname.required'] = 'The last name feild is required';
    $message['gateway.required'] = 'The payment gateway feild is required';

    if (!$sameAsShipping) {
      $message['s_fname.required'] = 'The first name feild is required';
      $message['s_lname.required'] = 'The last name feild is required';
      $message['s_email.required'] = 'The email feild is required';
      $message['s_phone.required'] = 'The phone no feild is required';
      $message['s_country.required'] = 'The country feild is required';
      $message['s_city.required'] = 'The city feild is required';
      $message['s_zip_code.required'] = 'The zipcode feild is required';
      $message['s_address.required'] = 'The address feild is required';
    }
    $request->validate($rules, $message);

    $gatewaySelection = strtolower(trim((string) $request->input('gateway')));
    $request->merge(['gateway' => $gatewaySelection]);
    $onlineGateway = OnlineGateway::where('keyword', $gatewaySelection)
      ->where('status', 1)
      ->first();
    $offlineGateway = null;

    if ($onlineGateway) {
      if (!app(PaymentGatewayPolicy::class)->canOfferShopOnline($gatewaySelection)) {
        Session::flash('error', 'That online payment method is not available for shop orders. Please choose another method.');
        return redirect()->back()->withInput();
      }
    } elseif (ctype_digit($gatewaySelection)) {
      $offlineGateway = OfflineGateway::whereKey((int) $gatewaySelection)
        ->where('status', 1)
        ->first();
    }

    if (!$onlineGateway && !$offlineGateway) {
      Session::flash('error', 'The selected payment method is invalid or unavailable.');
      return redirect()->back()->withInput();
    }

    try {
      $quote = app(ShopCheckoutPricingService::class)->quote(
        (array) Session::get('cart', []),
        Session::get('Shop_coupon_code')
      );
    } catch (\DomainException $exception) {
      Session::forget(['Shop_discount', 'Shop_coupon_code']);

      return redirect()->back()->withInput()->with('error', $exception->getMessage());
    }
    Session::put('cart', $quote['cart']);
    Session::put('Shop_discount', $quote['discount']);
    $request->merge([
      'discount' => $quote['discount'],
      'coupon_code' => $quote['coupon_code'],
    ]);

    if ($request['gateway'] == 'razorpay') {
      $razorpay = new RazorpayController();

      return $razorpay->enrolmentProcess($request);
    } else if ($request['gateway'] == 'instamojo') {
      $instamojo = new InstamojoController();

      return $instamojo->enrolmentProcess($request);
    } else if ($request['gateway'] == 'paystack') {
      $paystack = new PaystackController();

      return $paystack->enrolmentProcess($request);
    } else if ($request['gateway'] == 'flutterwave') {
      $flutterwave = new FlutterwaveController();

      return $flutterwave->enrolmentProcess($request);
    } else if ($request['gateway'] == 'mercadopago') {
      $mercadopago = new MercadoPagoController();

      return $mercadopago->enrolmentProcess($request);
    } else if ($request['gateway'] == 'mollie') {
      $mollie = new MollieController();

      return $mollie->enrolmentProcess($request);
    } else if ($request['gateway'] == 'stripe') {
      $stripe = new StripeController();

      return $stripe->enrolmentProcess($request);
    } else if ($request['gateway'] == 'paytm') {
      $paytm = new PaytmController();

      return $paytm->enrolmentProcess($request);
    } else if ($request['gateway'] == 'midtrans') {
      Session::put('midtrans_payment_type', 'shop');
      $midtrans = new MidtransController();

      return $midtrans->purchaseProcess($request);
    } else if ($request['gateway'] == 'iyzico') {
      $iyzico = new IyzipayController();

      return $iyzico->purchaseProcess($request);
    } else if ($request['gateway'] == 'paytabs') {
      $paytabs = new PaytabsController();

      return $paytabs->purchaseProcess($request);
    } else if ($request['gateway'] == 'toyyibpay') {
      $toyyibpay = new ToyyibpayController();

      return $toyyibpay->purchaseProcess($request);
    } else if ($request['gateway'] == 'phonepe') {
      $phonepe = new PhonepeController();

      return $phonepe->purchaseProcess($request);
    } else if ($request['gateway'] == 'yoco') {
      $yoco = new YocoController();

      return $yoco->purchaseProcess($request);
    } else if ($request['gateway'] == 'xendit') {
      $yoco = new XenditController();

      return $yoco->purchaseProcess($request);
    } else if ($request['gateway'] == 'myfatoorah') {
      $myfatoorah = new MyFatoorahController();
      return $myfatoorah->purchaseProcess($request);
    } else if ($request['gateway'] == 'perfect_money') {
      $perfect_money = new PerfectMoneyController();
      return $perfect_money->purchaseProcess($request);
    } else if ($offlineGateway) {
      $offline = new OfflineController();

      return $offline->enrolmentProcess($request);
    }

    Session::flash('error', 'That online payment method is not supported for shop orders.');
    return redirect()->back()->withInput();
  }

  public function storeData($info)
  {
    $currencyInfo = $this->getCurrencyInfo();
    $order = ProductOrder::create([
      'user_id' => $info['user_id'],
      'billing_fname' => $info['fname'],
      'billing_lname' => $info['lname'],
      'billing_email' => $info['email'],
      'billing_phone' => $info['phone'],
      'billing_country' => $info['country'],
      'billing_state' => $info['state'],
      'billing_city' => $info['city'],
      'billing_zip_code' => $info['zip_code'],
      'billing_address' => $info['address'],

      'shipping_fname' => $info['s_fname'],
      'shipping_lname' => $info['s_lname'],
      'shipping_email' => $info['s_email'],
      'shipping_phone' => $info['s_phone'],
      'shipping_country' => $info['s_country'],
      'shipping_state' => $info['s_state'],
      'shipping_city' => $info['s_city'],
      'shipping_zip_code' => $info['s_zip_code'],
      'shipping_address' => $info['s_address'],

      'cart_total' => $info['cart_total'],
      'discount' => $info['discount'],
      'tax' => $info['tax'],
      'tax_percentage' => $info['tax_percentage'],
      'total' => $info['grand_total'],

      'currency_text' => $currencyInfo->base_currency_text,
      'currency_text_position' => $currencyInfo->base_currency_text_position,
      'currency_symbol' => $currencyInfo->base_currency_symbol,
      'currency_symbol_position' => $currencyInfo->base_currency_symbol_position,

      'order_number' => Str::upper(Str::random(20)),
      'shipping_method' => $info['shipping_method'],
      'shipping_charge' => $info['shipping_charge'],

      'method' => $info['method'],
      'gateway_type' => $info['gateway_type'],
      'payment_status' => $info['payment_status'],
      'order_status' => $info['order_status'],
      'tnxid' => $info['tnxid'],
      'charge_id' => $info['charge_id'],
      'invoice' => array_key_exists('attachmentFile', $info) ? $info['attachmentFile'] : null,
      'receipt' => array_key_exists('attachmentFile', $info) ? $info['attachmentFile'] : null,
      'conversation_id' => array_key_exists('conversation_id', $info) ? $info['conversation_id'] : null,

    ]);

    return $order;
  }
  //storeOders
  public function storeOders($info)
  {
    $language = $this->getLanguage();
    $items = app(ProductOrderInventoryService::class)->reserveFromCart(
      $info,
      (array) Session::get('cart', []),
      (int) $language->id
    );

    // The durable order and its inventory reservation now own this purchase.
    // Clear session state only after that boundary succeeds.
    app(ShopCheckoutSessionService::class)->clearCompletedCheckout();

    return $items;
  }

  /**
   * Persist a legacy gateway order and its authoritative inventory evidence as
   * one transaction. Session state is cleared only after the outer commit.
   */
  public function storeOrderWithItems(array $info): ProductOrder
  {
    $language = $this->getLanguage();
    if (!$language) {
      throw new \DomainException('The checkout language is unavailable.');
    }
    $cart = (array) Session::get('cart', []);

    $order = DB::transaction(function () use ($info, $cart, $language): ProductOrder {
      $order = $this->storeData($info);
      app(ProductOrderInventoryService::class)->reserveFromCart(
        $order,
        $cart,
        (int) $language->id
      );

      return $order;
    }, 3);

    app(ShopCheckoutSessionService::class)->clearCompletedCheckout();
    app(PaidProductOrderFulfillmentService::class)->dispatch((int) $order->id);

    return $order;
  }


  public function complete(Request $request, $via = null)
  {
    $queryResult['paidVia'] = $via;
    return view('frontend.payment.order_success');
  }

  public function cancel()
  {
    return redirect()->route('shopping.cart');
  }

  public function sendMail($orderInfo, bool $throwOnFailure = false)
  {
    if (!$throwOnFailure) {
      app(PaidProductOrderFulfillmentService::class)->dispatch((int) $orderInfo->id);
      return true;
    }

    // first get the mail template info from db
    $mailTemplate = MailTemplate::where('mail_type', 'product_order')->first();
    $mailSubject = $mailTemplate->mail_subject;
    $mailBody = $mailTemplate->mail_body;

    // second get the website title & mail's smtp info from db
    $info = DB::table('basic_settings')
      ->select('website_title')
      ->first();

    $customerName = $orderInfo->billing_fname . ' ' . $orderInfo->billing_lname;
    $orderId = $orderInfo->order_number;

    $websiteTitle = $info->website_title;

    $mailBody = str_replace('{customer_name}', $customerName, $mailBody);
    $mailBody = str_replace('{order_id}', $orderId, $mailBody);
    $mailBody = str_replace('{website_title}', $websiteTitle, $mailBody);

    try {
      app(ConfiguredMailer::class)->sendHtml(
        $orderInfo->billing_email,
        $mailSubject,
        $mailBody,
        [app(ProductOrderDocumentStorage::class)->attachmentPath($orderInfo)]
      );

      return true;
    } catch (\Exception $e) {
      throw $e;
    }
  }
  public function generateInvoice($orderInfo)
  {
    $width = "40%";
    $float = "right";
    $mb = "35px";
    $ml = "18px";

    $order_items = OrderItem::where('product_order_id', $orderInfo->id)->get();

    $contents = PDF::loadView(
      'frontend.shop.invoice',
      compact('orderInfo', 'order_items', 'width', 'float', 'mb', 'ml')
    )->output();

    return app(ProductOrderDocumentStorage::class)->storeInvoice($orderInfo, $contents);
  }
}
