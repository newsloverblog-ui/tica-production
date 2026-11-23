<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Subscriber extends Model
{
  use HasFactory;

  /**
   * The attributes that are mass assignable.
   *
   * @var array
   */
  protected $fillable = ['email_id', 'source', 'subscribed_at', 'unsubscribed_at', 'consent_ip_hash'];

  protected $casts = [
    'subscribed_at' => 'datetime',
    'unsubscribed_at' => 'datetime',
  ];

  public function scopeCurrentlySubscribed(Builder $query): Builder
  {
    return static::hasConsentColumn('unsubscribed_at')
      ? $query->whereNull('unsubscribed_at')
      : $query;
  }

  public static function hasConsentColumn(string $column): bool
  {
    try {
      return Schema::hasColumn((new static())->getTable(), $column);
    } catch (\Throwable) {
      return false;
    }
  }
}
