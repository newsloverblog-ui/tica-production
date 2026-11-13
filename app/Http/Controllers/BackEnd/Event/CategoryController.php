<?php

namespace App\Http\Controllers\BackEnd\Event;

use App\Http\Controllers\Controller;
use App\Http\Requests\Event\EventCategoryRequest;
use App\Http\Requests\Event\UpdateCategoryRequest;
use Illuminate\Http\Request;
use App\Models\Event\EventCategory;
use App\Models\Language;
use App\Http\Helpers\UploadFile;
use App\Models\Event\EventContent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class CategoryController extends Controller
{
  public function index(Request $request)
  {
    $language = Language::where('code', $request->language)->firstOrFail();
    $information['language'] = $language;

    $information['categories'] = EventCategory::where('language_id', $language->id)
      ->withCount('events_contens as events_count')
      ->orderBy('serial_number')
      ->orderByDesc('id')
      ->get();

    $information['langs'] = Language::all();
    $information['icons'] = config('category_icons');

    $information['themeInfo'] = DB::table('basic_settings')->select('theme_version')->first();

    return view('backend.event.category.index', $information);
  }

  public function store(EventCategoryRequest $request)
  {
    $ins = $request->only(['language_id', 'name', 'icon', 'status', 'serial_number', 'is_featured']);
    $ins['slug'] = createSlug($request->name);
    EventCategory::create($ins);
    Session::flash('success', 'Added Successfully');

    return response()->json(['status' => 'success'], 200);
  }

  public function updateFeatured(Request $request, $id)
  {
    $category = EventCategory::find($id);

    if ($request['is_featured'] == 'yes') {
      $category->update(['is_featured' => 'yes']);

      Session::flash('success', 'Updated Successfully!');
    } else {
      $category->update(['is_featured' => 'no']);

      Session::flash('success', 'Updated Successfully!');
    }

    return redirect()->back();
  }

  public function update(UpdateCategoryRequest $request)
  {
    $ins = $request->only(['name', 'icon', 'status', 'serial_number', 'is_featured']);
    $ins['slug'] = createSlug($request->name);
    EventCategory::findOrFail($request->id)->update($ins);

    Session::flash('success', 'Updated Successfully!');

    return response()->json(['status' => 'success'], 200);
  }

  public function destroy($id)
  {
    $category = EventCategory::where('id', $id)->first();
    @unlink(public_path('assets/admin/img/event-category/') . $category->image);

    //events
    $event_contents = EventContent::where('event_category_id', $category->id)->get();
    foreach ($event_contents as $event_content) {
      $event_content->delete();
    }

    $category->delete();
    return redirect()->back()->with('success', 'Deleted Successfully');
  }

  public function bulkDestroy(Request $request)
  {
    $ids = $request->ids;

    foreach ($ids as $id) {
      $category = EventCategory::where('id', $id)->first();
      @unlink(public_path('assets/admin/img/event-category/') . $category->image);

      //events
      $event_contents = EventContent::where('event_category_id', $category->id)->get();
      foreach ($event_contents as $event_content) {
        $event_content->delete();
      }

      $category->delete();
    }
    Session::flash('success', 'Deleted Successfully');
    return response()->json(['status' => 'success'], 200);
  }
}
