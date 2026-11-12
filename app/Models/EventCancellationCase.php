<?php

namespace App\Models;

use App\Models\Event\Booking;
use Illuminate\Database\Eloquent\Model;

class EventCancellationCase extends Model
{
    protected $fillable = [
        'event_cancellation_request_id', 'booking_id', 'payment_intent_id',
        'payment_refund_id', 'payment_refund_dispute_id', 'resolved_by_admin_id',
        'external_refund_reference', 'status', 'amount', 'reason', 'resolution', 'resolved_at',
    ];

    protected $casts = ['amount' => 'decimal:2', 'resolved_at' => 'datetime'];

    public function cancellationRequest()
    {
        return $this->belongsTo(EventCancellationRequest::class, 'event_cancellation_request_id');
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function paymentIntent()
    {
        return $this->belongsTo(PaymentIntent::class);
    }

    public function paymentRefund()
    {
        return $this->belongsTo(PaymentRefund::class);
    }

    public function paymentRefundDispute()
    {
        return $this->belongsTo(PaymentRefundDispute::class);
    }
}
