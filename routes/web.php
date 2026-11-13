<?php

use Illuminate\Support\Facades\Route;

Route::get('/site.webmanifest', 'FrontEnd\ManifestController@web')->name('manifest.web');
Route::get('/scanner.webmanifest', 'FrontEnd\ManifestController@scanner')->name('manifest.scanner');
use Spatie\GoogleCalendar\Event;

/*
|--------------------------------------------------------------------------
| User Interface Routes
|--------------------------------------------------------------------------
*/

Route::get('/offline', 'FrontEnd\HomeController@offline');

Route::get('/health/live', function () {
  return response()->json(['status' => 'ok'])->header('Cache-Control', 'no-store');
})->name('health.live');

Route::get('/health/ready', 'OperationalHealthController@ready')->name('health.ready');

Route::get('/api/vercel/cron', App\Http\Controllers\VercelCronController::class)
  ->middleware('throttle:6,1')
  ->name('vercel.cron');

Route::post('/analytics/events', 'FrontEnd\AnalyticsController@store')
  ->middleware('throttle:60,1')
  ->name('analytics.events.store');

Route::get('login', 'FrontEnd\TicaUiController@page')->defaults('page', 'login')->middleware('change.lang')->name('login');


Route::get('midtrans/cancel', 'FrontEnd\HomeController@midtrans_cancel')->name('midtrans_cancel'); // banking er IPN
Route::post('xendit/callback', 'FrontEnd\HomeController@xendit_callback')
  ->middleware(['payment.gateway:event,xendit', 'throttle:60,1'])->name('xendit_cancel');
Route::get('myfatoorah/callback', 'FrontEnd\HomeController@myfatoorah_callback')
  ->middleware(['payment.gateway:event,myfatoorah', 'throttle:60,1'])->name('myfatoorah_callback');

Route::get('myfatoorah/cancel', 'FrontEnd\HomeController@myfatoorah_cancel')->name('myfatoorah_cancel');

/*
|--------------------------------------------------------------------------
| Paystack Webhook
|
| MUST be declared outside ALL middleware groups so that Paystack's servers
| can POST to it without a browser session or CSRF token.
|
| Also add 'paystack/webhook' to the $except array in:
|   app/Http/Middleware/VerifyCsrfToken.php
|
| And register the URL in Paystack Dashboard:
|   Settings → API Keys & Webhooks → Live → https://tica.ng/paystack/webhook
|--------------------------------------------------------------------------
*/
Route::post(
    '/paystack/webhook',
    [App\Http\Controllers\FrontEnd\PaymentGateway\PaystackController::class, 'webhook']
)->middleware(['webhook.receipt:paystack', 'throttle:120,1'])->name('paystack.webhook');

Route::post(
    '/flutterwave/webhook',
    [App\Http\Controllers\FrontEnd\PaymentGateway\FlutterwaveController::class, 'webhook']
)->middleware(['webhook.receipt:flutterwave', 'throttle:120,1'])->name('flutterwave.webhook');

// Monnify webhook: register https://<your-domain>/monnify/webhook under
// Developer → Webhook URLs → Transaction Completion in the Monnify dashboard.
Route::post(
    '/monnify/webhook',
    [App\Http\Controllers\FrontEnd\PaymentGateway\MonnifyController::class, 'webhook']
)->middleware(['payment.gateway:event,monnify', 'webhook.receipt:monnify', 'throttle:120,1'])->name('monnify.webhook');

// Stripe webhook: add https://<your-domain>/stripe/webhook in Stripe → Developers → Webhooks
// with checkout.session.completed, .async_payment_succeeded, .async_payment_failed and .expired.
Route::post(
    '/stripe/webhook',
    [App\Http\Controllers\FrontEnd\PaymentGateway\StripeController::class, 'webhook']
)->middleware(['payment.gateway:event,stripe', 'webhook.receipt:stripe', 'throttle:120,1'])->name('stripe.webhook');

/*
|---------------------------------------------------------------------------------
| Customer routes are goes here
|---------------------------------------------------------------------------------
*/
/*---socialite---*/
##==facebook
Route::get('login/facebook/callback', 'FrontEnd\CustomerController@handleFacebookCallback');

#====google
Route::get('login/google/callback', 'FrontEnd\CustomerController@handleGoogleCallback');
/*---socialite end---*/

Route::middleware('change.lang')->prefix('/customer')->group(function () {
  Route::middleware('guest:customer', 'change.lang')->group(function () {
    Route::get('/login', 'FrontEnd\CustomerController@login')->name('customer.login');
    Route::get('/signup', 'FrontEnd\CustomerController@signup')->name('customer.signup');
    Route::post('/create', 'FrontEnd\CustomerController@create')->middleware('throttle:authentication')->name('customer.create');
    Route::post('/store', 'FrontEnd\CustomerController@authentication')->middleware('throttle:authentication')->name('customer.authentication');
    Route::post('/login-code/request', 'FrontEnd\CustomerController@requestLoginCode')->middleware('throttle:3,10')->name('customer.login-code.request');
    Route::post('/login-code/verify', 'FrontEnd\CustomerController@verifyLoginCode')->middleware('throttle:authentication')->name('customer.login-code.verify');

    /*---socialite---*/
    ##==facebook
    Route::get('auth/facebook', 'FrontEnd\CustomerController@facebookRedirect')->name('auth.facebook');

    #====google
    Route::get('auth/google', 'FrontEnd\CustomerController@googleRedirect')->name('auth.google');
    /*---socialite end---*/



    Route::get('/forget-password', 'FrontEnd\CustomerController@forget_passord')->name('customer.forget.password');
    Route::post('/send-forget-mail', 'FrontEnd\CustomerController@forget_mail')->middleware('throttle:authentication')->name('customer.forget.mail');
    Route::get('/reset-password', 'FrontEnd\CustomerController@reset_password')->name('customer.reset.password');
    Route::post('/update-forget-password', 'FrontEnd\CustomerController@update_password')->middleware('throttle:authentication')->name('customer.update-forget-password');
    Route::get('/verify-email', 'FrontEnd\CustomerController@verify_email')->name('customer.verify.email');
    Route::post('/send-verify-link', 'FrontEnd\CustomerController@send_verification_link')
      ->middleware('throttle:authentication')->name('customer.send.verify.link');
  });
  Route::post('/logout', 'FrontEnd\CustomerController@logout')
    ->middleware('auth:customer')->name('customer.logout');
  Route::get('/change-password', 'FrontEnd\CustomerController@change_password')
    ->middleware(['auth:customer', 'Deactive:customer', 'EmailStatus:customer'])
    ->name('customer.change.password');
  Route::post('/update-password', 'FrontEnd\CustomerController@updated_password')
    ->middleware(['auth:customer', 'Deactive:customer', 'EmailStatus:customer', 'throttle:authentication'])
    ->name('customer.password.update');
});

