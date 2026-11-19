<?php

namespace App\Http\Controllers\BackEnd\Organizer;

use App\Http\Controllers\Controller;
use App\Services\DuplicateAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/** Admin > Organizers > Duplicate accounts: one decision per email, never automatic. */
class DuplicateAccountController extends Controller
{
    public function index(DuplicateAccountService $duplicates): View
    {
        return view('backend.end-user.organizer.duplicates.index', ['rows' => $duplicates->find()]);
    }

    public function resolve(Request $request, DuplicateAccountService $duplicates): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'integer'],
            'organizer_id' => ['required', 'integer'],
            'decision' => ['required', Rule::in([DuplicateAccountService::KEEP_ORGANIZER, DuplicateAccountService::KEEP_CUSTOMER, DuplicateAccountService::LEAVE])],
            'confirm' => ['accepted'],
        ], [
            'confirm.accepted' => __('Tick the box to confirm this change.'),
        ]);

        try {
            $message = $duplicates->resolve((int) $validated['customer_id'], (int) $validated['organizer_id'], $validated['decision'], (int) Auth::guard('admin')->id());
        } catch (RuntimeException $e) {
            return back()->with('warning', $e->getMessage());
        }

        return redirect()->route('admin.duplicate_accounts.index')->with('success', $message);
    }
}
