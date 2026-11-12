<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventCancellationRequest extends Model
{
    protected $fillable = [
        'public_id', 'event_id', 'organizer_id', 'requested_by_organizer_id',
        'requested_by_admin_id', 'reviewed_by_admin_id', 'status',
        'event_status_before_request', 'reason', 'customer_message',
        'rejection_reason', 'eligible_refund_count', 'eligible_refund_amount',
        'queued_refund_count', 'queued_refund_amount', 'manual_review_count',
        'manual_review_amount', 'requested_at', 'reviewed_at', 'approved_at',
        'rejected_at',
    ];

    protected $casts = [
        'event_status_before_request' => 'boolean',
        'eligible_refund_amount' => 'decimal:2',
        'queued_refund_amount' => 'decimal:2',
        'manual_review_amount' => 'decimal:2',
        'requested_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function organizer()
    {
        return $this->belongsTo(Organizer::class);
    }

    public function refunds()
    {
        return $this->hasMany(PaymentRefund::class);
    }

    public function cases()
    {
        return $this->hasMany(EventCancellationCase::class);
    }
}
