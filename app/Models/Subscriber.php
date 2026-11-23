<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
}
