<?php

namespace App\Http\Controllers\BackEnd\Organizer;

use App\Http\Controllers\Controller;
use App\Models\OrganizerKycDocument;
use App\Models\OrganizerKycSubmission;
use App\Services\OrganizerKycService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use RuntimeException;

class OrganizerKycReviewController extends Controller
{
    public function index(Request $request)
    {
        $submissions = OrganizerKycSubmission::with('organizer')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->latest('submitted_at')->paginate(25)->withQueryString();
        return view('backend.end-user.organizer.kyc.index', compact('submissions'));
    }

    public function show(OrganizerKycSubmission $submission)
    {
        $submission->load(['organizer', 'documents']);
        return view('backend.end-user.organizer.kyc.show', compact('submission'));
    }

    public function review(Request $request, OrganizerKycSubmission $submission)
    {
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['verified', 'rejected'])],
            'rejection_reason' => ['nullable', 'required_if:decision,rejected', 'string', 'max:2000'],
        ]);

        try {
            (new OrganizerKycService())->review(
                $submission->id,
                $validated['decision'],
                $validated['rejection_reason'] ?? null,
                (int) Auth::guard('admin')->id()
            );
        } catch (RuntimeException $exception) {
            return redirect()->back()->with('warning', $exception->getMessage());
        }

        \App\Support\AdminQueues::forget();
        $verified = $validated['decision'] === 'verified';
        \App\Support\OrganizerInbox::send((int) $submission->organizer_id, 'kyc.' . $validated['decision'],
            $verified ? __('You are verified') : __('Your identity check needs changes'),
            $verified ? __('You can now be paid. Add or confirm your bank account.') : (string) ($validated['rejection_reason'] ?? ''),
            url('/organizer/kyc'));

        return redirect()->route('admin.organizer_kyc.show', $submission)->with('success', 'KYC review saved.');
    }

    public function download(OrganizerKycDocument $document)
    {
        abort_unless(Storage::disk($document->disk)->exists($document->path), 404);
        return Storage::disk($document->disk)->download($document->path, $document->original_name);
    }
}
