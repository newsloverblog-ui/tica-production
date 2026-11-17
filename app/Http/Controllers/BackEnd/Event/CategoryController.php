<?php

namespace App\Http\Controllers\BackEnd\Event;

use App\Http\Controllers\Controller;
use App\Http\Requests\Event\EventCategoryRequest;
use App\Http\Requests\Event\UpdateCategoryRequest;
use Illuminate\Http\Request;
use App\Models\Event\EventCategory;
use App\Models\Language;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;

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
    $data = $request->validate(['is_featured' => ['required', Rule::in(['yes', 'no'])]]);
    EventCategory::findOrFail($id)->update(['is_featured' => $data['is_featured']]);
    Session::flash('success', 'Featured placement updated.');

    return redirect()->back();
  }

  public function updateStatus(Request $request, $id)
  {
    $data = $request->validate(['status' => ['required', 'boolean']]);
    EventCategory::findOrFail($id)->update(['status' => (int) $data['status']]);
    Session::flash('success', (int) $data['status'] === 1 ? 'Category is now visible.' : 'Category is now hidden.');

    return redirect()->back();
  }

  public function reorder(Request $request)
  {
    $data = $request->validate([
      'ids' => ['required', 'array', 'min:1'],
      'ids.*' => ['required', 'integer', 'distinct', 'exists:event_categories,id'],
    ]);

    $categories = EventCategory::query()->whereIn('id', $data['ids'])->get()->keyBy('id');
    $languageIds = $categories->pluck('language_id')->unique();
    if ($languageIds->count() !== 1) {
      return response()->json(['message' => 'Categories from different languages cannot be reordered together.'], 422);
    }

    DB::transaction(function () use ($data, $categories) {
      foreach ($data['ids'] as $position => $id) {
        $categories->get((int) $id)->update(['serial_number' => $position + 1]);
      }
    });

    return response()->json(['status' => 'success', 'message' => 'Category order saved.']);
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
    $category = EventCategory::findOrFail($id);
    $eventCount = $category->events_contens()->count();
    if ($eventCount > 0) {
      return redirect()->back()->with('warning', "Move the {$eventCount} linked " . ($eventCount === 1 ? 'event' : 'events') . ' to another category before deleting this one.');
    }

    if ($category->image) {
      @unlink(public_path('assets/admin/img/event-category/') . $category->image);
    }
    $category->delete();
    return redirect()->back()->with('success', 'Category deleted.');
  }

  public function bulkDestroy(Request $request)
  {
    $data = $request->validate([
      'ids' => ['required', 'array', 'min:1'],
      'ids.*' => ['required', 'integer', 'distinct', 'exists:event_categories,id'],
    ]);
    $categories = EventCategory::query()->withCount('events_contens as events_count')->whereIn('id', $data['ids'])->get();
    $blocked = $categories->filter(fn ($category) => $category->events_count > 0);
    if ($blocked->isNotEmpty()) {
      return response()->json([
        'status' => 'error',
        'message' => 'Move linked events before deleting: ' . $blocked->pluck('name')->implode(', ') . '.',
      ], 422);
    }

    DB::transaction(function () use ($categories) {
      foreach ($categories as $category) {
        if ($category->image) {
          @unlink(public_path('assets/admin/img/event-category/') . $category->image);
        }
        $category->delete();
      }
    });
    Session::flash('success', 'Categories deleted.');
    return response()->json(['status' => 'success'], 200);
  }
}
