<?php

namespace App\Http\Controllers\BackEnd\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Event\Booking;
use App\Services\AccountTypeGuard;
use App\Services\BookingCompletionUrlService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * "My tickets" for organisers who used to be customers: the orders and
 * tickets they bought before the upgrade, read-only.
 */
class OrganizerPastTicketsController extends Controller
{
  public function index(BookingCompletionUrlService $urls): View
  {
    $organizer = Auth::guard('organizer')->user();
    abort_unless(AccountTypeGuard::hasMigrationColumns(), 404);
    $customerIds = Customer::query()->where('migrated_to_organizer_id', $organizer->id)->pluck('id')->all();
    abort_if(!$customerIds, 404);

    $bookings = Booking::query()
      ->with('event:event_id,title')
      ->whereIn('customer_id', $customerIds)
      ->latest('id')
      ->paginate(20);

    return view('organizer.my-tickets', [
      'bookings' => $bookings,
      'ticketUrl' => fn (Booking $b) => in_array($b->paymentStatus, ['completed', 'free'], true) ? $urls->make((int) $b->event_id, (int) $b->id) : null,
    ]);
  }
}
