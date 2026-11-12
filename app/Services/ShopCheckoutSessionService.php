<?php

namespace App\Services;

use Illuminate\Support\Facades\Session;

final class ShopCheckoutSessionService
{
    /**
     * Remove only state derived from the completed shop checkout. Other
     * session data (authentication, locale, flash messages) must survive.
     */
    public function clearCompletedCheckout(): void
    {
        Session::forget([
            'cart',
            'Shop_discount',
            'Shop_coupon_code',
            'shipping_cost',
            'arrData',
            'paymentId',
        ]);
    }
}
