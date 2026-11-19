<?php

namespace App\Http\Controllers\BackEnd;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\SupportTicket;
use App\Models\SupportTicketStatus;
use App\Services\SupportTicketAttachmentStorage;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Mews\Purifier\Facades\Purifier;

class SupportTicketController extends Controller
{
  //index
  public function index(Request $request)
  {
    $role_id = Auth::guard('admin')->user()->role_id;

    $status = $ticket_id = null;
    if ($request->filled('status')) {
      $status = $request['status'];
    }
    if ($request->filled('ticket_id')) {
      $ticket_id = $request['ticket_id'];
    }
    if (empty($role_id)) {
      $collection = SupportTicket::with('customer')->when($status, function ($query, $status) {
        return $query->where('status',  $status);
      })
        ->when($ticket_id, function ($query, $ticket_id) {
          return $query->where('id', 'like', '%' . $ticket_id . '%');
        })
        ->orderByDesc('id')
        ->paginate(10);
    } else {
      $collection = SupportTicket::with('customer')->when($status, function ($query, $status) {
        return $query->where('status',  $status);
      })
        ->when($ticket_id, function ($query, $ticket_id) {
          return $query->where('id', 'like', '%' . $ticket_id . '%');
        })
        ->where('admin_id', Auth::guard('admin')->user()->id)
        ->orderByDesc('id')
        ->paginate(10);
    }

    return view('backend.support_ticket.index', compact('collection'));
  }
  //message
  public function message($id)
  {
    $ticket = SupportTicket::where('id', $id)->firstOrFail();
    return view('backend.support_ticket.messages', compact('ticket'));
  }
  public function zip_file_upload(Request $request)
  {
    $rules = [
      'file' => ['required', 'file', 'mimes:zip', 'mimetypes:application/zip,application/x-zip-compressed', 'max:5000'],
    ];

    $messages = [
      'file.max' => ' zip file may not be greater than 5 MB',
    ];

    $validator = Validator::make($request->all(), $rules, $messages);
    if ($validator->fails()) {
      return response()->json(array('errors' => $validator->getMessageBag()->toArray()));
    }

    return response()->json(['data' => 1]);
  }
  public function ticketreply(Request $request, $id)
  {
    $rules = [
      'reply' => ['required', 'string', 'max:5000'],
      'file' => ['nullable', 'file', 'mimes:zip', 'mimetypes:application/zip,application/x-zip-compressed', 'max:5000'],
    ];

    $messages = [
      'file.max' => ' zip file may not be greater than 5 MB',
    ];

    $request->validate($rules, $messages);
    $ticket = SupportTicket::query()->findOrFail($id);
    $input = [
      'reply' => Purifier::clean((string) $request->reply, 'youtube'),
      'user_id' => Auth::guard('admin')->id(),
      'support_ticket_id' => $ticket->id,
      'type' => 2,
    ];

    if ($request->hasFile('file')) {
      $input['file'] = app(SupportTicketAttachmentStorage::class)
        ->store($request->file('file'), 'replies');
    }

    $data = new Conversation();
    $data->create($input);

    SupportTicket::where('id', $id)->update([
      'last_message' => Carbon::now(),
      'status' => 2,
    ]);
    \App\Support\AdminQueues::forget();
    $ticket = $ticket->fresh();
    if ($ticket && $ticket->user_type === 'organizer') {
      \App\Support\OrganizerInbox::send((int) $ticket->user_id, 'support.reply', __('TICA replied to your request'), (string) $ticket->subject, route('organizer.support_tickets.message', $ticket->id));
    }
    if ($ticket) {
      app(\App\Services\SupportTicketMailService::class)->replied($ticket->fresh(), (string) $input['reply']);
    }

    Session::flash('success', 'Message Sent Successfully');
    return back();
  }
  //ticket_closed
  public function ticket_closed($id)
  {
    SupportTicket::where('id', $id)->update([
      'status' => 3,
    ]);
    if ($ticket = SupportTicket::find($id)) {
      app(\App\Services\SupportTicketMailService::class)->closed($ticket);
    }
    Session::flash('success', 'Support Ticket close successfully!');
    return back();
  }

