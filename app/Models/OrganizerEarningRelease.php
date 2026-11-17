<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganizerEarningRelease extends Model
{
    protected $fillable = [
        'public_id', 'organizer_id', 'event_id', 'booking_id',
        'financial_ledger_transaction_id', 'currency', 'gross_amount_minor',
        'platform_fee_minor', 'processor_fee_minor', 'organizer_amount_minor',
        'status', 'available_at', 'released_at', 'held_at', 'hold_reason',
    ];

    protected $casts = [
        'gross_amount_minor' => 'integer',
        'platform_fee_minor' => 'integer',
        'processor_fee_minor' => 'integer',
        'organizer_amount_minor' => 'integer',
        'available_at' => 'datetime', 'released_at' => 'datetime', 'held_at' => 'datetime',
    ];
}