Route::get('customer/email/verify/{customer}', 'FrontEnd\CustomerController@signupVerify')
  ->middleware('signed', 'throttle:authentication')
  ->name('customer.email.verify');

Route::get('/ticket-claim/{booking}', 'FrontEnd\CustomerController@claimTickets')
  ->middleware(['signed', 'throttle:authentication'])
  ->name('customer.ticket-claim');

Route::get('/ticket-transfer/{transfer}/{token}', 'FrontEnd\Event\CustomerBookingController@showTransferInvitation')
  ->middleware('signed', 'throttle:authentication')
  ->name('ticket.transfer.invitation');

Route::post('/ticket-transfer/{transfer}/{token}/accept', 'FrontEnd\Event\CustomerBookingController@acceptTransfer')
  ->middleware('auth:customer', 'Deactive:customer', 'EmailStatus:customer', 'signed', 'throttle:authentication')
  ->name('ticket.transfer.accept');

Route::get('/event-rsvp/{invitation}/{token}', 'FrontEnd\EventGuestRsvpController@show')
  ->middleware('signed', 'throttle:authentication')
  ->name('event.guest-rsvp.show');
Route::post('/event-rsvp/{invitation}/{token}', 'FrontEnd\EventGuestRsvpController@respond')
  ->middleware('signed', 'throttle:authentication')
  ->name('event.guest-rsvp.respond');

Route::get('/event/{event}/registration', 'FrontEnd\EventRegistrationController@show')
  ->middleware('throttle:60,1')
  ->name('event.registration.show');
Route::post('/event/{event}/registration', 'FrontEnd\EventRegistrationController@store')
  ->middleware('throttle:checkout')
  ->name('event.registration.store');
Route::get('/event/{event}/registration/embed', 'FrontEnd\EventRegistrationController@showEmbedded')
  ->middleware('throttle:60,1')
  ->name('event.registration.embed.show');
Route::post('/event/{event}/registration/embed', 'FrontEnd\EventRegistrationController@storeEmbedded')
  ->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class)
  ->middleware(['signed', 'throttle:checkout'])
  ->name('event.registration.embed.store');

Route::get('/registration-allocation/{allocation}/{token}', 'FrontEnd\EventRegistrationAllocationController@show')
  ->middleware('signed', 'throttle:authentication')->name('event.registration-allocation.show');
Route::post('/registration-allocation/{allocation}/{token}', 'FrontEnd\EventRegistrationAllocationController@invite')
  ->middleware('signed', 'throttle:checkout')->name('event.registration-allocation.invite');
Route::get('/allocated-registration/{claim}/{token}', 'FrontEnd\EventRegistrationAllocationController@claim')
  ->middleware('signed', 'throttle:authentication')->name('event.registration-allocation.claim.show');
Route::post('/allocated-registration/{claim}/{token}', 'FrontEnd\EventRegistrationAllocationController@submit')
  ->middleware('signed', 'throttle:checkout')->name('event.registration-allocation.claim.submit');

