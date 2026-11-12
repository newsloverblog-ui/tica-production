<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganizerKycSubmission extends Model
{
    protected $fillable = [
        'public_id', 'organizer_id', 'entity_type', 'legal_name', 'business_name',
        'registration_number', 'id_type', 'status', 'rejection_reason',
        'reviewed_by_admin_id', 'submitted_at', 'reviewed_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function organizer()
    {
        return $this->belongsTo(Organizer::class);
    }

    public function documents()
    {
        return $this->hasMany(OrganizerKycDocument::class, 'submission_id');
    }
}
