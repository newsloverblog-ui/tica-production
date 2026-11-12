<?php

namespace App\Services;

use App\Models\EventRegistrationBadgeSetting;
use App\Models\EventRegistrationForm;
use App\Models\EventRegistrationSubmission;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use PDF;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class EventRegistrationBadgeService
{
    public function save(EventRegistrationForm $form, int $organizerId, array $data): EventRegistrationBadgeSetting
    {
        $this->authorize($form, $organizerId);
        $form->loadMissing('questions');
        $attendeeKeys = $form->questions->where('scope', 'attendee')->pluck('question_key');
        foreach (['company_question_key', 'role_question_key'] as $field) {
            $key = trim((string) ($data[$field] ?? ''));
            if ($key !== '' && !$attendeeKeys->contains($key)) {
                throw ValidationException::withMessages([$field => 'Choose an attendee field from this registration form.']);
            }
        }
        $accent = strtoupper(trim((string) ($data['accent_color'] ?? '#FE9B59')));
        if (!preg_match('/^#[0-9A-F]{6}$/', $accent)) {
            throw ValidationException::withMessages(['accent_color' => 'Enter a six-digit hex colour such as #FE9B59.']);
        }
        $setting = EventRegistrationBadgeSetting::updateOrCreate(['form_id' => $form->id], [
            'event_id' => $form->event_id, 'organizer_id' => $organizerId,
            'badge_title' => trim((string) ($data['badge_title'] ?? '')) ?: null,
            'company_question_key' => trim((string) ($data['company_question_key'] ?? '')) ?: null,
            'role_question_key' => trim((string) ($data['role_question_key'] ?? '')) ?: null,
            'accent_color' => $accent, 'paper_size' => 'a6',
        ]);
        AuditLogger::record('event.registration_badge.settings_saved', $setting, [], [
            'form_id' => $form->id, 'company_question_key' => $setting->company_question_key,
            'role_question_key' => $setting->role_question_key, 'accent_color' => $setting->accent_color,
        ], ['event_id' => $form->event_id]);
        return $setting;
    }

    public function badgeData(EventRegistrationForm $form, int $organizerId): Collection
    {
        $this->authorize($form, $organizerId);
        $setting = $form->badgeSetting()->firstOrCreate([
            'form_id' => $form->id,
        ], [
            'event_id' => $form->event_id, 'organizer_id' => $organizerId,
            'accent_color' => '#FE9B59', 'paper_size' => 'a6',
        ]);
        $submissions = EventRegistrationSubmission::with([
            'attendees.answers', 'attendees.ticket', 'attendees.bookingTicket', 'event.information',
        ])->where('form_id', $form->id)->where('organizer_id', $organizerId)
            ->where('status', 'ticketed')->orderBy('id')->limit(1001)->get();
        $attendees = $submissions->flatMap->attendees;
        if ($attendees->count() > 1000) {
            throw new DomainException('Badge batches are limited to 1,000 attendees. Export this event in smaller operational batches.');
        }
        return $attendees->filter(fn ($attendee) => $attendee->bookingTicket && in_array($attendee->bookingTicket->status, ['issued', 'checked_in'], true))
            ->map(function ($attendee) use ($setting): array {
                $answers = $attendee->answers->keyBy('question_key');
                $value = static function (?string $key) use ($answers): ?string {
                    if (!$key || !$answers->has($key)) return null;
                    $raw = data_get($answers->get($key)->value, 'value');
                    return is_array($raw) ? implode(', ', array_map('strval', $raw)) : trim((string) $raw);
                };
                $ticket = $attendee->bookingTicket;
                $svg = QrCode::format('svg')->size(260)->margin(1)->generate(signedTicketUnitPayload($ticket->public_id, $ticket->event_id));
                return [
                    'name' => $attendee->name,
                    'company' => $value($setting->company_question_key),
                    'role' => $value($setting->role_question_key),
                    'registration_type' => $attendee->ticket
                        ? $attendee->ticket->title.($attendee->variation_name ? ' — '.$attendee->variation_name : '')
                        : null,
                    'reference' => $ticket->public_id,
                    'qr_data_uri' => 'data:image/svg+xml;base64,'.base64_encode((string) $svg),
                ];
            })->values();
    }

    public function download(EventRegistrationForm $form, int $organizerId)
    {
        $badges = $this->badgeData($form, $organizerId);
        if ($badges->isEmpty()) throw new DomainException('No ticketed attendees are available for badge printing.');
        $form->loadMissing('event.information', 'badgeSetting');
        AuditLogger::record('event.registration_badge.downloaded', $form, [], [
            'badge_count' => $badges->count(),
        ], ['event_id' => $form->event_id]);
        $response = PDF::loadView('frontend.event.registration-badges', [
            'badges' => $badges, 'setting' => $form->badgeSetting,
            'eventTitle' => $form->event?->information?->title ?: $form->name,
        ])->setPaper('a6', 'portrait')->download('event-badges-'.$form->public_id.'.pdf');
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }

    private function authorize(EventRegistrationForm $form, int $organizerId): void
    {
        if ((int) $form->organizer_id !== $organizerId) {
            throw new AuthorizationException('You cannot manage another organizer’s badge design.');
        }
    }
}
