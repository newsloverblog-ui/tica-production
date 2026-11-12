<?php

namespace App\Services;

use App\Models\ShopManagement\OrderItem;
use App\Models\ShopManagement\Product;
use App\Models\ShopManagement\ProductContent;
use App\Models\ShopManagement\ProductOrder;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProductOrderInventoryService
{
    public function reserveFromCart(ProductOrder $order, array $cart, int $languageId): Collection
    {
        if ($cart === []) {
            throw new DomainException('Your cart is empty.');
        }

        $items = DB::transaction(function () use ($order, $cart, $languageId): Collection {
            $lockedOrder = ProductOrder::whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            $existingItems = OrderItem::where('product_order_id', $lockedOrder->id)->get();
            if ($lockedOrder->inventory_reserved_at) {
                if ($existingItems->isEmpty()) {
                    throw new DomainException('The order inventory marker is inconsistent with its items.');
                }

                return $existingItems;
            }
            if ($existingItems->isNotEmpty()) {
                throw new DomainException('The order already contains items without an inventory reservation marker.');
            }

            $quantities = [];
            foreach ($cart as $productId => $cartItem) {
                if (!ctype_digit((string) $productId)) {
                    throw new DomainException('The cart contains an invalid product.');
                }
                $quantity = filter_var($cartItem['qty'] ?? null, FILTER_VALIDATE_INT);
                if ($quantity === false || $quantity < 1 || $quantity > 100) {
                    throw new DomainException('The cart contains an invalid product quantity.');
                }
                $quantities[(int) $productId] = $quantity;
            }
            ksort($quantities);

            $products = Product::whereIn('id', array_keys($quantities))
                ->where('status', 1)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            if ($products->count() !== count($quantities)) {
                throw new DomainException('One or more products are no longer available.');
            }

            $contents = ProductContent::whereIn('product_id', array_keys($quantities))
                ->where('language_id', $languageId)
                ->get()
                ->keyBy('product_id');
            if ($contents->count() !== count($quantities)) {
                throw new DomainException('One or more products are missing checkout information.');
            }

            $serverSubtotal = 0.0;
            foreach ($quantities as $productId => $quantity) {
                $product = $products->get($productId);
                $serverSubtotal += (float) $product->current_price * $quantity;
                if (strtolower((string) $product->type) !== 'digital' && (int) $product->stock < $quantity) {
                    throw new DomainException($contents->get($productId)->title . ' no longer has enough stock.');
                }
            }
            if (abs($serverSubtotal - (float) $lockedOrder->cart_total) >= 0.005) {
                throw new DomainException('Product prices changed while this cart was open. Review the cart before paying.');
            }

            foreach ($quantities as $productId => $quantity) {
                $product = $products->get($productId);
                $content = $contents->get($productId);
                if (strtolower((string) $product->type) !== 'digital') {
                    $product->stock = (string) ((int) $product->stock - $quantity);
                    $product->save();
                }

                OrderItem::create([
                    'product_order_id' => $lockedOrder->id,
                    'product_id' => $productId,
                    'user_id' => $lockedOrder->user_id,
                    'title' => $content->title,
                    'sku' => $product->sku,
                    'qty' => $quantity,
                    'category' => '',
                    'image' => $product->feature_image,
                    'summery' => $content->summary,
                    'description' => $content->description,
                    'price' => $product->current_price,
                    'previous_price' => $product->previous_price,
                ]);
            }

            $lockedOrder->forceFill(['inventory_reserved_at' => now()])->save();
            AuditLogger::record('product_inventory.reserved', $lockedOrder, [], [
                'items' => count($quantities), 'quantity' => array_sum($quantities),
            ]);

            return OrderItem::where('product_order_id', $lockedOrder->id)->get();
        }, 3);

        app(ProductOrderAccountingService::class)->recordCompleted($order->fresh());

        return $items;
    }

    public function release(ProductOrder $order, string $reason): void
    {
        $this->releaseInventory($order, $reason, false);
    }

    public function releaseRefunded(ProductOrder $order, string $reason): void
    {
        $this->releaseInventory($order, $reason, true);
    }

    private function releaseInventory(ProductOrder $order, string $reason, bool $allowRefunded): void
    {
        DB::transaction(function () use ($order, $reason, $allowRefunded): void {
            $lockedOrder = ProductOrder::whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            if (!$lockedOrder->inventory_reserved_at || $lockedOrder->inventory_released_at) {
                return;
            }
            $paymentStatus = strtolower((string) $lockedOrder->payment_status);
            if ($paymentStatus === 'completed') {
                throw new DomainException('Paid order inventory cannot be released without a controlled refund or cancellation workflow.');
            }
            if ($allowRefunded && ($paymentStatus !== 'refunded' || $lockedOrder->refund_status !== 'refunded')) {
                throw new DomainException('Inventory can only be restored through a processed full-refund record.');
            }
            if (!$allowRefunded && $paymentStatus === 'refunded') {
                throw new DomainException('Refunded order inventory must be restored through the controlled refund workflow.');
            }

            $items = OrderItem::where('product_order_id', $lockedOrder->id)
                ->orderBy('product_id')
                ->get();
            if ($items->isEmpty()) {
                throw new DomainException(
                    'The order inventory reservation has no item evidence and requires inventory review.'
                );
            }

            $quantities = [];
            foreach ($items as $item) {
                $productId = filter_var($item->product_id, FILTER_VALIDATE_INT, [
                    'options' => ['min_range' => 1, 'max_range' => PHP_INT_MAX],
                ]);
                $quantity = filter_var($item->qty, FILTER_VALIDATE_INT, [
                    'options' => ['min_range' => 1, 'max_range' => PHP_INT_MAX],
                ]);
                if ($productId === false || $quantity === false) {
                    throw new DomainException(
                        'The order contains invalid inventory evidence and requires inventory review.'
                    );
                }
                if (isset($quantities[$productId])) {
                    throw new DomainException(
                        'The order contains duplicate product inventory evidence and requires inventory review.'
                    );
                }
                $quantities[$productId] = $quantity;
            }

            $products = Product::whereIn('id', array_keys($quantities))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            if ($products->count() !== count($quantities)) {
                throw new DomainException(
                    'One or more reserved products no longer exist and require inventory review.'
                );
            }

            foreach ($quantities as $productId => $quantity) {
                $product = $products->get($productId);
                if (strtolower((string) $product->type) !== 'digital') {
                    $product->stock = (string) ((int) $product->stock + $quantity);
                    $product->save();
                }
            }

            $lockedOrder->forceFill(['inventory_released_at' => now()])->save();
            AuditLogger::record('product_inventory.released', $lockedOrder, [], [
                'reason' => $reason, 'quantity' => array_sum($quantities),
            ]);
        }, 3);
    }
}