Route::prefix('/customer')->middleware('auth:customer', 'Deactive:customer', 'change.lang', 'EmailStatus:customer')->group(function () {
  Route::get('/dashboard', 'FrontEnd\CustomerController@index')->name('customer.dashboard');
  Route::get('/edit-profile', 'FrontEnd\CustomerController@edit_profile')->name('customer.edit.profile');
  Route::post('/update-profile', 'FrontEnd\CustomerController@update_profile')->name('customer.profile.update');
  Route::get('/my-data', 'FrontEnd\CustomerDataExportController@index')->name('customer.data-export.index');
  Route::post('/my-data', 'FrontEnd\CustomerDataExportController@store')
    ->middleware('throttle:2,60')->name('customer.data-export.store');
  Route::get('/my-data/{export}/download', 'FrontEnd\CustomerDataExportController@download')
    ->middleware(['signed', 'throttle:6,1'])->name('customer.data-export.download');
  Route::post('/privacy/deletion-request', 'FrontEnd\CustomerPrivacyRequestController@store')
    ->middleware('throttle:2,1440')->name('customer.privacy-request.store');
  Route::post('/privacy/deletion-request/{privacyRequest}/cancel', 'FrontEnd\CustomerPrivacyRequestController@cancel')
    ->middleware('throttle:6,1')->name('customer.privacy-request.cancel');

  Route::get('/wishlist', 'FrontEnd\CustomerController@wishlist')->name('customer.wishlist');
  Route::get('/my-bookings', 'FrontEnd\Event\CustomerBookingController@my_booking')->name('customer.booking.my_booking');
  Route::get('/booking/details/{id}', 'FrontEnd\Event\CustomerBookingController@details')->name('customer.booking_details');
  Route::get('/booking/{id}/invoice', 'FrontEnd\Event\CustomerBookingController@invoice')->name('customer.booking_invoice');
  Route::get('/booking/{id}/tickets.pdf', 'FrontEnd\Event\CustomerBookingController@currentTickets')
    ->middleware('throttle:6,1')->name('customer.booking.current-tickets');
  Route::post('/booking/{id}/resend-tickets', 'FrontEnd\Event\CustomerBookingController@resendTickets')
    ->middleware('throttle:3,10')->name('customer.booking.resend-tickets');
  Route::post('/booking/ticket/{id}/transfer', 'FrontEnd\Event\CustomerBookingController@transferTicket')->name('customer.booking.ticket.transfer');
  Route::post('/booking/ticket-transfer/{id}/cancel', 'FrontEnd\Event\CustomerBookingController@cancelTransfer')->name('customer.booking.ticket.transfer.cancel');
  Route::post('/booking/ticket/{id}/reissue', 'FrontEnd\Event\CustomerBookingController@reissueTicket')->name('customer.booking.ticket.reissue');
  Route::get('/booking/ticket/{id}/qr', 'FrontEnd\Event\CustomerBookingController@ticketQr')->name('customer.booking.ticket.qr');

  Route::get('/support-ticket', 'FrontEnd\SupportTicketController@index')->name('customer.support_tickert');
  Route::get('/support-ticket/create', 'FrontEnd\SupportTicketController@create')->name('customer.support_tickert.create');
  Route::post('/support-ticket/store', 'FrontEnd\SupportTicketController@store')->name('customer.support_ticket.store');
  Route::get('/support-ticket/message/{id}', 'FrontEnd\SupportTicketController@message')->name('customer.support_ticket.message');
  Route::post('/support-ticket/reply/{id}', 'FrontEnd\SupportTicketController@reply')->name('customer-reply');

  Route::get('/my-orders', 'FrontEnd\Shop\CustomerOrderController@index')->middleware('tica.no-shop')->name('customer.my_orders');
  Route::get('/my-orders/details/{id}', 'FrontEnd\Shop\CustomerOrderController@details')->middleware('tica.no-shop')->name('customer.order_details');
  Route::get('/my-orders/{id}/invoice', 'FrontEnd\Shop\CustomerOrderController@invoice')->middleware('tica.no-shop')->name('customer.order_invoice');
});


/*
|---------------------------------------------------------------------------------
| Customer routes end
|---------------------------------------------------------------------------------
*/

/*
|---------------------------------------------------------------------------------
| event booking routes are goes here
|---------------------------------------------------------------------------------
*/
Route::middleware('change.lang')->group(function () {
  Route::post('/ticket-booking/{id}', 'FrontEnd\Event\BookingController@index')->middleware('throttle:checkout')->name('ticket.booking');
  Route::get('/event-booking/{id}/cancel', 'FrontEnd\Event\BookingController@cancel')
    ->middleware('signed')
    ->name('event_booking.cancel');
  Route::get('/event-booking-complete', 'FrontEnd\Event\BookingController@complete')
    ->middleware('signed')
    ->name('event_booking.complete');
});


