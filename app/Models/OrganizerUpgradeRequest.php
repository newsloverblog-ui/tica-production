<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A customer's request to turn their account into an organiser account. */
class OrganizerUpgradeRequest extends Model
{
    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';

    protected $fillable = [
        'customer_id', 'organisation', 'contact_name', 'phone', 'status', 'reason',
        'organizer_id', 'reviewed_by', 'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class);
    }
}
