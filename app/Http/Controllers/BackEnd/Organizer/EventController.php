<?php

namespace App\Http\Controllers\BackEnd\Organizer;

use App\Http\Controllers\Controller;
use App\Http\Helpers\UploadFile;
use Illuminate\Http\Request;
use App\Http\Requests\Event\StoreRequest;
use App\Http\Requests\Event\UpdateRequest;
use App\Models\City;
use App\Models\Country;
use App\Models\Language;
use App\Models\Event;
use App\Models\Event\EventImage;
use App\Models\Event\EventContent;
use App\Models\Event\EventDates;
use App\Models\Event\Ticket;
use App\Models\State;
use App\Services\OperationalArchiveService;
use App\Services\OperationalStatusService;
use App\Services\EventLifecycleNotificationService;
use App\Services\AuditLogger;
use App\Rules\ImageMimeTypeRule;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Mews\Purifier\Facades\Purifier;

class EventController extends Controller
{
  public function deleteDate($id)
  {
    DB::transaction(function () use ($id) {
      $date = EventDates::query()
        ->whereKey($id)
        ->whereHas('event', function ($query) {
          $query->where('organizer_id', Auth::guard('organizer')->id());
        })
        ->lockForUpdate()
        ->firstOrFail();

      AuditLogger::record('event.date_deleted', $date, [
        'event_id' => $date->event_id,
        'start_date_time' => $date->start_date_time,
        'end_date_time' => $date->end_date_time,
      ]);
      $date->delete();
    }, 3);

    return 'success';
  }

  //index
  public function index(Request $request)
  {
    $information['langs'] = Language::all();

    $language = Language::where('code', $request->language)->firstOrFail();
    $information['language'] = $language;

    $event_type = request()->input('event_type');
    $title = null;
    if (request()->filled('title')) {
      $title = request()->input('title');
    }


    $events = Event::join('event_contents', 'event_contents.event_id', '=', 'events.id')
      ->join('event_categories', 'event_categories.id', '=', 'event_contents.event_category_id')
      ->where('event_contents.language_id', '=', $language->id)
      ->when($title, function ($query) use ($title) {
        return $query->where('event_contents.title', 'like', '%' . $title . '%');
      })
      ->where('events.organizer_id', '=', Auth::guard('organizer')->user()->id)
      ->when($event_type, function ($query, $event_type) {
        return $query->where('events.event_type', $event_type);
      })
      ->select('events.*', 'event_contents.id as eventInfoId', 'event_contents.title', 'event_contents.slug', 'event_categories.name as category')
      ->orderByDesc('events.id')
      ->paginate(10);

    $information['events'] = $events;
    return view('organizer.event.index', $information);
  }
  //choose_event_type
  // Events are created with the TICA design's step-by-step wizard.
  public function choose_event_type()
  {
    return redirect('/create');
  }
  //online_event
  public function add_event()
  {
    if (!request()->boolean('legacy')) {
      return redirect('/create');
    }
    // get all the languages from db
    $languages = Language::get();
    $countries = Country::get();
    $information['getCurrencyInfo']  = $this->getCurrencyInfo();
    $information['languages'] = $languages;
    $information['countries'] = $countries;
    return view('organizer.event.create', $information);
  }
  //city_state
  public function city_state($id)
  {
    $city = City::where('country_id', $id)->orderBy('name', 'asc')->get();
    $state = State::where('country_id', $id)->orderBy('name', 'asc')->get();

    $result = [];
    $result['city'] = $city;
    $result['state'] = $state;
    return $result;
  }

