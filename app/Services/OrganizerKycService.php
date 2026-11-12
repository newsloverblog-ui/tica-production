<?php

namespace App\Services;

use App\Models\Organizer;
use App\Models\OrganizerKycDocument;
use App\Models\OrganizerKycSubmission;
use App\Rules\KycDocumentRule;
use App\Support\LifecycleMailCatalog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use Illuminate\Validation\ValidationException;

class OrganizerKycService
{
    public function submit(int $organizerId, array $data, array $documents): OrganizerKycSubmission
    {
        $storedPaths = [];
        try {
            return DB::transaction(function () use ($organizerId, $data, $documents, &$storedPaths) {
                $organizer = Organizer::whereKey($organizerId)->lockForUpdate()->firstOrFail();
                $latest = OrganizerKycSubmission::where('organizer_id', $organizerId)
                    ->lockForUpdate()->latest('id')->first();
                if ($latest && in_array($latest->status, ['pending', 'verified'], true)) {
                    throw new RuntimeException('A pending or verified KYC submission cannot be replaced.');
                }

                $submission = OrganizerKycSubmission::create([
                    'public_id' => (string) Str::uuid(),
                    'organizer_id' => $organizerId,
                    'entity_type' => $data['entity_type'],
                    'legal_name' => $data['legal_name'],
                    'business_name' => $data['business_name'] ?? null,
                    'registration_number' => $data['registration_number'] ?? null,
                    'id_type' => $data['id_type'],
                    'status' => 'pending',
                    'submitted_at' => now(),
                ]);

                foreach ($documents as $type => $file) {
                    if (!$file instanceof UploadedFile) continue;
                    $rule = new KycDocumentRule();
                    if (!$rule->passes($type, $file)) {
                        throw ValidationException::withMessages([$type => [$rule->message()]]);
                    }
                    $path = 'kyc/' . $organizerId . '/' . $submission->public_id . '/' . Str::uuid() . '.' . KycDocumentRule::extension($file);
                    Storage::disk('local')->put($path, file_get_contents($file->getRealPath()));
                    $storedPaths[] = $path;
                    OrganizerKycDocument::create([
                        'submission_id' => $submission->id,
                        'document_type' => $type,
                        'disk' => 'local',
                        'path' => $path,
                        'original_name' => mb_substr($file->getClientOriginalName(), 0, 191),
                        'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                        'size' => $file->getSize(),
                        'sha256' => hash_file('sha256', $file->getRealPath()),
                    ]);
                }

                $organizer->update(['kyc_status' => 'pending', 'kyc_verified_at' => null]);
                AuditLogger::record('organizer.kyc_submitted', $submission, [], [
                    'status' => 'pending', 'entity_type' => $submission->entity_type,
                ]);
                return $submission->load('documents');
            }, 3);
        } catch (Throwable $exception) {
            foreach ($storedPaths as $path) Storage::disk('local')->delete($path);
            throw $exception;
        }
    }

    public function review(
        int $submissionId,
        string $decision,
        ?string $rejectionReason,
        int $adminId
    ): OrganizerKycSubmission {
        return DB::transaction(function () use ($submissionId, $decision, $rejectionReason, $adminId) {
            if (!in_array($decision, ['verified', 'rejected'], true)) {
                throw new RuntimeException('Invalid KYC decision.');
            }
            if ($decision === 'rejected' && trim((string) $rejectionReason) === '') {
                throw new RuntimeException('A rejection reason is required.');
            }

            $submission = OrganizerKycSubmission::whereKey($submissionId)->lockForUpdate()->firstOrFail();
            if ($submission->status !== 'pending') {
                throw new RuntimeException('Only pending KYC submissions can be reviewed.');
            }
            $organizer = Organizer::whereKey($submission->organizer_id)->lockForUpdate()->firstOrFail();
            $before = ['status' => $submission->status, 'organizer_kyc_status' => $organizer->kyc_status];
            $submission->update([
                'status' => $decision,
                'rejection_reason' => $decision === 'rejected' ? trim((string) $rejectionReason) : null,
                'reviewed_by_admin_id' => $adminId,
                'reviewed_at' => now(),
            ]);
            $organizer->update([
                'kyc_status' => $decision,
                'verified_legal_name' => $decision === 'verified' ? $submission->legal_name : null,
                'kyc_verified_at' => $decision === 'verified' ? now() : null,
            ]);
            if ($decision === 'verified') {
                // A bank account given with the identity check goes live now, if its name still matches.
                (new OrganizerPayoutAccountService())->activateStaged($organizer, [$submission->legal_name, $submission->business_name]);
            }
            AuditLogger::record('organizer.kyc_reviewed', $submission, $before, [
                'status' => $decision, 'organizer_kyc_status' => $decision,
            ], ['rejection_reason' => $submission->rejection_reason]);

            if ($decision === 'verified') {
                $organizerId = (int) $organizer->id;
                DB::afterCommit(function () use ($organizerId, $submission): void {
                    $approved = Organizer::find($organizerId);
                    app(LifecycleMailService::class)->queueSafely(
                        LifecycleMailCatalog::ORGANIZER_VERIFICATION_APPROVED,
                        $approved?->email,
                        ['payout_account_url' => url('/organizer/kyc')],
                        $submission
                    );
                });
            }
            return $submission->fresh();
        }, 3);
    }
}
