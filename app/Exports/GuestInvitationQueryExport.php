<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class GuestInvitationQueryExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(private Builder $invitationQuery) {}

    public function query(): Builder { return $this->invitationQuery; }

    public function headings(): array
    {
        return [
            'Invitation Reference', 'Event', 'Name', 'Email', 'Party Size', 'RSVP Status',
            'Delivery Status', 'Invited At', 'Responded At', 'Checked In At',
            'RSVP Consent', 'Marketing Consent',
        ];
    }

    public function map($invitation): array
    {
        return [
            $this->safeCell($invitation->public_id),
            $this->safeCell($invitation->event?->information?->title ?: 'Event #'.$invitation->event_id),
            $this->safeCell($invitation->name),
            $this->safeCell($invitation->email),
            (int) $invitation->party_size,
            $this->safeCell($invitation->status),
            $this->safeCell($invitation->delivery_status),
            optional($invitation->invited_at)->toIso8601String(),
            optional($invitation->responded_at)->toIso8601String(),
            optional($invitation->checked_in_at)->toIso8601String(),
            $invitation->rsvp_consent_at ? 'Yes' : 'No',
            $invitation->marketing_consent_at ? 'Yes' : 'No',
        ];
    }

    private function safeCell(mixed $value): mixed
    {
        if (!is_string($value)) return $value;
        $value = str_replace(["\r\n", "\r", "\n"], ' ', $value);
        return preg_match('/^[=+\-@]/u', $value) === 1 ? "'".$value : $value;
    }
}
