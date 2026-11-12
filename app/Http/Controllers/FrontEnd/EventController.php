<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Event;
use App\Models\EventRegistrationForm;
use App\Models\Event\Coupon;
use App\Models\Event\EventCategory;
use App\Models\Event\EventContent;
use App\Models\Event\EventDates;
use App\Models\Event\EventImage;
use App\Models\Event\Ticket;
use App\Models\Event\Wishlist;
use App\Models\Organizer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;
use App\Services\EventWaitlistService;
use App\Services\EventPublicVisibility;
use App\Services\EventCouponService;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class EventController extends Controller
{
  private $now_date_time;
  public function __construct()
  {
    $this->now_date_time = Carbon::now();
  }
  //index
  public function index(Request $request)
  {
    $language = $this->getLanguage();
    $information  = [];
    $categories = EventCategory::where([['language_id', $language->id], ['status', 1]])->orderBy('serial_number', 'asc')->get();
    $information['categories'] = $categories;
    $countries = Country::get();
    $information['countries'] = $countries;


    //for filter
    $category = $location =  $event_type = $min = $max = $keyword = $date1 = $date2 = null;

    if ($request->filled('category')) {
      $category = $request['category'];
      $category = EventCategory::where([['slug', $category], ['status', 1]])->first();
      $category = $category->id;
    }
    $eventSIds = [];
    if ($request->filled('location')) {
      $location = $request['location'];

      $event_contents = EventContent::where(function ($query) use ($location) {
        return $query->where('address', 'like', '%' . $location . '%')
          ->orWhere('city', 'like', '%' . $location . '%')
          ->orWhere('country', 'like', '%' . $location . '%')
          ->orWhere('state', 'like', '%' . $location . '%');
      })->where('language_id', $language->id)->get();

      foreach ($event_contents as $event_content) {
        if (!in_array($event_content->event_id, $eventSIds)) {
          array_push($eventSIds, $event_content->event_id);
        }
      }
    }

    if ($request->filled('event')) {
      $event_type = $request['event'];
    }
    $eventIds = [];

    if ($request->filled('min') && $request->filled('max')) {
      $min = $request['min'];
      $max = $request['max'];

      $tickets = Ticket::where('tickets.f_price', '>=', $min)->where('tickets.f_price', '<=', $max)->get();

      foreach ($tickets as $ticket) {
        if (!in_array($ticket->event_id, $eventIds)) {
          array_push($eventIds, $ticket->event_id);
        }
      }
    }

    if ($request->filled('search-input')) {
      $keyword = $request['search-input'];
    }
    $eventIds2 = [];
    if ($request->filled('dates')) {
      $dateArray = preg_split('/\s+-\s+/', trim((string) $request->input('dates')));
      if (count($dateArray) === 2) {
        try {
          $rangeStart = Carbon::parse($dateArray[0])->startOfDay();
          $rangeEnd = Carbon::parse($dateArray[1])->endOfDay();
          if ($rangeEnd->lt($rangeStart)) {
            [$rangeStart, $rangeEnd] = [$rangeEnd->copy()->startOfDay(), $rangeStart->copy()->endOfDay()];
          }
          $date1 = $rangeStart->toDateString();
          $date2 = $rangeEnd->toDateString();

          $q_events = EventDates::whereDate('start_date', '<=', $date2)->whereDate('end_date', '>=', $date1)->get();
          foreach ($q_events as $evnt) {
            if (!in_array($evnt->event_id, $eventIds2)) {
              array_push($eventIds2, $evnt->event_id);
            }
          }

          $events = Event::whereDate('start_date', '<=', $date2)->whereDate('end_date', '>=', $date1)->get();
          foreach ($events as $event) {
            if (!in_array($event->id, $eventIds2)) {
              array_push($eventIds2, $event->id);
            }
          }
        } catch (\Throwable $exception) {
          $date1 = $date2 = null;
        }
      }
    }


    $events = EventContent::join('events', 'events.id', 'event_contents.event_id')
      ->where('event_contents.language_id', $language->id)
      ->when($category, function ($query, $category) {
        return $query->where('event_contents.event_category_id', '=', $category);
      })
      ->when($event_type, function ($query, $event_type) {
        return $query->where('events.event_type', '=', $event_type);
      })
      ->when(($min && $max), function ($query) use ($eventIds) {
        return $query->whereIn('events.id', $eventIds);
      })
      ->when($location, function ($query) use ($eventSIds) {
        return $query->whereIn('events.id', $eventSIds);
      })
      ->when(($date1 && $date2), function ($query) use ($eventIds2) {
        return $query->whereIn('events.id', $eventIds2);
      })
      ->when($keyword, function ($query, $keyword) {
        return $query->where('event_contents.title', 'like', '%' . $keyword . '%');
      })
      ->where('events.status', 1)
      ->whereDate('events.end_date_time', '>=', $this->now_date_time)
      ->select('events.*', 'event_contents.title', 'event_contents.description', 'event_contents.city', 'event_contents.state', 'event_contents.country', 'event_contents.address', 'event_contents.zip_code', 'event_contents.slug')
      ->orderBy('events.id', 'desc')
      ->paginate(9);

    $max = Ticket::max('f_price');
    $min = Ticket::min('f_price');
    $information['max'] = $max;
    $information['min'] = $min;
    $information['events'] = $events;

    return view('frontend.event.event', compact('information'));
  }

  //details
  public function details($slug, $id)
  {
    return $this->renderDetails($slug, $id, false);
  }

  public function preview($slug, $id)
  {
    $event = Event::query()->findOrFail($id);

    $isAdmin = Auth::guard('admin')->check();
    $isOwner = Auth::guard('organizer')->check()
      && (int) $event->organizer_id === (int) Auth::guard('organizer')->id();

    abort_unless($isAdmin || $isOwner, 404);

    return $this->renderDetails($slug, $id, true);
  }

  private function renderDetails($slug, $id, bool $isPreview)
  {
    try {
      $language = $this->getLanguage();
      $information = [];

      //remove all session data
      Session::forget('selTickets');
      Session::forget('total');
      Session::forget('quantity');
      Session::forget('total_early_bird_dicount');
      Session::forget('event');
      Session::forget('online_gateways');
      Session::forget('offline_gateways');

      $tickets_count = Ticket::where('event_id', $id)->get()->count();
      $information['tickets_count'] = $tickets_count;
      if ($tickets_count < 1) {
        $content = EventContent::join('events', 'events.id', 'event_contents.event_id')
          ->join('event_images', 'event_images.event_id', '=', 'events.id')
          ->join('event_categories', 'event_categories.id', '=', 'event_contents.event_category_id')
          ->where('event_contents.language_id', $language->id)
          ->where('events.id', $id)
          ->when(!$isPreview, fn ($query) => app(EventPublicVisibility::class)->apply($query))
          ->select('events.*', 'event_contents.title', 'event_contents.slug as eventSlug', 'event_contents.description', 'meta_keywords', 'meta_description', 'event_contents.event_category_id', 'event_categories.name', 'event_categories.slug', 'event_contents.city', 'event_contents.state', 'event_contents.country', 'event_contents.address', 'event_contents.zip_code', 'event_contents.refund_policy')
          ->first();
        if (is_null($content)) {
          abort(404);
        }
      } else {
        $content = EventContent::join('events', 'events.id', 'event_contents.event_id')
          ->join('tickets', 'tickets.event_id', '=', 'events.id')
          ->join('event_images', 'event_images.event_id', '=', 'events.id')
          ->join('event_categories', 'event_categories.id', '=', 'event_contents.event_category_id')
          ->where('event_contents.language_id', $language->id)
          ->where('events.id', $id)
          ->when(!$isPreview, fn ($query) => app(EventPublicVisibility::class)->apply($query))
          ->select('events.*', 'event_contents.title', 'event_contents.slug as eventSlug', 'event_contents.description', 'meta_keywords', 'meta_description', 'event_contents.event_category_id', 'event_categories.name', 'event_categories.slug', 'tickets.price', 'tickets.variations', 'tickets.pricing_type', 'event_contents.city', 'event_contents.state', 'event_contents.country', 'event_contents.address', 'event_contents.zip_code', 'event_contents.refund_policy')
          ->first();
        if (is_null($content)) {
          abort(404);
        }
      }

      // Keep one stable, human-readable URL for every event. Old or mistyped
      // slugs remain recoverable through the numeric id, then redirect here.
      if (!empty($content->eventSlug) && urldecode($slug) !== $content->eventSlug) {
        $routeName = $isPreview ? request()->route()->getName() : 'event.details';
        return redirect()->route($routeName, [$content->eventSlug, $content->id], 301);
      }

      $information['content'] = $content;
      $information['is_preview'] = $isPreview;
      $images = EventImage::where('event_id', $id)->get();
      $information['images'] = $images;

      $information['organizer'] = '';
      if ($content) {
        if ($content->organizer_id != NULL) {
          $organizer = Organizer::where('id', $content->organizer_id)->first();
          $information['organizer'] = $organizer;
        }
      }

      $missingContentMessage = 'No event content found for ' . $language->name . ' Language';
      if (Session::get('message') === $missingContentMessage) {
        Session::forget(['message', 'alert-type']);
      }

      $category_id = $content->event_category_id;
      $event_id = $content->id;
      $related_events = EventContent::join('events', 'events.id', 'event_contents.event_id')
        ->where('event_contents.language_id', $language->id)
        ->where('event_contents.event_category_id', $category_id)
        ->where('events.id', '!=', $event_id)
        ->where('events.status', 1)
        ->when(
          Schema::hasColumn('events', 'lifecycle_status'),
          fn ($query) => $query->where('events.lifecycle_status', 'scheduled')
        )
        ->whereDate('events.end_date_time', '>=', $this->now_date_time)
        ->select('events.*', 'event_contents.title', 'event_contents.description', 'event_contents.slug', 'event_contents.city', 'event_contents.country')
        ->orderBy('events.id', 'desc')
        ->get();


      $information['related_events'] = $related_events;
      $event = Event::findOrFail($content->id);
      $information['registration_form'] = ($event->registration_mode ?: 'ticketing') === 'corporate'
        ? EventRegistrationForm::query()
          ->where('event_id', $event->id)
          ->where('status', 'published')
          ->first()
        : null;
      $information['waitlist_options'] = ($event->registration_mode ?: 'ticketing') === 'ticketing'
        ? app(EventWaitlistService::class)->soldOutSelections($event)
        : collect();
      return view('frontend.event.event-details', $information); //code...
    } catch (HttpExceptionInterface $th) {
      throw $th;
    } catch (\Throwable $th) {
      // Unexpected dependency, database and rendering failures must remain
      // server errors so monitoring and callers can distinguish an outage
      // from a genuinely missing event.
      throw $th;
    }
  }
  //applyCoupon
  public function applyCoupon(Request $request)
  {
    $validated = $request->validate([
      'coupon_code' => ['required', 'string', 'max:100'],
    ]);
    Session::forget(['discount', 'event_coupon_id']);

    $event = Session::get('event');
    $eventId = (int) ($event->id ?? 0);
    $selectedTickets = Session::get('selTickets');
    $subtotal = (float) Session::get('sub_total', 0);
    if ($eventId < 1 || !is_array($selectedTickets) || $selectedTickets === [] || $subtotal <= 0) {
      return response()->json(['status' => 'error', 'message' => 'Your checkout session has expired.']);
    }

    $coupon = Coupon::query()->where('code', trim($validated['coupon_code']))->first();
    $amount = $coupon
      ? app(EventCouponService::class)->discount(
        $coupon,
        $eventId,
        $subtotal,
        (float) Session::get('total_early_bird_dicount', 0)
      )
      : null;

    if ($amount === null) {
      return response()->json(['status' => 'error', 'message' => 'Coupon is not valid']);
    }

    Session::put('event_coupon_id', $coupon->id);
    Session::put('discount', $amount);

    return response()->json(['status' => 'success', 'message' => 'Coupon applied successfully']);
  }

  //add_to_wishlist
  public function add_to_wishlist($id)
  {
    if (Auth::guard('customer')->check()) {
      $customer_id = Auth::guard('customer')->user()->id;
      $check = Wishlist::where('event_id', $id)->where('customer_id', $customer_id)->first();

      if (!empty($check)) {
        $notification = array('message' => 'You already added this event into your wishlist..!', 'alert-type' => 'error');
        return back()->with($notification);
      } else {
        $add = new Wishlist;
        $add->event_id = $id;
        $add->customer_id = $customer_id;
        $add->save();
        $notification = array('message' => 'Add to your wishlist successfully..!', 'alert-type' => 'success');
        return back()->with($notification);
      }
    } else {
      return redirect()->route('customer.login');
    }
  }
}
