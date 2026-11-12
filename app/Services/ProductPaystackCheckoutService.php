<?php

namespace App\Services;

use App\Exceptions\GatewayOutcomeUnknownException;
use App\Models\BasicSettings\Basic;
use App\Models\ProductPaymentIntent;
use App\Models\ShopManagement\ProductOrder;
use App\Models\ShopManagement\ShippingCharge;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ProductPaystackCheckoutService
{
    public function start(
        array $checkout,
        array $cart,
        int $languageId,
        ?int $customerId,
        ?PaystackGatewayClient $client = null
    ): ProductPaymentIntent {
        $settings = Basic::query()->select([
            'shop_tax', 'base_currency_text', 'base_currency_text_position',
            'base_currency_symbol', 'base_currency_symbol_position',
        ])->firstOrFail();

        $currency = strtoupper((string) $settings->base_currency_text);
        if ($currency !== 'NGN') {
            throw new DomainException('Paystack shop checkout requires NGN as the base currency.');
        }

        $quote = app(ShopCheckoutPricingService::class)->quote(
            $cart,
            $checkout['coupon_code'] ?? null
        );
        $subtotal = $quote['subtotal'];
        $normalizedCart = $quote['cart'];
        $shipping = $this->shipping($checkout['shipping_method'] ?? null);
        $discount = $quote['discount'];
        $taxPercentage = max(0, (float) ($settings->shop_tax ?? 0));
        $tax = round((($subtotal - $discount) * $taxPercentage) / 100, 2);
        $total = round($subtotal - $discount + $tax + $shipping['charge'], 2);
        if ($total <= 0) {
            throw new DomainException('The payable order total must be greater than zero.');
        }

        $reference = 'tica-shop-' . Str::lower(Str::random(32));
        [$order, $intent] = DB::transaction(function () use (
            $checkout, $customerId, $subtotal, $discount, $taxPercentage, $tax,
            $total, $shipping, $settings, $currency, $reference, $normalizedCart, $languageId
        ): array {
            $same = !empty($checkout['sameas_shipping']);
            $order = ProductOrder::create([
                'user_id' => $customerId ?: 0,
                'billing_fname' => $checkout['fname'],
                'billing_lname' => $checkout['lname'],
                'billing_email' => $checkout['email'],
                'billing_phone' => $checkout['phone'],
                'billing_country' => $checkout['country'],
                'billing_state' => $checkout['state'] ?? null,
                'billing_city' => $checkout['city'],
                'billing_zip_code' => $checkout['zip_code'],
                'billing_address' => $checkout['address'],
                'shipping_fname' => $same ? $checkout['fname'] : $checkout['s_fname'],
                'shipping_lname' => $same ? $checkout['lname'] : $checkout['s_lname'],
                'shipping_email' => $same ? $checkout['email'] : $checkout['s_email'],
                'shipping_phone' => $same ? $checkout['phone'] : $checkout['s_phone'],
                'shipping_country' => $same ? $checkout['country'] : $checkout['s_country'],
                'shipping_state' => $same ? ($checkout['state'] ?? null) : ($checkout['s_state'] ?? null),
                'shipping_city' => $same ? $checkout['city'] : $checkout['s_city'],
                'shipping_zip_code' => $same ? $checkout['zip_code'] : $checkout['s_zip_code'],
                'shipping_address' => $same ? $checkout['address'] : $checkout['s_address'],
                'cart_total' => $subtotal,
                'discount' => $discount,
                'tax' => $tax,
                'tax_percentage' => $taxPercentage,
                'total' => $total,
                'currency_text' => $currency,
                'currency_text_position' => $settings->base_currency_text_position,
                'currency_symbol' => $settings->base_currency_symbol,
                'currency_symbol_position' => $settings->base_currency_symbol_position,
                'order_number' => Str::upper(Str::random(20)),
                'shipping_method' => $shipping['title'],
                'shipping_charge' => $shipping['charge'],
                'method' => 'Paystack',
                'gateway_type' => 'online',
                'payment_status' => 'pending',
                'order_status' => 'pending',
                'tnxid' => $reference,
                'charge_id' => $shipping['id'],
            ]);

            app(ProductOrderInventoryService::class)->reserveFromCart($order, $normalizedCart, $languageId);

            $intent = ProductPaymentIntent::create([
                'gateway' => 'paystack',
                'reference' => $reference,
                'product_order_id' => $order->id,
                'customer_id' => $customerId,
                'status' => 'created',
                'amount' => $total,
                'currency' => $currency,
                'email' => strtolower(trim((string) $checkout['email'])),
                'expires_at' => now()->addDay(),
            ]);

            AuditLogger::record('product_payment.created', $intent, [], [
                'status' => 'created', 'amount' => $total, 'currency' => $currency,
            ], ['gateway' => 'paystack', 'reference' => $reference, 'order_id' => $order->id]);

            return [$order, $intent];
        }, 3);

        try {
            $payload = ($client ?: new PaystackGatewayClient())->initializeTransaction(
                $reference,
                (string) $intent->email,
                $total,
                $currency,
                route('product_order.paystack.notify'),
                ['product_order_id' => $order->id, 'payment_kind' => 'product_order']
            );
            if ((string) ($payload['data']['reference'] ?? '') !== $reference) {
                throw new RuntimeException('Paystack returned a different transaction reference.');
            }

            $intent->update([
                'status' => 'initialized',
                'authorization_url' => $payload['data']['authorization_url'],
                'last_error' => null,
            ]);
            return $intent->fresh();
        } catch (GatewayOutcomeUnknownException $exception) {
            $intent->update(['status' => 'verification_required', 'last_error' => $exception->getMessage()]);
            app(IncidentAlertService::class)->alert(
                'product-paystack-init-unknown:' . $intent->id,
                'Product Paystack initialization has an unknown outcome.',
                ['reference' => $reference, 'order_id' => $order->id]
            );
            return $intent->fresh();
        } catch (\Throwable $exception) {
            $intent->update(['status' => 'failed', 'last_error' => $exception->getMessage()]);
            app(ProductOrderInventoryService::class)->release($order->fresh(), 'payment_initialization_failed');
            AuditLogger::record('product_payment.initialization_failed', $intent, [], ['status' => 'failed'], [
                'gateway' => 'paystack', 'reference' => $reference,
            ]);
            throw $exception;
        }
    }

    private function shipping(mixed $shippingMethod): array
    {
        if ($shippingMethod === null || $shippingMethod === '') {
            return ['id' => null, 'title' => null, 'charge' => 0.0];
        }
        if (!ctype_digit((string) $shippingMethod)) {
            throw new DomainException('The selected shipping method is invalid.');
        }
        $shipping = ShippingCharge::find((int) $shippingMethod);
        if (!$shipping) {
            throw new DomainException('The selected shipping method is no longer available.');
        }
        return ['id' => $shipping->id, 'title' => $shipping->title, 'charge' => (float) $shipping->charge];
    }
}
