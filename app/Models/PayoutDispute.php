<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayoutDispute extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_REJECTED = 'rejected';

    public const CATEGORIES = [
        'delayed', 'incorrect_amount', 'rejected_payout', 'missing_payout', 'other',
    ];

    protected $fillable = [
        'public_id', 'withdrawal_id', 'organizer_id', 'resolved_by_admin_id',
        'category', 'description', 'status', 'withdrawal_status', 'amount',
        'resolution', 'opened_at', 'reviewed_at', 'closed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'opened_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function withdrawal()
    {
        return $this->belongsTo(Withdraw::class, 'withdrawal_id');
    }

    public function organizer()
    {
        return $this->belongsTo(Organizer::class);
    }
}
