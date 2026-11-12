<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Event\EventContent;
use App\Services\EventShareCardService;
use App\Services\EventPublicVisibility;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EventShareCardController extends Controller
{
    public function show(
        Request $request,
        Event $event,
        EventShareCardService $cards,
        EventPublicVisibility $visibility
    ): Response
    {
        abort_unless($visibility->allows($event), 404);

        $content = EventContent::query()
            ->join('languages', 'languages.id', '=', 'event_contents.language_id')
            ->where('event_contents.event_id', $event->id)
            ->orderByDesc('languages.is_default')
            ->select('event_contents.*')
            ->firstOrFail();

        $etag = '"' . sha1(implode('|', [
            $event->id,
            optional($event->updated_at)->getTimestamp(),
            optional($content->updated_at)->getTimestamp(),
            $event->thumbnail,
        ])) . '"';
        if (hash_equals($etag, (string) $request->header('If-None-Match'))) {
            return response('', 304, ['ETag' => $etag]);
        }

        return response($cards->render($event, $content), 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'inline; filename="tica-event-' . $event->id . '.png"',
            'Cache-Control' => 'public, max-age=86400, stale-while-revalidate=604800',
            'ETag' => $etag,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
