<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventRegistrationInvoice extends Model
{
    protected $fillable = [
        'public_id', 'invoice_number', 'submission_id', 'form_id', 'event_id', 'organizer_id',
        'booking_id', 'reservation_token', 'status', 'currency', 'currency_symbol',
        'subtotal', 'tax', 'commission', 'total', 'line_items', 'purchase_order_number',
        'payment_reference', 'payment_note', 'invoice_path', 'issued_at', 'due_at',
        'paid_at', 'cancelled_at', 'marked_paid_by_organizer_id',
    ];

    protected $hidden = ['purchase_order_number', 'payment_reference', 'payment_note'];

    protected $casts = [
        'line_items' => 'encrypted:array',
        'purchase_order_number' => 'encrypted',
        'payment_reference' => 'encrypted',
        'payment_note' => 'encrypted',
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'commission' => 'decimal:2',
        'total' => 'decimal:2',
        'issued_at' => 'datetime',
        'due_at' => 'datetime',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function submission()
    {
        return $this->belongsTo(EventRegistrationSubmission::class, 'submission_id');
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function booking()
    {
        return $this->belongsTo(\App\Models\Event\Booking::class);
    }
}
