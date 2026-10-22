<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\KycProfile;
use App\Notifications\KycApproved;
use App\Notifications\KycRejected;
use App\States\Kyc\Approved;
use App\States\Kyc\Rejected;
use App\States\Kyc\UnderReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class KycController extends Controller
{
    /**
     * Get paginated list of KYC profiles under review (AR-1).
     */
    public function index(Request $request): JsonResponse
    {
        $profiles = KycProfile::with('user:id,full_name,phone,email')
            ->where('status', UnderReview::$name)
            ->orderBy('created_at', 'asc')
            ->paginate(15);

        // Map over the results to append 15-minute pre-signed S3 URLs
        $profiles->getCollection()->transform(function ($kyc) {
            try {
                $kyc->id_document_url = Storage::disk('s3')->temporaryUrl(
                    $kyc->id_document_s3_key,
                    now()->addMinutes(15)
                );
                $kyc->id_document_back_url = $kyc->id_document_back_s3_key
                    ? Storage::disk('s3')->temporaryUrl($kyc->id_document_back_s3_key, now()->addMinutes(15))
                    : null;
                $kyc->selfie_url = Storage::disk('s3')->temporaryUrl(
                    $kyc->selfie_s3_key,
                    now()->addMinutes(15)
                );
            } catch (\Throwable $e) {
                $kyc->id_document_url = null;
                $kyc->id_document_back_url = null;
                $kyc->selfie_url = null;
            }

            return $kyc;
        });

        return response()->json($profiles);
    }

    /**
     * Approve a KYC profile (AR-1).
     */
    public function approve(Request $request, KycProfile $kyc): JsonResponse
    {
        if (! $kyc->status->canTransitionTo(Approved::class)) {
            return response()->json(['message' => 'Cannot approve this profile in its current state.'], 400);
        }

        $kyc->status->transitionTo(Approved::class);
        $kyc->update([
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'rejection_reason' => null,
        ]);

        $kyc->user->notify(new KycApproved);

        return response()->json(['message' => 'KYC Profile Approved']);
    }

    /**
     * Reject a KYC profile (AR-1).
     */
    public function reject(Request $request, KycProfile $kyc): JsonResponse
    {
        $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
            'rejection_code' => ['nullable', 'string', 'in:BLURRY_DOCUMENT,NAME_MISMATCH,FACE_MISMATCH,EXPIRED_DOCUMENT,SUSPECTED_FRAUD'],
        ]);

        if (! $kyc->status->canTransitionTo(Rejected::class)) {
            return response()->json(['message' => 'Cannot reject this profile in its current state.'], 400);
        }

        $kyc->status->transitionTo(Rejected::class);
        $kyc->update([
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'rejection_reason' => $request->reason,
        ]);

        $kyc->user->notify(new KycRejected($request->reason));

        return response()->json(['message' => 'KYC Profile Rejected']);
    }
}