  public function unassign_stuff($id)
  {
    SupportTicket::where('id', $id)->update([
      'admin_id' => null,
    ]);

    Session::flash('success', 'Unassign stuff successfully!');
    return back();
  }

  //setting
  public function setting()
  {
    $content = SupportTicketStatus::where('id', 1)->first();
    return view('backend.support_ticket.setting', compact('content'));
  }
  //update_setting
  public function update_setting(Request $request)
  {
    $status = SupportTicketStatus::where('id', 1)->first();
    $status->support_ticket_status = $request->support_ticket_status;
    $status->save();
    Session::flash('success', 'Updated Successfully');
    return response()->json(['status' => 'success'], 200);
  }
  //assign_stuff.supoort.ticket
  public function assign_stuff(Request $request, $id)
  {
    $support_ticket = SupportTicket::where('id', $id)->first();
    $support_ticket->admin_id = $request->admin_id;
    $support_ticket->save();
    Session::flash('success', 'Add Stuff to this support ticket successfully..!');
    return back();
  }


  //delete
  public function delete($id)
  {
    //delete all support ticket
    $support_ticket = SupportTicket::find($id);
    if ($support_ticket) {
      //delete conversation 
      $messages = $support_ticket->messages()->get();
      $storage = app(SupportTicketAttachmentStorage::class);
      foreach ($messages as $message) {
        $storage->deleteStoredOrLegacy(
          $message->file,
          'assets/admin/img/support-ticket/' . $message->file
        );
        $message->delete();
      }
      $legacyDirectory = $support_ticket->user_type === 'organizer'
        ? 'assets/admin/img/support-ticket/attachment/'
        : 'assets/admin/img/support-ticket/';
      $storage->deleteStoredOrLegacy(
        $support_ticket->attachment,
        $legacyDirectory . $support_ticket->attachment
      );
      $support_ticket->delete();
    }
    Session::flash('success', 'Deleted Successfully');
    return back();
  }

  public function bulk_delete(Request $request)
  {
    $validated = $request->validate([
      'ids' => ['required', 'array', 'max:100'],
      'ids.*' => ['required', 'integer', 'distinct'],
    ]);

    $ids = $validated['ids'];
    foreach ($ids as $id) {
      $support_ticket = SupportTicket::find($id);
      if ($support_ticket) {
        //delete conversation 
        $messages = $support_ticket->messages()->get();
        $storage = app(SupportTicketAttachmentStorage::class);
        foreach ($messages as $message) {
          $storage->deleteStoredOrLegacy(
            $message->file,
            'assets/admin/img/support-ticket/' . $message->file
          );
          $message->delete();
        }
        $legacyDirectory = $support_ticket->user_type === 'organizer'
          ? 'assets/admin/img/support-ticket/attachment/'
          : 'assets/admin/img/support-ticket/';
        $storage->deleteStoredOrLegacy(
          $support_ticket->attachment,
          $legacyDirectory . $support_ticket->attachment
        );
        $support_ticket->delete();
      }
    }
    Session::flash('success', 'Deleted Successfully');
    return Response::json(['status' => 'success'], 200);
  }

  public function downloadAttachment($id, SupportTicketAttachmentStorage $storage)
  {
    $ticket = SupportTicket::query()
      ->whereKey($id)
      ->whereNotNull('attachment')
      ->firstOrFail();

    if (!$storage->isManagedPath($ticket->attachment)) {
      $legacyDirectory = $ticket->user_type === 'organizer'
        ? 'assets/admin/img/support-ticket/attachment/'
        : 'assets/admin/img/support-ticket/';
      $legacyPath = $legacyDirectory . $ticket->attachment;
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