  public function gallerystore(Request $request)
  {
    $event = $request->filled('event_id') ? Event::query()
      ->whereKey($request->integer('event_id'))
      ->where('organizer_id', Auth::guard('organizer')->id())
      ->firstOrFail() : null;

    $rules = [
      'event_id' => ['nullable', 'integer'],
      'file' => ['required', 'file', 'max:5120', new ImageMimeTypeRule()],
    ];
    $validator = Validator::make($request->all(), $rules);
    
    if ($validator->fails()) {
        $validator->getMessageBag()->add('error', 'true');
        return response()->json($validator->errors());
    }
    
    $img = $request->file('file');
    $extensions = [
      'image/jpeg' => 'jpg',
      'image/png' => 'png',
      'image/webp' => 'webp',
      'image/gif' => 'gif',
    ];
    $extension = $extensions[$img->getMimeType()] ?? null;
    abort_if($extension === null, 422, 'Unsupported image type.');
    $filename = Str::uuid() . '.' . $extension;
    UploadFile::storeAs(public_path('assets/admin/img/event-gallery/'), $img, $filename);
    
    $pi = new EventImage;
    $pi->event_id = $event?->id;
    $pi->organizer_id = Auth::guard('organizer')->id();
    $pi->image = $filename;
    $pi->save();
    
    return response()->json(['status' => 'success', 'file_id' => $pi->id]);

  }
  public function imagermv(Request $request)
  {
    $request->validate(['fileid' => ['required', 'integer']]);
    $pi = EventImage::query()
      ->whereKey($request->integer('fileid'))
      ->where(function ($query) {
        $query->where('organizer_id', Auth::guard('organizer')->id())
          ->orWhereHas('event', function ($eventQuery) {
            $eventQuery->where('organizer_id', Auth::guard('organizer')->id());
          });
      })
      ->firstOrFail();
    @unlink(public_path('assets/admin/img/event-gallery/') . $pi->image);
    $pi->delete();
    return $pi->id;
  }

