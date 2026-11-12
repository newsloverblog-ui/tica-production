<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventRegistrationFormTemplate extends Model
{
    protected $fillable = [
        'public_id', 'organizer_id', 'name', 'description', 'definition', 'version',
    ];

    protected $casts = ['definition' => 'array', 'version' => 'integer'];

    public function getRouteKeyName(): string { return 'public_id'; }
    public function organizer() { return $this->belongsTo(Organizer::class); }
}
