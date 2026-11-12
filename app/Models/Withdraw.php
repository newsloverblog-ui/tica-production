<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Withdraw extends Model
{
  use HasFactory;

  public const STATUS_PENDING = 0;
  public const STATUS_PAID = 1;
  public const STATUS_REJECTED = 2;
  public const STATUS_HELD = 3;
  public const STATUS_PROCESSING = 4;

  protected $casts = [
    'reviewed_at' => 'datetime',
    'paid_at' => 'datetime',
  ];

  public function method()
  {
    return $this->belongsTo(WithdrawPaymentMethod::class, 'method_id', 'id');
  }
  public function organizer()
  {
    return $this->belongsTo(Organizer::class);
  }

  public function payoutAccount()
  {
    return $this->belongsTo(OrganizerPayoutAccount::class, 'payout_account_id');
  }

  public function payoutTransfers()
  {
    return $this->hasMany(PayoutTransfer::class, 'withdrawal_id');
  }

  public function latestPayoutTransfer()
  {
    return $this->hasOne(PayoutTransfer::class, 'withdrawal_id')->latestOfMany();
  }

  public function payoutDisputes()
  {
    return $this->hasMany(PayoutDispute::class, 'withdrawal_id');
  }

  public function latestPayoutDispute()
  {
    return $this->hasOne(PayoutDispute::class, 'withdrawal_id')->latestOfMany();
  }
}
