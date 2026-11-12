<?php

namespace App\Services;

use App\Models\ShopManagement\ProductOrder;

final readonly class ManualProductOrderPaymentResult
{
    public function __construct(
        public ProductOrder $order,
        public string $before,
        public bool $changed,
    ) {}
}
