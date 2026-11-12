<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A promoter or channel link; buyers who arrive through it are credited to it. */
class TicaTrackingLink extends Model
{
  protected $table = 'tica_tracking_links';

  protected $fillable = ['organizer_id', 'event_id', 'name', 'code', 'clicks'];
}
