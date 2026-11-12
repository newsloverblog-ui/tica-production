<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventGuestImportRow extends Model
{
    protected $fillable = [
        'import_id', 'row_number', 'email_fingerprint', 'status', 'invitation_id',
        'error_code', 'error_message', 'row_fingerprint',
    ];

    protected $hidden = ['email_fingerprint', 'row_fingerprint'];

    public function import() { return $this->belongsTo(EventGuestImport::class, 'import_id'); }
    public function invitation() { return $this->belongsTo(EventGuestInvitation::class, 'invitation_id'); }
}
