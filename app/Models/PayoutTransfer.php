<?php

namespace App\Models;

use App\Casts\PrefixedEncryptedString;
use Illuminate\Database\Eloquent\Model;

class PayoutTransfer extends Model
{
    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
        'recipient_code' => 'encrypted',
        'gateway_response' => 'encrypted:array',
        'last_error' => PrefixedEncryptedString::class,
        'dispatch_attempts' => 'integer',
        'dispatch_last_attempt_at' => 'datetime',
        'dispatch_last_error' => 'encrypted',
        'dispatch_alerted_at' => 'datetime',
        'notification_dispatch_attempts' => 'integer',
        'notification_dispatch_last_attempt_at' => 'datetime',
        'notification_dispatch_last_error' => 'encrypted',
        'notification_dispatch_alerted_at' => 'datetime',
        'submitted_at' => 'datetime',
        'last_verified_at' => 'datetime',
        'webhook_received_at' => 'datetime',
        'completed_at' => 'datetime',
        'organizer_notified_at' => 'datetime',
        'finance_notified_at' => 'datetime',
    ];

    public function withdrawal()
    {
        return $this->belongsTo(Withdraw::class, 'withdrawal_id');
    }

    public function payoutAccount()
    {
        return $this->belongsTo(OrganizerPayoutAccount::class, 'payout_account_id');
    }
}
