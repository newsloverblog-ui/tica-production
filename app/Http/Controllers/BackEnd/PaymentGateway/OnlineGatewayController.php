<?php

namespace App\Http\Controllers\BackEnd\PaymentGateway;

use App\Http\Controllers\Controller;
use App\Models\PaymentGateway\OnlineGateway;
use App\Services\PaymentGatewayPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class OnlineGatewayController extends Controller
{
  public function index()
  {
    $policy = app(PaymentGatewayPolicy::class);
    $gatewayInfo['certifiedEventGateways'] = $policy->productionReadyGateways();
    $gatewayInfo['legacyActivationAllowed'] = $policy->canOfferOnline('razorpay');
    $gatewayInfo['instamojo'] = OnlineGateway::where('keyword', 'instamojo')->first();
    $gatewayInfo['paystack'] = OnlineGateway::where('keyword', 'paystack')->first();
    $gatewayInfo['monnify'] = OnlineGateway::where('keyword', 'monnify')->first();
    $gatewayInfo['flutterwave'] = OnlineGateway::where('keyword', 'flutterwave')->first();
    $gatewayInfo['razorpay'] = OnlineGateway::where('keyword', 'razorpay')->first();
    $gatewayInfo['mercadopago'] = OnlineGateway::where('keyword', 'mercadopago')->first();
    $gatewayInfo['mollie'] = OnlineGateway::where('keyword', 'mollie')->first();
    $gatewayInfo['stripe'] = OnlineGateway::where('keyword', 'stripe')->first();
    $gatewayInfo['paytm'] = OnlineGateway::where('keyword', 'paytm')->first();
    $gatewayInfo['midtrans'] = OnlineGateway::where('keyword', 'midtrans')->first();
    $gatewayInfo['iyzico'] = OnlineGateway::where('keyword', 'iyzico')->first();
    $gatewayInfo['paytabs'] = OnlineGateway::where('keyword', 'paytabs')->first();
    $gatewayInfo['toyyibpay'] = OnlineGateway::where('keyword', 'toyyibpay')->first();
    $gatewayInfo['phonepe'] = OnlineGateway::where('keyword', 'phonepe')->first();
    $gatewayInfo['yoco'] = OnlineGateway::where('keyword', 'yoco')->first();
    $gatewayInfo['xendit'] = OnlineGateway::where('keyword', 'xendit')->first();
    $gatewayInfo['myfatoorah'] = OnlineGateway::where('keyword', 'myfatoorah')->first();
    $gatewayInfo['perfect_money'] = OnlineGateway::where('keyword', 'perfect_money')->first();

    return view('backend.payment-gateways.online-gateways', $gatewayInfo);
  }

  public function updateInstamojoInfo(Request $request)
  {
    $instamojoInfo = $this->gatewayWithPreservedCredentials($request, 'instamojo', [
      'instamojo_key' => 'key',
      'instamojo_token' => 'token',
    ]);
    $rules = [
      'instamojo_status' => 'required|in:0,1',
      'instamojo_sandbox_status' => 'required',
      'instamojo_key' => 'required',
      'instamojo_token' => 'required'
    ];

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
      return redirect()->back()->withErrors($validator->errors());
    }

    $information['sandbox_status'] = $request->instamojo_sandbox_status;
    $information['key'] = $request->instamojo_key;
    $information['token'] = $request->instamojo_token;

    $instamojoInfo->update([
      'information' => json_encode($information),
      'status' => $request->instamojo_status
    ]);

    Session::flash('success', 'Updated Instamojo Informaion Successfully');

    return redirect()->back();
  }

  public function updatePaystackInfo(Request $request)
  {
    $paystackInfo = $this->gatewayWithPreservedCredentials($request, 'paystack', ['paystack_key' => 'key']);

    $rules = [
      'paystack_status' => 'required|in:0,1',
      'paystack_key' => 'required|string|max:1000'
    ];

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
      return redirect()->back()->withErrors($validator->errors());
    }

    $information['key'] = $request->paystack_key;

    $paystackInfo->update([
      'information' => json_encode($information),
      'status' => $request->paystack_status
    ]);

    Session::flash('success', 'Updated Paystack Informaion Successfully');

    return redirect()->back();
  }

  public function updateMonnifyInfo(Request $request)
  {
    $monnifyInfo = $this->gatewayWithPreservedCredentials($request, 'monnify', [
      'monnify_api_key' => 'api_key',
      'monnify_secret_key' => 'secret_key',
    ]);

    $validator = Validator::make($request->all(), [
      'monnify_status' => 'required|in:0,1',
      'monnify_mode' => 'required|in:sandbox,live',
      'monnify_api_key' => 'required|string|max:200',
      'monnify_secret_key' => 'required|string|max:200',
      'monnify_contract_code' => 'required|string|max:60',
    ], [
      'monnify_api_key.required' => 'Enter the Monnify API key.',
      'monnify_secret_key.required' => 'Enter the Monnify secret key.',
      'monnify_contract_code.required' => 'Enter the Monnify contract code.',
    ]);

    if ($validator->fails()) {
      return redirect()->back()->withErrors($validator->errors());
    }

    $information = [
      'api_key' => trim($request->monnify_api_key),
      'secret_key' => trim($request->monnify_secret_key),
      'contract_code' => trim($request->monnify_contract_code),
      'mode' => $request->monnify_mode,
    ];

    // Check the keys with Monnify before switching the gateway on.
    if ((string) $request->monnify_status === '1') {
      try {
        (new \App\Services\MonnifyGatewayClient($information))->verifyCredentials();
      } catch (\Throwable $exception) {
        return redirect()->back()->withErrors(['monnify_api_key' => 'Monnify did not accept these keys in ' . $information['mode'] . ' mode: ' . $exception->getMessage()]);
      }
    }

    $monnifyInfo->update([
      'information' => json_encode($information),
      'status' => $request->monnify_status,
    ]);
    \Illuminate\Support\Facades\Cache::forget('monnify_token:' . $information['mode'] . ':' . hash('sha256', $information['api_key']));

    Session::flash('success', 'Monnify settings saved.');

    return redirect()->back();
  }

  public function updateFlutterwaveInfo(Request $request)
  {
    $flutterwaveInfo = $this->gatewayWithPreservedCredentials(
      $request,
      'flutterwave',
      ['flutterwave_secret_key' => 'secret_key']
    );

    $rules = [
      'flutterwave_status' => 'required|in:0,1',
      'flutterwave_public_key' => 'required|string|max:1000',
      'flutterwave_secret_key' => 'required|string|max:1000'
    ];

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
      return redirect()->back()->withErrors($validator->errors());
    }

    $information['public_key'] = $request->flutterwave_public_key;
    $information['secret_key'] = $request->flutterwave_secret_key;

    $flutterwaveInfo->update([
      'information' => json_encode($information),
      'status' => $request->flutterwave_status
    ]);

    Session::flash('success', 'Updated Flutterwave Informaion Successfully');

    return redirect()->back();
  }

  public function updateRazorpayInfo(Request $request)
  {
    $razorpayInfo = $this->gatewayWithPreservedCredentials(
      $request,
      'razorpay',
      ['razorpay_secret' => 'secret']
    );
    $rules = [
      'razorpay_status' => 'required|in:0,1',
      'razorpay_key' => 'required',
      'razorpay_secret' => 'required'
    ];

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
      return redirect()->back()->withErrors($validator->errors());
    }

    $information['key'] = $request->razorpay_key;
    $information['secret'] = $request->razorpay_secret;

    $razorpayInfo->update([
      'information' => json_encode($information),
      'status' => $request->razorpay_status
    ]);

    Session::flash('success', 'Updated Razorpay Informaion Successfully');

    return redirect()->back();
  }

  public function updateMercadoPagoInfo(Request $request)
  {
    $mercadopagoInfo = $this->gatewayWithPreservedCredentials(
      $request,
      'mercadopago',
      ['mercadopago_token' => 'token']
    );
    $rules = [
      'mercadopago_status' => 'required|in:0,1',
      'mercadopago_sandbox_status' => 'required',
      'mercadopago_token' => 'required'
    ];

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
      return redirect()->back()->withErrors($validator->errors());
    }

    $information['sandbox_status'] = $request->mercadopago_sandbox_status;
    $information['token'] = $request->mercadopago_token;

    $mercadopagoInfo->update([
      'information' => json_encode($information),
      'status' => $request->mercadopago_status
    ]);

    Session::flash('success', 'Updated Mercadopago Informaion Successfully');

    return redirect()->back();
  }

  public function updateMollieInfo(Request $request)
  {
    $mollieInfo = $this->gatewayWithPreservedCredentials($request, 'mollie', ['mollie_key' => 'key']);
    $rules = [
      'mollie_status' => 'required|in:0,1',
      'mollie_key' => 'required'
    ];

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
      return redirect()->back()->withErrors($validator->errors());
    }

    $information['key'] = $request->mollie_key;

    $mollieInfo->update([
      'information' => json_encode($information),
      'status' => $request->mollie_status
    ]);

    Session::flash('success', 'Updated Mollie Informaion Successfully');

    return redirect()->back();
  }

  public function updateStripeInfo(Request $request)
  {
    $stripeInfo = $this->gatewayWithPreservedCredentials($request, 'stripe', [
      'stripe_secret' => 'secret',
      'stripe_webhook_secret' => 'webhook_secret',
    ]);

    $validator = Validator::make($request->all(), [
      'stripe_status' => 'required|in:0,1',
      'stripe_key' => ['required', 'string', 'max:255', 'regex:/^pk_(test|live)_/'],
      'stripe_secret' => ['required', 'string', 'max:255', 'regex:/^(sk|rk)_(test|live)_/'],
      'stripe_webhook_secret' => ['required_if:stripe_status,1', 'nullable', 'string', 'max:255', 'regex:/^whsec_/'],
    ], [
      'stripe_key.regex' => 'The publishable key starts with pk_test_ or pk_live_.',
      'stripe_secret.regex' => 'The secret key starts with sk_test_ or sk_live_.',
      'stripe_webhook_secret.required_if' => 'Add the webhook signing secret before switching Stripe on.',
      'stripe_webhook_secret.regex' => 'The webhook signing secret starts with whsec_.',
    ]);

    if ($validator->fails()) {
      return redirect()->back()->withErrors($validator->errors());
    }

    $information = [
      'key' => trim($request->stripe_key),
      'secret' => trim($request->stripe_secret),
      'webhook_secret' => trim((string) $request->stripe_webhook_secret),
    ];
    if (str_contains($information['key'], '_test_') !== str_contains($information['secret'], '_test_')) {
      return redirect()->back()->withErrors(['stripe_secret' => 'Use test keys together or live keys together.']);
    }

    // Check the secret key with Stripe before switching the gateway on.
    if ((string) $request->stripe_status === '1') {
      try {
        (new \App\Services\StripeGatewayClient($information))->verifyCredentials();
      } catch (\Throwable $exception) {
        return redirect()->back()->withErrors(['stripe_secret' => 'Stripe did not accept this secret key: ' . $exception->getMessage()]);
      }
    }

    $stripeInfo->update([
      'information' => json_encode($information),
      'status' => $request->stripe_status,
    ]);

    Session::flash('success', 'Stripe settings saved.');

    return redirect()->back();
  }

  public function updatePaytmInfo(Request $request)
  {
    $paytmInfo = $this->gatewayWithPreservedCredentials(
      $request,
      'paytm',
      ['paytm_merchant_key' => 'merchant_key']
    );
    $rules = [
      'paytm_status' => 'required|in:0,1',
      'paytm_environment' => 'required',
      'paytm_merchant_key' => 'required',
      'paytm_merchant_mid' => 'required',
      'paytm_merchant_website' => 'required',
      'paytm_industry_type' => 'required'
    ];

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
      return redirect()->back()->withErrors($validator->errors());
    }

    $information['environment'] = $request->paytm_environment;
    $information['merchant_key'] = $request->paytm_merchant_key;
    $information['merchant_mid'] = $request->paytm_merchant_mid;
    $information['merchant_website'] = $request->paytm_merchant_website;
    $information['industry_type'] = $request->paytm_industry_type;

    $paytmInfo->update([
      'information' => json_encode($information),
      'status' => $request->paytm_status
    ]);

    Session::flash('success', 'Updated Paytm Informaion Successfully');

    return redirect()->back();
  }

  public function updateMidtransInfo(Request $request)
  {
    $data = $this->gatewayWithPreservedCredentials($request, 'midtrans', ['server_key' => 'server_key']);
    $rules = [
      'status' => 'required|in:0,1',
      'is_production' => 'required',
      'server_key' => 'required'
    ];

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
      return redirect()->back()->withErrors($validator->errors());
    }

    $information['is_production'] = $request->is_production;
    $information['server_key'] = $request->server_key;

    $data->update([
      'information' => json_encode($information),
      'status' => $request->status
    ]);

    Session::flash('success', 'Updated Midtrans Information Successfully');

    return redirect()->back();
  }

  public function updatePaytabsInfo(Request $request)
  {
    $data = $this->gatewayWithPreservedCredentials($request, 'paytabs', ['server_key' => 'server_key']);
    $rules = [
      'status' => 'required|in:0,1',
      'country' => 'required',
      'server_key' => 'required',
      'profile_id' => 'required',
      'api_endpoint' => 'required'
    ];

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
      return redirect()->back()->withErrors($validator->errors());
    }

    $information['server_key'] = $request->server_key;
    $information['profile_id'] = $request->profile_id;
    $information['country'] = $request->country;
    $information['api_endpoint'] = $request->api_endpoint;

    $data->update([
      'information' => json_encode($information),
      'status' => $request->status
    ]);

    Session::flash('success', 'Updated Paytabs Information Successfully');

    return redirect()->back();
  }

  public function updateToyyibpayInfo(Request $request)
  {
    $data = $this->gatewayWithPreservedCredentials($request, 'toyyibpay', ['secret_key' => 'secret_key']);
    $rules = [
      'status' => 'required|in:0,1',
      'sandbox_status' => 'required',
      'secret_key' => 'required',
      'category_code' => 'required'
    ];

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
      return redirect()->back()->withErrors($validator->errors());
    }

    $information['sandbox_status'] = $request->sandbox_status;
    $information['secret_key'] = $request->secret_key;
    $information['category_code'] = $request->category_code;

    $data->update([
      'information' => json_encode($information),
      'status' => $request->status
    ]);

    Session::flash('success', 'Updated Toyyibpay Information Successfully');

    return redirect()->back();
  }

  public function updateIyzicoInfo(Request $request)
  {
    $data = $this->gatewayWithPreservedCredentials($request, 'iyzico', ['secret_key' => 'secret_key']);
    $rules = [
      'status' => 'required|in:0,1',
      'sandbox_status' => 'required',
      'api_key' => 'required',
      'secret_key' => 'required'
    ];

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
      return redirect()->back()->withErrors($validator->errors());
    }

    $information['sandbox_status'] = $request->sandbox_status;
    $information['api_key'] = $request->api_key;
    $information['secret_key'] = $request->secret_key;

    $data->update([
      'information' => json_encode($information),
      'status' => $request->status
    ]);

    Session::flash('success', 'Updated Iyzico Information Successfully');

    return redirect()->back();
  }
  public function updatePhonepeInfo(Request $request)
  {
    $data = $this->gatewayWithPreservedCredentials($request, 'phonepe', ['salt_key' => 'salt_key']);
    $rules = [
      'status' => 'required|in:0,1',
      'sandbox_status' => 'required',
      'merchant_id' => 'required',
      'salt_key' => 'required',
      'salt_index' => 'required'
    ];

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
      return redirect()->back()->withErrors($validator->errors());
    }

    $information['merchant_id'] = $request->merchant_id;
    $information['sandbox_status'] = $request->sandbox_status;
    $information['salt_key'] = $request->salt_key;
    $information['salt_index'] = $request->salt_index;

    $data->update([
      'information' => json_encode($information),
      'status' => $request->status
    ]);

    Session::flash('success', 'Updated Phonepe Information Successfully');

    return redirect()->back();
  }
  public function updateYocoInfo(Request $request)
  {
    $data = $this->gatewayWithPreservedCredentials($request, 'yoco', ['secret_key' => 'secret_key']);
    $rules = [
      'status' => 'required|in:0,1',
      'secret_key' => 'required',
    ];

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
      return redirect()->back()->withErrors($validator->errors());
    }

    $information['secret_key'] = $request->secret_key;

    $data->update([
      'information' => json_encode($information),
      'status' => $request->status
    ]);

    Session::flash('success', 'Updated Yoco Information Successfully');

    return redirect()->back();
  }
  public function updateXenditInfo(Request $request)
  {
    $data = $this->gatewayWithPreservedCredentials($request, 'xendit', ['secret_key' => 'secret_key']);
    $rules = [
      'status' => 'required|in:0,1',
      'secret_key' => 'required',
    ];

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
      return redirect()->back()->withErrors($validator->errors());
    }

    $information['secret_key'] = $request->secret_key;

    $data->update([
      'information' => json_encode($information),
      'status' => $request->status
    ]);

    Session::flash('success', 'Updated Xendit Information Successfully');

    return redirect()->back();
  }
  public function updateMyFatoorahInfo(Request $request)
  {
    $data = $this->gatewayWithPreservedCredentials($request, 'myfatoorah', ['token' => 'token']);
    $rules = [
      'status' => 'required|in:0,1',
      'sandbox_status' => 'required',
      'token' => 'required',
    ];

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
      return redirect()->back()->withErrors($validator->errors());
    }

    $information = [
      'token' => $request->token,
      'sandbox_status' => $request->sandbox_status
    ];

    $data->update([
      'information' => json_encode($information),
      'status' => $request->status
    ]);

    Session::flash('success', 'Updated Xendit Information Successfully');

    return redirect()->back();
  }

  public function updatePerfectMoneyInfo(Request $request)
  {
    $rules = [
      'status' => 'required|in:0,1',
      'perfect_money_wallet_id' => 'required'
    ];

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
      return redirect()->back()->withErrors($validator->errors());
    }

    $information = [
      'perfect_money_wallet_id' => $request->perfect_money_wallet_id
    ];

    $data = $this->gatewayWithPreservedCredentials($request, 'perfect_money', []);

    $data->update([
      'information' => json_encode($information),
      'status' => $request->status
    ]);

    Session::flash('success', 'Updated Perfect Money Information Successfully');

    return redirect()->back();
  }

  private function gatewayWithPreservedCredentials(
    Request $request,
    string $keyword,
    array $requestToInformationKeys
  ): OnlineGateway {
    $gateway = OnlineGateway::where('keyword', $keyword)->firstOrFail();
    $statusKey = match ($keyword) {
      'instamojo', 'paystack', 'monnify', 'flutterwave', 'razorpay', 'mercadopago',
      'mollie', 'stripe', 'paytm' => $keyword . '_status',
      default => 'status',
    };

    if ((string) $request->input($statusKey) === '1'
      && !app(PaymentGatewayPolicy::class)->canOfferOnline($keyword)) {
      throw ValidationException::withMessages([
        $statusKey => 'This legacy gateway cannot be activated in this environment. Use a hardened gateway or the explicit local integration-development opt-in.',
      ]);
    }

    $existing = json_decode((string) $gateway->information, true) ?: [];
    $preserved = [];

    foreach ($requestToInformationKeys as $requestKey => $informationKey) {
      if (trim((string) $request->input($requestKey)) === '' && !empty($existing[$informationKey])) {
        $preserved[$requestKey] = $existing[$informationKey];
      }
    }

    if ($preserved !== []) {
      $request->merge($preserved);
    }

    return $gateway;
  }
}
