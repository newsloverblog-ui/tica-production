<?php

namespace App\Services;

use App\Models\ShopManagement\Product;
use App\Models\ShopManagement\ShopCoupon;
use Carbon\Carbon;
use DomainException;

class ShopCheckoutPricingService
{
    public function quote(array $cart, ?string $couponCode = null): array
    {
        if ($cart === []) {
            throw new DomainException('Your cart is empty.');
        }

        $ids = [];
        foreach (array_keys($cart) as $id) {
            if (!ctype_digit((string) $id) || (int) $id < 1) {
                throw new DomainException('The cart contains an invalid product.');
            }
            $ids[] = (int) $id;
        }

        $products = Product::query()
            ->whereIn('id', $ids)
            ->where('status', 1)
            ->get()
            ->keyBy('id');
        if ($products->count() !== count($ids)) {
            throw new DomainException('A product in your cart is no longer available.');
        }

        $subtotal = 0.0;
        $normalized = [];
        foreach ($cart as $id => $item) {
            $product = $products->get((int) $id);
            $quantity = filter_var($item['qty'] ?? null, FILTER_VALIDATE_INT);
            if ($quantity === false || $quantity < 1 || $quantity > 100) {
                throw new DomainException('The cart contains an invalid quantity.');
            }
            if ($product->type !== 'digital' && (int) $product->stock < $quantity) {
                throw new DomainException('A product in your cart no longer has enough stock.');
            }

            $price = round((float) $product->current_price, 2);
            if ($price < 0) {
                throw new DomainException('A product in your cart has an invalid price.');
            }

            $normalized[(int) $id] = array_merge((array) $item, [
                'qty' => $quantity,
                'price' => $price,
                'photo' => $product->feature_image,
                'type' => $product->type,
            ]);
            $subtotal += $price * $quantity;
        }

        $subtotal = round($subtotal, 2);
        [$normalizedCode, $discount] = $this->couponDiscount($couponCode, $subtotal);

        return [
            'cart' => $normalized,
            'subtotal' => $subtotal,
            'coupon_code' => $normalizedCode,
            'discount' => $discount,
        ];
    }

    private function couponDiscount(?string $couponCode, float $subtotal): array
    {
        $code = trim((string) $couponCode);
        if ($code === '') {
            return [null, 0.0];
        }
        if (mb_strlen($code) > 100) {
            throw new DomainException('The coupon is invalid or no longer available.');
        }

        $coupon = ShopCoupon::query()->where('code', $code)->first();
        if (!$coupon) {
            throw new DomainException('The coupon is invalid or no longer available.');
        }

        try {
            $startsAt = Carbon::parse((string) $coupon->start_date)->startOfDay();
            $endsAt = Carbon::parse((string) $coupon->end_date)->endOfDay();
        } catch (\Throwable) {
            throw new DomainException('The coupon is invalid or no longer available.');
        }

        $now = now();
        $minimumSpend = max(0, round((float) $coupon->minimum_spend, 2));
        if ($now->lt($startsAt) || $now->gt($endsAt) || $subtotal < $minimumSpend) {
            throw new DomainException('The coupon is invalid or the minimum spend has not been reached.');
        }

        $value = round((float) $coupon->value, 2);
        $type = strtolower(trim((string) $coupon->type));
        if ($value <= 0 || !in_array($type, ['fixed', 'percentage'], true)) {
            throw new DomainException('The coupon configuration is invalid.');
        }
        if ($type === 'percentage' && $value > 100) {
            throw new DomainException('The coupon configuration is invalid.');
        }

        $discount = $type === 'fixed'
            ? $value
            : round($subtotal * ($value / 100), 2);

        return [$code, min($subtotal, max(0, round($discount, 2)))];
    }
}
