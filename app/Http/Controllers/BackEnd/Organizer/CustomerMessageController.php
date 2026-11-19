<?php

namespace App\Http\Controllers\BackEnd\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\SupportTicket;
use App\Services\SupportTicketAttachmentStorage;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Mews\Purifier\Facades\Purifier;

class CustomerMessageController extends Controller
{
    public function index(Request $request)
    {
        $query = SupportTicket::query()
            ->with(['customer', 'event'])
            ->where('recipient_type', 'organizer')
            ->where('recipient_organizer_id', Auth::guard('organizer')->id())
            ->orderByDesc('last_message');

        if ($request->filled('q')) {
            $term = trim((string) $request->q);
            $query->where(function ($builder) use ($term): void {
                $builder->where('subject', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%");
            });
        }

        return view('organizer.customer_messages.index', ['collection' => $query->paginate(15)]);
    }

    public function show(SupportTicket $supportTicket)
    {
        $ticket = $this->owned($supportTicket)->load(['customer', 'event', 'messages']);
        return view('organizer.customer_messages.show', compact('ticket'));
    }

    public function reply(Request $request, SupportTicket $supportTicket)
    {
        $ticket = $this->owned($supportTicket);
        $validated = $request->validate([
            'reply' => ['required', 'string', 'max:5000'],
            'file' => ['nullable', 'file', 'mimes:zip', 'mimetypes:application/zip,application/x-zip-compressed', 'max:5000'],
        ]);

        $message = [
            'reply' => Purifier::clean($validated['reply'], 'youtube'),
            'type' => 3,
            'user_id' => Auth::guard('organizer')->id(),
            'support_ticket_id' => $ticket->id,
        ];
        if ($request->hasFile('file')) {
            $message['file'] = app(SupportTicketAttachmentStorage::class)->store($request->file('file'), 'replies');
        }
        Conversation::create($message);
        $ticket->update(['last_message' => Carbon::now(), 'status' => 2]);
        app(\App\Services\SupportTicketMailService::class)->replied($ticket->fresh(), $validated['reply']);

        return back()->with('success', 'Reply sent.');
    }

    public function close(SupportTicket $supportTicket)
    {
        $ticket = $this->owned($supportTicket);
        $ticket->update(['status' => 3, 'last_message' => Carbon::now()]);
        app(\App\Services\SupportTicketMailService::class)->closed($ticket->fresh());
        return back()->with('success', 'Conversation closed.');
    }

    public function downloadAttachment(SupportTicket $supportTicket, SupportTicketAttachmentStorage $storage)
    {
        $ticket = $this->owned($supportTicket);
        abort_unless($ticket->attachment, 404);
        if (!$storage->isManagedPath($ticket->attachment)) {
            $legacyPath = 'assets/admin/img/support-ticket/' . $ticket->attachment;
            $migrated = $storage->migrateLegacy($ticket->attachment, $legacyPath, 'attachments');
            abort_unless($migrated, 404);
            $ticket->forceFill(['attachment' => $migrated])->save();
            $storage->deleteLegacy($legacyPath);
        }
        return $storage->download($ticket->attachment, 'customer-message-' . $ticket->id . '.zip');
    }

    public function downloadReplyAttachment(Conversation $conversation, SupportTicketAttachmentStorage $storage)
    {
        $reply = $conversation->load('ticket');
        $this->owned($reply->ticket);
        abort_unless($reply->file, 404);
        if (!$storage->isManagedPath($reply->file)) {
            $legacyPath = 'assets/admin/img/support-ticket/' . $reply->file;
            $migrated = $storage->migrateLegacy($reply->file, $legacyPath, 'replies');
            abort_unless($migrated, 404);
            $reply->forceFill(['file' => $migrated])->save();
            $storage->deleteLegacy($legacyPath);
        }
        return $storage->download($reply->file, 'customer-message-reply-' . $reply->id . '.zip');
    }

    private function owned(SupportTicket $ticket): SupportTicket
    {
        abort_unless(
            $ticket->recipient_type === 'organizer'
            && (int) $ticket->recipient_organizer_id === (int) Auth::guard('organizer')->id(),
            404
        );
        return $ticket;
    }
}
