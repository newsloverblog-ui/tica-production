<?php

namespace App\Http\Controllers\FrontEnd\PaymentGateway\Concerns;

use App\Http\Controllers\FrontEnd\Event\BookingController;
use App\Models\PaymentIntent;
use App\Models\Transaction;
use App\Services\AuditLogger;
use App\Services\EventSaleAccountingService;
use App\Services\PaidBookingFulfillmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

/**
 * Turns a payment the gateway has already confirmed into one booking. A
 * database lock and the ledger's unique gateway reference make repeat calls
 * (redirect, webhook, recovery) safe.
 */
trait FulfilsVerifiedPayment
{
    // =========================================================================
    // Fulfil a verified payment exactly once
    // =========================================================================
    protected function processVerifiedPayment(string $gateway, $reference, $arrData)
    {
        $lockName = $gateway . '_ref_' . hash('sha256', (string) $reference);
        $acquired = DB::select('SELECT GET_LOCK(?, 0) AS acquired', [$lockName])[0]->acquired ?? 0;
        if (!$acquired) {
            return ['status' => 'duplicate', 'bookingInfo' => null];
        }

        try {
            $existingRow = Transaction::query()->where('gateway', $gateway)->where('gateway_reference', $reference)->first();
            $intent = PaymentIntent::where('gateway', $gateway)->where('reference', $reference)->first();
            if ($existingRow) {
                if ($intent && $intent->status !== 'completed') {
                    $intent->update(['status' => 'completed', 'booking_id' => $existingRow->booking_id, 'processed_at' => now(), 'last_error' => null]);
                }
                return ['status' => 'already_processed', 'bookingInfo' => null, 'existingTxn' => $existingRow];
            }
            if (!$intent) {
                throw new \LogicException('A durable payment intent is required before fulfillment.');
            }

            if (!empty($arrData['event_date'])) {
                Session::put('event_date', $arrData['event_date']);
            }

            $bookingInfo = null;
            DB::transaction(function () use ($gateway, $reference, $arrData, &$bookingInfo) {
                $intent = PaymentIntent::where('gateway', $gateway)->where('reference', $reference)->lockForUpdate()->firstOrFail();
                if ($intent->status === 'completed') {
                    return;
                }
                $intent->update(['status' => 'processing', 'last_error' => null]);

                if (Transaction::where('gateway', $gateway)->where('gateway_reference', $reference)->lockForUpdate()->exists()) {
                    return;
                }

                $bookingInfo = (new BookingController())->storeData($arrData);
                if (!$bookingInfo) {
                    throw new \Exception('storeData() returned null for reference: ' . $reference);
                }

                $bookingInfo->paymentStatus = 1;
                $bookingInfo->transcation_type = 1;
                $bookingInfo->transcation_id = $reference;
                app(EventSaleAccountingService::class)->apply($bookingInfo, $gateway, $reference);

                $intent->update(['status' => 'completed', 'booking_id' => $bookingInfo->id, 'processed_at' => now(), 'last_error' => null]);
                AuditLogger::record('payment.completed', $intent, ['status' => 'processing'], ['status' => 'completed'], [
                    'gateway' => $gateway,
                    'reference' => $reference,
                    'booking_id' => $bookingInfo->id,
                    'amount' => (float) $intent->amount,
                    'currency' => $intent->currency,
                ]);
            });

            if (!$bookingInfo) {
                $existingTxn = Transaction::where('gateway', $gateway)->where('gateway_reference', $reference)->first();
                return ['status' => 'already_processed', 'bookingInfo' => null, 'existingTxn' => $existingTxn];
            }

            Session::forget('event_date');
            DB::select('SELECT RELEASE_LOCK(?)', [$lockName]);

            $bookingInfo = $bookingInfo->fresh();
            app(PaidBookingFulfillmentService::class)->dispatch($bookingInfo->id);

            return ['status' => 'success', 'bookingInfo' => $bookingInfo];
        } catch (\Exception $e) {
            Log::error(ucfirst($gateway) . ' payment processing failed.', [
                'reference' => $reference,
                'exception_type' => $e::class,
                'exception_code' => $e->getCode(),
            ]);
            PaymentIntent::where('gateway', $gateway)->where('reference', $reference)->where('status', '!=', 'completed')
                ->update(['status' => 'failed', 'last_error' => 'Payment processing failed; inspect the correlated exception report.']);
            throw $e;
        } finally {
            DB::select('SELECT RELEASE_LOCK(?)', [$lockName]);
        }
    }

    protected function completedRedirect(Request $request, PaymentIntent $intent)
    {
        $request->session()->forget(['eventId', 'arrData', 'discount', 'selTickets', 'paymentId', 'event_date']);

        return redirect(app(\App\Services\BookingCompletionUrlService::class)->make((int) $intent->event_id, (int) $intent->booking_id));
    }
}