Route::prefix('event-booking')->group(function () {
  Route::post('/apply-coupon', 'FrontEnd\EventController@applyCoupon')->middleware('throttle:checkout')->name('apply-coupon');

  Route::get('/instamojo/notify', 'FrontEnd\PaymentGateway\InstamojoController@notify')
    ->middleware(['payment.gateway:event,instamojo', 'throttle:60,1'])->name('event_booking.instamojo.notify');

  // Paystack browser callback (GET) — user redirected back here after paying
  Route::get('/paystack/notify', 'FrontEnd\PaymentGateway\PaystackController@notify')
    ->middleware(['payment.gateway:event,paystack', 'throttle:checkout'])
    ->name('event_booking.paystack.notify');
  Route::get('/paystack/recovery/{reference}', 'FrontEnd\PaymentGateway\PaystackController@recovery')
    ->middleware(['payment.gateway:event,paystack', 'signed', 'throttle:checkout'])
    ->name('event_booking.paystack.recovery');
  Route::post('/paystack/recovery/{reference}', 'FrontEnd\PaymentGateway\PaystackController@retryRecovery')
    ->middleware(['payment.gateway:event,paystack', 'signed', 'throttle:checkout'])
    ->name('event_booking.paystack.recovery.verify');

  // Monnify: the buyer returns here; tickets are issued only after Monnify's API confirms payment
  Route::get('/monnify/notify', 'FrontEnd\PaymentGateway\MonnifyController@notify')
    ->middleware(['payment.gateway:event,monnify', 'throttle:checkout'])
    ->name('event_booking.monnify.notify');
  Route::get('/monnify/recovery/{reference}', 'FrontEnd\PaymentGateway\MonnifyController@recovery')
    ->middleware(['payment.gateway:event,monnify', 'signed', 'throttle:checkout'])
    ->name('event_booking.monnify.recovery');
  Route::post('/monnify/recovery/{reference}', 'FrontEnd\PaymentGateway\MonnifyController@retryRecovery')
    ->middleware(['payment.gateway:event,monnify', 'signed', 'throttle:checkout'])
    ->name('event_booking.monnify.recovery.verify');

  // Stripe Checkout: the buyer returns here; tickets are issued only after Stripe's API confirms payment
  Route::get('/stripe/notify', 'FrontEnd\PaymentGateway\StripeController@notify')
    ->middleware(['payment.gateway:event,stripe', 'throttle:checkout'])
    ->name('event_booking.stripe.notify');
  Route::get('/stripe/recovery/{reference}', 'FrontEnd\PaymentGateway\StripeController@recovery')
    ->middleware(['payment.gateway:event,stripe', 'signed', 'throttle:checkout'])
    ->name('event_booking.stripe.recovery');
  Route::post('/stripe/recovery/{reference}', 'FrontEnd\PaymentGateway\StripeController@retryRecovery')
    ->middleware(['payment.gateway:event,stripe', 'signed', 'throttle:checkout'])
    ->name('event_booking.stripe.recovery.verify');

  Route::get('/flutterwave/notify', 'FrontEnd\PaymentGateway\FlutterwaveController@notify')
    ->middleware(['payment.gateway:event,flutterwave', 'throttle:checkout'])
    ->name('event_booking.flutterwave.notify');
  Route::get('/flutterwave/recovery/{reference}', 'FrontEnd\PaymentGateway\FlutterwaveController@recovery')
    ->middleware(['payment.gateway:event,flutterwave', 'signed', 'throttle:checkout'])
    ->name('event_booking.flutterwave.recovery');
  Route::post('/flutterwave/recovery/{reference}', 'FrontEnd\PaymentGateway\FlutterwaveController@retryRecovery')
    ->middleware(['payment.gateway:event,flutterwave', 'signed', 'throttle:checkout'])
    ->name('event_booking.flutterwave.recovery.verify');

  Route::post('/razorpay/notify', 'FrontEnd\PaymentGateway\RazorpayController@notify')
    ->middleware(['payment.gateway:event,razorpay', 'throttle:60,1'])->name('event_booking.razorpay.notify');

  Route::get('/mercadopago/notify', 'FrontEnd\PaymentGateway\MercadoPagoController@notify')
    ->middleware(['payment.gateway:event,mercadopago', 'throttle:60,1'])->name('event_booking.mercadopago.notify');

  Route::get('/mollie/notify', 'FrontEnd\PaymentGateway\MollieController@notify')
    ->middleware(['payment.gateway:event,mollie', 'throttle:60,1'])->name('event_booking.mollie.notify');

  Route::post('/paytm/notify', 'FrontEnd\PaymentGateway\PaytmController@notify')
    ->middleware(['payment.gateway:event,paytm', 'throttle:60,1'])->name('event_booking.paytm.notify');

  Route::get('make-payment', 'FrontEnd\PaymentGateway\MidtransController@makePayment')
    ->middleware(['payment.gateway:event,midtrans', 'throttle:60,1'])->name('makePayment');
  Route::get('notify/{orderId}?', 'FrontEnd\PaymentGateway\MidtransController@ccNotify')
    ->middleware(['payment.gateway:event,midtrans', 'throttle:60,1'])->name('event.midtrans.notify'); // credit card er IPN
  Route::get('bank-notify', 'FrontEnd\PaymentGateway\MidtransController@bankNotify')
    ->middleware(['payment.gateway:event,midtrans', 'throttle:60,1'])->name('bank.notify'); // banking er IPN

  //iyzico
  Route::post('/iyzico/notify', 'FrontEnd\PaymentGateway\IyzipayController@notify')
    ->middleware(['payment.gateway:event,iyzico', 'throttle:60,1'])->name('event_booking.iyzico.notify');
  //iyzico end

  //toyyibpay
  Route::get('/toyyibpay/notify', 'FrontEnd\PaymentGateway\ToyyibpayController@notify')
    ->middleware(['payment.gateway:event,toyyibpay', 'throttle:60,1'])->name('event_booking.toyyibpay.notify');

  //paytabs
  Route::get('/paytabs/make-payment', 'FrontEnd\PaymentGateway\PaytabsController@makePayment')
    ->middleware(['payment.gateway:event,paytabs', 'throttle:60,1']);

  Route::post('/paytabs/notify', 'FrontEnd\PaymentGateway\PaytabsController@notify')
    ->middleware(['payment.gateway:event,paytabs', 'throttle:60,1'])->name('event_booking.paytabs.notify');

  Route::any('/phonepe/notify', 'FrontEnd\PaymentGateway\PhonepeController@notify')
    ->middleware(['payment.gateway:event,phonepe', 'throttle:60,1'])->name('event_booking.phonepe.notify');
  //paytabs end

  //yoco
  Route::get('/yoco/notify', 'FrontEnd\PaymentGateway\YocoController@notify')
    ->middleware(['payment.gateway:event,yoco', 'throttle:60,1'])->name('event_booking.yoco.notify');
  //xindit
  Route::get('/xendit/notify', 'FrontEnd\PaymentGateway\XenditController@notify')
    ->middleware(['payment.gateway:event,xendit', 'throttle:60,1'])->name('event_booking.xindit.notify');

  //perfect money
  Route::get('/perfect-money/notify', 'FrontEnd\PaymentGateway\PerfectMoneyController@notify')
    ->middleware(['payment.gateway:event,perfect_money', 'throttle:60,1'])->name('event_booking.perfect-money.notify');

  Route::get('/perfect-money/cancel', 'FrontEnd\PaymentGateway\PerfectMoneyController@cancel')
    ->middleware(['payment.gateway:event,perfect_money', 'throttle:60,1'])->name('event_booking.perfect-money.cancel');
});
/*
|---------------------------------------------------------------------------------
| Event Booking payment routes are end
|---------------------------------------------------------------------------------
*/


Route::post('/push-notification/store-endpoint', 'FrontEnd\PushNotificationController@store')
  ->middleware('throttle:30,1')->name('push-notification.store-endpoint');

Route::get('/change-language', 'Controller@changeLanguage')->name('change_language');

Route::post('/store-subscriber', 'Controller@storeSubscriber')
  ->middleware('throttle:public-form')->name('store_subscriber');

/*
|---------------------------------------------------------------------------------
| Frontend pages routes are goes here
|---------------------------------------------------------------------------------
*/

