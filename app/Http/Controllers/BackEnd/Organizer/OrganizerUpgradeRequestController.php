<?php

namespace App\Http\Controllers\BackEnd\Organizer;

use App\Http\Controllers\Controller;
use App\Models\OrganizerUpgradeRequest;
use App\Services\OrganizerUpgradeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/** Admin > Organizers > Upgrade requests: customers asking to become organisers. */
class OrganizerUpgradeRequestController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status', OrganizerUpgradeRequest::PENDING);
        $status = in_array($status, ['pending', 'approved', 'rejected', 'all'], true) ? $status : 'pending';
        $requests = OrganizerUpgradeRequest::query()
            ->with('customer:id,fname,lname,email,username,created_at,provider')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('backend.end-user.organizer.upgrades.index', [
            'requests' => $requests,
            'status' => $status,
            'counts' => OrganizerUpgradeRequest::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->all(),
        ]);
    }

    public function decide(Request $request, OrganizerUpgradeRequest $upgrade, OrganizerUpgradeService $upgrades): RedirectResponse
    {
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'reason' => ['nullable', 'required_if:decision,reject', 'string', 'max:1000'],
        ], [
            'reason.required_if' => __('Tell the customer why their request was rejected.'),
        ]);

        try {
            if ($validated['decision'] === 'approve') {
                $organizer = $upgrades->approve($upgrade, (int) Auth::guard('admin')->id());
                $message = __(':email is now an organizer (@:username).', ['email' => $organizer->email, 'username' => $organizer->username]);
            } else {
                $upgrades->reject($upgrade, trim($validated['reason']), (int) Auth::guard('admin')->id());
                $message = __('Request rejected. The customer has been emailed the reason.');
            }
        } catch (RuntimeException $e) {
            return back()->with('warning', $e->getMessage());
        }

        return redirect()->route('admin.organizer_upgrades.index')->with('success', $message);
    }
}
