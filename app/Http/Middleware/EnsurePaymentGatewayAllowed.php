<?php

namespace App\Http\Middleware;

use App\Services\PaymentGatewayPolicy;
use Closure;
use Illuminate\Http\Request;

class EnsurePaymentGatewayAllowed
{
    public function __construct(private readonly PaymentGatewayPolicy $policy)
    {
    }

    public function handle(Request $request, Closure $next, string $scope, string $gateway)
    {
        $allowed = $scope === 'shop'
            ? $this->policy->canOfferShopOnline($gateway)
            : $this->policy->canOfferOnline($gateway);

        abort_unless($allowed, 404);

        return $next($request);
    }
}
