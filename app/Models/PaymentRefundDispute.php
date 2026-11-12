<?php

namespace App\Models;

use App\Models\Event\Booking;
use Illuminate\Database\Eloquent\Model;

class PaymentRefundDispute extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_REFUND_QUEUED = 'refund_queued';
    public const STATUS_REJECTED = 'rejected';

    public const CATEGORIES = [
        'duplicate_scan', 'entry_issue', 'event_service_failure', 'exceptional_circumstances', 'other',
    ];

    protected $fillable = [
        'public_id', 'payment_intent_id', 'booking_id', 'organizer_id',
        'requested_by_organizer_id', 'requested_by_admin_id', 'reviewed_by_admin_id',
        'payment_refund_id', 'category', 'description', 'status', 'requested_amount',
        'currency', 'checked_in_ticket_count', 'resolution', 'opened_at', 'reviewed_at', 'closed_at',
    ];

    protected $casts = [
        'requested_amount' => 'decimal:2',
        'opened_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'closed_at' => 'datetime',
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

    public function refund()
    {
        return $this->belongsTo(PaymentRefund::class, 'payment_refund_id');
    }
}