Route::middleware('change.lang')->group(function () {
  Route::get('/', 'FrontEnd\TicaUiController@page')->name('index');
  Route::get('discover', 'FrontEnd\TicaUiController@page')->defaults('page', 'discover')->name('events');
  Route::get('events', fn () => redirect('/discover', 301));
  Route::get('event/{event}/share-card.png', 'FrontEnd\EventShareCardController@show')
    ->whereNumber('event')
    ->name('event.share-card');
  Route::get('event/{slug}/{id}', 'FrontEnd\TicaUiController@event')->whereNumber('id')->name('event.details');
  Route::get('checkout/{slug}/{id}', 'FrontEnd\TicaUiController@checkout')->whereNumber('id')->name('tica.checkout');
  Route::post('event/{event}/waitlist', 'FrontEnd\EventWaitlistController@store')
    ->middleware('throttle:10,1')
    ->name('event.waitlist.store');
  Route::post('addto/wishlist/{id}', 'FrontEnd\EventController@add_to_wishlist')
    ->whereNumber('id')->middleware('auth:customer')->name('addto.wishlist');
  Route::post('remove/wishlist/{id}', 'FrontEnd\CustomerController@remove_wishlist')
    ->whereNumber('id')->middleware('auth:customer')->name('remove.wishlist');

  Route::post('/check-out2', 'FrontEnd\CheckOutController@checkout2')->middleware('throttle:checkout')->name('check-out2');
  Route::get('/checkout', 'FrontEnd\CheckOutController@checkout')->name('check-out');

  Route::middleware(['change.lang', 'tica.no-shop'])->prefix('shop')->group(function () {
    Route::get('/', 'FrontEnd\Shop\ShopController@index')->name('shop');
    Route::get('/details/{slug}/{id}', 'FrontEnd\Shop\ShopController@details')->name('shop.details');
    Route::post('review-submit', 'FrontEnd\Shop\ShopController@review')
      ->middleware(['auth:customer', 'Deactive:customer', 'EmailStatus:customer', 'throttle:10,1'])
      ->name('product.review.submit');
    Route::post('add-to-cart/{id}', 'FrontEnd\Shop\ShopController@addToCart')
      ->whereNumber('id')->middleware('throttle:60,1')->name('add.cart');
    Route::post('add-to-cart-2/{id}', 'FrontEnd\Shop\ShopController@addToCart2')
      ->whereNumber('id')->middleware('throttle:60,1')->name('add.cart2');

    Route::post('order-now', 'FrontEnd\Shop\ShopController@orderNow')
      ->middleware('throttle:60,1')->name('order-now');

    Route::get('cart/', 'FrontEnd\Shop\ShopController@cart')->name('shopping.cart');
    Route::post('cart/item/remove/{id}', 'FrontEnd\Shop\ShopController@cartitemremove')
      ->whereNumber('id')->middleware('throttle:60,1')->name('cart.item.remove');
    Route::post('cart/update', 'FrontEnd\Shop\ShopController@updatecart')
      ->middleware('throttle:60,1')->name('cart.update');
    Route::get('checkout', 'FrontEnd\Shop\ShopController@checkout')->name('shop.checkout');
    Route::post('apply-coupon/', 'FrontEnd\Shop\ShopController@applyCoupon')
      ->middleware('throttle:checkout')->name('shop.apply-coupon');
    Route::post('buy/', 'FrontEnd\Shop\OrderController@enrol')->middleware('throttle:checkout')->name('shop.buy');
  });

  Route::get('/product-order/{id}/cancel', 'FrontEnd\Shop\OrderController@cancel')->middleware('tica.no-shop')->name('product_order.cancel');
  Route::get('/product-order-complete/complete/{via?}', 'FrontEnd\Shop\OrderController@complete')->middleware('tica.no-shop')->name('product_order.complete');
  Route::get('organizer/details/{id}/{name}', 'FrontEnd\OrganizerController@details')->name('frontend.organizer.details');
  Route::get('organizers/', 'FrontEnd\OrganizerController@index')->name('frontend.all.organizer');

  Route::post('organizers/contact/send-mail', 'FrontEnd\OrganizerController@sendMail')
    ->middleware('throttle:public-form')->name('organizer.contact.send_mail');
});

Route::get('event-waitlist/{waitlist}/unsubscribe', 'FrontEnd\EventWaitlistController@confirmUnsubscribe')
  ->middleware(['signed', 'throttle:30,1'])
  ->name('event.waitlist.unsubscribe.confirm');
Route::post('event-waitlist/{waitlist}/unsubscribe', 'FrontEnd\EventWaitlistController@unsubscribe')
  ->middleware(['signed', 'throttle:10,1'])
  ->name('event.waitlist.unsubscribe');
/*
|---------------------------------------------------------------------------------
| Frontend pages routes are end
|---------------------------------------------------------------------------------
*/


/*
||===================================================
|| Product order routes are goes here
||===================================================
*/

