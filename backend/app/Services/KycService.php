<?php

namespace App\Services;

use App\Models\KycProfile;
use App\Models\User;
use App\Models\States\UnderReview;
use App\Services\AuditLogService;
use Illuminate\Http\Request;

/**
 * KYC submission and admin review service.
 * SRS FR-2.1, FR-2.3.
 */
class KycService
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    /**
     * Submit or resubmit KYC documents.
     * Transitions: PENDING_SUBMISSION → UNDER_REVIEW (or REJECTED → PENDING_SUBMISSION → UNDER_REVIEW)
     */
    public function submit(User $delala, array $data, Request $request): KycProfile
    {
        $kyc = $delala->kycProfile;

        $oldValues = $kyc ? ['status' => (string) $kyc->status] : [];

        if (! $kyc) {
            $kyc = KycProfile::create(['user_id' => $delala->id]);
        }

        // If rejected, must go back to pending_submission first
        if ($kyc->isRejected()) {
            $kyc->status->transitionTo(\App\Models\States\PendingSubmission::class);
            $kyc->refresh();
        }

        // Update artifact S3 keys
        $kyc->fill([
            'id_document_s3_key'      => $data['id_document_s3_key'],
            'id_document_back_s3_key' => $data['id_document_back_s3_key'] ?? null,
            'selfie_s3_key'           => $data['selfie_s3_key'],
            'document_type'           => $data['document_type'],
            'rejection_reason'        => null, // clear previous rejection
        ]);

        // Transition to UNDER_REVIEW
        $kyc->status->transitionTo(UnderReview::class);
        $kyc->save();

        $this->auditLog->log(
            event: 'kyc_submitted',
            auditableType: KycProfile::class,
            auditableId: $kyc->id,
            oldValues: $oldValues,
            newValues: ['status' => 'under_review'],
            request: $request,
        );

        return $kyc->fresh();
    }
}
