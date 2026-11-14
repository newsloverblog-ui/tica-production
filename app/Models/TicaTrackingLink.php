<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A promoter or channel link; buyers who arrive through it are credited to it. */
class TicaTrackingLink extends Model
{
  protected $table = 'tica_tracking_links';

  protected $fillable = ['organizer_id', 'event_id', 'name', 'channel', 'influencer', 'code', 'clicks', 'is_active', 'last_clicked_at'];

  protected $casts = ['is_active' => 'boolean', 'last_clicked_at' => 'datetime'];

  public function organizer()
  {
    return $this->belongsTo(Organizer::class);
  }

  public function event()
  {
    return $this->belongsTo(Event::class);
  }
}
