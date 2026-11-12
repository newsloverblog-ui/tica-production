<?php

namespace App\Http\Controllers\BackEnd\ShopManagement;

use App\Http\Controllers\Controller;
use App\Services\ProductOrderRefundService;
use App\Models\ProductPaymentIntent;
use App\Services\ProductGatewayRefundService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class ProductOrderRefundController extends Controller
{
    public function store(
        Request $request,
        ProductOrderRefundService $refunds,
        ProductGatewayRefundService $gatewayRefunds
    )
    {
        $validated = $request->validate([
            'product_order_id' => ['required', 'integer'],
            'external_reference' => ['nullable', 'string', 'max:191'],
            'reason' => ['required', 'string', 'max:2000'],
            'restore_inventory' => ['nullable', 'boolean'],
            'confirm_full_refund' => ['accepted'],
        ]);

        try {
            $intent = ProductPaymentIntent::query()
                ->where('product_order_id', (int) $validated['product_order_id'])
                ->where('gateway', 'paystack')
                ->where('status', 'completed')
                ->first();
            if ($intent) {
                $refund = $gatewayRefunds->requestFull(
                    (int) $validated['product_order_id'],
                    $validated['reason'],
                    (int) Auth::guard('admin')->id(),
                    (bool) ($validated['restore_inventory'] ?? false)
                );
                return back()->with('success', 'Full product refund '.$refund->public_id.' was queued for Paystack submission. Financial records will change only after provider confirmation.');
            }
            if (empty($validated['external_reference'])) {
                return back()->withInput()->with('error', 'An external refund reference is required for a manual payment refund.');
            }
            $refund = $refunds->recordFullManualRefund(
                (int) $validated['product_order_id'],
                $validated['external_reference'],
                $validated['reason'],
                (int) Auth::guard('admin')->id(),
                (bool) ($validated['restore_inventory'] ?? false)
            );
        } catch (RuntimeException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        } catch (QueryException $exception) {
            report($exception);

            return back()->withInput()->with('error', 'The refund could not be recorded because its order or external reference conflicts with an existing refund.');
        }

        return back()->with('success', 'Full product refund '.$refund->public_id.' was recorded and reconciled.');
    }
}
