<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KycProfile;
use App\States\Kyc\UnderReview;
use App\States\Kyc\Rejected;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class KycController extends Controller
{
    /**
     * Submit KYC documents (Delala only).
     */
    public function submit(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isDelala()) {
            return response()->json(['message' => 'Only Delalas can submit KYC.'], 403);
        }

        $kyc = $user->kycProfile;

        // If they already have an approved profile, or one under review, reject the submission.
        if ($kyc && in_array($kyc->status->getValue(), ['approved', 'under_review'])) {
            return response()->json([
                'message' => 'You already have an active or pending KYC submission.'
            ], 400);
        }

        $request->validate([
            'document_type' => 'required|string|in:national_id,passport,drivers_license,kebele_id',
            'id_document' => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240', // 10MB limit
            'selfie' => 'required|file|mimes:jpg,jpeg,png|max:10240',
        ]);

        // Store in S3 (private bucket)
        $idPath = $request->file('id_document')->store('kyc/ids', 's3');
        $selfiePath = $request->file('selfie')->store('kyc/selfies', 's3');

        if (! $kyc) {
            $kyc = new KycProfile();
            $kyc->user_id = $user->id;
        }

        $kyc->document_type = $request->document_type;
        $kyc->id_document_s3_key = $idPath;
        $kyc->selfie_s3_key = $selfiePath;
        
        // If it was rejected previously, reset rejection reason
        $kyc->rejection_reason = null;

        $kyc->save();
        
        // Transition state
        if ($kyc->status->canTransitionTo(UnderReview::class)) {
            $kyc->status->transitionTo(UnderReview::class);
        }

        return response()->json([
            'message' => 'KYC documents submitted successfully. Please wait for admin approval.',
            'profile' => $kyc->fresh(),
        ], 201);
    }
}
