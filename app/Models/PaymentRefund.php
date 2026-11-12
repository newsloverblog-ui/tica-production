<?php

namespace App\Models;

use App\Casts\PrefixedEncryptedString;
use App\Models\Event\Booking;
use Illuminate\Database\Eloquent\Model;

class PaymentRefund extends Model
{
    protected $fillable = [
        'public_id', 'payment_intent_id', 'event_cancellation_request_id', 'payment_refund_dispute_id',
        'transaction_id', 'booking_id',
        'organizer_id', 'requested_by_admin_id', 'requested_by_organizer_id',
        'reviewed_by_admin_id', 'reviewed_at', 'rejection_reason', 'gateway',
        'gateway_reference', 'gateway_refund_id', 'gateway_status', 'amount',
        'currency', 'reason', 'status', 'gateway_response', 'last_error',
        'requested_at', 'submitted_at', 'completed_at', 'financial_applied_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'gateway_response' => 'encrypted:array',
        'last_error' => PrefixedEncryptedString::class,
        'requested_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'submitted_at' => 'datetime',
        'completed_at' => 'datetime',
        'financial_applied_at' => 'datetime',
    ];

    public function paymentIntent()
    {
        return $this->belongsTo(PaymentIntent::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function organizer()
    {
        return $this->belongsTo(Organizer::class);
    }

    public function eventCancellationRequest()
    {
        return $this->belongsTo(EventCancellationRequest::class);
    }

    public function dispute()
    {
        return $this->belongsTo(PaymentRefundDispute::class, 'payment_refund_dispute_id');
    }
}