Route::prefix('product-order')->group(function () {
  Route::get('paystack/notify', 'FrontEnd\Shop\PaymentGateway\PaystackController@notify')
    ->middleware(['payment.gateway:shop,paystack', 'throttle:20,1'])->name('product_order.paystack.notify');
  Route::get('paystack/recovery/{intent}', 'FrontEnd\Shop\PaymentGateway\PaystackController@recovery')
    ->middleware(['payment.gateway:shop,paystack', 'signed', 'throttle:20,1'])->name('product_order.paystack.recovery');
  Route::post('paystack/recovery/{intent}', 'FrontEnd\Shop\PaymentGateway\PaystackController@retryRecovery')
    ->middleware(['payment.gateway:shop,paystack', 'signed', 'throttle:10,1'])->name('product_order.paystack.recovery.verify');
  Route::get('instamojo/notify', 'FrontEnd\Shop\PaymentGateway\InstamojoController@notify')
    ->middleware(['payment.gateway:shop,instamojo', 'throttle:60,1'])->name('product_order.instamojo.notify');
  Route::post('razorpay/notify', 'FrontEnd\Shop\PaymentGateway\RazorpayController@notify')
    ->middleware(['payment.gateway:shop,razorpay', 'throttle:60,1'])->name('product_order.razorpay.notify');
  Route::post('mercadopago/notify', 'FrontEnd\Shop\PaymentGateway\MercadoPagoController@notify')
    ->middleware(['payment.gateway:shop,mercadopago', 'throttle:60,1'])->name('product_order.mercadopago.notify');
  Route::get('mollie/notify', 'FrontEnd\Shop\PaymentGateway\MollieController@notify')
    ->middleware(['payment.gateway:shop,mollie', 'throttle:60,1'])->name('product_order.mollie.notify');
  Route::post('paytm/notify', 'FrontEnd\Shop\PaymentGateway\PaytmController@notify')
    ->middleware(['payment.gateway:shop,paytm', 'throttle:60,1'])->name('product_order.paytm.notify');

  Route::post('flutterwave/notify', 'FrontEnd\Shop\PaymentGateway\FlutterwaveController@notify')
    ->middleware(['payment.gateway:shop,flutterwave', 'throttle:60,1'])->name('product_order.flutterwave.notify');

  Route::get('make-payment', 'FrontEnd\Shop\PaymentGateway\MidtransController@makePayment')
    ->middleware(['payment.gateway:shop,midtrans', 'throttle:60,1'])->name('shop.makePayment');
  Route::get('notify/{orderId}', 'FrontEnd\Shop\PaymentGateway\MidtransController@ccNotify')
    ->middleware(['payment.gateway:shop,midtrans', 'throttle:60,1'])->name('shop.midtrans.notify'); // credit card er IPN
  Route::get('bank-notify', 'FrontEnd\Shop\PaymentGateway\MidtransController@bankNotify')
    ->middleware(['payment.gateway:shop,midtrans', 'throttle:60,1'])->name('shop.bank.notify'); // banking er IPN

  //paytabs
  Route::get('/paytabs/make-payment', 'FrontEnd\Shop\PaymentGateway\PaytabsController@makePayment')
    ->middleware(['payment.gateway:shop,paytabs', 'throttle:60,1'])->name('shop.paytabs.makePayment');
  Route::post('/paytabs/notify', 'FrontEnd\Shop\PaymentGateway\PaytabsController@notify')
    ->middleware(['payment.gateway:shop,paytabs', 'throttle:60,1'])->name('shop.paytabs.notify');

  Route::get('/toyyibpay/notify', 'FrontEnd\Shop\PaymentGateway\ToyyibpayController@notify')
    ->middleware(['payment.gateway:shop,toyyibpay', 'throttle:60,1'])->name('shop.toyyibpay.notify');

  //phonepe
  Route::any('/phonepe/notify', 'FrontEnd\Shop\PaymentGateway\PhonepeController@notify')
    ->middleware(['payment.gateway:shop,phonepe', 'throttle:60,1'])->name('shop.phonepe.notify');

  //yoco
  Route::get('/yoco/notify', 'FrontEnd\Shop\PaymentGateway\YocoController@notify')
    ->middleware(['payment.gateway:shop,yoco', 'throttle:60,1'])->name('shop.yoco.notify');

  // xendit
  Route::get('/xendit/notify', 'FrontEnd\Shop\PaymentGateway\XenditController@notify')
    ->middleware(['payment.gateway:shop,xendit', 'throttle:60,1'])->name('shop.xendit.notify');

  Route::post('/iyzico/notify', 'FrontEnd\Shop\PaymentGateway\IyzipayController@notify')
    ->middleware(['payment.gateway:shop,iyzico', 'throttle:60,1'])->name('shop.iyzico.notify');

  //perfect money
  Route::get('/perfect-money/notify', 'FrontEnd\Shop\PaymentGateway\PerfectMoneyController@notify')
    ->middleware(['payment.gateway:shop,perfect_money', 'throttle:60,1'])->name('shop.perfect-money.notify');

  Route::get('/perfect-money/cancel', 'FrontEnd\Shop\PaymentGateway\PerfectMoneyController@cancel')
    ->middleware(['payment.gateway:shop,perfect_money', 'throttle:60,1'])->name('shop.perfect-money.cancel');
});


/*
|---------------------------------------------------------------------------------
| Product order routes are end
|---------------------------------------------------------------------------------
*/




