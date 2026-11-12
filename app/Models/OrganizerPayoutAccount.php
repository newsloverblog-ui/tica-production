<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganizerPayoutAccount extends Model
{
    protected $fillable = [
        'public_id', 'organizer_id', 'provider', 'provider_recipient_code',
        'recipient_created_at', 'country_code', 'currency',
        'bank_code', 'bank_name', 'account_number', 'account_number_hash',
        'account_last4', 'resolved_account_name', 'name_match_status', 'status',
        'is_default', 'verified_at', 'hold_until', 'locked_at',
    ];

    protected $casts = [
        'account_number' => 'encrypted',
        'provider_recipient_code' => 'encrypted',
        'is_default' => 'boolean',
        'verified_at' => 'datetime',
        'hold_until' => 'datetime',
        'locked_at' => 'datetime',
        'recipient_created_at' => 'datetime',
    ];

    public function organizer()
    {
        return $this->belongsTo(Organizer::class);
    }

    public function withdrawals()
    {
        return $this->hasMany(Withdraw::class, 'payout_account_id');
    }

    public function maskedNumber(): string
    {
        return '******' . $this->account_last4;
    }
}
