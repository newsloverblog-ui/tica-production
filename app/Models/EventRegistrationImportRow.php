<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventRegistrationImportRow extends Model
{
    protected $fillable = [
        'import_id', 'row_number', 'registration_key', 'status', 'submission_id',
        'error_code', 'error_message', 'row_fingerprint',
    ];

    protected $hidden = ['row_fingerprint'];

    public function import() { return $this->belongsTo(EventRegistrationImport::class, 'import_id'); }
    public function submission() { return $this->belongsTo(EventRegistrationSubmission::class, 'submission_id'); }
}