Route::middleware('change.lang')->group(function () {

  Route::get('/blog', 'FrontEnd\TicaUiController@blog')->name('blogs');

  Route::get('/blog/{slug}', 'FrontEnd\TicaUiController@blogPost')->name('blog_details');

  Route::get('/faq', fn () => redirect('/how#faq', 301))->name('faqs');
  

  Route::get('/contact', 'FrontEnd\TicaUiController@page')->defaults('page', 'contact')->name('contact');
  Route::get('/about', 'FrontEnd\TicaUiController@page')->defaults('page', 'about')->name('about');
  Route::get('/about-us', fn () => redirect('/about', 301));
  Route::get('/pricing', 'FrontEnd\TicaUiController@page')->defaults('page', 'pricing')->name('pricing');
  Route::get('/how', 'FrontEnd\TicaUiController@page')->defaults('page', 'how')->name('tica.how');
  Route::get('/join', 'FrontEnd\TicaUiController@page')->defaults('page', 'join')->name('tica.join');
  Route::get('/policies/{section?}', 'FrontEnd\TicaUiController@page')->defaults('page', 'policies')
    ->where('section', '[a-z]+')->name('tica.policies');
  Route::get('/tickets', 'FrontEnd\TicaUiController@page')->defaults('page', 'tickets')->name('tica.tickets');
  Route::get('/account', 'FrontEnd\TicaUiController@page')->defaults('page', 'account')->name('tica.account');
  Route::get('/signup', 'FrontEnd\TicaUiController@page')->defaults('page', 'signup')->name('tica.signup');
  Route::get('/forgot', 'FrontEnd\TicaUiController@page')->defaults('page', 'forgot')->name('tica.forgot');
  Route::get('/create', 'FrontEnd\TicaUiController@page')->defaults('page', 'create')->middleware('tica.team')->name('tica.create');
  Route::get('/create/{id}', 'FrontEnd\TicaUiController@organizerEdit')->whereNumber('id')
    ->middleware(['auth:organizer', 'Deactive:organizer', 'tica.team'])->name('tica.edit');
  Route::get('/dashboard/{section?}', 'FrontEnd\TicaUiController@page')->defaults('page', 'dashboard')
    ->where('section', '[a-z-]+')->middleware('tica.team')->name('tica.dashboard');
  Route::get('/checkin', 'FrontEnd\TicaUiController@page')->defaults('page', 'checkin')->middleware('tica.team')->name('tica.checkin');
  Route::get('/team/accept/{token}', 'FrontEnd\TicaUiController@teamAccept')->where('token', '[A-Za-z0-9]{20,100}')->name('tica.team.accept');
});

Route::post('/contact/send-mail', 'FrontEnd\ContactController@sendMail')
  ->middleware('throttle:public-form')->name('contact.send_mail');

Route::post('/advertisement/{id}/total-view', 'Controller@countAdView')
  ->whereNumber('id')->middleware('throttle:120,1')->name('advertisement.count-view');



// service unavailable route
Route::get('/service-unavailable', 'Controller@serviceUnavailable')->name('service_unavailable')->middleware('exists.down');

/*
|--------------------------------------------------------------------------
| Admin Panel Routes
|--------------------------------------------------------------------------
*/
Route::prefix('/admin')->middleware('guest:admin')->group(function () {
  // admin redirect to login page route
  Route::get('/', 'BackEnd\AdminController@login')->name('admin.login');

  // admin login attempt route
  Route::post('/auth', 'BackEnd\AdminController@authentication')
    ->middleware('throttle:authentication')->name('admin.auth');

  // admin forget password route
  Route::get('/forget-password', 'BackEnd\AdminController@forgetPassword')->name('admin.forget_password');

  // send mail to admin for forget password route
  Route::post('/mail-for-forget-password', 'BackEnd\AdminController@sendMail')->middleware('throttle:authentication')->name('admin.mail_for_forget_password');
  Route::get('/reset-password', 'BackEnd\AdminController@resetPassword')->name('admin.reset_password');
  Route::post('/reset-password', 'BackEnd\AdminController@updateForgottenPassword')->middleware('throttle:authentication')->name('admin.update_forgotten_password');
});

Route::get('/login-verification', 'AccountSecurityController@challenge')->name('mfa.login.challenge');
Route::get('/login-verification/app', 'TwoFactorController@challenge')->name('totp.login.challenge');
Route::post('/login-verification/app', 'TwoFactorController@verify')->middleware('throttle:authentication')->name('totp.login.verify');
Route::post('/login-verification', 'AccountSecurityController@verifyLogin')->middleware('throttle:authentication')->name('mfa.login.verify');
Route::post('/login-verification/cancel', 'AccountSecurityController@cancel')->middleware('throttle:authentication')->name('mfa.login.cancel');
Route::get('/account-security/{guard}', 'AccountSecurityController@show')->whereIn('guard', ['admin', 'organizer', 'customer'])->name('account-security.show');
Route::post('/account-security/{guard}/request', 'AccountSecurityController@requestChange')->whereIn('guard', ['admin', 'organizer', 'customer'])->middleware('throttle:3,10')->name('account-security.request');
Route::post('/account-security/{guard}/verify', 'AccountSecurityController@verifyChange')->whereIn('guard', ['admin', 'organizer', 'customer'])->middleware('throttle:authentication')->name('account-security.verify');


