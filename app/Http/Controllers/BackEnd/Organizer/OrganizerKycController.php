<?php

namespace App\Http\Controllers\BackEnd\Organizer;

use App\Http\Controllers\Controller;
use App\Models\OrganizerKycDocument;
use App\Models\OrganizerKycSubmission;
use App\Rules\KycDocumentRule;
use App\Models\OrganizerPayoutAccount;
use App\Services\OrganizerKycService;
use App\Services\OrganizerPayoutAccountService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use RuntimeException;
use Throwable;

class OrganizerKycController extends Controller
{
    public function show()
    {
        $organizer = Auth::guard('organizer')->user();
        $submission = OrganizerKycSubmission::with('documents')
            ->where('organizer_id', $organizer->id)
            ->latest('id')->first();
        $payoutAccount = OrganizerPayoutAccount::where('organizer_id', $organizer->id)
            ->where('status', 'verified')->where('is_default', true)->latest('id')->first();
        $stagedAccount = OrganizerPayoutAccount::where('organizer_id', $organizer->id)
            ->whereIn('status', ['pending_kyc', 'name_mismatch'])->latest('id')->first();
        $banks = [];
        $bankLoadError = null;
        try {
            $banks = Cache::remember('paystack.ngn.banks', now()->addDay(), function () {
                return (new OrganizerPayoutAccountService())->banks();
            });
        } catch (Throwable $exception) {
            report($exception);
            $bankLoadError = 'We can\'t reach the banks right now, so you can add your bank account after your identity is approved.';
        }
        return view('organizer.kyc.show', compact(
            'organizer', 'submission', 'payoutAccount', 'stagedAccount', 'banks', 'bankLoadError'
        ));
    }

    public function submit(Request $request)
    {
        $validated = $request->validate([
            'entity_type' => ['required', Rule::in(['individual', 'business'])],
            'legal_name' => ['required', 'string', 'max:191'],
            'business_name' => ['nullable', 'required_if:entity_type,business', 'string', 'max:191'],
            'registration_number' => ['nullable', 'required_if:entity_type,business', 'string', 'max:100'],
            'id_type' => ['required', Rule::in(['national_id', 'drivers_license', 'passport', 'voters_card'])],
            'identity_document' => ['required', new KycDocumentRule()],
            'business_document' => ['nullable', 'required_if:entity_type,business', new KycDocumentRule()],
            'address_document' => ['nullable', new KycDocumentRule()],
            'bank_code' => ['nullable', 'required_with:account_number', 'string', 'max:30'],
            'account_number' => ['nullable', 'required_with:bank_code', 'regex:/^[0-9]{10}$/'],
        ], [
            'account_number.regex' => 'Enter your 10-digit account number.',
            'bank_code.required_with' => 'Choose your bank.',
        ]);

        // Check the bank account before anything is saved, so a wrong name stops here.
        $accounts = new OrganizerPayoutAccountService();
        $looked = null;
        if (!empty($validated['bank_code'])) {
            try {
                $looked = $accounts->lookup($validated['bank_code'], $validated['account_number']);
            } catch (RuntimeException $exception) {
                return redirect()->back()->withInput()->withErrors(['account_number' => $exception->getMessage()]);
            }
            if (!$accounts->matchesAny([$validated['legal_name'], $validated['business_name'] ?? null], $looked['name'])) {
                return redirect()->back()->withInput()->withErrors([
                    'account_number' => 'The bank says this account belongs to ' . $looked['name'] . '. That doesn\'t match the legal name you entered.',
                ]);
            }
        }

        try {
            (new OrganizerKycService())->submit((int) Auth::guard('organizer')->id(), $validated, array_filter([
                'identity' => $request->file('identity_document'),
                'business_registration' => $request->file('business_document'),
                'address' => $request->file('address_document'),
            ]));
        } catch (Throwable $exception) {
            if ($exception instanceof RuntimeException) {
                return redirect()->back()->with('warning', $exception->getMessage());
            }
            throw $exception;
        }

        if ($looked) {
            try {
                $accounts->stage((int) Auth::guard('organizer')->id(), [$validated['legal_name'], $validated['business_name'] ?? null], $validated['bank_code'], $validated['account_number'], $looked);
            } catch (RuntimeException $exception) {
                return redirect()->route('organizer.kyc.show')->with('warning', 'Your details were sent for review, but the bank account wasn\'t saved: ' . $exception->getMessage());
            }
        }

        return redirect()->route('organizer.kyc.show')->with('success', 'Thanks. We\'ll review your details within 1 working day.');
    }

    public function download(OrganizerKycDocument $document)
    {
        $submission = $document->submission;
        abort_unless($submission && (int) $submission->organizer_id === (int) Auth::guard('organizer')->id(), 403);
        abort_unless(Storage::disk($document->disk)->exists($document->path), 404);
        return Storage::disk($document->disk)->download($document->path, $document->original_name);
    }

    public function verifyPayoutAccount(Request $request)
    {
        $validated = $request->validate([
            'bank_code' => ['required', 'string', 'max:30'],
            'account_number' => ['required', 'regex:/^[0-9]{10}$/'],
        ], [
            'account_number.regex' => 'Enter a valid 10-digit Nigerian bank account number.',
        ]);

        $organizer = Auth::guard('organizer')->user();
        try {
            if ($organizer->kyc_status === 'verified') {
                (new OrganizerPayoutAccountService())->verify((int) $organizer->id, $validated['bank_code'], $validated['account_number']);
                $message = 'Bank account saved. Payouts can go to it in 24 hours.';
            } else {
                $submission = OrganizerKycSubmission::where('organizer_id', $organizer->id)->latest('id')->first();
                if (!$submission || $submission->status !== 'pending') {
                    throw new RuntimeException('Send your identity details first, then add your bank account.');
                }
                (new OrganizerPayoutAccountService())->stage((int) $organizer->id, [$submission->legal_name, $submission->business_name], $validated['bank_code'], $validated['account_number']);
                $message = 'Bank account saved. It switches on when your identity is approved.';
            }
        } catch (RuntimeException $exception) {
            return redirect()->back()->withInput()->withErrors(['account_number' => $exception->getMessage()]);
        }

        return redirect()->route('organizer.kyc.show')->with('success', $message);
    }

    public function confirmPayoutLock(Request $request, string $account)
    {
        $payoutAccount = OrganizerPayoutAccount::where('public_id', $account)->firstOrFail();
        return view('organizer.kyc.lock-payout-account', compact('payoutAccount'));
    }

    public function lockPayoutAccount(Request $request, string $account)
    {
        (new OrganizerPayoutAccountService())->lock($account);

        return redirect()->route('organizer.login')->with(
            'success',
            'Payouts are locked. Sign in and contact Tica support before adding another account.'
        );
    }
}
