<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventAgendaItem;
use App\Models\EventProgrammePerson;
use App\Support\PublicImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class EventProgrammeService
{
    /**
     * Replace the editable programme while retaining existing portraits when
     * the organiser has not selected a new file.
     */
    public function sync(Event $event, Request $request, array $validated): void
    {
        if (!Schema::hasTable('event_programme_people') || !Schema::hasTable('event_agenda_items')) {
            return;
        }
        $people = $request->boolean('programme_enabled') ? ($validated['programme_people'] ?? []) : [];
        $agenda = $request->boolean('programme_enabled') ? ($validated['agenda_items'] ?? []) : [];
        $existing = EventProgrammePerson::query()->where('event_id', $event->id)->get()->keyBy('id');
        $kept = [];
        $saved = [];

        foreach ($people as $index => $person) {
            $id = (int) ($person['id'] ?? 0);
            $model = $id ? $existing->get($id) : null;
            if ($id && !$model) {
                throw ValidationException::withMessages(["programme_people.$index.id" => 'This programme guest no longer belongs to the event. Reload and try again.']);
            }
            $model ??= new EventProgrammePerson(['event_id' => $event->id]);
            $image = $model->image;
            if ($request->boolean("programme_people.$index.remove_image")) {
                $image = null;
            }
            if ($request->hasFile("programme_people.$index.image")) {
                $image = PublicImage::store(
                    $request->file("programme_people.$index.image"),
                    'assets/admin/img/event/programme',
                    900
                );
            }
            $model->fill([
                'event_id' => $event->id,
                'kind' => $validated['programme_type'],
                'name' => trim($person['name']),
                'role' => trim((string) ($person['role'] ?? '')) ?: null,
                'topic' => trim((string) ($person['topic'] ?? '')) ?: null,
                'bio' => trim((string) ($person['bio'] ?? '')) ?: null,
                'image' => $image,
                'sort_order' => $index,
                'is_visible' => true,
            ])->save();
            $kept[] = $model->id;
            $saved[$index] = $model;
        }

        EventAgendaItem::query()->where('event_id', $event->id)->delete();
        EventProgrammePerson::query()->where('event_id', $event->id)
            ->when($kept !== [], fn ($query) => $query->whereNotIn('id', $kept))
            ->delete();

        foreach ($agenda as $index => $item) {
            $personIndex = isset($item['person_index']) && $item['person_index'] !== '' ? (int) $item['person_index'] : null;
            EventAgendaItem::create([
                'event_id' => $event->id,
                'programme_person_id' => $personIndex !== null ? ($saved[$personIndex]->id ?? null) : null,
                'title' => trim($item['title']),
                'location' => trim((string) ($item['location'] ?? '')) ?: null,
                'description' => trim((string) ($item['description'] ?? '')) ?: null,
                'starts_at' => $item['starts_at'] ?? null,
                'ends_at' => $item['ends_at'] ?? null,
                'sort_order' => $index,
                'is_visible' => true,
            ]);
        }
    }
}