/*
|--------------------------------------------------------------------------
| TICA design endpoints (JSON, session + CSRF protected)
|--------------------------------------------------------------------------
*/
Route::prefix('tica-api')->middleware('change.lang')->group(function () {
  Route::post('/coupon', 'FrontEnd\TicaApiController@coupon')->middleware('throttle:checkout')->name('tica.api.coupon');
  Route::post('/waitlist', 'FrontEnd\TicaApiController@waitlist')->middleware('throttle:10,1')->name('tica.api.waitlist');
  Route::post('/checkout', 'FrontEnd\TicaApiController@checkout')->middleware('throttle:checkout')->name('tica.api.checkout');
  Route::post('/login-code', 'FrontEnd\TicaApiController@loginCode')->middleware('throttle:3,10')->name('tica.api.login-code');
  Route::post('/login-code/verify', 'FrontEnd\TicaApiController@loginCodeVerify')->middleware('throttle:authentication')->name('tica.api.login-code.verify');
  Route::post('/logout', 'FrontEnd\TicaApiController@logout')->middleware('throttle:30,1')->name('tica.api.logout');
  Route::post('/login', 'FrontEnd\TicaApiController@login')->middleware('throttle:authentication')->name('tica.api.login');
  Route::post('/signup', 'FrontEnd\TicaApiController@signup')->middleware('throttle:authentication')->name('tica.api.signup');
  Route::post('/forgot', 'FrontEnd\TicaApiController@forgot')->middleware('throttle:authentication')->name('tica.api.forgot');
  Route::post('/wishlist', 'FrontEnd\TicaApiController@wishlist')->middleware('throttle:60,1')->name('tica.api.wishlist');
  Route::post('/order-lookup', 'FrontEnd\TicaApiController@orderLookup')->middleware('throttle:10,1')->name('tica.api.order-lookup');

  Route::post('/team/accept', 'FrontEnd\TicaApiController@acceptTeamInvite')->middleware('throttle:authentication')->name('tica.api.team.accept');
  Route::post('/checkin/code', 'FrontEnd\TicaApiController@checkinCode')->middleware('throttle:scanner')->name('tica.api.checkin.code');

  Route::middleware(['auth:organizer', 'Deactive:organizer', 'EmailStatus:organizer'])->group(function () {
    Route::post('/events', 'FrontEnd\TicaOrganizerController@storeEvent')->middleware(['tica.team', 'throttle:20,1'])->name('tica.api.events.store');

    Route::prefix('organizer')->middleware('tica.team')->group(function () {
      Route::post('/events/{id}', 'FrontEnd\TicaWorkspaceController@updateEvent')->whereNumber('id')->middleware('throttle:60,1');
      Route::post('/events/{id}/details', 'FrontEnd\TicaOrganizerController@updateOrganizerEvent')->whereNumber('id')->middleware('throttle:30,1');
      Route::post('/events/{id}/date', 'FrontEnd\TicaWorkspaceController@changeDate')->whereNumber('id')->middleware('throttle:10,1');
      Route::post('/events/{id}/cancel', 'FrontEnd\TicaWorkspaceController@cancel')->whereNumber('id')->middleware('throttle:5,1');
      Route::get('/attendees', 'FrontEnd\TicaWorkspaceController@attendees')->middleware('throttle:120,1');
      Route::get('/attendees.csv', 'FrontEnd\TicaWorkspaceController@attendeesCsv')->middleware('throttle:10,1');
      Route::post('/orders/{booking}/resend', 'FrontEnd\TicaWorkspaceController@resend')->whereNumber('booking')->middleware('throttle:20,1');
      Route::post('/orders/{booking}/refund', 'FrontEnd\TicaWorkspaceController@refund')->whereNumber('booking')->middleware('throttle:10,1');
      Route::post('/message', 'FrontEnd\TicaWorkspaceController@message')->middleware('throttle:5,1');
      Route::post('/guests', 'FrontEnd\TicaWorkspaceController@guest')->middleware('throttle:30,1');
      Route::get('/banks', 'FrontEnd\TicaWorkspaceController@banks')->middleware('throttle:30,1');
      Route::post('/bank/resolve', 'FrontEnd\TicaWorkspaceController@resolveBank')->middleware('throttle:10,1');
      Route::post('/bank', 'FrontEnd\TicaWorkspaceController@saveBank')->middleware('throttle:10,10');
      Route::post('/payout-schedule', 'FrontEnd\TicaWorkspaceController@payoutSchedule')->middleware('throttle:20,1');
      Route::post('/withdraw', 'FrontEnd\TicaWorkspaceController@withdraw')->middleware('throttle:5,1');
      Route::post('/codes', 'FrontEnd\TicaWorkspaceController@storeCode')->middleware('throttle:20,1');
      Route::post('/links', 'FrontEnd\TicaWorkspaceController@storeLink')->middleware('throttle:20,1');
      Route::post('/promotions', 'FrontEnd\TicaWorkspaceController@storePromotion')->middleware('throttle:10,1');
      Route::post('/promotions/{campaign}/pay', 'FrontEnd\TicaWorkspaceController@payPromotion')->whereNumber('campaign')->middleware('throttle:5,10');
      Route::post('/promotions/{campaign}/cancel', 'FrontEnd\TicaWorkspaceController@cancelPromotion')->whereNumber('campaign')->middleware('throttle:10,1');
      Route::post('/tracking-pixels', 'FrontEnd\TicaWorkspaceController@storePixel')->middleware('throttle:10,10');
      Route::post('/team', 'FrontEnd\TicaWorkspaceController@invite')->middleware('throttle:10,1');
      Route::post('/two-factor/start', 'TwoFactorController@start')->middleware('throttle:10,1');
      Route::post('/two-factor/confirm', 'TwoFactorController@confirm')->middleware('throttle:10,1');
      Route::post('/two-factor/disable', 'TwoFactorController@disable')->middleware('throttle:10,1');
      Route::post('/two-factor/recovery-codes', 'TwoFactorController@regenerate')->middleware('throttle:10,1');
      Route::post('/team/{member}/remove', 'FrontEnd\TicaWorkspaceController@removeMember')->whereNumber('member')->middleware('throttle:20,1');
      Route::post('/team/{member}/role', 'FrontEnd\TicaWorkspaceController@changeMemberRole')->whereNumber('member')->middleware('throttle:20,1');
      Route::post('/profile', 'FrontEnd\TicaWorkspaceController@profile')->middleware('throttle:20,1');
      Route::post('/password', 'FrontEnd\TicaWorkspaceController@password')->middleware('throttle:authentication');
      Route::post('/prefs', 'FrontEnd\TicaWorkspaceController@prefs')->middleware('throttle:60,1');
    });
  });
});

/*
|--------------------------------------------------------------------------
| Custom Page Route For UI
|--------------------------------------------------------------------------
*/
// Vercel serves bundled assets before Laravel. Missing paths here are dynamic
// uploads, so redirect them to the durable public object store while preserving
// every legacy asset() URL already saved in views and database rows.
Route::get('/assets/{path}', 'FrontEnd\PublicAssetController@show')
  ->where('path', '.*')
  ->name('public_asset.show');

Route::get('/{slug}', 'FrontEnd\TicaUiController@customPage')->name('dynamic_page')->middleware('change.lang');

// fallback route
Route::fallback('FrontEnd\TicaUiController@missing')->middleware('change.lang');