  public function store(StoreRequest $request)
  {
    $event = DB::transaction(function () use ($request) {

      //calculate duration 
      if ($request->date_type == 'single') {
        $start = Carbon::parse($request->start_date . $request->start_time);
        $end =  Carbon::parse($request->end_date . $request->end_time);
        $diffent = DurationCalulate($start, $end);
      }
      //calculate duration end

      $in = $request->all();
      $in['is_featured'] = 'no';
      $in['duration'] = $request->date_type == 'single' ? $diffent : '';

      $img = $request->file('thumbnail');

      $in['organizer_id'] = Auth::guard('organizer')->user()->id;
      if ($request->hasFile('thumbnail')) {
        $filename = $this->imageName($img);
        $directory = public_path('assets/admin/img/event/thumbnail/');
        UploadFile::storeAs($directory, $request->file('thumbnail'), $filename);
        $in['thumbnail'] = $filename;
      }
      $in['f_price'] = $request->price;
      $in['end_date_time'] = Carbon::parse($request->end_date . ' ' . $request->end_time);
      $event = Event::create($in);

      if ($request->date_type == 'multiple') {
        $i = 1;
        foreach ($request->m_start_date as $key => $date) {
          $start = Carbon::parse($date . $request->m_start_time[$key]);
          $end =  Carbon::parse($request->m_end_date[$key] . $request->m_end_time[$key]);
          $diffent = DurationCalulate($start, $end);

          EventDates::create([
            'event_id' => $event->id,
            'start_date' => $date,
            'start_time' => $request->m_start_time[$key],
            'end_date' => $request->m_end_date[$key],
            'end_time' => $request->m_end_time[$key],
            'duration' => $diffent,
            'start_date_time' => $start,
            'end_date_time' => $end,
          ]);
          if ($i == 1) {
            $event->update([
              'duration' => $diffent
            ]);
          }
          $i++;
        }

        //update event date time
        $event_date = EventDates::where('event_id', $event->id)->orderBy('end_date_time', 'desc')->first();

        $event->end_date_time = $event_date->end_date_time;
        $event->save();
      }




      $in['event_id'] = $event->id;
      if ($request->event_type == 'online') {
        if (!$request->pricing_type) {
          $in['pricing_type'] = 'normal';
        }
        $in['early_bird_discount'] = $request->early_bird_discount_type;
        $in['early_bird_discount_type'] = $request->discount_type;
        $ticket = Ticket::create($in);
      }

      $slders = $request->slider_images;

      foreach ($slders as $key => $id) {
        $event_image = EventImage::query()->whereKey($id)->where('organizer_id', Auth::guard('organizer')->id())->first();
        if ($event_image) {
          $event_image->event_id = $event->id;
          $event_image->save();
        }
      }
      $languages = Language::all();

      foreach ($languages as $language) {
        $event_content = new EventContent();
        $event_content->language_id = $language->id;
        $event_content->event_category_id = $request[$language->code . '_category_id'];
        $event_content->event_id = $event->id;
        $event_content->title = $request[$language->code . '_title'];
        if ($request->event_type == 'venue') {
          $event_content->address = $request[$language->code . '_address'];
          $event_content->country = $request[$language->code . '_country'];
          $event_content->state = $request[$language->code . '_state'];
          $event_content->city = $request[$language->code . '_city'];
          $event_content->zip_code = $request[$language->code . '_zip_code'];
        }
        $event_content->slug = createSlug($request[$language->code . '_title']);
        $event_content->description = Purifier::clean($request[$language->code . '_description'], 'youtube');
        $event_content->refund_policy = $request[$language->code . '_refund_policy'];
        $event_content->meta_keywords = $request[$language->code . '_meta_keywords'];
        $event_content->meta_description = $request[$language->code . '_meta_description'];
        $event_content->save();
      }
      return $event;
    });

    Session::flash('success', 'Added Successfully');
    $languageCode = Language::where('is_default', 1)->value('code') ?? Language::value('code');
    $ticketRedirect = $event->event_type === 'online'
      ? route('organizer.event.ticket', ['language' => $languageCode, 'event_id' => $event->id, 'event_type' => $event->event_type])
      : route('organizer.event.add.ticket', ['language' => $languageCode, 'event_id' => $event->id, 'event_type' => $event->event_type]);
    $redirect = match ($request->input('next_step', 'tickets')) {
      'registration' => route('organizer.event.registrations.index', ['event_id' => $event->id]),
      'guests' => route('organizer.event.guest-list.index', ['event_id' => $event->id]),
      'checkin' => route('organizer.pwa', ['event_id' => $event->id]),
      'events' => route('organizer.event_management.event', ['language' => $languageCode]),
      default => $ticketRedirect,
    };
    return response()->json(['status' => 'success', 'event_id' => $event->id, 'redirect' => $redirect], 200);
  }
  /**
   * Update status (active/DeActive) of a specified resource.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
  public function updateStatus(Request $request, $id)
  {
    $status = (int) $request->validate([
      'status' => ['required', Rule::in([0, 1])],
    ])['status'];
    (new OperationalStatusService())->setEventStatus(
      (int) $id,
      $status,
      (int) Auth::guard('organizer')->id()
    );
    Session::flash('success', 'Updated Successfully');

    return redirect()->back();
  }
  /**
   * Update featured status of a specified resource.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
  public function updateFeatured(Request $request, $id)
  {
    $this->ownedEventForPromotion($id);
    Session::flash('warning', 'Featured placement is managed from the TICA Promote dashboard and requires payment and admin approval.');

    return redirect()->back();
  }

  private function ownedEventForPromotion(int $id): Event
  {
    return Event::query()->whereKey($id)->where('organizer_id', Auth::guard('organizer')->id())->firstOrFail();
  }

  public function edit($id)
  {
    if (!request()->boolean('legacy')) {
      return redirect('/create/' . (int) $id);
    }
    $event = Event::with('ticket')->where('id', $id)->firstOrFail();
    if (Auth::guard('organizer')->user()->id != $event->organizer_id) {
      return back();
    }

    if ($event->organizer_id != Auth::guard('organizer')->user()->id) {
      return redirect()->route('organizer.dashboard');
    }

    $information['event'] = $event;
    $information['getCurrencyInfo']  = $this->getCurrencyInfo();
    $information['languages'] = Language::all();
    $information['countries'] = Country::get();
    $information['cities'] = City::where('country_id',  $event->country)->get();
    $information['states'] = State::where('country_id',  $event->country)->get();

    return view('organizer.event.edit', $information);
  }
  public function imagedbrmv(Request $request)
  {
    $request->validate(['fileid' => ['required', 'integer']]);
    $pi = EventImage::query()->whereKey($request->integer('fileid'))
      ->where(function ($query) {
        $query->where('organizer_id', Auth::guard('organizer')->id())
          ->orWhereHas('event', fn ($eventQuery) => $eventQuery->where('organizer_id', Auth::guard('organizer')->id()));
      })->firstOrFail();
    $event_id = $pi->event_id;
    $image_count = EventImage::where('event_id', $event_id)->get()->count();
    if ($image_count > 1) {
      @unlink(public_path('assets/admin/img/event-gallery/') . $pi->image);
      $pi->delete();
      return $pi->id;
    } else {
      return 'false';
    }
    @unlink(public_path('assets/admin/img/event-gallery/') . $pi->image);
    $pi->delete();
    return $pi->id;
  }
  public function images($portid)
  {
    $event = Event::query()->whereKey($portid)->where('organizer_id', Auth::guard('organizer')->id())->firstOrFail();
    $images = EventImage::where('event_id', $event->id)->get();
    return $images;
  }

  public function update(UpdateRequest $request)
  {
    //calculate duration 
    if ($request->date_type == 'single') {
      $start = Carbon::parse($request->start_date . $request->start_time);
      $end =  Carbon::parse($request->end_date . $request->end_time);
      $diffent = DurationCalulate($start, $end);
    }
    //calculate duration end
    $img = $request->file('thumbnail');

    $in = $request->all();
    unset($in['is_featured']);

    $event = Event::where('id', $request->event_id)
      ->where('organizer_id', Auth::guard('organizer')->id())
      ->firstOrFail();
    $oldStart = app(EventLifecycleNotificationService::class)->start($event);
    if ($request->hasFile('thumbnail')) {
      $filename = $this->imageName($img);
      $directory = public_path('assets/admin/img/event/thumbnail/');
      UploadFile::storeAs($directory, $request->file('thumbnail'), $filename);
      UploadFile::delete($directory, (string) $event->thumbnail);
      $in['thumbnail'] = $filename;
    }

    $languages = Language::all();

    $i = 1;
    foreach ($languages as $language) {
      $event_content = EventContent::where('event_id', $event->id)->where('language_id', $language->id)->first();
      if (!$event_content) {
        $event_content = new EventContent();
      }
      $event_content->language_id = $language->id;
      $event_content->event_category_id = $request[$language->code . '_category_id'];
      $event_content->event_id = $event->id;
      $event_content->title = $request[$language->code . '_title'];
      if ($request->event_type == 'venue') {
        $event_content->address = $request[$language->code . '_address'];
        $event_content->country = $request[$language->code . '_country'];
        $event_content->state = $request[$language->code . '_state'];
        $event_content->city = $request[$language->code . '_city'];
        $event_content->zip_code = $request[$language->code . '_zip_code'];
      }
      $event_content->slug = createSlug($request[$language->code . '_title']);
      $event_content->description = Purifier::clean($request[$language->code . '_description'], 'youtube');
      $event_content->refund_policy = $request[$language->code . '_refund_policy'];
      $event_content->meta_keywords = $request[$language->code . '_meta_keywords'];
      $event_content->meta_description = $request[$language->code . '_meta_description'];
      $event_content->save();
    }
    if ($request->event_type == 'online') {
      if (!$request->pricing_type) {
        $pricing_type = 'normal';
      } else {
        $pricing_type = $request->pricing_type;
      }
      Ticket::where('event_id', $request->event_id)->update([
        'price' => $request->price,
        'f_price' => $request->price,
        'pricing_type' => $pricing_type,
        'ticket_available_type' => $request->ticket_available_type,
        'ticket_available' => $request->ticket_available,
        'max_ticket_buy_type' => $request->max_ticket_buy_type,
        'max_buy_ticket' => $request->max_buy_ticket,
        'early_bird_discount' => $request->early_bird_discount_type,
        'early_bird_discount_type' => $request->discount_type,
        'early_bird_discount_amount' => $request->early_bird_discount_amount,
        'early_bird_discount_date' => $request->early_bird_discount_date,
        'early_bird_discount_time' => $request->early_bird_discount_time,
      ]);
    }

    $event = Event::where('id', $event->id)->first();

    if ($request->date_type == 'multiple') {
      $i = 1;
      foreach ($request->m_start_date as $key => $date) {
        $start = Carbon::parse($date . $request->m_start_time[$key]);
        $end =  Carbon::parse($request->m_end_date[$key] . $request->m_end_time[$key]);
        $diffent = DurationCalulate($start, $end);

        if (!empty($request->date_ids[$key])) {
          $event_date = EventDates::where('id', $request->date_ids[$key])->first();
          $event_date->start_date = $date;
          $event_date->start_time = $request->m_start_time[$key];
          $event_date->end_date = $request->m_end_date[$key];
          $event_date->end_time = $request->m_end_time[$key];
          $event_date->duration = $diffent;
          $event_date->start_date_time = $start;
          $event_date->end_date_time = $end;
          $event_date->save();
        } else {
          EventDates::create([
            'event_id' => $event->id,
            'start_date' => $date,
            'start_time' => $request->m_start_time[$key],
            'end_date' => $request->m_end_date[$key],
            'end_time' => $request->m_end_time[$key],
            'duration' => $diffent,
            'start_date_time' => $start,
            'end_date_time' => $end,
          ]);
        }
        if ($i == 1) {
          $event->update([
            'duration' => $diffent
          ]);
        }
        $i++;
      }
    }

    if ($request->date_type == 'single') {
      $in['end_date_time'] = Carbon::parse($request->end_date . ' ' . $request->end_time);
      $in['duration'] = $diffent;
    } else {
      //update event date time
      $event_date = EventDates::where('event_id', $event->id)->orderBy('end_date_time', 'desc')->first();

      $in['end_date_time'] = $event_date->end_date_time;
    }

    if (($event->lifecycle_status ?: 'scheduled') !== 'scheduled') {
      $in['status'] = 0;
    }
    $event->update($in);
    app(EventLifecycleNotificationService::class)->notifyDateChanged($event, $oldStart);

    Session::flash('success', 'Updated Successfully');
    return response()->json(['status' => 'success'], 200);
  }

  private function imageName($file): string
  {
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $extension = $extensions[$file->getMimeType()] ?? null;
    abort_if($extension === null, 422, 'Unsupported image type.');

    return Str::uuid() . '.' . $extension;
  }

  /**
   * Remove the specified resource from storage.
   *
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
  public function destroy($id)
  {
    (new OperationalArchiveService())->archiveEvents(
      [(int) $id],
      (int) Auth::guard('organizer')->id()
    );

    return redirect()->back()->with('success', 'Event archived. Bookings, tickets and financial history were preserved.');
  }
  //bulk_delete
  public function bulk_delete(Request $request)
  {
    $ids = $request->validate(['ids' => ['required', 'array', 'min:1'], 'ids.*' => ['integer']])['ids'];
    (new OperationalArchiveService())->archiveEvents($ids, (int) Auth::guard('organizer')->id());
    Session::flash('success', 'Events archived. Bookings, tickets and financial history were preserved.');
    return response()->json(['status' => 'success'], 200);
  }
}
