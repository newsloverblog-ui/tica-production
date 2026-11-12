<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventRegistrationQuestion extends Model
{
    protected $fillable = [
        'public_id', 'form_id', 'ticket_id', 'question_key', 'label', 'help_text', 'type', 'scope',
        'is_required', 'options', 'condition', 'settings', 'position',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'options' => 'array',
        'condition' => 'array',
        'settings' => 'array',
        'position' => 'integer',
    ];

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function form()
    {
        return $this->belongsTo(EventRegistrationForm::class, 'form_id');
    }

    public function ticket()
    {
        return $this->belongsTo(\App\Models\Event\Ticket::class, 'ticket_id');
    }
}
