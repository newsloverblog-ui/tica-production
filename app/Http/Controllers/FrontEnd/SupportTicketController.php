<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\SupportTicket;
use App\Models\SupportTicketStatus;
use App\Services\SupportTicketAttachmentStorage;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Mews\Purifier\Facades\Purifier;

class SupportTicketController extends Controller
{
  public function index()
  {
    $collection = SupportTicket::where('user_id', Auth::guard('customer')->user()->id)->where('user_type', 'customer')->orderBy('id', 'desc')->get();
    return view('frontend.customer.dashboard.support_ticket.index', compact('collection'));
  }
  //create
  public function create()
  {
    return view('frontend.customer.dashboard.support_ticket.create');
  }
  //store
  public function store(Request $request)
  {
    $validated = $request->validate([
      'subject' => ['required', 'string', 'max:255'],
      'email' => ['required', 'email', 'max:255'],
      'description' => ['required', 'string', 'max:10000'],
      'attachment' => ['nullable', 'file', 'mimes:zip', 'mimetypes:application/zip,application/x-zip-compressed', 'max:5000']
    ]);

    $in = [
      'subject' => $validated['subject'],
      'email' => $validated['email'],
      'description' => Purifier::clean($validated['description'], 'youtube'),
      'user_id' => Auth::guard('customer')->id(),
      'user_type' => 'customer',
      'status' => 1,
      'last_message' => Carbon::now(),
    ];

    $file = $request->file('attachment');
    if ($file) {
      $in['attachment'] = app(SupportTicketAttachmentStorage::class)->store($file, 'attachments');
    }
    $save = SupportTicket::create($in);
    app(\App\Services\SupportTicketMailService::class)->created($save);
    Session::flash('success', 'Ticket has been submitted successfully..!');
    return back();
  }
  //message
  public function message($id)
  {
    $bex = SupportTicketStatus::first();

    if ($bex->support_ticket_status == 0) {
      return back();
    }
    $ticket = SupportTicket::query()
      ->whereKey($id)
      ->where('user_id', Auth::guard('customer')->id())
      ->where('user_type', 'customer')
      ->firstOrFail();

    return view('frontend.customer.dashboard.support_ticket.messages', compact('ticket'));
  }
  //reply
  public function reply(Request $request, $id)
  {
    $ticket = SupportTicket::query()
      ->whereKey($id)
      ->where('user_id', Auth::guard('customer')->id())
      ->where('user_type', 'customer')
      ->firstOrFail();

    $rules = [
      'reply' => ['required', 'string', 'max:5000'],
      'file' => ['nullable', 'file', 'mimes:zip', 'mimetypes:application/zip,application/x-zip-compressed', 'max:5000'],
    ];

    $messages = [
      'file.max' => ' zip file may not be greater than 5 MB',
    ];

    $request->validate($rules, $messages);
    $input = [
      'reply' => Purifier::clean($request->reply, 'youtube'),
      'type' => 1,
      'user_id' => Auth::guard('customer')->id(),
      'support_ticket_id' => $ticket->id,
    ];

    if ($request->hasFile('file')) {
      $input['file'] = app(SupportTicketAttachmentStorage::class)->store($request->file('file'), 'replies');
    }

    $data = new Conversation();
    $data->create($input);

    $ticket->update([
      'last_message' => Carbon::now()
    ]);
    // Customer replies are visible to support; only staff replies notify the requester.

    Session::flash('success', 'Message Sent Successfully');
    return back();
  }

  public function downloadAttachment($id, SupportTicketAttachmentStorage $storage)
  {
    $ticket = SupportTicket::query()
      ->whereKey($id)
      ->where('user_id', Auth::guard('customer')->id())
      ->where('user_type', 'customer')
      ->whereNotNull('attachment')
      ->firstOrFail();

    if (!$storage->isManagedPath($ticket->attachment)) {
      $legacyPath = 'assets/admin/img/support-ticket/' . $ticket->attachment;
      $migrated = $storage->migrateLegacy(
        $ticket->attachment,
        $legacyPath,
        'attachments'
      );
      abort_unless($migrated, 404);
      $ticket->forceFill(['attachment' => $migrated])->save();
      $storage->deleteLegacy($legacyPath);
    }

    return $storage->download($ticket->attachment, 'support-ticket-' . $ticket->id . '.zip');
  }

  public function downloadReplyAttachment($conversation, SupportTicketAttachmentStorage $storage)
  {
    $reply = Conversation::query()
      ->whereKey($conversation)
      ->whereNotNull('file')
      ->whereHas('ticket', function ($query) {
        $query->where('user_id', Auth::guard('customer')->id())
          ->where('user_type', 'customer');
      })
      ->firstOrFail();

    if (!$storage->isManagedPath($reply->file)) {
      $legacyPath = 'assets/admin/img/support-ticket/' . $reply->file;
      $migrated = $storage->migrateLegacy(
        $reply->file,
        $legacyPath,
        'replies'
      );
      abort_unless($migrated, 404);
      $reply->forceFill(['file' => $migrated])->save();
      $storage->deleteLegacy($legacyPath);
    }

    return $storage->download($reply->file, 'support-reply-' . $reply->id . '.zip');
  }
}
