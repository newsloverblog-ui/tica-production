<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganizerKycDocument extends Model
{
    protected $fillable = [
        'submission_id', 'document_type', 'disk', 'path', 'original_name',
        'mime_type', 'size', 'sha256',
    ];

    public function submission()
    {
        return $this->belongsTo(OrganizerKycSubmission::class, 'submission_id');
    }
}
