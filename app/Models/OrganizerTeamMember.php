<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Someone the organiser invited to help: a manager, finance or gate staff. */
class OrganizerTeamMember extends Model
{
  public const ROLES = ['manager', 'finance', 'gate'];

  protected $fillable = [
    'organizer_id', 'name', 'email', 'role', 'event_ids', 'password', 'invite_token_hash',
    'invite_expires_at', 'accepted_at', 'status', 'scanner_staff_id',
  ];

  protected $hidden = ['password', 'invite_token_hash'];

  protected $casts = [
    'event_ids' => 'array',
    'invite_expires_at' => 'datetime',
    'accepted_at' => 'datetime',
  ];

  public function organizer()
  {
    return $this->belongsTo(Organizer::class);
  }
}
