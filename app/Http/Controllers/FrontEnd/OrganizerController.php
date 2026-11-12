<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Event;
use App\Models\Event\EventCategory;
use App\Models\Event\EventContent;
use App\Models\Organizer;
use App\Models\OrganizerInfo;
use App\Services\ApplicationMailService;
use App\Services\EventPublicVisibility;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;

class OrganizerController extends Controller
{
  //show
  public function index(Request $request)
  {
    $language = $this->getLanguage();

    $organizer_name = $username = $location = null;

    $organizerIds = [];
    if ($request->filled('organizer')) {
      $organizer_name = $request->organizer;

      $organizer_infos = OrganizerInfo::where('name', 'like', '%' . $organizer_name . '%')
        ->where('language_id', $language->id)
        ->get();
      foreach ($organizer_infos as $info) {
        if (!in_array($info->organizer_id, $organizerIds)) {
          array_push($organizerIds, $info->organizer_id);
        }
      }
    }
    if ($request->filled('username')) {
      $username = $request->username;
    }
    $locationIds = [];
    if ($request->filled('location')) {
      $location = $request->location;
      $organizer_infos = OrganizerInfo::where('language_id', $language->id)
        ->where(function ($query) use ($location) {
          $query->where('city', 'like', '%' . $location . '%')
            ->orWhere('state', 'like', '%' . $location . '%')
            ->orWhere('country', 'like', '%' . $location . '%')
            ->orWhere('address', 'like', '%' . $location . '%');
        })
        ->get();
      foreach ($organizer_infos as $info) {
        if (!in_array($info->organizer_id, $locationIds)) {
          array_push($locationIds, $info->organizer_id);
        }
      }
    }

    $collection = Organizer::with(['organizer_info' => function ($query) use ($language) {
      return $query->where('language_id', $language->id);
    }])->where('status', 1)->when($username, function ($query) use ($username) {
      return $query->where('username', 'like', '%' . $username . '%');
    })
      ->when($location, function ($query) use ($locationIds) {
        return $query->whereIn('id', $locationIds);
      })
      ->when($organizer_name, function ($query) use ($organizerIds) {
        return $query->whereIn('id', $organizerIds);
      })
      ->paginate(20);

    return view('frontend.organizer.index', compact('collection'));
  }

  public function details(Request $request, $id, $name)
  {
    try {
      $language = $this->getLanguage();
      $information = [];
      $information['basicInfos'] = DB::table('basic_settings')
        ->select('google_recaptcha_status')
        ->first();

      if (filled($request->admin)) {
        $admin = Admin::first();
        $information['organizer'] = $admin;
        $information['admin'] = true;

        $events = Event::with(['tickets', 'information' => function ($query) use ($language) {
          return $query->where('language_id', $language->id);
        }])->where('organizer_id', NULL);
        app(EventPublicVisibility::class)->apply($events);
        $events = $events->get();
        $information['organizer_info'] = [];
      } else {
        $organizer = Organizer::where('id', $id)->where('status', 1)->firstOrFail();

        $information['organizer_info'] = OrganizerInfo::where('organizer_id', $id)->where('language_id', $language->id)->first();

        $information['organizer'] = $organizer;
        $information['admin'] = false;

        $events = Event::with(['tickets', 'information' => function ($query) use ($language) {
          return $query->where('language_id', $language->id);
        }])->where('organizer_id', $organizer->id);
        app(EventPublicVisibility::class)->apply($events);
        $events = $events->get();
      }

      $categories = EventCategory::where('status', 1)
        ->where('language_id', $language->id)
        ->orderBy('serial_number', 'asc')->get();
      $information['categories'] = $categories;


      $information['events'] = $events;
      return view('frontend.organizer.details', $information); //code...
    } catch (ModelNotFoundException $th) {
      return response()->view('errors.404', [], 404);
    } catch (\Exception $th) {
      throw $th;
    }
  }


  public function sendMail(Request $request)
  {

    $info = DB::table('basic_settings')
      ->select('google_recaptcha_status')
      ->first();

    $rules = [
      'id' => 'required|integer',
      'name' => 'required|string|max:100',
      'email' => 'required|string|email:rfc,dns|max:254',
      'subject' => 'required|string|max:200',
      'message' => 'required|string|max:5000'
    ];
    if ($info->google_recaptcha_status == 1) {
      $rules['g-recaptcha-response'] = 'required|captcha';
    }

    $msgs = [];

    if ($info->google_recaptcha_status == 1) {
      $msgs['g-recaptcha-response.required'] = 'Please verify that you are not a robot.';
      $msgs['g-recaptcha-response.captcha'] = 'Captcha error! try again later or contact site admin.';
    }

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
      return response()->json([
        'errors' => $validator->getMessageBag()->toArray()
      ], 400);
    }
    $organizer = Organizer::whereKey((int) $request->id)->where('status', 1)->firstOrFail();


    $name = $request->name;
    $subject = $request->subject;

    $message = '<p>Message: ' . nl2br(e((string) $request->message)) . '</p>'
      . '<p><strong>Enquirer Name:</strong> ' . e((string) $name)
      . '<br><strong>Enquirer Email:</strong> ' . e((string) $request->email) . '</p>';

    try {
      app(ApplicationMailService::class)->queueHtml(
        'organizer_contact_enquiry', $organizer->email, $subject, $message, [], $request->email, $organizer
      );

      Session::flash('message', 'Your contact request has been accepted for delivery.');
      Session::flash('alert-type', 'success');
    } catch (\Exception $e) {
      report($e);
      Session::flash('message', 'Something went wrong');
      Session::flash('alert-type', 'error');
    }

    return 'success';
  }
}
