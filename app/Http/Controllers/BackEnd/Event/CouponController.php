<?php

namespace App\Http\Controllers\BackEnd\Event;

use App\Http\Controllers\Controller;
use App\Http\Requests\CouponRequest;
use App\Models\Event;
use App\Models\Event\Coupon;
use App\Models\Language;
use App\Services\AuditLogger;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class CouponController extends Controller
{
  public function index(Request $request)
  {
    $filters = $request->validate([
      'search' => ['nullable', 'string', 'max:120'],
      'status' => ['nullable', 'in:active,pending,expired,paused'],
    ]);
    $today = now()->toDateString();
    $search = trim((string) ($filters['search'] ?? ''));
    $status = $filters['status'] ?? null;

    $information['coupons'] = Coupon::query()
      ->with('organizer:id,username')
      ->when($search !== '', function ($query) use ($search) {
        $query->where(function ($nested) use ($search) {
          $nested->where('name', 'like', '%'.$search.'%')
            ->orWhere('code', 'like', '%'.$search.'%');
        });
      })
      ->when($status === 'active', fn ($query) => $query->where('is_active', true)->whereDate('start_date', '<=', $today)->whereDate('end_date', '>=', $today))
      ->when($status === 'pending', fn ($query) => $query->where('is_active', true)->whereDate('start_date', '>', $today))
      ->when($status === 'expired', fn ($query) => $query->whereDate('end_date', '<', $today))
      ->when($status === 'paused', fn ($query) => $query->where('is_active', false))
      ->orderByDesc('id')
      ->paginate(15)
      ->withQueryString();
    $information['deLang'] = Language::where('is_default', 1)->first() ?: Language::query()->first();
    $information['events'] = Event::query()
      ->with([
        'organizer:id,username',
        'information' => fn ($query) => $information['deLang']
          ? $query->where('language_id', $information['deLang']->id)
          : $query,
      ])
      ->orderByDesc('id')
      ->get();
    $information['summary'] = [
      'total' => Coupon::query()->count(),
      'active' => Coupon::query()->where('is_active', true)->whereDate('start_date', '<=', $today)->whereDate('end_date', '>=', $today)->count(),
      'scheduled' => Coupon::query()->where('is_active', true)->whereDate('start_date', '>', $today)->count(),
      'uses' => (int) Coupon::query()->sum('used_count'),
    ];

    // also, get the currency information from db
    $information['currencyInfo'] = $this->getCurrencyInfo();

    return view('backend.event.coupon.index', $information);
  }

  public function store(CouponRequest $request)
  {
    $startDate = Carbon::parse($request->start_date);
    $endDate = Carbon::parse($request->end_date);

    $coupon = Coupon::create($request->except('start_date', 'end_date', 'events', 'id') + [
      'events' => json_encode($request->events),
      'start_date' => date_format($startDate, 'Y-m-d'),
      'end_date' => date_format($endDate, 'Y-m-d'),
      'organizer_id' => null,
      'is_active' => true,
    ]);

    AuditLogger::record('coupon.created_by_admin', $coupon, [], $coupon->only(['name', 'code', 'type', 'value', 'start_date', 'end_date']));

    Session::flash('success', 'Added Successfully');

    return response()->json(['status' => 'success'], 200);
  }

  public function update(CouponRequest $request)
  {
    $startDate = Carbon::parse($request->start_date);
    $endDate = Carbon::parse($request->end_date);
    $events = !empty($request->events) ? json_encode($request->events) : NULL;

    $coupon = Coupon::query()->findOrFail($request->integer('id'));
    $before = $coupon->only(['name', 'code', 'type', 'value', 'events', 'start_date', 'end_date']);
    $coupon->update(
      $request->except('start_date', 'end_date', 'events') + [
        'events' => $events,
        'start_date' => date_format($startDate, 'Y-m-d'),
        'end_date' => date_format($endDate, 'Y-m-d')
      ]
    );

    AuditLogger::record('coupon.updated_by_admin', $coupon, $before, $coupon->fresh()->only(array_keys($before)));

    Session::flash('success', 'Updated Successfully');

    return response()->json(['status' => 'success'], 200);
  }

  public function destroy($id)
  {
    $coupon = Coupon::query()->findOrFail($id);
    AuditLogger::record('coupon.deleted_by_admin', $coupon, $coupon->only(['name', 'code', 'type', 'value']), []);
    $coupon->delete();

    return redirect()->back()->with('success', 'Deleted Successfully');
  }
}
