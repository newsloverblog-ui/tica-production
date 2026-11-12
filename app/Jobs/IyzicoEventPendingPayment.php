<?php

namespace App\Jobs;

use App\Models\Event\Booking;
use App\Services\AuditLogger;
use App\Services\EventSaleAccountingService;
use App\Services\Iyzipay;
use App\Services\PaidBookingFulfillmentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class IyzicoEventPendingPayment implements ShouldQueue, ShouldBeEncrypted, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $timeout = 60;
    public int $uniqueFor = 600;

    public function __construct(public int $booking_id)
    {
        $this->onQueue('payments');
    }

    public function uniqueId(): string
    {
        return 'iyzico-event-' . $this->booking_id;
    }

    public function backoff(): array
    {
        return [60, 300, 900, 1800];
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->uniqueId()))->expireAfter(120)];
    }

    public function handle(EventSaleAccountingService $accounting, PaidBookingFulfillmentService $fulfillment): void
    {
        $eventBooking = Booking::find($this->booking_id);
        if (!$eventBooking || strtolower((string) $eventBooking->paymentStatus) !== 'pending'
            || strcasecmp((string) $eventBooking->paymentMethod, 'Iyzico') !== 0
            || !$eventBooking->conversation_id) {
            return;
        }

        $options = Iyzipay::options();
        $request = new \Iyzipay\Request\ReportingPaymentDetailRequest();
        $request->setPaymentConversationId($eventBooking->conversation_id);
        $paymentResponse = \Iyzipay\Model\ReportingPaymentDetail::create($request, $options);
        if (!$this->isPaid($paymentResponse)) {
            return;
        }

        $completed = false;
        DB::transaction(function () use ($accounting, &$completed): void {
            $booking = Booking::whereKey($this->booking_id)->lockForUpdate()->first();
            if (!$booking || strtolower((string) $booking->paymentStatus) !== 'pending') {
                return;
            }

            $booking->forceFill(['paymentStatus' => 'completed'])->save();
            $booking->paymentStatus = 1;
            $booking->transcation_type = 1;
            $accounting->apply($booking, 'iyzico', (string) $booking->conversation_id);
            AuditLogger::record('payment.completed', $booking, ['paymentStatus' => 'pending'], ['paymentStatus' => 'completed'], [
                'gateway' => 'iyzico', 'reference' => (string) $booking->conversation_id,
            ]);
            $completed = true;
        }, 3);

        if ($completed) {
            $fulfillment->dispatch($this->booking_id);
        }
    }

    private function isPaid(object $response): bool
    {
        foreach ((array) $response as $value) {
            $data = is_string($value) ? json_decode($value, true) : null;
            if (is_array($data) && ($data['status'] ?? null) === 'success'
                && isset($data['payments'][0]) && (int) ($data['payments'][0]['paymentStatus'] ?? 0) === 1) {
                return true;
            }
        }
        return false;
    }
}
